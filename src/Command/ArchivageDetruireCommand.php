<?php

declare(strict_types=1);

namespace App\Command;

use App\Archive\InventaireSuppression;
use App\Entity\ArchiveLot;
use App\Entity\ArchiveLotBeneficiaire;
use App\Repository\Archive\ArchivageEligibiliteRepository;
use App\Repository\Archive\ArchivagePiecesRepository;
use App\Repository\Archive\ArchivageSuppressionRepository;
use App\Repository\ArchiveLotBeneficiaireRepository;
use App\Repository\ArchiveLotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cinquième étape de la campagne d'archivage (Reco-03) : détruire.
 *
 * C'est le seul endroit du téléservice qui efface délibérément des données de
 * dossier, et l'opération est irréversible. Quatre verrous la protègent :
 *
 *  1. le lot doit être à l'état « réception validée » — les Archives de la
 *     Région ont confirmé avoir récupéré les archives ;
 *  2. l'archive de chaque compte doit exister et son EMPREINTE doit
 *     correspondre à celle inscrite au registre lors de la production ;
 *  3. l'éligibilité est RÉÉVALUÉE une dernière fois : un compte redevenu actif
 *     depuis la constitution est retiré, pas détruit ;
 *  4. rien n'est supprimé sans `--detruire` ; par défaut la commande compte.
 *
 * Chaque compte est traité dans sa propre transaction : un échec laisse les
 * autres intacts, et le compte en cause reste en l'état, à reprendre.
 */
#[AsCommand(
    name: 'normandie:archivage:detruire',
    description: "Détruire les dossiers d'un lot dont les Archives ont confirmé la réception (Reco-03)",
)]
class ArchivageDetruireCommand extends Command
{
    public function __construct(
        private readonly ArchiveLotRepository $lotRepository,
        private readonly ArchiveLotBeneficiaireRepository $lotBeneficiaireRepository,
        private readonly ArchivageEligibiliteRepository $eligibiliteRepository,
        private readonly ArchivagePiecesRepository $piecesRepository,
        private readonly ArchivageSuppressionRepository $suppressionRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $loggerArchivage,
        private readonly string $repertoireArchivage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('lot', null, InputOption::VALUE_REQUIRED, 'Référence du lot. Par défaut : la campagne en cours.')
            ->addOption('detruire', null, InputOption::VALUE_NONE, 'Détruire réellement. Sans cette option, la commande se contente de compter.')
            ->addOption('limite', null, InputOption::VALUE_REQUIRED, 'Ne traiter que les N premiers comptes.')
            ->addOption('verifier-ordre', null, InputOption::VALUE_NONE, "Confronter l'ordre de suppression aux clés étrangères du schéma, et s'arrêter là.")
            ->setHelp(<<<'AIDE'
                Compte ce qui serait détruit, sans rien supprimer :

                  <info>php bin/console normandie:archivage:detruire</info>

                Détruit réellement, après confirmation :

                  <info>php bin/console normandie:archivage:detruire --detruire</info>

                L'opération est IRRÉVERSIBLE. Elle n'est possible que sur un lot dont les
                Archives de la Région ont confirmé la réception.
                AIDE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if ($input->getOption('verifier-ordre')) {
                return $this->verifierLOrdre($io);
            }

            $lot = $this->resoudreLot($input);
            $detruire = (bool) $input->getOption('detruire');

            $io->title(sprintf('Archivage — destruction du lot %s', $lot->getReference()));

            $comptes = $this->lotBeneficiaireRepository->findParLotEtEtat($lot, ArchiveLotBeneficiaire::ETAT_PRODUITE);

            if ([] === $comptes) {
                $io->success('Aucun compte à détruire : tous les comptes produits ont déjà été traités.');

                return Command::SUCCESS;
            }

            $limite = $input->getOption('limite');

            if (null !== $limite && ctype_digit((string) $limite)) {
                $comptes = array_slice($comptes, 0, (int) $limite);
                $io->warning(sprintf('Traitement limité à %d comptes.', count($comptes)));
            }

            $encoreEligibles = array_fill_keys(
                $this->eligibiliteRepository->comptesEncoreEligibles(
                    array_map(static fn (ArchiveLotBeneficiaire $c): int => $c->getUserId(), $comptes),
                    new \DateTime()
                ),
                true
            );

            if (!$detruire) {
                return $this->simuler($io, $lot, $comptes, $encoreEligibles);
            }

            if (!$this->confirmer($io, $lot, count($comptes))) {
                return Command::SUCCESS;
            }

            return $this->detruire($io, $lot, $comptes, $encoreEligibles);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Destruction : echec', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * L'ordre de suppression respecte-t-il les clés étrangères du schéma ?
     *
     * Une table qui PORTE une clé étrangère doit être supprimée AVANT celle
     * qu'elle référence. Ce contrôle a été écrit après qu'un ordre déduit du
     * graphe métier — et non des clés réelles — a échoué à l'exécution sur
     * `remboursement_travaux.instruction_id`. Il n'a de sens que sur la vraie
     * base, d'où une option de commande plutôt qu'un test unitaire.
     */
    private function verifierLOrdre(SymfonyStyle $io): int
    {
        $io->title('Archivage — ordre de suppression et clés étrangères');

        $rang = [];

        foreach (InventaireSuppression::SUPPRESSIONS as $position => $suppression) {
            $rang[$suppression['table']] = $position;
        }

        $contraintes = $this->em->getConnection()->executeQuery(
            <<<'SQL'
            SELECT TABLE_NAME AS porteuse, COLUMN_NAME AS colonne, REFERENCED_TABLE_NAME AS referencee
              FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY TABLE_NAME, COLUMN_NAME
            SQL
        )->fetchAllAssociative();

        $fautes = [];
        $examinees = 0;

        foreach ($contraintes as $contrainte) {
            $porteuse = (string) $contrainte['porteuse'];
            $referencee = (string) $contrainte['referencee'];

            // Seules comptent les contraintes dont les DEUX tables sont
            // supprimées : les autres ne peuvent pas bloquer.
            if (!isset($rang[$porteuse], $rang[$referencee])) {
                continue;
            }

            ++$examinees;

            if ($rang[$porteuse] > $rang[$referencee]) {
                $fautes[] = sprintf(
                    '%s.%s → %s : « %s » est supprimée en %d%s position, après « %s » (%d%s). '
                    . 'La table qui porte la clé doit partir en premier.',
                    $porteuse,
                    (string) $contrainte['colonne'],
                    $referencee,
                    $porteuse,
                    $rang[$porteuse] + 1,
                    0 === $rang[$porteuse] ? 're' : 'e',
                    $referencee,
                    $rang[$referencee] + 1,
                    0 === $rang[$referencee] ? 're' : 'e'
                );
            }
        }

        if ([] === $fautes) {
            $io->success(sprintf(
                "Les %d contraintes qui portent sur les %d tables supprimées sont toutes respectées.",
                $examinees,
                count($rang)
            ));

            return Command::SUCCESS;
        }

        $io->error(sprintf('%d contraintes seraient violées à l\'exécution :', count($fautes)));
        $io->listing($fautes);

        $this->loggerArchivage->error('Destruction : ordre incompatible avec le schema', ['fautes' => $fautes]);

        return Command::FAILURE;
    }

    private function resoudreLot(InputInterface $input): ArchiveLot
    {
        $reference = $input->getOption('lot');

        $lot = (null !== $reference && '' !== $reference)
            ? $this->lotRepository->findParReference((string) $reference)
            : $this->lotRepository->findLotOuvert();

        if (null === $lot) {
            throw new \RuntimeException('Aucun lot à traiter.');
        }

        if (!$lot->destructionAutorisee()) {
            throw new \RuntimeException(sprintf(
                "Le lot %s est à l'état « %s ». La destruction n'est possible qu'à partir de l'état « %s », "
                . "c'est-à-dire une fois que les Archives de la Région ont confirmé avoir récupéré les "
                . 'archives. Aucune donnée ne doit disparaître avant que sa conservation soit assurée ailleurs.',
                $lot->getReference(),
                $lot->getEtat(),
                ArchiveLot::ETAT_RECEPTION_VALIDEE
            ));
        }

        return $lot;
    }

    /**
     * @param list<ArchiveLotBeneficiaire> $comptes
     * @param array<int, true>             $encoreEligibles
     */
    private function simuler(SymfonyStyle $io, ArchiveLot $lot, array $comptes, array $encoreEligibles): int
    {
        $io->section('Simulation — rien ne sera supprimé');

        $parTable = [];
        $retires = 0;
        $archivesManquantes = 0;

        foreach ($comptes as $compte) {
            if (!isset($encoreEligibles[$compte->getUserId()])) {
                ++$retires;

                continue;
            }

            if (null !== $this->motifDeRefusDeLArchive($lot, $compte)) {
                ++$archivesManquantes;

                continue;
            }

            foreach ($this->suppressionRepository->compter($this->suppressionRepository->collecter($compte->getUserId())) as $table => $nombre) {
                $parTable[$table] = ($parTable[$table] ?? 0) + $nombre;
            }
        }

        arsort($parTable);

        $io->table(
            ['Table', 'Lignes qui seraient supprimées'],
            array_map(static fn (string $t, int $n): array => [$t, $n], array_keys($parTable), $parTable)
        );

        $io->definitionList(
            ['Comptes examinés' => (string) count($comptes)],
            ['Comptes retirés (non éligibles)' => (string) $retires],
            ['Comptes sans archive vérifiable' => (string) $archivesManquantes],
            ['Lignes au total' => (string) array_sum($parTable)],
            ['Comptes anonymisés' => (string) (count($comptes) - $retires - $archivesManquantes)],
        );

        $io->text('Relancer avec <info>--detruire</info> pour exécuter. L\'opération est irréversible.');

        return Command::SUCCESS;
    }

    private function confirmer(SymfonyStyle $io, ArchiveLot $lot, int $nombre): bool
    {
        $io->warning(sprintf(
            "Destruction IRRÉVERSIBLE de %d comptes du lot %s : pièces effacées du disque, dossiers, "
            . 'historique et commentaires supprimés de la base, comptes anonymisés.',
            $nombre,
            $lot->getReference()
        ));

        $saisie = $io->ask(
            sprintf('Pour confirmer, saisir la référence du lot (%s)', $lot->getReference()),
            null
        );

        if ($saisie !== $lot->getReference()) {
            $io->text('Référence non confirmée : rien n\'a été supprimé.');

            return false;
        }

        return true;
    }

    /**
     * @param list<ArchiveLotBeneficiaire> $comptes
     * @param array<int, true>             $encoreEligibles
     */
    private function detruire(SymfonyStyle $io, ArchiveLot $lot, array $comptes, array $encoreEligibles): int
    {
        $io->section('Destruction');
        $io->progressStart(count($comptes));

        $detruits = 0;
        $retires = 0;
        $echecs = 0;
        $lignes = 0;
        $fichiers = 0;
        $connexion = $this->em->getConnection();

        // Les chemins des pièces sont relevés UNE FOIS, avant la première
        // suppression : la requête parcourt tout le graphe des dossiers, et
        // une fois les lignes effacées plus rien ne permet de les retrouver.
        $piecesParCompte = $this->piecesRepository->piecesParCompteEtDossier(
            $lot,
            ArchiveLotBeneficiaire::ETAT_PRODUITE
        );

        foreach ($comptes as $compte) {
            $userId = $compte->getUserId();

            if (!isset($encoreEligibles[$userId])) {
                $compte->setEtat(ArchiveLotBeneficiaire::ETAT_RETIREE);
                ++$retires;
                $this->em->flush();
                $io->progressAdvance();

                continue;
            }

            $refus = $this->motifDeRefusDeLArchive($lot, $compte);

            if (null !== $refus) {
                $compte->enErreur($refus);
                ++$echecs;
                $this->em->flush();
                $this->loggerArchivage->error('Destruction refusee : archive non verifiable', [
                    'reference' => $lot->getReference(),
                    'user_id' => $userId,
                    'motif' => $refus,
                ]);
                $io->progressAdvance();

                continue;
            }

            $connexion->beginTransaction();

            try {
                $chemins = $this->cheminsDesPieces($piecesParCompte, $userId);

                $ensembles = $this->suppressionRepository->collecter($userId);
                $effacees = $this->suppressionRepository->supprimer($ensembles);
                $this->suppressionRepository->anonymiserLeCompte($userId, $lot->getReference());

                $connexion->commit();

                $resultat = $this->suppressionRepository->supprimerLesFichiers($chemins);

                $compte->supprimeeLe(new \DateTime());
                $this->em->flush();

                ++$detruits;
                $lignes += array_sum($effacees);
                $fichiers += $resultat['supprimes'];

                $this->loggerArchivage->info('Destruction : compte traite', [
                    'reference' => $lot->getReference(),
                    'user_id' => $userId,
                    'lignes' => $effacees,
                    'fichiers_supprimes' => $resultat['supprimes'],
                    'fichiers_absents' => $resultat['absents'],
                    'fichiers_en_echec' => $resultat['echecs'],
                ]);
            } catch (\Throwable $e) {
                if ($connexion->isTransactionActive()) {
                    $connexion->rollBack();
                }

                $compte->enErreur($e->getMessage());
                $this->em->flush();
                ++$echecs;

                $this->loggerArchivage->error('Destruction : compte en echec', [
                    'reference' => $lot->getReference(),
                    'user_id' => $userId,
                    'message' => $e->getMessage(),
                ]);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->definitionList(
            ['Comptes détruits' => (string) $detruits],
            ['Comptes retirés' => (string) $retires],
            ['Échecs' => (string) $echecs],
            ['Lignes supprimées' => (string) $lignes],
            ['Fichiers effacés' => (string) $fichiers],
        );

        $restants = $this->lotBeneficiaireRepository->findUserIdsParLotEtEtat($lot, ArchiveLotBeneficiaire::ETAT_PRODUITE);

        if ([] === $restants && 0 === $echecs) {
            $lot->passerA(ArchiveLot::ETAT_DETRUIT)->setDateDestruction(new \DateTime());
            $this->em->flush();

            $io->success(sprintf(
                'Lot %s détruit. Reste à produire l\'attestation de destruction pour la Région.',
                $lot->getReference()
            ));
        } else {
            $io->warning(sprintf(
                'Le lot reste à l\'état « %s » : %d comptes à traiter, %d en échec.',
                $lot->getEtat(),
                count($restants),
                $echecs
            ));
        }

        $this->loggerArchivage->warning('Destruction : terminee', [
            'reference' => $lot->getReference(),
            'detruits' => $detruits,
            'retires' => $retires,
            'echecs' => $echecs,
            'lignes' => $lignes,
            'fichiers' => $fichiers,
        ]);

        return $echecs > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * L'archive du compte est-elle là, et intacte ? Rend le motif du refus, ou
     * null si la destruction peut avoir lieu.
     *
     * C'est le verrou qui garantit qu'on ne détruit rien dont la copie n'a pas
     * été vérifiée : ni archive absente, ni archive altérée depuis sa
     * production.
     */
    private function motifDeRefusDeLArchive(ArchiveLot $lot, ArchiveLotBeneficiaire $compte): ?string
    {
        $nom = $compte->getZipNom();
        $empreinte = $compte->getZipSha256();

        if (null === $nom || null === $empreinte) {
            return 'aucune archive enregistree au registre pour ce compte';
        }

        $chemin = rtrim($this->repertoireArchivage, '/\\')
            . \DIRECTORY_SEPARATOR . $lot->getReference()
            . \DIRECTORY_SEPARATOR . $nom;

        if (!is_file($chemin) || !is_readable($chemin)) {
            return sprintf('archive introuvable ou illisible : %s', $chemin);
        }

        $calculee = hash_file('sha256', $chemin);

        if ($calculee !== $empreinte) {
            return sprintf(
                'empreinte de l archive differente de celle du registre (%s attendue, %s calculee)',
                $empreinte,
                false === $calculee ? 'illisible' : $calculee
            );
        }

        return null;
    }

    /**
     * @param array<int, array<int, list<array{presente: bool, chemin: string}>>> $piecesParCompte
     *
     * @return list<string>
     */
    private function cheminsDesPieces(array $piecesParCompte, int $userId): array
    {
        $chemins = [];

        foreach ($piecesParCompte[$userId] ?? [] as $pieces) {
            foreach ($pieces as $piece) {
                if ($piece['presente']) {
                    $chemins[] = $piece['chemin'];
                }
            }
        }

        return $chemins;
    }
}

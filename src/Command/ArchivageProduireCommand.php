<?php

declare(strict_types=1);

namespace App\Command;

use App\Archive\ArchiveProducteur;
use App\Archive\BordereauVersement;
use App\Archive\DiffusionArchivage;
use App\Archive\PhotoExport;
use App\Entity\ArchiveLot;
use App\Entity\ArchiveLotBeneficiaire;
use App\Repository\Archive\ArchivageEligibiliteRepository;
use App\Repository\Archive\ArchivageHistoriqueRepository;
use App\Repository\Archive\ArchivagePiecesRepository;
use App\Repository\Archive\ArchiveExportPhotoRepository;
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
 * Troisième étape de la campagne annuelle d'archivage (Reco-03) : constituer
 * les archives et les déposer.
 *
 * Rien n'est supprimé ici. La commande écrit la photo de l'export, assemble une
 * archive par bénéficiaire et la dépose dans le répertoire que les Archives de
 * la Région viendront lire. La destruction est une étape distincte, qui ne
 * s'exécutera qu'après confirmation de réception.
 *
 * Deux verrous avant de produire :
 *
 *  1. le préavis donné aux professionnels doit être ÉCHU — ils doivent avoir eu
 *     le temps de réaliser leur propre export ;
 *  2. l'éligibilité de chaque compte est RÉÉVALUÉE. Plusieurs semaines séparent
 *     la sélection de la constitution, et un dossier peut avoir été ré-instruit
 *     entre-temps. Les comptes redevenus non éligibles sortent du lot.
 *
 * La commande est reprenable : un compte déjà produit est passé, et la photo
 * d'un dossier déjà photographié n'est pas réécrite.
 */
#[AsCommand(
    name: 'normandie:archivage:produire',
    description: "Constituer les archives d'un lot et les déposer (Reco-03)",
)]
class ArchivageProduireCommand extends Command
{
    public function __construct(
        private readonly ArchiveLotRepository $lotRepository,
        private readonly ArchiveLotBeneficiaireRepository $lotBeneficiaireRepository,
        private readonly ArchivageEligibiliteRepository $eligibiliteRepository,
        private readonly ArchivagePiecesRepository $piecesRepository,
        private readonly ArchivageHistoriqueRepository $historiqueRepository,
        private readonly ArchiveExportPhotoRepository $photoRepository,
        private readonly PhotoExport $photoExport,
        private readonly ArchiveProducteur $producteur,
        private readonly BordereauVersement $bordereau,
        private readonly DiffusionArchivage $diffusion,
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
            ->addOption('produire', null, InputOption::VALUE_NONE, 'Produire réellement. Sans cette option, la commande ne fait que vérifier.')
            ->addOption('limite', null, InputOption::VALUE_REQUIRED, 'Ne traiter que les N premiers comptes. Pour la recette.')
            ->addOption('ignorer-preavis', null, InputOption::VALUE_NONE, "Passer outre le préavis. Recette uniquement : en production, les professionnels doivent avoir eu leur délai.")
            ->setHelp(<<<'AIDE'
                Vérifie les conditions et annonce ce qui serait produit :

                  <info>php bin/console normandie:archivage:produire</info>

                Produit les archives et les dépose :

                  <info>php bin/console normandie:archivage:produire --produire</info>

                Sur un poste de développement, les pièces déposées ne sont pas répliquées : les
                archives contiendront leurs données et leur manifeste, et toutes les pièces y
                seront déclarées manquantes. C'est attendu.
                AIDE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $lot = $this->resoudreLot($input, $io);
            $produire = (bool) $input->getOption('produire');

            $io->title(sprintf('Archivage — constitution du lot %s', $lot->getReference()));

            $aProduire = $this->lotBeneficiaireRepository->findParLotEtEtat($lot, ArchiveLotBeneficiaire::ETAT_A_PRODUIRE);

            if ([] === $aProduire) {
                $io->success('Aucun compte à produire : le lot est déjà constitué.');

                return Command::SUCCESS;
            }

            $retires = $this->retirerLesComptesNonEligibles($io, $lot, $aProduire, $produire);
            $aProduire = array_values(array_filter(
                $aProduire,
                static fn (ArchiveLotBeneficiaire $ligne): bool => !isset($retires[$ligne->getUserId()])
            ));

            $limite = $input->getOption('limite');

            if (null !== $limite && ctype_digit((string) $limite)) {
                $aProduire = array_slice($aProduire, 0, (int) $limite);
                $io->warning(sprintf('Traitement limité à %d comptes (option --limite).', count($aProduire)));
            }

            if (!$produire) {
                $io->section('Rien n\'a été produit');
                $io->listing([
                    sprintf('%d comptes seraient traités.', count($aProduire)),
                    sprintf('%d comptes seraient retirés du lot (redevenus non éligibles).', count($retires)),
                    sprintf('Dépôt dans : %s', $this->repertoireArchivage),
                ]);
                $io->text('Relancer avec <info>--produire</info> pour constituer les archives.');

                return Command::SUCCESS;
            }

            $repertoire = $this->preparerRepertoire($lot);
            $io->text(sprintf('Dépôt dans <info>%s</info>', $repertoire));

            $photo = $this->prendreLaPhoto($io, $aProduire);

            $resultat = $this->produireLesArchives($io, $lot, $aProduire, $photo, $repertoire);

            $this->cloturer($io, $lot, $resultat);

            return $resultat['echecs'] > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Production : echec', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return Command::FAILURE;
        }
    }

    private function resoudreLot(InputInterface $input, SymfonyStyle $io): ArchiveLot
    {
        $reference = $input->getOption('lot');

        $lot = (null !== $reference && '' !== $reference)
            ? $this->lotRepository->findParReference((string) $reference)
            : $this->lotRepository->findLotOuvert();

        if (null === $lot) {
            throw new \RuntimeException(
                null !== $reference && '' !== $reference
                    ? sprintf('Aucun lot ne porte la référence %s.', (string) $reference)
                    : 'Aucune campagne en cours.'
            );
        }

        if (ArchiveLot::ETAT_ANNONCE !== $lot->getEtat()) {
            throw new \RuntimeException(sprintf(
                'Le lot %s est à l\'état « %s » : la constitution ne concerne que les lots à l\'état « %s ».',
                $lot->getReference(),
                $lot->getEtat(),
                ArchiveLot::ETAT_ANNONCE
            ));
        }

        if (null === $lot->getDateAnnonce()) {
            throw new \RuntimeException(sprintf(
                'Le lot %s n\'a pas été annoncé. Lancer normandie:archivage:annonce --envoyer au préalable : '
                . 'les professionnels doivent pouvoir réaliser leur export avant que les dossiers disparaissent.',
                $lot->getReference()
            ));
        }

        if (!$lot->preavisEchu()) {
            if (!$input->getOption('ignorer-preavis')) {
                throw new \RuntimeException(sprintf(
                    'Le préavis du lot %s court jusqu\'au %s. Aucune archive ne doit être constituée avant son '
                    . 'terme. Utiliser --ignorer-preavis en recette seulement.',
                    $lot->getReference(),
                    $lot->getDateFinPreavis()?->format('d/m/Y') ?? '?'
                ));
            }

            $io->warning(sprintf(
                'Préavis non échu (jusqu\'au %s) ignoré sur demande explicite. À ne jamais faire en production.',
                $lot->getDateFinPreavis()?->format('d/m/Y') ?? '?'
            ));
        }

        return $lot;
    }

    /**
     * Le verrou qui dépend du temps : un compte retenu en janvier peut avoir
     * déposé une nouvelle demande en février.
     *
     * @param list<ArchiveLotBeneficiaire> $aProduire
     *
     * @return array<int, true> identifiants des comptes retirés
     */
    private function retirerLesComptesNonEligibles(
        SymfonyStyle $io,
        ArchiveLot $lot,
        array $aProduire,
        bool $produire
    ): array {
        $userIds = array_map(static fn (ArchiveLotBeneficiaire $l): int => $l->getUserId(), $aProduire);
        $encore = array_fill_keys(
            $this->eligibiliteRepository->comptesEncoreEligibles($userIds, new \DateTime()),
            true
        );

        $retires = [];

        foreach ($aProduire as $ligne) {
            if (!isset($encore[$ligne->getUserId()])) {
                $retires[$ligne->getUserId()] = true;

                if ($produire) {
                    $ligne->setEtat(ArchiveLotBeneficiaire::ETAT_RETIREE);
                }
            }
        }

        if ([] === $retires) {
            $io->text('<info>Réévaluation : les ' . count($aProduire) . ' comptes du lot sont toujours éligibles.</info>');

            return [];
        }

        if ($produire) {
            $this->em->flush();
        }

        $io->warning(sprintf(
            '%d comptes sur %d ne sont plus éligibles et %s du lot.',
            count($retires),
            count($aProduire),
            $produire ? 'ont été retirés' : 'seraient retirés'
        ));

        $this->loggerArchivage->info('Production : comptes retires', [
            'reference' => $lot->getReference(),
            'retires' => array_keys($retires),
        ]);

        return $retires;
    }

    /**
     * @param list<ArchiveLotBeneficiaire> $aProduire
     *
     * @return array{entetes: list<string>, lignes: array<int, array{complete: array<string, string>, anonymisee: array<string, string>}>, lues: int}
     */
    private function prendreLaPhoto(SymfonyStyle $io, array $aProduire): array
    {
        $io->section('Photo de l\'export');
        $io->text('Lancement de l\'export complet, une fois pour tout le lot.');

        $debut = microtime(true);
        $dossiers = $this->dossiersDesComptes($aProduire);
        $photo = $this->photoExport->lignesDesDossiers($dossiers);

        $io->text(sprintf(
            '%d lignes d\'export lues en %.1f s, %d retenues pour le lot (%d colonnes).',
            $photo['lues'],
            microtime(true) - $debut,
            count($photo['lignes']),
            count($photo['entetes'])
        ));

        $manquants = array_diff_key($dossiers, $photo['lignes']);

        if ([] !== $manquants) {
            $io->warning(sprintf(
                "%d dossiers du lot n'apparaissent pas dans l'export : %s. "
                . "Leur archive portera leur historique et leurs pièces, mais pas de ligne de données.",
                count($manquants),
                implode(', ', array_slice(array_keys($manquants), 0, 10))
            ));
        }

        return $photo;
    }

    /**
     * Les dossiers des comptes à produire, par une lecture directe : la table
     * `beneficiaire` peut porter plusieurs lignes pour un même compte, et
     * l'archive doit rassembler les dossiers de toutes.
     *
     * @param list<ArchiveLotBeneficiaire> $aProduire
     *
     * @return array<int, true> identifiants de dossier en clés
     */
    private function dossiersDesComptes(array $aProduire): array
    {
        $userIds = array_map(static fn (ArchiveLotBeneficiaire $l): int => $l->getUserId(), $aProduire);

        if ([] === $userIds) {
            return [];
        }

        $ids = $this->em->getConnection()->executeQuery(
            <<<'SQL'
            SELECT d.id
              FROM beneficiaire b
              JOIN demande_ d ON d.beneficiaire_id = b.id
             WHERE b.user_id IN (:user_ids)
            SQL,
            ['user_ids' => $userIds],
            ['user_ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        )->fetchFirstColumn();

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * @param list<ArchiveLotBeneficiaire> $aProduire
     * @param array{entetes: list<string>, lignes: array<int, array{complete: array<string, string>, anonymisee: array<string, string>}>} $photo
     *
     * @return array{produites: int, echecs: int, pieces: int, manquantes: int, octets: int}
     */
    private function produireLesArchives(
        SymfonyStyle $io,
        ArchiveLot $lot,
        array $aProduire,
        array $photo,
        string $repertoire
    ): array {
        $io->section('Constitution des archives');

        $dejaPhotographies = $this->photoRepository->dossiersDejaPhotographies($lot);
        $piecesParCompte = $this->piecesRepository->piecesParCompteEtDossier($lot);

        $produites = 0;
        $echecs = 0;
        $pieces = 0;
        $manquantes = 0;
        $octets = 0;

        $io->progressStart(count($aProduire));

        foreach ($aProduire as $ligne) {
            $userId = $ligne->getUserId();

            try {
                $dossiersDuCompte = $this->dossiersDuCompte($userId);
                $lignesDuCompte = [];

                foreach ($dossiersDuCompte as $demandeId) {
                    if (isset($photo['lignes'][$demandeId])) {
                        $lignesDuCompte[$demandeId] = $photo['lignes'][$demandeId];
                    }
                }

                $historiques = [];

                foreach ($this->historiqueRepository->historiqueDesDossiers($dossiersDuCompte) as $demandeId => $entrees) {
                    $historiques[$demandeId] = $this->historiqueRepository->rendreEnTexte($demandeId, $entrees);
                }

                $archive = $this->producteur->produire(
                    $lot,
                    $userId,
                    $ligne->getDeclencheur(),
                    $photo['entetes'],
                    $lignesDuCompte,
                    $historiques,
                    $piecesParCompte[$userId] ?? [],
                    $repertoire
                );

                // La photo est écrite APRÈS l'archive : si l'archive échoue, rien
                // ne subsiste en base, et le compte reste à produire.
                $aPhotographier = array_diff_key($lignesDuCompte, $dejaPhotographies);
                $this->photoRepository->enregistrer($lot, $userId, $aPhotographier);

                $ligne->archiveProduite(
                    $archive['nom'],
                    $archive['taille'],
                    $archive['sha256'],
                    $archive['nb_pieces'],
                    $archive['nb_manquantes']
                );

                ++$produites;
                $pieces += $archive['nb_pieces'];
                $manquantes += $archive['nb_manquantes'];
                $octets += $archive['taille'];
            } catch (\Throwable $e) {
                ++$echecs;
                $ligne->enErreur($e->getMessage());

                $this->loggerArchivage->error('Production : archive en echec', [
                    'reference' => $lot->getReference(),
                    'user_id' => $userId,
                    'message' => $e->getMessage(),
                ]);
            }

            $this->em->flush();
            $io->progressAdvance();
        }

        $io->progressFinish();

        return [
            'produites' => $produites,
            'echecs' => $echecs,
            'pieces' => $pieces,
            'manquantes' => $manquantes,
            'octets' => $octets,
        ];
    }

    /**
     * @return list<int>
     */
    private function dossiersDuCompte(int $userId): array
    {
        $ids = $this->em->getConnection()->executeQuery(
            <<<'SQL'
            SELECT d.id
              FROM beneficiaire b
              JOIN demande_ d ON d.beneficiaire_id = b.id
             WHERE b.user_id = :user_id
             ORDER BY d.id
            SQL,
            ['user_id' => $userId]
        )->fetchFirstColumn();

        return array_map('intval', $ids);
    }

    /**
     * @param array{produites: int, echecs: int, pieces: int, manquantes: int, octets: int} $resultat
     */
    private function cloturer(SymfonyStyle $io, ArchiveLot $lot, array $resultat): void
    {
        $restants = $this->lotBeneficiaireRepository->findUserIdsParLotEtEtat(
            $lot,
            ArchiveLotBeneficiaire::ETAT_A_PRODUIRE
        );

        $io->definitionList(
            ['Archives produites' => (string) $resultat['produites']],
            ['Échecs' => (string) $resultat['echecs']],
            ['Pièces versées' => sprintf('%d dont %d manquantes', $resultat['pieces'], $resultat['manquantes'])],
            ['Volume des archives' => sprintf('%.1f Mio', $resultat['octets'] / 1048576)],
            ['Comptes restant à produire' => (string) count($restants)],
        );

        if ([] === $restants && 0 === $resultat['echecs']) {
            // Le bordereau est écrit MAINTENANT, tant que les noms et les
            // dossiers existent encore : après la destruction, plus rien ne
            // permettrait de le produire.
            $bordereau = $this->bordereau->produire($lot, $this->repertoireDuLot($lot));
            $this->diffuserLeBordereau($io, $lot, $bordereau, $resultat);

            // Constitué puis versé : le dépôt dans le répertoire EST le
            // versement, les Archives venant y lire par elles-mêmes.
            $lot->passerA(ArchiveLot::ETAT_CONSTITUE)->setDateConstitution(new \DateTime());
            $lot->passerA(ArchiveLot::ETAT_VERSE)->setDateVersement(new \DateTime());
            $this->em->flush();

            $io->text(sprintf('Bordereau de versement : <info>%s</info>', $bordereau));
            $io->success(sprintf(
                'Lot %s constitué et déposé. Les Archives de la Région peuvent le récupérer ; '
                . 'la destruction restera refusée jusqu\'à leur confirmation de réception.',
                $lot->getReference()
            ));
        } else {
            $io->warning(sprintf(
                'Le lot reste à l\'état « %s » : %d comptes à produire, %d en échec. '
                . 'La commande est reprenable, les comptes déjà produits seront passés.',
                $lot->getEtat(),
                count($restants),
                $resultat['echecs']
            ));
        }

        $this->loggerArchivage->info('Production : terminee', [
            'reference' => $lot->getReference(),
            'etat' => $lot->getEtat(),
        ] + $resultat);
    }

    /**
     * Envoie le bordereau aux administrateurs.
     *
     * Un échec d'envoi n'interrompt pas la campagne : le bordereau est écrit
     * sur le serveur, déposé avec les archives, et un courriel perdu se
     * renvoie. Il est en revanche signalé, pour que personne ne croie les
     * administrateurs prévenus.
     *
     * @param array{produites: int, pieces: int, manquantes: int, octets: int} $resultat
     */
    private function diffuserLeBordereau(SymfonyStyle $io, ArchiveLot $lot, string $bordereau, array $resultat): void
    {
        try {
            $diffusion = $this->diffusion->diffuserBordereau($lot, $bordereau, [
                'comptes' => $resultat['produites'],
                'dossiers' => $lot->getNbDossiers(),
                'pieces' => $resultat['pieces'],
                'manquantes' => $resultat['manquantes'],
                'octets' => $resultat['octets'],
            ]);

            if (0 === $diffusion['destinataires']) {
                $io->warning('Aucun administrateur à prévenir : le bordereau n\'a été envoyé à personne.');

                return;
            }

            if ($diffusion['echecs'] > 0) {
                $io->warning(sprintf(
                    'Bordereau envoyé à %d administrateurs, %d envois en échec.',
                    $diffusion['envoyes'],
                    $diffusion['echecs']
                ));

                return;
            }

            $io->text(sprintf('Bordereau envoyé à %d administrateurs.', $diffusion['envoyes']));
        } catch (\Throwable $e) {
            $io->warning(sprintf('Envoi du bordereau impossible : %s', $e->getMessage()));
            $this->loggerArchivage->error('Production : diffusion du bordereau en echec', [
                'reference' => $lot->getReference(),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** Le répertoire de dépôt propre à ce lot, sans le créer. */
    private function repertoireDuLot(ArchiveLot $lot): string
    {
        return rtrim($this->repertoireArchivage, '/\\') . \DIRECTORY_SEPARATOR . $lot->getReference();
    }

    private function preparerRepertoire(ArchiveLot $lot): string
    {
        if ('' === $this->repertoireArchivage) {
            throw new \RuntimeException(
                "Aucun répertoire de dépôt configuré (ARCHIVAGE_REPERTOIRE_DEPOT). C'est la DRI qui "
                . 'en donne le chemin, et les Archives de la Région y accèdent par SFTP.'
            );
        }

        $repertoire = $this->repertoireDuLot($lot);

        if (!is_dir($repertoire) && !mkdir($repertoire, 0750, true) && !is_dir($repertoire)) {
            throw new \RuntimeException(sprintf('Répertoire de dépôt impossible à créer : %s', $repertoire));
        }

        if (!is_writable($repertoire)) {
            throw new \RuntimeException(sprintf('Répertoire de dépôt non inscriptible : %s', $repertoire));
        }

        return $repertoire;
    }
}

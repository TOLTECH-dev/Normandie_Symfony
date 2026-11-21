<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ArchiveLot;
use App\Entity\ArchiveLotBeneficiaire;
use App\Repository\Archive\ArchivageEligibiliteRepository;
use App\Repository\ArchiveLotBeneficiaireRepository;
use App\Repository\ArchiveLotRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Première étape de la campagne annuelle d'archivage (Reco-03) : identifier les
 * comptes bénéficiaires archivables et constituer le lot.
 *
 * Cette commande ne touche à AUCUNE donnée de dossier. Sans --creer, elle ne
 * fait que lire et afficher ; avec --creer, elle n'écrit que dans le registre
 * (archive_lot et archive_lot_beneficiaire). Elle est donc rejouable autant de
 * fois que voulu, ce qui permet d'annoncer un volume à la Région avant
 * d'engager quoi que ce soit.
 *
 * Elle n'envoie aucun courriel : l'annonce aux partenaires et le démarrage du
 * préavis relèvent de l'étape suivante, pour que la constitution du lot et le
 * départ du délai restent deux décisions distinctes.
 */
#[AsCommand(
    name: 'normandie:archivage:selection',
    description: "Identifier les comptes bénéficiaires archivables et constituer le lot (Reco-03)",
)]
class ArchivageSelectionCommand extends Command
{
    public function __construct(
        private readonly ArchivageEligibiliteRepository $eligibiliteRepository,
        private readonly ArchiveLotRepository $lotRepository,
        private readonly ArchiveLotBeneficiaireRepository $lotBeneficiaireRepository,
        private readonly LoggerInterface $loggerArchivage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'creer',
                null,
                InputOption::VALUE_NONE,
                'Créer réellement le lot. Sans cette option, la commande se contente de compter.'
            )
            ->addOption(
                'date-reference',
                null,
                InputOption::VALUE_REQUIRED,
                "Date à laquelle l'éligibilité est calculée (AAAA-MM-JJ). Par défaut : maintenant."
            )
            ->addOption(
                'reference',
                null,
                InputOption::VALUE_REQUIRED,
                "Référence du lot. Par défaut : ARCH-<année de la date de référence>."
            )
            ->addOption(
                'auteur',
                null,
                InputOption::VALUE_REQUIRED,
                "Qui lance la campagne. Par défaut : Automate.",
                'Automate'
            )
            ->addOption(
                'limite',
                null,
                InputOption::VALUE_REQUIRED,
                'Ne retenir que les N premiers comptes. Réservé à la recette.'
            )
            ->setHelp(<<<'AIDE'
                Compte les comptes bénéficiaires archivables, par déclencheur :

                  <info>php bin/console normandie:archivage:selection</info>

                Constitue réellement le lot de l'année :

                  <info>php bin/console normandie:archivage:selection --creer</info>

                Rejoue une sélection passée à l'identique :

                  <info>php bin/console normandie:archivage:selection --date-reference=2027-01-10</info>
                AIDE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $dateReference = $this->lireDateReference($input);
            $reference = $this->lireReference($input, $dateReference);
            $creer = (bool) $input->getOption('creer');
            $limite = $this->lireLimite($input);

            $io->title(sprintf(
                'Archivage — sélection %s (éligibilité au %s)',
                $reference,
                $dateReference->format('d/m/Y H:i')
            ));

            $this->loggerArchivage->info('Selection : debut', [
                'reference' => $reference,
                'date_reference' => $dateReference->format('c'),
                'creer' => $creer,
                'limite' => $limite,
            ]);

            $this->afficherSynthese($io, $dateReference);

            $comptes = $this->eligibiliteRepository->comptesEligibles($dateReference);

            if ([] === $comptes) {
                $io->success('Aucun compte archivable à cette date.');
                $this->loggerArchivage->info('Selection : aucun compte eligible', ['reference' => $reference]);

                return Command::SUCCESS;
            }

            if (null !== $limite && count($comptes) > $limite) {
                $io->warning(sprintf(
                    'Sélection ramenée aux %d premiers comptes sur %d (option --limite, réservée à la recette).',
                    $limite,
                    count($comptes)
                ));
                $comptes = array_slice($comptes, 0, $limite);
            }

            $lignes = $this->preparerLignes($comptes);
            $nbDossiers = array_sum(array_column($lignes, 'nb_dossiers'));

            if (!$creer) {
                $io->section('Rien n\'a été écrit');
                $io->text([
                    sprintf('%d comptes et %d dossiers seraient retenus.', count($lignes), $nbDossiers),
                    'Relancer avec <info>--creer</info> pour constituer le lot.',
                ]);

                return Command::SUCCESS;
            }

            $lot = $this->creerLot($reference, $dateReference, (string) $input->getOption('auteur'), $lignes, $nbDossiers);

            $io->success(sprintf(
                'Lot %s créé à l\'état « %s » : %d comptes, %d dossiers.',
                $lot->getReference(),
                $lot->getEtat(),
                $lot->getNbComptes(),
                $lot->getNbDossiers()
            ));
            $io->text('Étape suivante : annoncer le lot aux partenaires, ce qui fait partir le préavis.');

            $this->loggerArchivage->info('Selection : lot cree', [
                'reference' => $lot->getReference(),
                'lot_id' => $lot->getId(),
                'nb_comptes' => $lot->getNbComptes(),
                'nb_dossiers' => $lot->getNbDossiers(),
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Selection : echec', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return Command::FAILURE;
        }
    }

    private function lireDateReference(InputInterface $input): \DateTimeInterface
    {
        $valeur = $input->getOption('date-reference');

        if (null === $valeur || '' === $valeur) {
            return new \DateTime();
        }

        $date = \DateTime::createFromFormat('Y-m-d', (string) $valeur);

        if (false === $date) {
            throw new \InvalidArgumentException(sprintf(
                'Date de référence illisible : « %s ». Format attendu : AAAA-MM-JJ.',
                (string) $valeur
            ));
        }

        // Fin de journée : un délai échu le jour même doit compter.
        return $date->setTime(23, 59, 59);
    }

    private function lireReference(InputInterface $input, \DateTimeInterface $dateReference): string
    {
        $valeur = $input->getOption('reference');

        if (null !== $valeur && '' !== $valeur) {
            return (string) $valeur;
        }

        return 'ARCH-' . $dateReference->format('Y');
    }

    private function lireLimite(InputInterface $input): ?int
    {
        $valeur = $input->getOption('limite');

        if (null === $valeur || '' === $valeur) {
            return null;
        }

        if (!ctype_digit((string) $valeur) || 0 === (int) $valeur) {
            throw new \InvalidArgumentException(sprintf(
                'Limite illisible : « %s ». Un entier strictement positif est attendu.',
                (string) $valeur
            ));
        }

        return (int) $valeur;
    }

    private function afficherSynthese(SymfonyStyle $io, \DateTimeInterface $dateReference): void
    {
        $synthese = $this->eligibiliteRepository->syntheseParDeclencheur($dateReference);

        if ([] === $synthese) {
            return;
        }

        $io->table(
            ['Déclencheur', 'Comptes', 'dont actifs', 'Dossiers'],
            array_map(
                static fn (array $ligne): array => [
                    $ligne['declencheur'],
                    $ligne['comptes'],
                    $ligne['comptes_actifs'],
                    $ligne['dossiers'],
                ],
                $synthese
            )
        );

        $io->text(sprintf(
            '<info>Total : %d comptes, %d dossiers.</info>',
            array_sum(array_column($synthese, 'comptes')),
            array_sum(array_column($synthese, 'dossiers'))
        ));
    }

    /**
     * Traduit les lignes d'éligibilité en lignes de registre.
     *
     * @param list<array{user_id: int, enabled: bool, nb_dossiers: int, date_eligibilite: ?string, situation_determinante: ?string}> $comptes
     *
     * @return list<array{user_id: int, declencheur: string, date_eligibilite: ?string, nb_dossiers: int}>
     */
    private function preparerLignes(array $comptes): array
    {
        $lignes = [];

        foreach ($comptes as $compte) {
            $lignes[] = [
                'user_id' => $compte['user_id'],
                'declencheur' => ArchiveLotBeneficiaire::declencheurPourSituation(
                    $compte['situation_determinante'],
                    $compte['nb_dossiers']
                ),
                'date_eligibilite' => $compte['date_eligibilite'],
                'nb_dossiers' => $compte['nb_dossiers'],
            ];
        }

        return $lignes;
    }

    /**
     * @param list<array{user_id: int, declencheur: string, date_eligibilite: ?string, nb_dossiers: int}> $lignes
     */
    private function creerLot(
        string $reference,
        \DateTimeInterface $dateReference,
        string $auteur,
        array $lignes,
        int $nbDossiers
    ): ArchiveLot {
        $ouvert = $this->lotRepository->findLotOuvert();

        if (null !== $ouvert) {
            throw new \RuntimeException(sprintf(
                'Le lot %s est encore en cours (état « %s »). Une seule campagne peut être ouverte à la fois : '
                . 'deux lots simultanés pourraient retenir le même compte.',
                $ouvert->getReference(),
                $ouvert->getEtat()
            ));
        }

        if (null !== $this->lotRepository->findParReference($reference)) {
            throw new \RuntimeException(sprintf(
                'Un lot porte déjà la référence %s. Utiliser --reference pour en donner une autre.',
                $reference
            ));
        }

        $lot = new ArchiveLot($reference, $dateReference, $auteur);
        $lot->setNbComptes(count($lignes))->setNbDossiers($nbDossiers);

        $this->lotRepository->enregistrer($lot);

        $inserees = $this->lotBeneficiaireRepository->insererComptes($lot, $lignes);

        if ($inserees !== count($lignes)) {
            throw new \RuntimeException(sprintf(
                'Le registre n\'a reçu que %d lignes sur %d attendues : lot %s incomplet.',
                $inserees,
                count($lignes),
                $reference
            ));
        }

        return $lot;
    }
}

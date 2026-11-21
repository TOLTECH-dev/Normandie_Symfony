<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Archive\ArchivagePurgeBancaireRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge des coordonnées bancaires des bénéficiaires (Reco-03).
 *
 * « Suppression de la rubrique coordonnées bancaires : 5 ans après la date de
 * paiement » (compte rendu du 17/11/2026). La Région précise que le RIB figure
 * déjà dans les dossiers de conventionnement et que les coordonnées bancaires
 * sont sur les synthèses RMH : la copie détenue par la plateforme peut donc
 * disparaître.
 *
 * Cette purge est INDÉPENDANTE des campagnes d'archivage : elle porte sur
 * toute la base, qu'un dossier soit archivé ou non. Elle se lance dans la même
 * campagne annuelle, par commodité, mais rien ne l'y oblige.
 *
 * La ligne d'instruction est conservée : seuls l'IBAN, le BIC et le RIB
 * disparaissent. L'instruction du remboursement reste nécessaire au suivi et
 * aux statistiques.
 */
#[AsCommand(
    name: 'normandie:archivage:purgeBancaire',
    description: "Supprimer les coordonnées bancaires des remboursements payés il y a plus de 5 ans (Reco-03)",
)]
class ArchivagePurgeBancaireCommand extends Command
{
    public function __construct(
        private readonly ArchivagePurgeBancaireRepository $purgeRepository,
        private readonly LoggerInterface $loggerArchivage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('purger', null, InputOption::VALUE_NONE, 'Purger réellement. Sans cette option, la commande se contente de compter.')
            ->addOption('annees', null, InputOption::VALUE_REQUIRED, 'Délai en années après la date de paiement.', '5')
            ->addOption('date-reference', null, InputOption::VALUE_REQUIRED, 'Date de référence (AAAA-MM-JJ). Par défaut : aujourd\'hui.')
            ->setHelp(<<<'AIDE'
                Compte ce qui serait purgé :

                  <info>php bin/console normandie:archivage:purgeBancaire</info>

                Purge réellement :

                  <info>php bin/console normandie:archivage:purgeBancaire --purger</info>
                AIDE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $annees = (int) $input->getOption('annees');

            if ($annees < 1) {
                throw new \InvalidArgumentException('Le délai doit être d\'au moins un an.');
            }

            $dateReference = $this->lireDate($input);
            $purger = (bool) $input->getOption('purger');

            $io->title(sprintf(
                'Archivage — coordonnées bancaires payées avant le %s',
                (clone $dateReference)->modify(sprintf('-%d year', $annees))->format('d/m/Y')
            ));

            $echues = $this->purgeRepository->instructionsEchues($dateReference, $annees);

            $lignes = [];
            $total = 0;
            $ribs = 0;

            foreach ($echues as $origine => $instructions) {
                $avecRib = count(array_filter(
                    $instructions,
                    static fn (array $i): bool => null !== $i['rib'] && '' !== $i['rib']
                ));

                $lignes[] = [$origine, count($instructions), $avecRib];
                $total += count($instructions);
                $ribs += $avecRib;
            }

            $io->table(['Origine', 'Instructions', 'dont RIB en PDF'], $lignes);

            if (0 === $total) {
                $io->success('Aucune coordonnée bancaire échue.');

                return Command::SUCCESS;
            }

            if (!$purger) {
                $io->text([
                    sprintf('%d instructions seraient purgées, dont %d avec un RIB à effacer du disque.', $total, $ribs),
                    'Relancer avec <info>--purger</info> pour exécuter.',
                ]);

                return Command::SUCCESS;
            }

            $resultat = $this->purgerReellement($io, $echues);

            $io->success(sprintf(
                '%d instructions purgées, %d RIB effacés du disque (%d introuvables).',
                $resultat['purgees'],
                $resultat['fichiers'],
                $resultat['absents']
            ));

            $this->loggerArchivage->warning('Purge bancaire : terminee', [
                'date_reference' => $dateReference->format('Y-m-d'),
                'annees' => $annees,
            ] + $resultat);

            return [] === $resultat['echecs'] ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Purge bancaire : echec', ['message' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }

    /**
     * @param array<string, list<array{id: int, rib: ?string, date_paiement: string}>> $echues
     *
     * @return array{purgees: int, fichiers: int, absents: int, echecs: list<string>}
     */
    private function purgerReellement(SymfonyStyle $io, array $echues): array
    {
        $purgees = 0;
        $fichiers = 0;
        $absents = 0;
        $echecs = [];

        foreach ($echues as $origine => $instructions) {
            if ([] === $instructions) {
                continue;
            }

            // Les fichiers d'abord : une fois la colonne vidée, l'extension du
            // RIB est perdue et son chemin n'est plus calculable.
            foreach ($instructions as $instruction) {
                $chemin = $this->purgeRepository->cheminDuRib($origine, $instruction['id'], $instruction['rib']);

                if (null === $chemin) {
                    continue;
                }

                if (!is_file($chemin)) {
                    ++$absents;

                    continue;
                }

                if (unlink($chemin)) {
                    ++$fichiers;
                } else {
                    $echecs[] = $chemin;
                }
            }

            $purgees += $this->purgeRepository->purger(
                $origine,
                array_map(static fn (array $i): int => $i['id'], $instructions)
            );

            $io->text(sprintf('  %s : %d instructions', $origine, count($instructions)));
        }

        if ([] !== $echecs) {
            $io->warning(sprintf('%d RIB n\'ont pas pu être effacés du disque.', count($echecs)));
        }

        return ['purgees' => $purgees, 'fichiers' => $fichiers, 'absents' => $absents, 'echecs' => $echecs];
    }

    private function lireDate(InputInterface $input): \DateTimeInterface
    {
        $valeur = $input->getOption('date-reference');

        if (null === $valeur || '' === $valeur) {
            return new \DateTime();
        }

        $date = \DateTime::createFromFormat('Y-m-d', (string) $valeur);

        if (false === $date) {
            throw new \InvalidArgumentException(sprintf('Date illisible : « %s ».', (string) $valeur));
        }

        return $date->setTime(0, 0);
    }
}

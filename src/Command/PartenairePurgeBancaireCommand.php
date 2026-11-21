<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Partenaire\PurgeBancairePartenaireRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge des coordonnées bancaires des partenaires inactifs (Reco-13).
 *
 * « Suppression de la rubrique coordonnées bancaires : 5 ans après date de
 * désactivation du compte sur la plateforme. »
 *
 * Les comptes partenaires ne sont NI archivés NI supprimés : la recommandation
 * le dit explicitement. Seule la rubrique bancaire disparaît, la fiche reste
 * entière. C'est la différence avec l'archivage des bénéficiaires, et elle est
 * voulue : un auditeur inactif peut revenir, et son historique d'intervention
 * reste nécessaire aux dossiers qu'il a traités.
 */
#[AsCommand(
    name: 'normandie:partenaire:purgeBancaire',
    description: "Supprimer les coordonnées bancaires des partenaires inactifs depuis plus de 5 ans (Reco-13)",
)]
class PartenairePurgeBancaireCommand extends Command
{
    public function __construct(
        private readonly PurgeBancairePartenaireRepository $depot,
        private readonly LoggerInterface $loggerArchivage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('purger', null, InputOption::VALUE_NONE, 'Purger réellement. Sans cette option, la commande se contente de lister.')
            ->addOption('annees', null, InputOption::VALUE_REQUIRED, 'Délai en années après la désactivation.', '5')
            ->addOption('date-reference', null, InputOption::VALUE_REQUIRED, 'Date de référence (AAAA-MM-JJ). Par défaut : aujourd\'hui.')
            ->setHelp(<<<'AIDE'
                Liste les partenaires concernés :

                  <info>php bin/console normandie:partenaire:purgeBancaire</info>

                Purge réellement :

                  <info>php bin/console normandie:partenaire:purgeBancaire --purger</info>

                Seule la rubrique bancaire est effacée : les fiches partenaires sont
                conservées, ainsi que leur historique d'activité.
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
            $limite = (clone $dateReference)->modify(sprintf('-%d year', $annees));

            $io->title(sprintf(
                'Partenaires — coordonnées bancaires des comptes inactifs avant le %s',
                $limite->format('d/m/Y')
            ));

            $echus = $this->depot->partenairesEchus($dateReference, $annees);

            if ([] === $echus) {
                $io->success('Aucune coordonnée bancaire de partenaire échue.');

                return Command::SUCCESS;
            }

            $io->table(
                ['Partenaire', 'Raison sociale', 'Inactif depuis', 'IBAN', 'BIC', 'RIB'],
                array_map(
                    static fn (array $p): array => [
                        $p['partenaire_id'],
                        $p['raison_sociale'] ?? '(sans raison sociale)',
                        (new \DateTime($p['date_inactif']))->format('d/m/Y'),
                        $p['iban'] ? 'oui' : '',
                        $p['bic'] ? 'oui' : '',
                        null !== $p['rib'] && '' !== $p['rib'] ? $p['rib'] : '',
                    ],
                    $echus
                )
            );

            $avecRib = count(array_filter($echus, static fn (array $p): bool => null !== $p['rib'] && '' !== $p['rib']));

            if (!$purger) {
                $io->text([
                    sprintf(
                        '%d partenaires seraient purgés, dont %d avec un RIB à effacer du disque.',
                        count($echus),
                        $avecRib
                    ),
                    'Les fiches partenaires elles-mêmes ne sont pas touchées.',
                    'Relancer avec <info>--purger</info> pour exécuter.',
                ]);

                return Command::SUCCESS;
            }

            $resultat = $this->purgerReellement($io, $echus);

            $io->success(sprintf(
                '%d partenaires purgés, %d RIB effacés du disque (%d introuvables).',
                $resultat['purges'],
                $resultat['fichiers'],
                $resultat['absents']
            ));

            $this->loggerArchivage->warning('Purge bancaire partenaires : terminee', [
                'date_reference' => $dateReference->format('Y-m-d'),
                'annees' => $annees,
            ] + $resultat);

            return [] === $resultat['echecs'] ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Purge bancaire partenaires : echec', ['message' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }

    /**
     * @param list<array{partenaire_id: int, option_id: int, rib: ?string}> $echus
     *
     * @return array{purges: int, fichiers: int, absents: int, echecs: list<string>}
     */
    private function purgerReellement(SymfonyStyle $io, array $echus): array
    {
        $fichiers = 0;
        $absents = 0;
        $echecs = [];

        // Les fichiers d'abord : une fois `rib_url` vidée, l'extension est
        // perdue et le chemin du RIB n'est plus calculable.
        foreach ($echus as $partenaire) {
            $chemin = $this->depot->cheminDuRib($partenaire['option_id'], $partenaire['rib']);

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

        $purges = $this->depot->purger(array_map(
            static fn (array $p): int => $p['option_id'],
            $echus
        ));

        if ([] !== $echecs) {
            $io->warning(sprintf('%d RIB n\'ont pas pu être effacés du disque.', count($echecs)));
        }

        return ['purges' => $purges, 'fichiers' => $fichiers, 'absents' => $absents, 'echecs' => $echecs];
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

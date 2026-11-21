<?php

declare(strict_types=1);

namespace App\Command;

use App\Archive\InventairePieces;
use App\Repository\Archive\ArchivagePiecesRepository;
use App\Repository\ArchiveLotRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recensement des pièces d'un lot d'archivage, avant de les verser (Reco-03).
 *
 * Commande de LECTURE SEULE, qui répond à trois questions que la constitution
 * des archives posera de toute façon, et qu'il vaut mieux poser d'abord :
 *
 *  - combien de pièces le lot emporte-t-il, et de quelle nature ;
 *  - combien manquent sur le disque, c'est-à-dire sont référencées en base mais
 *    introuvables — information à déclarer dans le manifeste, pas à masquer ;
 *  - quel volume en octets, ce qui est la question que la DRI doit trancher
 *    avant d'ouvrir le répertoire de dépôt.
 */
#[AsCommand(
    name: 'normandie:archivage:pieces',
    description: "Recenser les pièces d'un lot d'archivage et mesurer leur volume (Reco-03)",
)]
class ArchivagePiecesCommand extends Command
{
    public function __construct(
        private readonly ArchiveLotRepository $lotRepository,
        private readonly ArchivagePiecesRepository $piecesRepository,
        private readonly Connection $connection,
        private readonly LoggerInterface $loggerArchivage,
        private readonly string $appRootDossierDataSymfony,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'lot',
                null,
                InputOption::VALUE_REQUIRED,
                'Référence du lot. Par défaut : la campagne en cours.'
            )
            ->addOption(
                'detail',
                null,
                InputOption::VALUE_REQUIRED,
                "Afficher le détail des pièces des N premiers comptes."
            )
            ->addOption(
                'couverture',
                null,
                InputOption::VALUE_NONE,
                "Vérifier que l'inventaire couvre toutes les colonnes de pièces du schéma, et s'arrêter là."
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if ($input->getOption('couverture')) {
                return $this->verifierCouverture($io);
            }

            $reference = $input->getOption('lot');

            $lot = (null !== $reference && '' !== $reference)
                ? $this->lotRepository->findParReference((string) $reference)
                : $this->lotRepository->findLotOuvert();

            if (null === $lot) {
                throw new \RuntimeException(
                    null !== $reference && '' !== $reference
                        ? sprintf('Aucun lot ne porte la référence %s.', (string) $reference)
                        : 'Aucune campagne en cours. Lancer normandie:archivage:selection --creer au préalable.'
                );
            }

            $io->title(sprintf('Archivage — pièces du lot %s', $lot->getReference()));

            $synthese = $this->piecesRepository->syntheseParLibelle($lot);

            if ([] === $synthese) {
                $io->success('Aucune pièce référencée pour ce lot.');

                return Command::SUCCESS;
            }

            $io->table(
                ['Pièce', 'Nombre', 'Dossiers', 'Présentes', 'Manquantes', 'Volume'],
                array_map(
                    fn (array $ligne): array => [
                        $ligne['libelle'],
                        $ligne['pieces'],
                        $ligne['dossiers'],
                        $ligne['presentes'],
                        $ligne['pieces'] - $ligne['presentes'],
                        $this->lisible($ligne['octets']),
                    ],
                    $synthese
                )
            );

            $pieces = array_sum(array_column($synthese, 'pieces'));
            $presentes = array_sum(array_column($synthese, 'presentes'));
            $octets = array_sum(array_column($synthese, 'octets'));

            $io->definitionList(
                ['Pièces référencées' => (string) $pieces],
                ['Présentes sur le disque' => sprintf('%d (%s)', $presentes, $this->pourcentage($presentes, $pieces))],
                ['Manquantes' => sprintf('%d (%s)', $pieces - $presentes, $this->pourcentage($pieces - $presentes, $pieces))],
                ['Volume mesuré' => $this->lisible($octets)],
                ['Volume extrapolé' => 0 === $presentes
                    ? 'non mesurable (aucune pièce lisible)'
                    : $this->lisible((int) round($octets / $presentes * $pieces))],
            );

            if (0 === $presentes) {
                // Le répertoire peut exister tout en étant vide : c'est le cas sur un poste de
                // développement, où les fichiers déposés ne sont pas répliqués. Et sous Windows,
                // un chemin de production comme « /var/data_symfony/... » est résolu sur le
                // lecteur courant, donc is_dir() ne révèle rien. La seule condition qui a du sens
                // est donc de n'avoir trouvé AUCUNE pièce.
                $io->warning(sprintf(
                    "Aucune des %d pièces n'a été trouvée sous %s\n"
                    . 'Le volume n\'est pas mesurable ici : la mesure doit être faite sur la recette '
                    . 'ou la production, où les fichiers déposés se trouvent réellement.',
                    $pieces,
                    rtrim($this->appRootDossierDataSymfony, '/') . '/uploads/'
                ));
            } elseif ($pieces > $presentes) {
                $io->note(
                    'Les pièces manquantes sont normales sur un historique de huit ans. Elles seront '
                    . 'déclarées dans le manifeste de chaque archive, et comptées dans le registre.'
                );
            }

            $this->afficherDetail($io, $input, $lot);

            $this->loggerArchivage->info('Pieces : recensement', [
                'reference' => $lot->getReference(),
                'pieces' => $pieces,
                'presentes' => $presentes,
                'octets' => $octets,
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Pieces : echec', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * L'inventaire couvre-t-il encore toutes les colonnes de pièces du schéma ?
     *
     * Ce contrôle n'est pas un test unitaire parce qu'il n'aurait aucun sens
     * sans la vraie base : c'est le schéma de production qui fait foi. Il a sa
     * place dans la recette avant chaque campagne — une colonne de document
     * ajoutée depuis la dernière campagne, et ses fichiers ne seraient pas
     * versés, sans que rien ne le signale.
     */
    private function verifierCouverture(SymfonyStyle $io): int
    {
        $io->title("Archivage — couverture de l'inventaire des pièces");

        $declarees = [];

        foreach (InventairePieces::EMPLACEMENTS as $emplacement) {
            $declarees[InventairePieces::TABLES_PAR_ALIAS[$emplacement['alias']] . '.' . $emplacement['colonne']] = true;
        }

        $colonnes = $this->connection->executeQuery(
            <<<'SQL'
            SELECT TABLE_NAME, COLUMN_NAME
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLUMN_NAME LIKE '%\_url'
               AND TABLE_NAME NOT LIKE 'bak%'
               -- admin_import et newsletter ne portent pas de piece de dossier ;
               -- partenaire_option_auditeur releve de la Reco-13.
               AND TABLE_NAME NOT IN ('admin_import', 'newsletter', 'partenaire_option_auditeur')
             ORDER BY TABLE_NAME, ORDINAL_POSITION
            SQL
        )->fetchAllAssociative();

        $lignes = [];
        $manquantes = [];

        foreach ($colonnes as $colonne) {
            $cle = $colonne['TABLE_NAME'] . '.' . $colonne['COLUMN_NAME'];
            $horsPerimetre = isset(InventairePieces::HORS_PERIMETRE[$cle]);
            $couverte = isset($declarees[$cle]);

            $nombre = (int) $this->connection->executeQuery(sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `%s` IS NOT NULL AND `%s` <> \'\'',
                $colonne['TABLE_NAME'],
                $colonne['COLUMN_NAME'],
                $colonne['COLUMN_NAME']
            ))->fetchOne();

            $lignes[] = [
                $colonne['TABLE_NAME'],
                $colonne['COLUMN_NAME'],
                $nombre,
                match (true) {
                    $couverte => 'oui',
                    $horsPerimetre => '<comment>hors périmètre</comment>',
                    default => '<error>NON</error>',
                },
            ];

            if (!$couverte && !$horsPerimetre && $nombre > 0) {
                $manquantes[$cle] = $nombre;
            }
        }

        $io->table(['Table', 'Colonne', 'Pièces', 'Couverte'], $lignes);

        if ([] === $manquantes) {
            $io->success(sprintf(
                'Les %d colonnes de pièces du schéma sont toutes couvertes par l\'inventaire.',
                count($colonnes)
            ));

            return Command::SUCCESS;
        }

        $io->error('Des pièces échapperaient à l\'archivage :');

        foreach ($manquantes as $cle => $nombre) {
            $io->text(sprintf('  %s — %d pièces', $cle, $nombre));
        }

        $io->text(
            'Chaque ligne ci-dessus est soit un emplacement à ajouter à InventairePieces, '
            . 'soit un emplacement à déclarer hors périmètre, avec sa raison.'
        );

        $this->loggerArchivage->error('Pieces : couverture incomplete', ['manquantes' => $manquantes]);

        return Command::FAILURE;
    }

    private function afficherDetail(SymfonyStyle $io, InputInterface $input, \App\Entity\ArchiveLot $lot): void
    {
        $detail = $input->getOption('detail');

        if (null === $detail || '' === $detail || !ctype_digit((string) $detail)) {
            return;
        }

        $parCompte = $this->piecesRepository->piecesParCompteEtDossier($lot);
        $comptes = array_slice($parCompte, 0, (int) $detail, true);

        foreach ($comptes as $userId => $dossiers) {
            $io->section(sprintf('Compte %d', $userId));

            foreach ($dossiers as $demandeId => $pieces) {
                $io->text(sprintf('<info>Dossier %d</info> — %d pièces', $demandeId, count($pieces)));

                foreach ($pieces as $piece) {
                    $io->text(sprintf(
                        '  %s %s',
                        $piece['presente'] ? '<info>présente  </info>' : '<comment>manquante </comment>',
                        $piece['nom_archive']
                    ));
                }
            }
        }
    }

    private function lisible(int $octets): string
    {
        if ($octets < 1024) {
            return $octets . ' o';
        }

        foreach (['Kio', 'Mio', 'Gio', 'Tio'] as $i => $unite) {
            $valeur = $octets / 1024 ** ($i + 1);

            if ($valeur < 1024 || 'Tio' === $unite) {
                return sprintf('%.1f %s', $valeur, $unite);
            }
        }

        return $octets . ' o';
    }

    private function pourcentage(int $part, int $total): string
    {
        return 0 === $total ? '-' : sprintf('%.1f %%', 100 * $part / $total);
    }
}

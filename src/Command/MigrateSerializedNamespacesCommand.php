<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:migrateSerializedNamespaces',
    description: 'Migrer les données sérialisées contenant les anciens namespaces whiteLabel vers App'
)]
class MigrateSerializedNamespacesCommand extends Command
{
    private Connection $connection;

    /**
     * Mapping des tables et colonnes à migrer
     * Format: ['table_name' => ['column1', 'column2', ...]]
     */
    private const MIGRATION_MAP = [
        'instruction_audit_energie' => ['jp_reason', 'kbis_reason', 'ai_reason'],
        'instruction_travaux' => ['jp_reason', 'kbis_reason', 'ai_reason'],
        'remboursement_audit_energie_instruction' => ['cheque_reason', 'facture_reason', 'rib_reason'],
        'remboursement_audit_numerique_instruction' => ['cheque_reason', 'facture_reason', 'rib_reason'],
        'remboursement_travaux_instruction' => ['cheque_reason', 'facture_reason', 'rib_reason', 'fiche_travaux_reason'],
    ];

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Exécuter en mode simulation sans modifier la base de données')
            ->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'Migrer uniquement une table spécifique')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limiter le nombre de lignes à traiter par table', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $targetTable = $input->getOption('table');
        $limit = $input->getOption('limit') ? (int)$input->getOption('limit') : null;

        $io->title('Migration des namespaces sérialisés');

        if ($dryRun) {
            $io->warning('MODE SIMULATION - Aucune modification ne sera apportée à la base de données');
        }

        $totalUpdates = 0;
        $totalRows = 0;

        try {
            // Filtrer les tables si une table spécifique est demandée
            $migrationMap = $targetTable
                ? array_filter(self::MIGRATION_MAP, fn($key) => $key === $targetTable, ARRAY_FILTER_USE_KEY)
                : self::MIGRATION_MAP;

            if (empty($migrationMap)) {
                $io->error("Table '$targetTable' non trouvée dans la configuration de migration");
                return Command::FAILURE;
            }

            foreach ($migrationMap as $tableName => $columns) {
                $io->section("Traitement de la table: $tableName");

                $tableUpdates = $this->migrateTable($tableName, $columns, $dryRun, $limit, $io);

                $totalUpdates += $tableUpdates['updated'];
                $totalRows += $tableUpdates['total'];

                $io->success("Table $tableName: {$tableUpdates['updated']} lignes mises à jour sur {$tableUpdates['total']} analysées");
            }

            $io->newLine();
            $io->success([
                'Migration terminée avec succès!',
                "Total: $totalUpdates lignes mises à jour sur $totalRows analysées",
            ]);

            if ($dryRun) {
                $io->note('Ceci était une simulation. Relancez la commande sans --dry-run pour appliquer les modifications.');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $io->error([
                'Erreur lors de la migration:',
                $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Migrer une table spécifique
     *
     * @param string $tableName Nom de la table
     * @param array $columns Liste des colonnes à migrer
     * @param bool $dryRun Mode simulation
     * @param int|null $limit Limite de lignes à traiter
     * @param SymfonyStyle $io
     * @return array{total: int, updated: int}
     * @throws Exception
     */
    private function migrateTable(
        string $tableName,
        array $columns,
        bool $dryRun,
        ?int $limit,
        SymfonyStyle $io
    ): array {
        $updated = 0;
        $total = 0;

        // Vérifier si la table existe
        $tableExists = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = DATABASE() 
             AND table_name = ?",
            [$tableName]
        );

        if (!$tableExists) {
            $io->warning("Table $tableName n'existe pas - ignorée");
            return ['total' => 0, 'updated' => 0];
        }

        // Construire la requête pour récupérer les données
        $columnsString = implode(', ', $columns);
        $sql = "SELECT id, $columnsString FROM $tableName";

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        $rows = $this->connection->fetchAllAssociative($sql);
        $total = count($rows);

        if ($total === 0) {
            $io->note("Aucune donnée trouvée dans $tableName");
            return ['total' => 0, 'updated' => 0];
        }

        $io->progressStart($total);

        foreach ($rows as $row) {
            $id = $row['id'];
            $hasChanges = false;
            $updates = [];

            foreach ($columns as $column) {
                $serializedData = $row[$column];

                // Ignorer les valeurs NULL ou vides
                if ($serializedData === null || $serializedData === '') {
                    continue;
                }

                // Vérifier si la colonne contient des données sérialisées avec l'ancien namespace
                if ($this->containsLegacyNamespace($serializedData)) {
                    $migratedData = $this->migrateNamespaces($serializedData);

                    if ($migratedData !== $serializedData) {
                        $updates[$column] = $migratedData;
                        $hasChanges = true;

                        if ($io->isVeryVerbose()) {
                            $io->text("  ID $id - Colonne $column: namespace migré");
                        }
                    }
                }
            }

            // Appliquer les mises à jour si nécessaire
            if ($hasChanges && !$dryRun) {
                $setClause = implode(', ', array_map(fn($col) => "$col = :$col", array_keys($updates)));
                $updateSql = "UPDATE $tableName SET $setClause WHERE id = :id";

                $params = array_merge($updates, ['id' => $id]);
                $this->connection->executeStatement($updateSql, $params);
            }

            if ($hasChanges) {
                $updated++;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        return ['total' => $total, 'updated' => $updated];
    }

    /**
     * Vérifier si les données contiennent un ancien namespace
     */
    private function containsLegacyNamespace(string $data): bool
    {
        return str_contains($data, 'whiteLabel\\BackOfficeBundle\\Entity\\');
    }

    /**
     * Migrer les anciens namespaces vers les nouveaux
     */
    private function migrateNamespaces(string $serializedData): string
    {
        $migrated = $serializedData;

        // 1) Remplacer toutes les déclarations d'objet O:...:"whiteLabel\...\Entity\Name" -> App\Entity\Name
        $migrated = preg_replace_callback(
            '#O:(\d+):"whiteLabel\\\\BackOfficeBundle\\\\Entity\\\\([^\"]+)"#',
            function ($matches) {
                $entityName = $matches[2];
                $newNamespace = "App\\Entity\\$entityName";
                $newLength = strlen($newNamespace);
                return "O:$newLength:\"$newNamespace\"";
            },
            $migrated
        );

        // 2) Parcourir toutes les chaînes s:...:"..."; et réparer les clés/propriétés contenant
        //    l'ancien namespace ou encodées avec des octets nuls. On gère trois cas :
        //    - clés privées/protégées encodées avec des NUL ("\0ClassName\0property")
        //    - clés collées contenant le namespace + propriété (ex: "App\Entity\Xproperty")
        //    - autres chaînes contenant l'ancien namespace (remplacement simple)
        $knownProperties = ['id', 'filtre', 'slug', 'positionLast'];

        $migrated = preg_replace_callback(
            '#s:(\d+):"(.*?)";#s',
            function ($matches) use ($knownProperties) {
                $content = $matches[2];

                // Si la chaîne ne contient pas le namespace legacy ou App\Entity, rien à faire
                if (strpos($content, 'whiteLabel\\BackOfficeBundle\\Entity\\') === false
                    && strpos($content, 'App\\Entity\\') === false
                    && strpos($content, "\0") === false) {
                    return $matches[0];
                }

                // Cas 1: clé encodée avec octets nuls -> récupérer la dernière partie (le nom de propriété)
                if (strpos($content, "\0") !== false) {
                    $parts = explode("\0", $content);
                    $newContent = end($parts) ?: $content;
                    $newLength = strlen($newContent);
                    return 's:' . $newLength . ':"' . $newContent . '";';
                }

                // Remplacer d'abord le namespace legacy par App\Entity\ si présent
                $content = str_replace('whiteLabel\\BackOfficeBundle\\Entity\\', 'App\\Entity\\', $content);

                // Cas 2: clé collée contenant namespace + propriété -> si la chaîne se termine par
                // une propriété connue, extraire uniquement la propriété
                foreach ($knownProperties as $prop) {
                    if (str_ends_with($content, $prop)) {
                        $newContent = $prop;
                        $newLength = strlen($newContent);
                        return 's:' . $newLength . ':"' . $newContent . '";';
                    }
                }

                // Cas 3: autre occurrence (texte libre contenant namespace) -> remplacer namespace
                $newContent = $content;
                $newLength = strlen($newContent);
                return 's:' . $newLength . ':"' . $newContent . '";';
            },
            $migrated
        );

        return $migrated;
    }
}

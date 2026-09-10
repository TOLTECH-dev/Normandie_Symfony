<?php

declare(strict_types=1);

namespace App\Repository\Demande;

/**
 * Critères validés de la consultation des dossiers : recherches par colonne, tri et pagination.
 *
 * Seules les colonnes connues sont retenues et le tri est pris dans une liste blanche. Les valeurs de
 * recherche restent telles que saisies : elles ne sont jamais concaténées au SQL, seulement liées en
 * paramètres (cf. DemandeListSql).
 */
final class DemandeListCriteria
{
    /** Colonnes filtrables : champs de recherche du pied de tableau, repris par le formulaire d'export */
    public const FILTER_FIELDS = [
        'demandeId',
        'demandeType',
        'beneficiaireIdentifiant',
        'logement',
        'demandeDateCreation',
        'demandeStatutSlug',
        'structureConseiller',
        'partenaire',
        'commissionDate',
        'remboursementDate',
    ];

    /** Colonnes triables : toutes les colonnes affichées sauf « Action » */
    public const SORT_COLUMNS = [
        'demandeId',
        'demandeType',
        'beneficiaireIdentifiant',
        'logement',
        'demandeDateCreation',
        'demandeStatutSlug',
        'structureConseiller',
        'auditEId',
        'partenaire',
        'commissionDate',
        'remboursementDate',
    ];

    public const DEFAULT_LENGTH = 25;

    /** Plus grand nombre de lignes par page proposé par la liste */
    public const MAX_LENGTH = 100;

    /** @var array<string, string> */
    private array $filters;
    private string $sortColumn;
    private string $sortDirection;
    private int $start;
    private int $length;

    /**
     * @param array<mixed> $filters
     */
    public function __construct(
        array $filters = [],
        string $sortColumn = 'demandeId',
        string $sortDirection = 'desc',
        int $start = 0,
        int $length = self::DEFAULT_LENGTH
    ) {
        if (!in_array($sortColumn, self::SORT_COLUMNS, true)) {
            throw new \InvalidArgumentException(sprintf('Colonne de tri inconnue : %s', $sortColumn));
        }
        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(sprintf('Sens de tri inconnu : %s', $sortDirection));
        }
        if ($start < 0 || $length < 1) {
            throw new \InvalidArgumentException('Pagination invalide');
        }

        $this->filters = self::normalizeFilters($filters);
        $this->sortColumn = $sortColumn;
        $this->sortDirection = $sortDirection;
        $this->start = $start;
        $this->length = $length;
    }

    /**
     * Critères envoyés par DataTables (traitement côté serveur). Toute valeur inattendue est remplacée
     * par la valeur par défaut : tri sur le numéro décroissant, 25 lignes à partir de la première.
     *
     * @param array<mixed> $post
     */
    public static function fromDataTables(array $post): self
    {
        $columns = is_array($post['columns'] ?? null) ? $post['columns'] : [];

        $filters = [];
        foreach ($columns as $column) {
            if (is_array($column) && is_string($column['data'] ?? null)) {
                $filters[$column['data']] = $column['search']['value'] ?? null;
            }
        }

        $order = is_array($post['order'][0] ?? null) ? $post['order'][0] : [];
        $columnIndex = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
        $sortColumn = false !== $columnIndex ? ($columns[$columnIndex]['data'] ?? null) : null;

        if (!in_array($sortColumn, self::SORT_COLUMNS, true)) {
            $sortColumn = 'demandeId';
            $sortDirection = 'desc';
        } else {
            $dir = $order['dir'] ?? null;
            $sortDirection = is_string($dir) && 'asc' === strtolower($dir) ? 'asc' : 'desc';
        }

        $length = (int) filter_var($post['length'] ?? null, FILTER_VALIDATE_INT);

        return new self(
            $filters,
            $sortColumn,
            $sortDirection,
            max(0, (int) filter_var($post['start'] ?? null, FILTER_VALIDATE_INT)),
            $length < 1 ? self::DEFAULT_LENGTH : min($length, self::MAX_LENGTH)
        );
    }

    /**
     * Ne garde que les colonnes filtrables dont la valeur est une chaîne non vide.
     *
     * @param array<mixed> $fields
     *
     * @return array<string, string>
     */
    public static function normalizeFilters(array $fields): array
    {
        $filters = [];
        foreach (self::FILTER_FIELDS as $field) {
            $value = $fields[$field] ?? null;
            if (is_int($value)) {
                $value = (string) $value;
            }
            if (is_string($value) && '' !== $value) {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    /** @return array<string, string> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function hasFilters(): bool
    {
        return [] !== $this->filters;
    }

    public function getSortColumn(): string
    {
        return $this->sortColumn;
    }

    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getLength(): int
    {
        return $this->length;
    }
}

<?php

declare(strict_types=1);

namespace App\Repository\User;

/**
 * Critères validés de la liste des comptes : recherches, tri et pagination.
 *
 * Les noms de colonnes provenaient du client et étaient concaténés dans la requête
 * (`u.<colonne> LIKE :param`, `orderBy($colonne, $sens)`). Un profil habilité pouvait donc filtrer et
 * trier sur n'importe quel champ de l'entité, dont `password` et `confirmationToken`, et en extraire
 * la valeur caractère par caractère. Seules les colonnes affichées par la page d'administration sont
 * désormais acceptées ; les valeurs saisies ne sont jamais écrites dans la requête, mais liées en
 * paramètres (cf. UserRepository).
 */
final class UserListCriteria
{
    /** Colonnes de la page d'administration (attributs data-fieldCode du tableau) */
    public const COLUMNS = ['id', 'username', 'firstname', 'lastname', 'email', 'enabled', 'roles', 'lastLogin'];

    public const DEFAULT_SORT = 'id';
    public const DEFAULT_LENGTH = 25;

    /** Plus grand nombre de lignes par page proposé par la liste */
    public const MAX_LENGTH = 100;

    /** @var array<string, string> */
    private array $filters;
    private string $search;
    private string $sortColumn;
    private string $sortDirection;
    private int $start;
    private int $length;

    /**
     * @param array<mixed> $filters recherche par colonne
     * @param string       $search  recherche globale, appliquée à toutes les colonnes
     */
    public function __construct(
        array $filters = [],
        string $search = '',
        string $sortColumn = self::DEFAULT_SORT,
        string $sortDirection = 'desc',
        int $start = 0,
        int $length = self::DEFAULT_LENGTH
    ) {
        if (!in_array($sortColumn, self::COLUMNS, true)) {
            throw new \InvalidArgumentException(sprintf('Colonne de tri inconnue : %s', $sortColumn));
        }
        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(sprintf('Sens de tri inconnu : %s', $sortDirection));
        }
        if ($start < 0 || $length < 1) {
            throw new \InvalidArgumentException('Pagination invalide');
        }

        $this->filters = self::normalizeFilters($filters);
        $this->search = $search;
        $this->sortColumn = $sortColumn;
        $this->sortDirection = $sortDirection;
        $this->start = $start;
        $this->length = $length;
    }

    /**
     * Critères envoyés par DataTables. Toute valeur inattendue est remplacée par la valeur par
     * défaut : tri sur le numéro décroissant, 25 lignes à partir de la première.
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

        if (!in_array($sortColumn, self::COLUMNS, true)) {
            $sortColumn = self::DEFAULT_SORT;
            $sortDirection = 'desc';
        } else {
            $dir = $order['dir'] ?? null;
            $sortDirection = is_string($dir) && 'asc' === strtolower($dir) ? 'asc' : 'desc';
        }

        $search = $post['search']['value'] ?? '';
        $length = (int) filter_var($post['length'] ?? null, FILTER_VALIDATE_INT);

        return new self(
            $filters,
            is_string($search) ? $search : '',
            $sortColumn,
            $sortDirection,
            max(0, (int) filter_var($post['start'] ?? null, FILTER_VALIDATE_INT)),
            $length < 1 ? self::DEFAULT_LENGTH : min($length, self::MAX_LENGTH)
        );
    }

    /**
     * Ne garde que les colonnes de la page dont la valeur est une chaîne non vide.
     *
     * @param array<mixed> $fields
     *
     * @return array<string, string>
     */
    public static function normalizeFilters(array $fields): array
    {
        $filters = [];
        foreach (self::COLUMNS as $column) {
            $value = $fields[$column] ?? null;
            if (is_int($value)) {
                $value = (string) $value;
            }
            if (is_string($value) && '' !== $value) {
                $filters[$column] = $value;
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

    public function getSearch(): string
    {
        return $this->search;
    }

    public function hasSearch(): bool
    {
        return '' !== $this->search;
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

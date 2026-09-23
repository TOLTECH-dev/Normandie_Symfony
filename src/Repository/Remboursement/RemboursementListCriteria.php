<?php

declare(strict_types=1);

namespace App\Repository\Remboursement;

use App\Repository\Demande\DemandeListCriteria;

/**
 * Critères validés de la liste « Remboursement des aides » : recherches par colonne, recherche
 * générale, tri et pagination.
 *
 * Même principe que la consultation des dossiers (DemandeListCriteria) : seules les colonnes
 * connues sont retenues, le tri est pris dans une liste blanche, et les valeurs de recherche ne
 * sont jamais écrites dans le SQL — elles sont liées en paramètres (cf. RemboursementListSql).
 */
final class RemboursementListCriteria
{
    /**
     * Colonnes filtrables. Les colonnes affichées regroupent plusieurs informations (« dossier » =
     * numéro, numéro de chèque et thématique ; « beneficiaire » = identité et logement) : un champ
     * de recherche interroge alors tous les champs de son groupe. Les noms atomiques restent
     * acceptés, la liste ayant été présentée ainsi jusqu'ici.
     */
    public const FILTER_FIELDS = [
        'dossier',
        'beneficiaire',
        'demandeId',
        'numeroCheque',
        'logement',
        'statut',
        'structureConseiller',
        'partenaire',
        'demandeType',
        'RMHDate',
    ];

    /** Colonnes triables : toutes les colonnes affichées sauf « Action » */
    public const SORT_COLUMNS = [
        'dossier',
        'beneficiaire',
        'demandeId',
        'numeroCheque',
        'logement',
        'statut',
        'structureConseiller',
        'partenaire',
        'demandeType',
        'RMHDate',
    ];

    /** Champs à choix multiple : valeurs séparées par une barre verticale, comme pour les dossiers */
    public const MULTI_VALUE_FIELDS = ['demandeType', 'statut'];

    public const DEFAULT_LENGTH = 25;

    /** Plus grand nombre de lignes par page proposé par la liste */
    public const MAX_LENGTH = 100;

    /** @var array<string, string> */
    private array $filters;
    private string $sortColumn;
    private string $sortDirection;
    private int $start;
    private int $length;
    private string $globalSearch;

    /**
     * @param array<mixed> $filters
     */
    public function __construct(
        array $filters = [],
        string $sortColumn = 'demandeId',
        string $sortDirection = 'desc',
        int $start = 0,
        int $length = self::DEFAULT_LENGTH,
        string $globalSearch = ''
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
        $this->globalSearch = DemandeListCriteria::normalizeSearch($globalSearch);
    }

    /**
     * Critères envoyés par DataTables (traitement côté serveur). Toute valeur inattendue est
     * remplacée par la valeur par défaut : tri sur le numéro de dossier décroissant, 25 lignes.
     *
     * @param array<mixed> $post
     */
    public static function fromDataTables(array $post): self
    {
        $columns = is_array($post['columns'] ?? null) ? $post['columns'] : [];

        // Une colonne affichée regroupe parfois plusieurs champs : c'est son nom logique qui compte
        $nomDeColonne = static function (mixed $column): ?string {
            if (!is_array($column)) {
                return null;
            }
            foreach (['name', 'data'] as $cle) {
                if (is_string($column[$cle] ?? null) && '' !== $column[$cle]) {
                    return $column[$cle];
                }
            }

            return null;
        };

        $filters = [];
        foreach ($columns as $column) {
            $nom = $nomDeColonne($column);
            if (null !== $nom) {
                $filters[$nom] = $column['search']['value'] ?? null;
            }
        }

        $order = is_array($post['order'][0] ?? null) ? $post['order'][0] : [];
        $columnIndex = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
        $sortColumn = false !== $columnIndex ? $nomDeColonne($columns[$columnIndex] ?? null) : null;

        if (!in_array($sortColumn, self::SORT_COLUMNS, true)) {
            $sortColumn = 'demandeId';
            $sortDirection = 'desc';
        } else {
            $dir = $order['dir'] ?? null;
            $sortDirection = is_string($dir) && 'asc' === strtolower($dir) ? 'asc' : 'desc';
        }

        $length = (int) filter_var($post['length'] ?? null, FILTER_VALIDATE_INT);
        $recherche = is_array($post['search'] ?? null) ? ($post['search']['value'] ?? '') : '';

        return new self(
            $filters,
            $sortColumn,
            $sortDirection,
            max(0, (int) filter_var($post['start'] ?? null, FILTER_VALIDATE_INT)),
            $length < 1 ? self::DEFAULT_LENGTH : min($length, self::MAX_LENGTH),
            DemandeListCriteria::normalizeSearch($recherche)
        );
    }

    /**
     * Ne garde que les colonnes filtrables dont la valeur est une chaîne non vide, bornée en
     * longueur — largement pour les champs à choix multiple, qui portent plusieurs libellés.
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
            if (is_array($value) && in_array($field, self::MULTI_VALUE_FIELDS, true)) {
                $value = implode(DemandeListCriteria::MULTI_VALUE_SEPARATOR, array_filter($value, 'is_string'));
            }
            if (is_string($value) && '' !== $value) {
                $borne = in_array($field, self::MULTI_VALUE_FIELDS, true)
                    ? DemandeListCriteria::MAX_MULTI_LENGTH
                    : DemandeListCriteria::MAX_SEARCH_LENGTH;
                $filters[$field] = mb_substr($value, 0, $borne);
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

    public function getGlobalSearch(): string
    {
        return $this->globalSearch;
    }

    public function hasGlobalSearch(): bool
    {
        return '' !== $this->globalSearch;
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

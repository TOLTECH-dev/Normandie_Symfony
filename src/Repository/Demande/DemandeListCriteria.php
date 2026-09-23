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
    /**
     * Colonnes filtrables : champs de recherche du pied de tableau, repris par le formulaire d'export.
     *
     * Les colonnes affichées regroupent plusieurs informations (« dossier » = numéro et date de
     * demande, « beneficiaire » = identité et logement, « echeances » = commission et remboursement) :
     * un champ de recherche interroge alors tous les champs de son groupe. Les noms atomiques restent
     * acceptés, pour les exports enregistrés avant ce regroupement.
     */
    public const FILTER_FIELDS = [
        'dossier',
        'beneficiaire',
        'echeances',
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
        'dossier',
        'beneficiaire',
        'echeances',
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

    /**
     * Champs à choix multiple : leur valeur est une liste de valeurs séparées par une barre verticale
     * (« Travaux niveau 1|Travaux niveau 2 »). Aucun libellé de thématique ni de statut n'en contient,
     * et les valeurs restent vérifiées une à une avant d'entrer dans le SQL (cf. DemandeListSql).
     */
    public const MULTI_VALUE_FIELDS = ['demandeType', 'demandeStatutSlug'];

    public const MULTI_VALUE_SEPARATOR = '|';

    /** Longueur maximale d'une recherche : au-delà, la valeur est tronquée */
    public const MAX_SEARCH_LENGTH = 100;

    /** Longueur maximale d'une liste de valeurs choisies (les libellés de statut sont longs) */
    public const MAX_MULTI_LENGTH = 4000;

    /** Nombre maximal de valeurs retenues dans une liste */
    public const MAX_MULTI_VALUES = 100;

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
        $this->globalSearch = self::normalizeSearch($globalSearch);
    }

    /** Recherche générale : une chaîne, tronquée, liée en paramètre comme les autres */
    public static function normalizeSearch(mixed $value): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) ? mb_substr(trim($value), 0, self::MAX_SEARCH_LENGTH) : '';
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

        // Une colonne affichée regroupe parfois plusieurs champs : c'est son nom logique qui compte,
        // le nom de donnée ne servant qu'à défaut.
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

        // Recherche générale du tableau : elle porte sur toutes les informations affichées.
        $recherche = is_array($post['search'] ?? null) ? ($post['search']['value'] ?? '') : '';

        return new self(
            $filters,
            $sortColumn,
            $sortDirection,
            max(0, (int) filter_var($post['start'] ?? null, FILTER_VALIDATE_INT)),
            $length < 1 ? self::DEFAULT_LENGTH : min($length, self::MAX_LENGTH),
            self::normalizeSearch($recherche)
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
            // Un champ à choix multiple peut arriver sous forme de tableau (envoi de formulaire)
            if (is_array($value) && in_array($field, self::MULTI_VALUE_FIELDS, true)) {
                $value = implode(self::MULTI_VALUE_SEPARATOR, array_filter($value, 'is_string'));
            }
            if (is_string($value) && '' !== $value) {
                // Même borne que la recherche générale : une saisie démesurée n'a pas de sens
                $borne = in_array($field, self::MULTI_VALUE_FIELDS, true)
                    ? self::MAX_MULTI_LENGTH
                    : self::MAX_SEARCH_LENGTH;
                $filters[$field] = mb_substr($value, 0, $borne);
            }
        }

        return $filters;
    }

    /**
     * Découpe la valeur d'un champ à choix multiple. Les valeurs vides et les doublons sont écartés,
     * et le nombre de valeurs est borné : la liste vient d'un formulaire, pas d'une saisie libre.
     *
     * @return list<string>
     */
    public static function splitValues(string $value): array
    {
        $valeurs = [];
        foreach (explode(self::MULTI_VALUE_SEPARATOR, $value) as $valeur) {
            $valeur = trim($valeur);
            if ('' !== $valeur && !in_array($valeur, $valeurs, true)) {
                $valeurs[] = $valeur;
            }
            if (count($valeurs) >= self::MAX_MULTI_VALUES) {
                break;
            }
        }

        return $valeurs;
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

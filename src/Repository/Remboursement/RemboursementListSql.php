<?php

declare(strict_types=1);

namespace App\Repository\Remboursement;

use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;

/**
 * Conditions de recherche et de tri de la liste « Remboursement des aides ».
 *
 * Sécurité : aucune saisie n'entre dans le SQL. Les recherches sont liées en paramètres nommés, la
 * colonne et le sens du tri viennent d'une liste blanche, la pagination est faite d'entiers. La
 * requête elle-même (jointures, périmètre du profil) reste celle du dépôt, inchangée : cette classe
 * ne produit que le fragment WHERE, l'expression de tri et les paramètres à lier.
 */
final class RemboursementListSql
{
    /**
     * Recherches « contient » : champ => expressions testées (OU).
     *
     * Les alias sont ceux de Remboursement_Repository::findForListAjax.
     */
    private const LIKE_FILTERS = [
        // Colonnes regroupées de la liste
        'dossier'             => ['d.id', 't.numero_cheque'],
        'beneficiaire'        => ['b.nom', 'b.prenom', 'b.nom_SCI', 'l.code_postal', 'l.ville'],

        // Noms atomiques, tels que la liste les employait jusqu'ici
        'demandeId'           => ['d.id'],
        'numeroCheque'        => ['t.numero_cheque'],
        'logement'            => ['l.code_postal', 'l.ville'],
        'structureConseiller' => [
            'si_dae.nom', 'sc_dae.nom', 'si_dan.nom', 'sc_dan.nom', 'si_dt.nom', 'sc_dt.nom',
        ],
        'partenaire'          => ['pi_dae.raison_sociale', 'pi_dan.raison_sociale', 'pi_dtd.raison_sociale'],
        'RMHDate'             => ["DATE_FORMAT(dRMH.date_RMH, '%d/%m/%Y')"],

        // Le filtre de statut de la liste est une saisie libre : la recherche reste partielle, et
        // porte sur les deux familles de statut comme avant la correction. L'égalité stricte ne
        // vaudrait que pour une sélection dans une liste déroulante.
        'statut'              => ['ds.slug', 'rs.slug'],
    ];

    /** Champs balayés par la recherche générale (le statut est ajouté à part) */
    private const GLOBAL_SEARCH_FIELDS = ['dossier', 'beneficiaire', 'structureConseiller', 'partenaire', 'RMHDate'];

    /** Statut affiché : celui du remboursement s'il existe, sinon celui de la demande */
    private const STATUT_EXPRESSION = 'IF(r.id, rs.slug, ds.slug)';

    /** Expressions de tri autorisées : nom de colonne => expression SQL */
    private const SORT_EXPRESSIONS = [
        'dossier'             => 'd.id',
        'demandeId'           => 'd.id',
        'numeroCheque'        => 't.numero_cheque',
        'beneficiaire'        => 'b.nom',
        'logement'            => 'l.ville',
        'statut'              => self::STATUT_EXPRESSION,
        'structureConseiller' => 'structureConseiller',
        'partenaire'          => 'partenaire',
        'demandeType'         => 'd.type',
        'RMHDate'             => 'dRMH.date_RMH',
    ];

    /** Thématiques sans niveau de travaux : code affiché => type de demande */
    private const TYPES_SANS_NIVEAU = [
        Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
        Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
        Demande_::DEMANDE_TRAVAUX_TYPE,
        Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
        Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE,
    ];

    /** Thématiques de travaux : code affiché => premier caractère du niveau */
    private const NIVEAUX_TRAVAUX = [
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE            => '0',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE            => '1',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE => '2',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE => '6',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE  => '7',
    ];

    /**
     * Thématiques de travaux versées en deux fois : code affiché => [niveau, versement].
     * Le versement désigne le numéro d'opération du titre (BBC1 ou BBC2).
     */
    private const NIVEAUX_TRAVAUX_BBC = [
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE         => ['3', 1],
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE         => ['3', 2],
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE          => ['4', 1],
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE          => ['4', 2],
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE  => ['8', 1],
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE  => ['8', 2],
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE => ['9', 1],
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE => ['9', 2],
    ];

    /**
     * Fragment WHERE des recherches et paramètres à lier.
     *
     * @return array{0: string, 1: array<string, string>} le fragment commence par « AND », ou est vide
     */
    public static function conditions(
        RemboursementListCriteria $criteria,
        int $operationBBC1,
        int $operationBBC2
    ): array {
        $conditions = [];
        $params = [];

        foreach ($criteria->getFilters() as $field => $value) {
            if (isset(self::LIKE_FILTERS[$field])) {
                $param = 'filtre_' . $field;
                $tests = array_map(
                    static fn (string $expression): string => $expression . ' LIKE :' . $param,
                    self::LIKE_FILTERS[$field]
                );
                $conditions[] = '(' . implode(' OR ', $tests) . ')';
                $params[$param] = '%' . $value . '%';
            } elseif ('demandeType' === $field) {
                // Choix multiple : chaque code est repris d'une liste blanche, jamais de la saisie
                $thematiques = [];
                foreach (RemboursementListCriteria::splitValues($value) as $code) {
                    $condition = self::demandeTypeCondition($code, $operationBBC1, $operationBBC2);
                    if (null !== $condition) {
                        $thematiques[] = $condition;
                    }
                }
                if ([] !== $thematiques) {
                    $conditions[] = '(' . implode(' OR ', $thematiques) . ')';
                }
            }
        }

        if ($criteria->hasGlobalSearch()) {
            $expressions = [];
            foreach (self::GLOBAL_SEARCH_FIELDS as $field) {
                foreach (self::LIKE_FILTERS[$field] as $expression) {
                    $expressions[] = $expression;
                }
            }
            $expressions[] = self::STATUT_EXPRESSION;

            $tests = array_map(
                static fn (string $expression): string => $expression . ' LIKE :recherche_generale',
                $expressions
            );
            $conditions[] = '(' . implode(' OR ', $tests) . ')';
            $params['recherche_generale'] = '%' . $criteria->getGlobalSearch() . '%';
        }

        return [[] === $conditions ? '' : ' AND ' . implode(' AND ', $conditions), $params];
    }

    /** Fragment « ORDER BY », construit à partir de la liste blanche des colonnes triables */
    public static function orderBy(RemboursementListCriteria $criteria): string
    {
        $expression = self::SORT_EXPRESSIONS[$criteria->getSortColumn()] ?? 'd.id';
        $sens = 'asc' === $criteria->getSortDirection() ? 'ASC' : 'DESC';

        // Départage stable : deux lignes de même valeur gardent le même ordre d'une page à l'autre
        return sprintf(' ORDER BY %s %s, d.id DESC, r.id ASC', $expression, $sens);
    }

    /** Fragment « LIMIT », fait d'entiers validés par les critères */
    public static function limit(RemboursementListCriteria $criteria): string
    {
        return sprintf(' LIMIT %d, %d', $criteria->getStart(), $criteria->getLength());
    }

    /** Condition d'une thématique, ou null si le code n'est pas reconnu */
    private static function demandeTypeCondition(string $value, int $operationBBC1, int $operationBBC2): ?string
    {
        if (!ctype_digit($value)) {
            return null;
        }

        $code = (int) $value;

        if (in_array($code, self::TYPES_SANS_NIVEAU, true)) {
            return sprintf('(d.type = %d AND dtd.niveau IS NULL)', $code);
        }

        if (isset(self::NIVEAUX_TRAVAUX[$code])) {
            return sprintf(
                "(d.type = %d AND SUBSTRING(dtd.niveau, 1, 1) = '%s')",
                Demande_::DEMANDE_TRAVAUX_TYPE,
                self::NIVEAUX_TRAVAUX[$code]
            );
        }

        if (isset(self::NIVEAUX_TRAVAUX_BBC[$code])) {
            [$niveau, $versement] = self::NIVEAUX_TRAVAUX_BBC[$code];

            return sprintf(
                "(d.type = %d AND SUBSTRING(dtd.niveau, 1, 1) = '%s' AND t.numero_operation = %d)",
                Demande_::DEMANDE_TRAVAUX_TYPE,
                $niveau,
                1 === $versement ? $operationBBC1 : $operationBBC2
            );
        }

        return null;
    }
}

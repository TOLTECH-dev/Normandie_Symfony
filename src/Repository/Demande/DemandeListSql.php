<?php

declare(strict_types=1);

namespace App\Repository\Demande;

use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux_devis;
use App\Entity\Remboursement_statut;

/**
 * Requêtes SQL de la consultation des dossiers (page DataTables, comptage) et filtres de l'export.
 *
 * Sécurité : aucune saisie n'est concaténée. Les recherches sont liées en paramètres nommés, la colonne
 * et le sens du tri viennent d'une liste blanche, les identifiants de périmètre sont des entiers typés.
 *
 * Performance : la page est lue en deux temps. La première requête ne joint que les tables utiles au
 * filtre, au périmètre et au tri, et ne renvoie que les couples (dossier, remboursement) de la page ;
 * la seconde lit le détail de ces seules lignes, sous-requête « audit énergétique » comprise.
 */
final class DemandeListSql
{
    /**
     * Jointures disponibles : alias => [SQL, alias parent], dans l'ordre d'écriture (parent avant enfant).
     * Hors titres, remboursements et EPCI, chacune porte sur une clé primaire : l'omettre quand rien ne
     * la référence ne change pas les lignes renvoyées.
     */
    private const JOINS = [
        'b'         => ['INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id', null],
        'l'         => ['INNER JOIN logement l ON l.id = d.logement_id', null],
        'ds'        => ['INNER JOIN demande_statut ds ON ds.id = d.statut_id', null],
        'dCP'       => ['LEFT JOIN date_CP dCP ON dCP.id = d.dateCP_id', null],
        'dae'       => ['LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id', null],
        's_dae'     => ['LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id', 'dae'],
        'si_dae'    => ['LEFT JOIN structure_identification si_dae ON s_dae.structure_identification_id = si_dae.id', 's_dae'],
        'sc_dae'    => ['LEFT JOIN structure_conseiller sc_dae ON dae.conseiller_id = sc_dae.id', 'dae'],
        'p_dae'     => ['LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id', 'dae'],
        'pi_dae'    => ['LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id', 'p_dae'],
        'dan'       => ['LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id', null],
        's_dan'     => ['LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id', 'dan'],
        'si_dan'    => ['LEFT JOIN structure_identification si_dan ON s_dan.structure_identification_id = si_dan.id', 's_dan'],
        'sc_dan'    => ['LEFT JOIN structure_conseiller sc_dan ON dan.conseiller_id = sc_dan.id', 'dan'],
        'p_dan'     => ['LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id', 'dan'],
        'pi_dan'    => ['LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id', 'p_dan'],
        'dt'        => ['LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id', null],
        's_dt'      => ['LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id', 'dt'],
        'si_dt'     => ['LEFT JOIN structure_identification si_dt ON s_dt.structure_identification_id = si_dt.id', 's_dt'],
        'sc_dt'     => ['LEFT JOIN structure_conseiller sc_dt ON dt.conseiller_id = sc_dt.id', 'dt'],
        'dtd'       => ['LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id', 'dt'],
        'p_dtd'     => ['LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id', 'dtd'],
        'pi_dtd'    => ['LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id', 'p_dtd'],
        'p_dtd_aud' => ['LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id', 'dtd'],
        't'         => ['LEFT JOIN titre t ON t.demande_id = d.id', null],
        't2'        => ['LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id', 't'],
        'r2'        => ['LEFT JOIN remboursement_ r2 ON r2.demande_id = d.id AND r2.titre_id = t2.id', 't2'],
        'r'         => ['LEFT JOIN remboursement_ r ON r.titre_id = t.id', 't'],
        'rs'        => ['LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id', 'r'],
        'dRMH'      => ['LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id', 'r'],
        'uv'        => ['LEFT JOIN up_ville uv ON l.INSEE = uv.code_insee', 'l'],
        'o'         => ['LEFT JOIN orientation o ON uv.id = o.ville_id', 'uv'],
    ];

    /**
     * Toujours jointes : les INNER JOIN écartent les dossiers incomplets, titres et remboursements
     * servent à la condition sur les titres BBC.
     */
    private const BASE_ALIASES = ['b', 'l', 'ds', 't', 't2', 'r2', 'r'];

    /** Tables lues par le détail d'une ligne (detailQuery) */
    private const DETAIL_ALIASES = [
        'b', 'l', 'ds', 'dCP', 'si_dae', 'sc_dae', 'pi_dae', 'si_dan', 'sc_dan', 'pi_dan',
        'dt', 'si_dt', 'sc_dt', 'dtd', 'pi_dtd', 'r', 'rs', 'dRMH',
    ];

    /**
     * Champs balayés par la recherche générale : les groupes affichés, plus la structure, le
     * professionnel et le statut (ajouté à part, son expression dépendant du remboursement).
     */
    private const GLOBAL_SEARCH_FIELDS = [
        'dossier',
        'beneficiaire',
        'echeances',
        'structureConseiller',
        'partenaire',
    ];

    /** Recherches « contient » : champ => [expressions testées (OU), alias nécessaires] */
    private const LIKE_FILTERS = [
        // Colonnes regroupées de la liste : un champ de recherche interroge tout son groupe
        'dossier'                 => [['d.id', "DATE_FORMAT(d.date_creation, '%d/%m/%Y')"], []],
        'beneficiaire'            => [
            ['b.nom', 'b.prenom', 'b.nom_SCI', 'l.code_postal', 'l.ville'],
            ['b', 'l'],
        ],
        'echeances'               => [
            ["DATE_FORMAT(dCP.date_CP, '%d/%m/%Y')", "DATE_FORMAT(dRMH.date_RMH, '%d/%m/%Y')"],
            ['dCP', 'dRMH'],
        ],

        'demandeId'               => [['d.id'], []],
        // (suite : champs atomiques, conservés pour les exports enregistrés avant le regroupement)
        'beneficiaireIdentifiant' => [['b.nom', 'b.prenom', 'b.nom_SCI'], ['b']],
        'logement'                => [['l.code_postal', 'l.ville'], ['l']],
        'demandeDateCreation'     => [["DATE_FORMAT(d.date_creation, '%d/%m/%Y')"], []],
        'structureConseiller'     => [
            ['si_dae.nom', 'sc_dae.nom', 'si_dan.nom', 'sc_dan.nom', 'si_dt.nom', 'sc_dt.nom'],
            ['si_dae', 'sc_dae', 'si_dan', 'sc_dan', 'si_dt', 'sc_dt'],
        ],
        'partenaire'              => [
            ['pi_dae.raison_sociale', 'pi_dan.raison_sociale', 'pi_dtd.raison_sociale'],
            ['pi_dae', 'pi_dan', 'pi_dtd'],
        ],
        'commissionDate'          => [["DATE_FORMAT(dCP.date_CP, '%d/%m/%Y')"], ['dCP']],
        'remboursementDate'       => [["DATE_FORMAT(dRMH.date_RMH, '%d/%m/%Y')"], ['dRMH']],
    ];

    /** Thématiques sans niveau de travaux (filtre : d.type = code et aucun niveau) */
    private const TYPES_SANS_NIVEAU = [
        Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
        Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
        Demande_::DEMANDE_TRAVAUX_TYPE,
        Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
        Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE,
    ];

    /** Thématiques de travaux : code affiché => premier caractère de demande_travaux_devis.niveau */
    private const NIVEAUX_TRAVAUX = [
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE                      => '0',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE                      => '1',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE           => '2',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_CODE         => '3',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_CODE          => '4',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE        => '6',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE         => '7',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_CODE  => '8',
        Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_CODE => '9',
    ];

    /** @var array<string, true> */
    private array $aliases = [];

    /** @var list<string> */
    private array $conditions = [];

    /** @var array<string, string> */
    private array $params = [];

    public function __construct(int $bbcNiveau1, int $bbcNiveau2)
    {
        $this->addAliases(self::BASE_ALIASES);

        // Condition historique sur les titres BBC1 / BBC2, reprise à l'identique
        $this->conditions[] = sprintf(
            '(t.id IS NULL'
            . ' OR t.numero_operation NOT IN (%1$d, %2$d)'
            . ' OR t.numero_operation = %2$d AND r2.statut_id = %3$d'
            . ' OR t.numero_operation = %1$d AND r.statut_id != %3$d'
            . ' OR t.numero_operation = %1$d AND r.id IS NULL)',
            $bbcNiveau1,
            $bbcNiveau2,
            Remboursement_statut::STATUS_22
        );
    }

    /* ----------------------------------------------------------------
                        PÉRIMÈTRE DU PROFIL CONNECTÉ
    ---------------------------------------------------------------- */

    /** Conseiller : dossiers des bénéficiaires rattachés à sa structure */
    public function restrictToStructure(int $structureId): self
    {
        return $this->restrict(sprintf('b.structure_rattachement_id = %d', $structureId), ['b']);
    }

    /** Auditeur : audits qui lui sont confiés, travaux avec audit qu'il réalise */
    public function restrictToAuditeur(int $partenaireId): self
    {
        return $this->restrict(
            sprintf("(p_dae.id = %1\$d OR p_dan.id = %1\$d OR (p_dtd_aud.id = %1\$d AND dt.audit = '1'))", $partenaireId),
            ['p_dae', 'p_dan', 'p_dtd_aud', 'dt']
        );
    }

    /** Rénovateur : travaux qu'il réalise */
    public function restrictToRenovateur(int $partenaireId): self
    {
        return $this->restrict(sprintf('dtd.renovateur_id = %d', $partenaireId), ['dtd']);
    }

    /** EPCI : logements des communes qui lui sont orientées */
    public function restrictToEpci(int $epciId): self
    {
        return $this->restrict(sprintf('o.EPCI_id = %d', $epciId), ['o']);
    }

    /** Technique : travaux non abandonnés disposant d'une fiche technique ou d'un devis */
    public function restrictToTechnique(): self
    {
        return $this->restrict(
            sprintf(
                '(d.statut_id <> %d AND (dt.fiche_technique_id IS NOT NULL OR dt.travaux_devis_id IS NOT NULL))',
                Demande_statut::STATUS_15
            ),
            ['dt']
        );
    }

    /* ----------------------------------------------------------------
                                RECHERCHES
    ---------------------------------------------------------------- */

    /**
     * Ajoute les recherches de la liste. Le statut recherché est celui affiché : statut du remboursement
     * s'il existe, sinon statut de la demande.
     *
     * @param array<string, string> $filters recherches validées (DemandeListCriteria::normalizeFilters)
     */
    public function filterBy(array $filters): self
    {
        foreach (self::filterParts($filters, false) as [$condition, $params, $aliases]) {
            $this->conditions[] = $condition;
            $this->params += $params;
            $this->addAliases($aliases);
        }

        return $this;
    }

    /**
     * Recherche générale : la valeur est cherchée dans toutes les informations affichées, statut
     * compris. Comme les recherches par colonne, elle est liée en paramètre, jamais concaténée.
     */
    public function searchEverywhere(string $recherche): self
    {
        $recherche = DemandeListCriteria::normalizeSearch($recherche);
        if ('' === $recherche) {
            return $this;
        }

        $expressions = [];
        $aliases = [];
        foreach (self::GLOBAL_SEARCH_FIELDS as $field) {
            [$champs, $aliasDuChamp] = self::LIKE_FILTERS[$field];
            foreach ($champs as $champ) {
                $expressions[] = $champ;
            }
            $aliases = array_merge($aliases, $aliasDuChamp);
        }

        // Statut affiché : celui du remboursement s'il existe, sinon celui de la demande
        $expressions[] = 'IF(rs.slug IS NOT NULL, rs.slug, ds.slug)';
        $aliases[] = 'ds';
        $aliases[] = 'rs';

        $tests = array_map(
            static fn (string $expression): string => $expression . ' LIKE :recherche_generale',
            $expressions
        );

        $this->conditions[] = '(' . implode(' OR ', $tests) . ')';
        $this->params['recherche_generale'] = '%' . $recherche . '%';
        $this->addAliases(array_values(array_unique($aliases)));

        return $this;
    }

    /**
     * Conditions et paramètres des recherches pour la requête d'export, qui joint déjà toutes les tables.
     * Le statut recherché y est celui de la demande ou du remboursement.
     *
     * @param array<string, string> $filters recherches validées (DemandeListCriteria::normalizeFilters)
     *
     * @return array{0: list<string>, 1: array<string, string>}
     */
    public static function exportFilterConditions(array $filters): array
    {
        $conditions = [];
        $params = [];
        foreach (self::filterParts($filters, true) as [$condition, $conditionParams]) {
            $conditions[] = $condition;
            $params += $conditionParams;
        }

        return [$conditions, $params];
    }

    /* ----------------------------------------------------------------
                                REQUÊTES
    ---------------------------------------------------------------- */

    /** Nombre de lignes de la liste (couples dossier / remboursement) */
    public function countQuery(): string
    {
        return 'SELECT COUNT(*) FROM ('
            . 'SELECT 1 FROM demande_ d ' . self::joinClause($this->aliases)
            . ' WHERE ' . implode(' AND ', $this->conditions)
            . ' GROUP BY d.id, r.id'
            . ') AS lignes';
    }

    /**
     * Couples (demandeId, remboursementId) de la page, dans l'ordre d'affichage. Le numéro de dossier
     * puis le remboursement départagent les égalités, pour une pagination stable.
     */
    public function pageQuery(string $sortColumn, string $sortDirection, int $start, int $length): string
    {
        $direction = 'asc' === $sortDirection ? 'ASC' : 'DESC';
        $limit = sprintf(' LIMIT %d, %d', max(0, $start), max(1, $length));
        $where = ' WHERE ' . implode(' AND ', $this->conditions);

        if ('auditEId' === $sortColumn) {
            // Sous-requête corrélée évaluée sur les couples regroupés plutôt que sur chaque ligne jointe
            return 'SELECT lignes.demandeId, lignes.remboursementId FROM ('
                . 'SELECT d.id AS demandeId, r.id AS remboursementId, MAX(d.logement_id) AS logementId'
                . ' FROM demande_ d ' . self::joinClause($this->aliases) . $where
                . ' GROUP BY d.id, r.id'
                . ') AS lignes'
                . ' ORDER BY ' . self::auditEnergieSubquery('lignes.demandeId', 'lignes.logementId') . ' ' . $direction
                . ', lignes.demandeId DESC, lignes.remboursementId ASC'
                . $limit;
        }

        $aliases = $this->aliases;
        if ('demandeId' === $sortColumn) {
            $order = 'd.id ' . $direction;
        } else {
            [$expression, $sortAliases] = self::sortExpression($sortColumn);
            $aliases += array_fill_keys($sortAliases, true);
            // MAX : valeur identique sur tout le groupe, sans dépendre du mode ONLY_FULL_GROUP_BY
            $order = 'MAX(' . $expression . ') ' . $direction . ', d.id DESC';
        }

        return 'SELECT d.id AS demandeId, r.id AS remboursementId'
            . ' FROM demande_ d ' . self::joinClause($aliases) . $where
            . ' GROUP BY d.id, r.id'
            . ' ORDER BY ' . $order . ', r.id ASC'
            . $limit;
    }

    /**
     * Détail des lignes des dossiers :ids (liste d'entiers), une par couple dossier / remboursement.
     * Colonnes identiques à celles attendues par la liste DataTables.
     */
    public static function detailQuery(): string
    {
        return 'SELECT'
            . ' d.id AS demandeId,'
            . ' d.statut_description AS demandeStatutDescriptionFormatted,'
            . ' ' . self::demandeTypeExpression() . ' AS demandeType,'
            . ' dt.audit AS audit,'
            . ' ' . self::auditEnergieSubquery('d.id', 'l.id') . ' AS auditEId,'
            . ' dtd.niveau AS travauxNiveauCheque,'
            . ' dtd.is_bonification_aide AS demandeTravauxDevisIsBonificationAide,'
            . ' ' . self::beneficiaireExpression() . ' AS beneficiaireIdentifiant,'
            . " CONCAT(l.code_postal, ' ', l.ville) AS logement,"
            . " DATE_FORMAT(d.date_creation, '%Y/%m/%d') AS demandeDateCreation,"
            . ' ds.slug AS demandeStatutSlug,'
            . ' ds.description AS demandeStatutDescription,'
            . ' ' . self::structureConseillerExpression() . ' AS structureConseiller,'
            . ' ' . self::partenaireExpression() . ' AS partenaire,'
            . " DATE_FORMAT(dCP.date_CP, '%Y/%m/%d') AS commissionDate,"
            . ' d.statut_id AS demandeStatutId,'
            . ' b.id AS beneficiaireId,'
            . ' b.type AS beneficiaireType,'
            . ' l.id AS logementId,'
            . " DATE_FORMAT(dRMH.date_RMH, '%Y/%m/%d') AS remboursementDate,"
            . " '' AS action,"
            . ' 0 AS countCommentaire,'
            . ' rs.description AS remboursementStatutDescription,'
            . ' rs.slug AS remboursementStatutSlug,'
            . ' r.id AS remboursementId,'
            . ' r.statut_description AS remboursementStatutDescriptionFormatted'
            . ' FROM demande_ d ' . self::joinClause(array_fill_keys(self::DETAIL_ALIASES, true))
            . ' WHERE d.id IN (:ids)'
            . ' GROUP BY d.id, r.id';
    }

    /** @return array<string, string> */
    public function getParams(): array
    {
        return $this->params;
    }

    /* ----------------------------------------------------------------
                                INTERNE
    ---------------------------------------------------------------- */

    /**
     * @param list<string> $aliases
     */
    private function restrict(string $condition, array $aliases): self
    {
        $this->conditions[] = $condition;
        $this->addAliases($aliases);

        return $this;
    }

    /**
     * @param list<string> $aliases
     */
    private function addAliases(array $aliases): void
    {
        foreach ($aliases as $alias) {
            if (!isset(self::JOINS[$alias])) {
                throw new \LogicException(sprintf('Jointure inconnue : %s', $alias));
            }
            $this->aliases[$alias] = true;
        }
    }

    /**
     * Jointures demandées et leurs parents, dans l'ordre de JOINS.
     *
     * @param array<string, true> $aliases
     */
    private static function joinClause(array $aliases): string
    {
        foreach (array_keys($aliases) as $alias) {
            for ($parent = self::JOINS[$alias][1]; null !== $parent; $parent = self::JOINS[$parent][1]) {
                $aliases[$parent] = true;
            }
        }

        return implode(' ', array_column(array_intersect_key(self::JOINS, $aliases), 0));
    }

    /**
     * Une entrée par recherche reconnue : [condition, paramètres, alias nécessaires]. Les noms de
     * paramètres ne dérivent que des colonnes connues ; une valeur n'est jamais écrite dans le SQL.
     *
     * @param array<string, string> $filters
     *
     * @return list<array{0: string, 1: array<string, string>, 2: list<string>}>
     */
    private static function filterParts(array $filters, bool $statutDemandeOuRemboursement): array
    {
        $parts = [];
        foreach (DemandeListCriteria::normalizeFilters($filters) as $field => $value) {
            $param = 'filtre_' . $field;
            $like = [$param => '%' . $value . '%'];

            if (isset(self::LIKE_FILTERS[$field])) {
                [$expressions, $aliases] = self::LIKE_FILTERS[$field];
                $tests = array_map(static fn (string $expression): string => $expression . ' LIKE :' . $param, $expressions);
                $parts[] = ['(' . implode(' OR ', $tests) . ')', $like, $aliases];
            } elseif ('demandeStatutSlug' === $field) {
                // Choix multiple, pris dans la liste des statuts : égalité stricte, valeurs liées
                $params = [];
                $placeholders = [];
                foreach (DemandeListCriteria::splitValues($value) as $rang => $statut) {
                    $nom = $param . '_' . $rang;
                    $placeholders[] = ':' . $nom;
                    $params[$nom] = $statut;
                }
                if ([] === $placeholders) {
                    continue;
                }

                $liste = implode(', ', $placeholders);
                $condition = $statutDemandeOuRemboursement
                    ? sprintf('(ds.slug IN (%1$s) OR rs.slug IN (%1$s))', $liste)
                    : sprintf('IF(rs.slug IS NOT NULL, rs.slug IN (%1$s), ds.slug IN (%1$s))', $liste);
                $parts[] = [$condition, $params, ['ds', 'rs']];
            } elseif ('demandeType' === $field) {
                // Choix multiple : chaque code est repris d'une liste blanche, jamais de la saisie
                $conditions = [];
                foreach (DemandeListCriteria::splitValues($value) as $code) {
                    $condition = self::demandeTypeCondition($code);
                    if (null !== $condition) {
                        $conditions[] = $condition;
                    }
                }
                if ([] !== $conditions) {
                    $parts[] = ['(' . implode(' OR ', $conditions) . ')', [], ['dtd']];
                }
            }
        }

        return $parts;
    }

    /** Condition de la thématique choisie dans la liste déroulante ; null si le code est inconnu */
    private static function demandeTypeCondition(string $value): ?string
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

        return null;
    }

    /**
     * Expression triée pour une colonne (identique à la valeur affichée) et alias nécessaires.
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function sortExpression(string $column): array
    {
        return match ($column) {
            // Colonnes regroupées : le tri porte sur leur information principale
            'dossier'                 => ['d.id', []],
            'beneficiaire'            => [self::beneficiaireExpression(), ['b']],
            'echeances'               => ["DATE_FORMAT(dCP.date_CP, '%Y/%m/%d')", ['dCP']],

            'demandeType'             => [self::demandeTypeExpression(), ['dtd']],
            'beneficiaireIdentifiant' => [self::beneficiaireExpression(), ['b']],
            'logement'                => ["CONCAT(l.code_postal, ' ', l.ville)", ['l']],
            'demandeDateCreation'     => ["DATE_FORMAT(d.date_creation, '%Y/%m/%d')", []],
            'demandeStatutSlug'       => ['ds.slug', ['ds']],
            'structureConseiller'     => [
                self::structureConseillerExpression(),
                ['si_dae', 'sc_dae', 'si_dan', 'sc_dan', 'si_dt', 'sc_dt'],
            ],
            'partenaire'              => [self::partenaireExpression(), ['pi_dae', 'pi_dan', 'pi_dtd']],
            'commissionDate'          => ["DATE_FORMAT(dCP.date_CP, '%Y/%m/%d')", ['dCP']],
            'remboursementDate'       => ["DATE_FORMAT(dRMH.date_RMH, '%Y/%m/%d')", ['dRMH']],
            default                   => throw new \InvalidArgumentException(sprintf('Colonne de tri inconnue : %s', $column)),
        };
    }

    /** Code de thématique affiché : type de demande, ou niveau pour les travaux */
    private static function demandeTypeExpression(): string
    {
        $niveaux = '';
        foreach (self::NIVEAUX_TRAVAUX as $code => $niveau) {
            $niveaux .= sprintf(" WHEN '%s' THEN %d", $niveau, $code);
        }

        return sprintf(
            'CASE d.type'
            . ' WHEN %1$d THEN %1$d'
            . ' WHEN %2$d THEN %2$d'
            . ' WHEN %3$d THEN %3$d'
            . ' WHEN %4$d THEN CASE SUBSTRING(dtd.niveau, 1, 1)%5$s ELSE %6$d END'
            . ' WHEN %7$d THEN %7$d'
            . ' END',
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_TRAVAUX_TYPE,
            $niveaux,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_A_DEFINIR_CODE,
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
        );
    }

    /** « NOM Prénom », précédé de la raison sociale pour une SCI */
    private static function beneficiaireExpression(): string
    {
        return "CASE WHEN ('1 | sci' != b.type)"
            . " THEN CONCAT(' ', UPPER(b.nom), ' ', CONCAT(UCASE(LEFT(b.prenom, 1)), LCASE(SUBSTRING(b.prenom, 2))))"
            . " ELSE CONCAT_WS(' ', CONCAT(UCASE(LEFT(b.nom_SCI, 1)), LCASE(SUBSTRING(b.nom_SCI, 2))), '<br>',"
            . " CONCAT(UPPER(b.nom), ' ', CONCAT(UCASE(LEFT(b.prenom, 1)), LCASE(SUBSTRING(b.prenom, 2)))))"
            . ' END';
    }

    /** « Structure - Conseiller » du volet correspondant au type de demande */
    private static function structureConseillerExpression(): string
    {
        $volet = static fn (string $suffix): string => sprintf(
            "CASE WHEN ('' != sc_%1\$s.nom) THEN CONCAT(' ', si_%1\$s.nom, ' - ', sc_%1\$s.nom) ELSE si_%1\$s.nom END",
            $suffix
        );

        return sprintf(
            'CASE d.type WHEN %d THEN %s WHEN %d THEN %s WHEN %d THEN %s WHEN %d THEN %s WHEN %d THEN %s ELSE NULL END',
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            $volet('dae'),
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
            $volet('dan'),
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE,
            $volet('dan'),
            Demande_::DEMANDE_TRAVAUX_TYPE,
            $volet('dt'),
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
            $volet('dae')
        );
    }

    /** Raison sociale de l'auditeur ou du rénovateur selon le type de demande */
    private static function partenaireExpression(): string
    {
        return sprintf(
            'CASE d.type'
            . ' WHEN %d THEN pi_dae.raison_sociale'
            . ' WHEN %d THEN pi_dan.raison_sociale'
            . ' WHEN %d THEN pi_dan.raison_sociale'
            . ' WHEN %d THEN pi_dtd.raison_sociale'
            . ' WHEN %d THEN pi_dae.raison_sociale'
            . ' ELSE NULL END',
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_TRAVAUX_TYPE,
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
        );
    }

    /**
     * Autre audit énergétique non abandonné et non remboursé sur le même logement.
     *
     * @param string $demandeId  expression SQL de l'identifiant du dossier courant
     * @param string $logementId expression SQL de l'identifiant de son logement
     */
    private static function auditEnergieSubquery(string $demandeId, string $logementId): string
    {
        return sprintf(
            '(SELECT dAuditE.id FROM demande_ dAuditE'
            . ' LEFT JOIN remboursement_ rAuditE ON rAuditE.demande_id = dAuditE.id AND rAuditE.statut_id = %d'
            . ' WHERE dAuditE.logement_id = %s AND dAuditE.id <> %s'
            . ' AND (dAuditE.type = %d OR dAuditE.type = %d)'
            . ' AND dAuditE.statut_id <> %d'
            . ' AND rAuditE.demande_id IS NULL'
            . ' LIMIT 1)',
            Remboursement_statut::STATUS_20,
            $logementId,
            $demandeId,
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
            Demande_statut::STATUS_15
        );
    }
}

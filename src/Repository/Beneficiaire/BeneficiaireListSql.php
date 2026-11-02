<?php

declare(strict_types=1);

namespace App\Repository\Beneficiaire;

/**
 * Fragments SQL des listes de bénéficiaires : condition de recherche, tri et pagination.
 *
 * Toute expression SQL atteignable depuis le navigateur est énumérée ici. Une colonne absente de
 * ces tableaux ne peut être ni cherchée ni triée : c'est ce qui ferme l'injection, et non un
 * échappement de la saisie.
 *
 * Les valeurs saisies sont rendues sous forme de paramètres nommés, que le dépôt lie à l'exécution.
 */
final class BeneficiaireListSql
{
    /**
     * Colonnes de la liste des bénéficiaires, et expressions sur lesquelles porte la recherche.
     * Reprises telles quelles de l'ancienne construction, pour ne rien changer aux résultats.
     *
     * @var array<string, list<string>>
     */
    private const RECHERCHE_LISTE = [
        'beneficiaireNomPrenom' => ['LCASE(b.nom)', 'LCASE(b.prenom)', 'LCASE(b.nom_SCI)'],
        'beneficiaireType' => ['LCASE(SUBSTRING(b.type, 4))'],
        'beneficiaireCodePostalVille' => ['b.code_postal', 'LCASE(b.ville)'],
        'beneficiaireEmail' => ['LCASE(b.email)'],
        'beneficiaireStructureConseiller' => ['LCASE(scr.nom)', 'LCASE(scr.prenom)', 'LCASE(sir.nom)'],
    ];

    /**
     * Colonnes de l'assistance aux bénéficiaires de l'espace conseiller.
     *
     * @var array<string, list<string>>
     */
    private const RECHERCHE_ASSISTANCE = [
        'beneficiaireNomPrenom' => ['LCASE(b.nom)', 'LCASE(b.prenom)', 'LCASE(b.nom_SCI)'],
        'beneficiaireEmail' => ['LCASE(b.email)'],
        'beneficiaireAuteurCreation' => ['b.auteur_creation'],
    ];

    /**
     * Expressions de tri.
     *
     * Ce sont les ALIAS du SELECT, et non les colonnes de la table : l'ancienne construction les
     * employait déjà, et trier sur « b.nom » plutôt que sur l'alias changerait l'ordre des SCI,
     * dont le nom affiché vient de `nom_SCI`. Ces identifiants sont figés ici, aucune saisie ne
     * les atteint.
     *
     * Exception : `beneficiaireStructureConseiller` est une colonne du tableau sans alias
     * correspondant dans le SELECT. L'ancienne version produisait donc une erreur SQL au tri sur
     * cette colonne ; elle est rattachée au nom de la structure, ce qui la rend simplement
     * utilisable.
     *
     * @var array<string, string>
     */
    private const TRI = [
        'beneficiaireId' => 'beneficiaireId',
        'beneficiaireNomPrenom' => 'beneficiaireNomPrenom',
        'beneficiaireType' => 'beneficiaireType',
        'beneficiaireCodePostalVille' => 'beneficiaireCodePostalVille',
        'beneficiaireEmail' => 'beneficiaireEmail',
        'beneficiaireStructureConseiller' => 'structureIdentificationRattachementNom',
        'beneficiaireAuteurCreation' => 'beneficiaireAuteurCreation',
        'nombreLogement' => 'nombreLogement',
    ];

    /**
     * Condition de recherche et paramètres à lier.
     *
     * Chaque mot saisi doit correspondre à au moins une colonne de l'écran : les mots sont liés par
     * AND, les colonnes par OR, comme avant la correction.
     *
     * @return array{0: string, 1: array<string, string>} fragment « AND (…) » et paramètres
     */
    public static function conditions(BeneficiaireListCriteria $criteria): array
    {
        $fragments = [];
        $parametres = [];

        if (null !== $criteria->getStructureRattachement()) {
            $fragments[] = 'b.structure_rattachement_id = :structure_rattachement';
            $parametres['structure_rattachement'] = (string) $criteria->getStructureRattachement();
        }

        $mots = $criteria->motsRecherches();
        if ([] === $mots) {
            return [[] === $fragments ? '' : ' AND ' . implode(' AND ', $fragments), $parametres];
        }

        $colonnes = BeneficiaireListCriteria::VARIANTE_ASSISTANCE === $criteria->getVariante()
            ? self::RECHERCHE_ASSISTANCE
            : self::RECHERCHE_LISTE;

        $expressions = [];
        foreach ($colonnes as $listeExpressions) {
            foreach ($listeExpressions as $expression) {
                $expressions[] = $expression;
            }
        }

        foreach ($mots as $rang => $mot) {
            $parametre = 'recherche_' . $rang;
            $comparaisons = array_map(
                static fn (string $expression): string => $expression . ' LIKE :' . $parametre,
                $expressions
            );

            $fragments[] = '(' . implode(' OR ', $comparaisons) . ')';
            // La recherche était faite en minuscules sur des expressions LCASE : on conserve ce
            // comportement, le terme saisi étant abaissé ici plutôt que dans la requête.
            $parametres[$parametre] = '%' . mb_strtolower($mot, 'UTF-8') . '%';
        }

        return [' AND ' . implode(' AND ', $fragments), $parametres];
    }

    /**
     * Fragment « ORDER BY », construit à partir d'une colonne déjà validée par les critères.
     */
    public static function orderBy(BeneficiaireListCriteria $criteria): string
    {
        $expression = self::TRI[$criteria->getColonneTri()] ?? self::TRI[BeneficiaireListCriteria::TRI_DEFAUT];

        return sprintf(' ORDER BY %s %s', $expression, 'asc' === $criteria->getSensTri() ? 'ASC' : 'DESC');
    }

    /**
     * Fragment « LIMIT ». Les deux valeurs sont des entiers rendus par %d : aucune saisie ne peut
     * s'y glisser.
     */
    public static function limit(BeneficiaireListCriteria $criteria): string
    {
        return sprintf(' LIMIT %d, %d', max(0, $criteria->getDebut()), max(1, $criteria->getLongueur()));
    }
}

<?php

declare(strict_types=1);

namespace App\Repository\Titre;

/**
 * Tri validé de la liste des chèques.
 *
 * La colonne et le sens venaient du navigateur et étaient passés tels quels à
 * `QueryBuilder::orderBy()`, qui les recopie dans le DQL. Un profil habilité pouvait donc trier sur
 * n'importe quel champ des entités jointes — titre, demande, bénéficiaire, travaux — et s'en servir
 * comme d'un oracle pour en deviner le contenu, ou provoquer des erreurs d'analyse révélant la
 * structure des requêtes.
 *
 * Contrairement à la liste des bénéficiaires, la valeur recherchée n'était pas en cause : elle est
 * liée en paramètre par App\Trait\GenerateQueryBuilderTrait.
 *
 * Seules les colonnes réellement affichées par l'écran sont acceptées ici ; toute autre valeur
 * ramène au tri par défaut, sans erreur.
 */
final class TitreListSort
{
    /**
     * Colonnes du tableau des chèques, telles que la page les nomme, et expression de tri
     * correspondante. Les valeurs sont des alias du SELECT ou des champs des entités jointes :
     * ce sont elles, et elles seules, qui peuvent atteindre le DQL.
     *
     * @var array<string, string>
     */
    private const COLONNES = [
        'demandeType' => 'd.type',
        'beneficiaire' => 'beneficiaire',
        'demandeId' => 't.demandeId',
        'productionId' => 't.productionId',
        'numeroCheque' => 't.numeroCheque',
        'valeurTitre' => 't.valeurTitre',
        'dateEmission' => 't.dateEmission',
        'dateValidite' => 't.dateValidite',
    ];

    public const COLONNE_DEFAUT = 'demandeId';

    private function __construct(
        private readonly string $expression,
        private readonly string $sens
    ) {
    }

    /**
     * @param array<mixed> $postData critères envoyés par DataTables
     */
    public static function depuisDataTables(array $postData): self
    {
        $colonnes = is_array($postData['columns'] ?? null) ? $postData['columns'] : [];

        $index = filter_var($postData['order'][0]['column'] ?? null, FILTER_VALIDATE_INT);
        $nom = false !== $index && null !== $index ? ($colonnes[$index]['data'] ?? null) : null;

        if (!is_string($nom) || !isset(self::COLONNES[$nom])) {
            $nom = self::COLONNE_DEFAUT;
        }

        $sens = $postData['order'][0]['dir'] ?? null;

        return new self(
            self::COLONNES[$nom],
            is_string($sens) && 'asc' === strtolower(trim($sens)) ? 'ASC' : 'DESC'
        );
    }

    /** Expression de tri, toujours issue de la liste ci-dessus. */
    public function expression(): string
    {
        return $this->expression;
    }

    /** Sens de tri, toujours « ASC » ou « DESC ». */
    public function sens(): string
    {
        return $this->sens;
    }
}

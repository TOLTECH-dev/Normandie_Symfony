<?php

declare(strict_types=1);

namespace App\Repository\Beneficiaire;

/**
 * Critères validés des listes de bénéficiaires : recherche, tri et pagination.
 *
 * Le terme recherché était concaténé dans la requête SQL (`LCASE(b.nom) LIKE '%<saisie>%'`), de même
 * que la colonne de tri, le sens, le rang de départ et le nombre de lignes. La liste était donc
 * ouverte à l'injection SQL, dans un écran qui expose des données personnelles : adresses de
 * courriel, revenus fiscaux, composition du foyer.
 *
 * Les valeurs saisies ne sont plus écrites dans la requête, mais liées en paramètres ; la colonne de
 * tri est choisie dans une liste fermée (cf. BeneficiaireListSql).
 *
 * Deux écrans partagent ce mécanisme, avec des colonnes différentes :
 *   - la liste des bénéficiaires du back-office (`beneficiaire_list_all_ajax`) ;
 *   - l'assistance aux bénéficiaires de l'espace conseiller (`conseiller_beneficiaire_list_ajax`).
 */
final class BeneficiaireListCriteria
{
    public const VARIANTE_LISTE = 'liste';
    public const VARIANTE_ASSISTANCE = 'assistance';

    /** Colonnes de la liste des bénéficiaires (attributs « data » du tableau) */
    public const COLONNES_LISTE = [
        'beneficiaireId',
        'beneficiaireNomPrenom',
        'beneficiaireType',
        'beneficiaireCodePostalVille',
        'beneficiaireEmail',
        'beneficiaireStructureConseiller',
        'nombreLogement',
    ];

    /** Colonnes de l'assistance aux bénéficiaires */
    public const COLONNES_ASSISTANCE = [
        'beneficiaireId',
        'beneficiaireNomPrenom',
        'beneficiaireEmail',
        'beneficiaireAuteurCreation',
    ];

    public const TRI_DEFAUT = 'beneficiaireId';
    public const LONGUEUR_DEFAUT = 25;

    /** Plus grand nombre de lignes par page proposé par les listes */
    public const LONGUEUR_MAX = 100;

    /** Périmètre du conseiller : structure de rattachement des bénéficiaires qu'il peut voir */
    private ?int $structureRattachement = null;

    private function __construct(
        private readonly string $variante,
        private readonly string $recherche,
        private readonly string $colonneTri,
        private readonly string $sensTri,
        private readonly int $debut,
        private readonly int $longueur
    ) {
    }

    /**
     * Restreint la liste aux bénéficiaires rattachés à une structure.
     *
     * L'identifiant vient de la base et non du navigateur, mais il était tout de même concaténé
     * dans la requête : il est désormais lié comme les autres valeurs.
     */
    public function pourStructure(?int $structureId): self
    {
        $copie = clone $this;
        $copie->structureRattachement = $structureId;

        return $copie;
    }

    public function getStructureRattachement(): ?int
    {
        return $this->structureRattachement;
    }

    /**
     * @param array<mixed> $post critères envoyés par DataTables
     */
    public static function pourListe(array $post): self
    {
        return self::depuisDataTables($post, self::VARIANTE_LISTE, self::COLONNES_LISTE);
    }

    /**
     * @param array<mixed> $post critères envoyés par DataTables
     */
    public static function pourAssistance(array $post): self
    {
        return self::depuisDataTables($post, self::VARIANTE_ASSISTANCE, self::COLONNES_ASSISTANCE);
    }

    /**
     * Toute valeur inattendue est remplacée par la valeur par défaut, sans erreur : une liste qui
     * refuserait une requête mal formée donnerait à un attaquant un moyen de distinguer ses essais.
     *
     * @param array<mixed>  $post
     * @param list<string>  $colonnes
     */
    private static function depuisDataTables(array $post, string $variante, array $colonnes): self
    {
        $colonnesPost = is_array($post['columns'] ?? null) ? $post['columns'] : [];

        $ordre = is_array($post['order'][0] ?? null) ? $post['order'][0] : [];
        $index = filter_var($ordre['column'] ?? null, FILTER_VALIDATE_INT);
        $colonneTri = false !== $index && null !== $index ? ($colonnesPost[$index]['data'] ?? null) : null;

        if (!in_array($colonneTri, $colonnes, true)) {
            $colonneTri = self::TRI_DEFAUT;
            $sensTri = 'desc';
        } else {
            $sens = $ordre['dir'] ?? null;
            $sensTri = is_string($sens) && 'asc' === strtolower($sens) ? 'asc' : 'desc';
        }

        $recherche = $post['search']['value'] ?? '';
        $longueur = (int) filter_var($post['length'] ?? null, FILTER_VALIDATE_INT);

        return new self(
            $variante,
            is_string($recherche) ? trim($recherche) : '',
            $colonneTri,
            $sensTri,
            max(0, (int) filter_var($post['start'] ?? null, FILTER_VALIDATE_INT)),
            $longueur < 1 ? self::LONGUEUR_DEFAUT : min($longueur, self::LONGUEUR_MAX)
        );
    }

    public function getVariante(): string
    {
        return $this->variante;
    }

    public function getRecherche(): string
    {
        return $this->recherche;
    }

    public function aUneRecherche(): bool
    {
        return '' !== $this->recherche;
    }

    /**
     * La recherche globale porte sur chaque mot saisi : « dupont caen » retient les bénéficiaires
     * qui correspondent aux deux, comme avant la correction.
     *
     * @return list<string>
     */
    public function motsRecherches(): array
    {
        if ('' === $this->recherche) {
            return [];
        }

        $mots = array_filter(array_map('trim', explode(' ', $this->recherche)), static fn (string $m): bool => '' !== $m);

        // Une recherche très découpée multiplierait les conditions sans servir personne.
        return array_values(array_slice($mots, 0, 10));
    }

    public function getColonneTri(): string
    {
        return $this->colonneTri;
    }

    public function getSensTri(): string
    {
        return $this->sensTri;
    }

    public function getDebut(): int
    {
        return $this->debut;
    }

    public function getLongueur(): int
    {
        return $this->longueur;
    }
}

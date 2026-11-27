<?php

declare(strict_types=1);

namespace App\Motif;

use App\Entity\Instruction_reason;
use App\Entity\Remboursement_reason;

/**
 * Les deux familles de motifs de non-conformité (D26013).
 *
 * Le projet en compte deux, portées par deux tables et deux entités de forme
 * identique : les motifs de l'instruction administrative d'une demande
 * (`instruction_reason`) et ceux de l'instruction d'un remboursement
 * (`remboursement_reason`). Elles ne se mélangent jamais : un motif de chèque
 * n'a pas de sens sur un avis d'imposition.
 *
 * À l'intérieur d'une famille, la colonne `filtre` désigne le DOCUMENT auquel
 * le motif se rapporte. Sa valeur est un code technique écrit en dur dans les
 * formulaires d'instruction ; cette classe lui associe le libellé affiché.
 *
 * Les identifiants se recoupent d'une famille à l'autre : le motif n° 3 existe
 * des deux côtés et ne désigne pas la même chose. Toute adresse qui vise un
 * motif doit donc porter sa famille.
 */
final class FamilleDeMotifs
{
    public const INSTRUCTION = 'instruction';
    public const REMBOURSEMENT = 'remboursement';

    /**
     * La position réservée au motif « Autre », en dernier de chaque liste.
     *
     * Ce n'est pas une convention d'affichage mais une dépendance technique :
     * displayOption.js ouvre le champ de saisie libre quand la DERNIÈRE case du
     * groupe est cochée. Un motif placé après « Autre » prendrait sa place et
     * déclencherait la saisie libre à tort ; « Autre » désactivé, le champ ne
     * s'afficherait plus jamais.
     */
    public const POSITION_AUTRE = 99;

    public const LIBELLE_AUTRE = 'Autre';

    /**
     * La longueur maximale d'un libellé, en caractères.
     *
     * `remboursement_reason.slug` est un TINYTEXT, soit 255 OCTETS. Un libellé
     * français bien accentué consomme plus d'un octet par caractère, et MySQL
     * tronque silencieusement. 200 caractères laissent la marge nécessaire
     * (le plus long libellé en service en fait 104).
     */
    public const LONGUEUR_MAX_LIBELLE = 200;

    /**
     * @var array<string, string>
     */
    private const LIBELLES = [
        self::INSTRUCTION => 'Instruction administrative',
        self::REMBOURSEMENT => 'Remboursement',
    ];

    /**
     * @var array<string, class-string>
     */
    private const ENTITES = [
        self::INSTRUCTION => Instruction_reason::class,
        self::REMBOURSEMENT => Remboursement_reason::class,
    ];

    /**
     * Les documents de chaque famille, dans l'ordre d'affichage des écrans
     * d'instruction. Les codes sont ceux attendus par les `findByFiltre`.
     *
     * @var array<string, array<string, string>>
     */
    private const DOCUMENTS = [
        self::INSTRUCTION => [
            'JP' => 'Justificatif de propriété',
            'KBIS' => 'KBIS',
            'AI' => "Avis d'imposition",
            'MANDAT' => 'Mandat donné au conseiller',
        ],
        self::REMBOURSEMENT => [
            'cheque' => 'Chèque',
            'facture' => 'Facture',
            'rib' => 'RIB',
            'fiche_travaux' => 'Fiche descriptive des travaux',
        ],
    ];

    /**
     * Les positions qu'un motif peut occuper dans sa liste.
     *
     * Seules les places LIBRES sont proposées : deux motifs de même position
     * s'affichent dans un ordre que seule la clé primaire départage, et un
     * réordonnancement ultérieur devient imprévisible.
     *
     * La liste s'arrête à la première place libre après la dernière occupée.
     * Proposer tout le reste jusqu'à 98 n'apporterait rien : les positions ne
     * sont qu'un ordre d'affichage, et des trous n'y servent à rien. Ainsi,
     * avec les places 1, 2, 3 et 5 prises, on propose 4 puis 6.
     *
     * La position réservée à « Autre » n'est jamais proposée.
     *
     * @param list<int> $prises     les positions déjà occupées dans cette liste
     * @param int|null  $conservee  la position du motif en cours de modification,
     *                              qu'il doit pouvoir garder
     *
     * @return list<int>
     */
    public static function positionsDisponibles(array $prises, ?int $conservee = null): array
    {
        $maximum = self::POSITION_AUTRE - 1;

        $occupees = [];
        $derniere = 0;

        foreach ($prises as $position) {
            if ($position < 1 || $position > $maximum) {
                continue;
            }

            // La borne tient compte de TOUTES les places prises, y compris
            // celle du motif en cours de modification : sans quoi un motif seul
            // en position 3 ne se verrait pas proposer la 2, pourtant libre.
            $derniere = max($derniere, $position);

            if ($position !== $conservee) {
                $occupees[$position] = true;
            }
        }

        $libres = [];

        for ($position = 1; $position <= min($derniere + 1, $maximum); ++$position) {
            if (!isset($occupees[$position])) {
                $libres[] = $position;
            }
        }

        return $libres;
    }

    /**
     * Un motif protégé est celui qui occupe la position réservée : il ne peut
     * être ni désactivé ni déplacé.
     *
     * La reconnaissance se fait sur la POSITION et non sur le libellé, qui
     * reste modifiable : c'est la position qui fait fonctionner la saisie
     * libre, pas le mot « Autre ».
     */
    public static function estProtege(?int $positionLast): bool
    {
        return self::POSITION_AUTRE === $positionLast;
    }

    /**
     * @return list<string>
     */
    public static function toutes(): array
    {
        return array_keys(self::LIBELLES);
    }

    public static function existe(string $famille): bool
    {
        return isset(self::LIBELLES[$famille]);
    }

    public static function libelle(string $famille): string
    {
        return self::LIBELLES[$famille] ?? $famille;
    }

    /**
     * @return class-string
     */
    public static function entite(string $famille): string
    {
        if (!self::existe($famille)) {
            throw new \InvalidArgumentException('Famille de motifs inconnue : ' . $famille);
        }

        return self::ENTITES[$famille];
    }

    /**
     * @return array<string, string>
     */
    public static function documents(string $famille): array
    {
        return self::DOCUMENTS[$famille] ?? [];
    }

    public static function libelleDuDocument(string $famille, ?string $filtre): string
    {
        if (null === $filtre) {
            return '';
        }

        return self::DOCUMENTS[$famille][$filtre] ?? $filtre;
    }

    /**
     * Les choix du formulaire, groupés par famille.
     *
     * Une seule liste déroulante suffit ainsi à désigner à la fois la famille
     * et le document, sans formulaire dynamique. La valeur porte les deux,
     * séparés par une barre verticale — caractère absent des codes.
     *
     * @return array<string, array<string, string>>
     */
    public static function choixGroupes(): array
    {
        $choix = [];

        foreach (self::DOCUMENTS as $famille => $documents) {
            foreach ($documents as $filtre => $libelle) {
                $choix[self::libelle($famille)][$libelle] = $famille . '|' . $filtre;
            }
        }

        return $choix;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function separerLeChoix(string $valeur): array
    {
        $morceaux = explode('|', $valeur, 2);

        if (2 !== count($morceaux) || !self::existe($morceaux[0]) || !isset(self::DOCUMENTS[$morceaux[0]][$morceaux[1]])) {
            throw new \InvalidArgumentException('Document de non-conformité inconnu : ' . $valeur);
        }

        return [$morceaux[0], $morceaux[1]];
    }
}

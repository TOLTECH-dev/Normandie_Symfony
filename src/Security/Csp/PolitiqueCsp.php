<?php

declare(strict_types=1);

namespace App\Security\Csp;

/**
 * Une politique de sécurité du contenu (Reco-04 de l'audit ALMOND).
 *
 * La politique était jusqu'ici une chaîne écrite en dur dans le listener, ce
 * qui interdisait de la faire varier entre la recette et la production — l'un
 * des quatre points de la recommandation. Elle est désormais une donnée :
 * un tableau de directives, déclaré en configuration et surchargeable par
 * environnement.
 *
 * Deux gardes-fous à l'assemblage, parce qu'une politique se construit par
 * concaténation dans un en-tête HTTP :
 *
 *  - une source contenant « ; » ajouterait une directive que personne n'a
 *    voulue, par exemple en changeant `default-src` ;
 *  - une source contenant un retour à la ligne ajouterait un EN-TÊTE entier.
 *
 * Les sources viennent de notre configuration et non d'un utilisateur, mais
 * une politique de sécurité qu'on assemble sans vérifier ce qu'on y met serait
 * une curieuse façon de traiter le sujet.
 */
final class PolitiqueCsp
{
    /**
     * Marqueur remplacé par le nonce de la requête au moment de l'assemblage.
     * Écrit ainsi plutôt que `'nonce-...'` pour que la configuration n'ait pas
     * à connaître la forme exacte attendue par la norme.
     */
    final public const JETON_NONCE = "'nonce'";

    /** @var array<string, list<string>> */
    private readonly array $directives;

    /**
     * @param array<string, array<int, string>> $directives nom de directive => sources
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $directives)
    {
        if ([] === $directives) {
            throw new \InvalidArgumentException('Une politique CSP sans directive ne protège rien.');
        }

        $valides = [];

        foreach ($directives as $nom => $sources) {
            $this->verifierLeNom($nom);

            $valides[$nom] = array_values(array_map(
                fn (mixed $source): string => $this->verifierLaSource($nom, $source),
                $sources
            ));
        }

        $this->directives = $valides;
    }

    /**
     * L'en-tête, nonce de la requête substitué.
     *
     * Une directive sans source est rendue seule — c'est la forme attendue
     * pour `upgrade-insecure-requests` ou `block-all-mixed-content`.
     */
    public function enTete(string $nonce): string
    {
        $morceaux = [];

        foreach ($this->directives as $nom => $sources) {
            $sources = array_map(
                static fn (string $source): string => self::JETON_NONCE === $source
                    ? sprintf("'nonce-%s'", $nonce)
                    : $source,
                $sources
            );

            $morceaux[] = [] === $sources ? $nom : $nom . ' ' . implode(' ', $sources);
        }

        return implode('; ', $morceaux);
    }

    /**
     * La politique porte-t-elle cette directive, et avec quelles sources ?
     * Utile aux tests et aux contrôles de recette.
     *
     * @return list<string>
     */
    public function sources(string $directive): array
    {
        return $this->directives[$directive] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function directives(): array
    {
        return $this->directives;
    }

    /**
     * Une politique identique, augmentée d'une directive de rapport.
     *
     * `report-uri` est officiellement dépassée mais reste la seule comprise
     * par une partie des navigateurs ; `report-to` est la forme actuelle. Les
     * deux sont posées, ce qui est la pratique courante tant que le parc n'a
     * pas basculé.
     */
    public function avecRapportVers(string $uri): self
    {
        if ('' === trim($uri)) {
            return $this;
        }

        return new self($this->directives + [
            'report-uri' => [$uri],
            'report-to' => ['csp'],
        ]);
    }

    private function verifierLeNom(mixed $nom): void
    {
        if (!is_string($nom) || 1 !== preg_match('/^[a-z][a-z0-9-]*$/', $nom)) {
            throw new \InvalidArgumentException(sprintf(
                'Nom de directive CSP invalide : %s',
                is_string($nom) ? '« ' . $nom . ' »' : get_debug_type($nom)
            ));
        }
    }

    private function verifierLaSource(string $directive, mixed $source): string
    {
        if (!is_string($source) || '' === trim($source)) {
            throw new \InvalidArgumentException(sprintf(
                'Source vide ou invalide dans la directive « %s ».',
                $directive
            ));
        }

        if (1 === preg_match('/[;\r\n]/', $source)) {
            throw new \InvalidArgumentException(sprintf(
                'Source interdite dans la directive « %s » : « %s ». Un point-virgule ajouterait une '
                . "directive, un retour à la ligne ajouterait un en-tête entier.",
                $directive,
                $source
            ));
        }

        return trim($source);
    }
}

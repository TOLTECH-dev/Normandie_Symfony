<?php

declare(strict_types=1);

namespace App\Security\Perimetre;

/**
 * Périmètre d'un compte : profil déterminant, identifiant porté par le nom de compte et références
 * résolues (structure du conseiller, EPCI du contact).
 *
 * Vulnérabilité 6 / recommandation 10 de l'audit ALMOND : ces informations étaient déduites à
 * l'identique dans chaque requête et chaque contrôle d'accès (listes et export des dossiers, liste des
 * remboursements, accès à un dossier, accès aux documents). Elles le sont désormais une seule fois, par
 * PerimetreResolver. Les règles propres à chaque domaine restent chez leurs consommateurs.
 *
 * Les rôles sont les rôles bruts du compte, sans la hiérarchie : c'est ce que lisaient les règles.
 */
final class Perimetre
{
    /** Ordre dans lequel les règles examinaient les profils quand un compte en cumule plusieurs */
    private const PRIORITE = ['ROLE_CONSEILLER', 'ROLE_AUDITEUR', 'ROLE_RENOVATEUR', 'ROLE_EPCI', 'ROLE_TECHNIQUE'];

    /**
     * @param list<string> $roles rôles bruts du compte
     */
    public function __construct(
        private readonly array $roles,
        private readonly string $username,
        private readonly ?int $structureId,
        private readonly ?int $epciId,
    ) {
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function aLeRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Profil qui détermine le périmètre des dossiers (conseiller, auditeur, rénovateur, EPCI ou
     * technique), ou null pour un profil sans restriction (instructeur, client, administrateur).
     */
    public function profil(): ?string
    {
        foreach (self::PRIORITE as $role) {
            if ($this->aLeRole($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Identifiant porté par le nom de compte, après sa première lettre (C00008 → 8). Un suffixe non
     * numérique donne 0 : c'est le comportement de la plupart des règles.
     */
    public function identifiant(): int
    {
        return (int) substr($this->username, 1);
    }

    /**
     * Même identifiant, mais null si le suffixe n'est pas numérique. L'export des dossiers et la liste
     * des remboursements n'appliquent alors aucun périmètre, contrairement aux autres règles :
     * divergence historique conservée, sans effet sur les comptes existants (seuls les comptes du profil
     * technique ont un suffixe non numérique, et leur périmètre n'utilise pas l'identifiant).
     */
    public function identifiantNumerique(): ?int
    {
        $suffixe = substr($this->username, 1);

        return is_numeric($suffixe) ? (int) $suffixe : null;
    }

    /** Structure de rattachement du conseiller, ou null si le compte n'est pas conseiller ou sans structure */
    public function structureId(): ?int
    {
        return $this->structureId;
    }

    /** EPCI du contact, ou null si le compte n'est pas un contact EPCI ou sans EPCI */
    public function epciId(): ?int
    {
        return $this->epciId;
    }
}

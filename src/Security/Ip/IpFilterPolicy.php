<?php

declare(strict_types=1);

namespace App\Security\Ip;

use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Reco-18 (plan de traitement des risques CEE) : les interfaces d'administration ne sont
 * accessibles qu'depuis les réseaux des organismes concernés.
 *
 * Le filtrage est applicatif et non au niveau du serveur web : le back-office est une interface
 * unique, partagée avec des partenaires externes (auditeurs, rénovateurs, conseillers, EPCI) qui
 * se connectent depuis n'importe où. Seuls les profils listés ci-dessous sont restreints.
 *
 * Chaque groupe porte sa propre liste de réseaux, au format IP ou CIDR (IPv4 et IPv6), séparés par
 * des virgules. Une liste vide désactive le filtrage pour le groupe : c'est le réglage des postes
 * de développement, et cela évite d'enfermer tout le monde dehors si la configuration manque.
 *
 * Un compte qui relève de plusieurs groupes est accepté dès qu'une seule de ses listes correspond.
 */
final class IpFilterPolicy
{
    /** @var array<string, list<string>> */
    private readonly array $reseauxParGroupe;

    /**
     * @param array<string, list<string|null>> $reseauxParGroupe groupe => réseaux autorisés
     * @param array<string, list<string>>      $rolesParGroupe   groupe => rôles concernés
     */
    public function __construct(
        array $reseauxParGroupe,
        private readonly array $rolesParGroupe
    ) {
        $this->reseauxParGroupe = self::normaliser($reseauxParGroupe);
    }

    /**
     * Écarte les entrées vides avant tout usage.
     *
     * Ce n'est pas une précaution de confort : le processeur « csv: » de Symfony s'appuie sur
     * str_getcsv(), qui rend `[null]` — et non `[]` — pour une chaîne vide. La liste vide, qui est
     * pourtant le réglage documenté pour désactiver le filtrage d'un groupe, arrivait donc ici
     * comme un réseau valant `null` : le groupe passait pour filtré, et IpUtils::checkIp() levait
     * une TypeError à la première connexion d'un compte concerné. Constaté en recette le
     * 06/11/2026 ; les tests ne l'avaient pas vu, construisant la politique avec de vrais tableaux
     * vides plutôt qu'avec ce que produit le conteneur.
     *
     * @param array<string, list<string|null>> $reseauxParGroupe
     *
     * @return array<string, list<string>>
     */
    private static function normaliser(array $reseauxParGroupe): array
    {
        $normalises = [];

        foreach ($reseauxParGroupe as $groupe => $reseaux) {
            $retenus = [];

            foreach ((array) $reseaux as $reseau) {
                $reseau = trim((string) $reseau);

                if ('' !== $reseau) {
                    $retenus[] = $reseau;
                }
            }

            $normalises[$groupe] = $retenus;
        }

        return $normalises;
    }

    /**
     * Le compte est-il soumis au filtrage, et depuis quels réseaux ?
     */
    public function estFiltre(UserInterface $user): bool
    {
        return [] !== $this->reseauxApplicables($user);
    }

    public function autorise(UserInterface $user, ?string $ip): bool
    {
        $reseaux = $this->reseauxApplicables($user);
        if ([] === $reseaux) {
            return true;
        }

        if (null === $ip || '' === $ip) {
            return false;
        }

        return IpUtils::checkIp($ip, $reseaux);
    }

    /**
     * Groupes dont relève le compte, pour la journalisation.
     *
     * @return list<string>
     */
    public function groupes(UserInterface $user): array
    {
        $groupes = [];
        foreach ($this->rolesParGroupe as $groupe => $roles) {
            if ([] !== array_intersect($roles, $this->roles($user))) {
                $groupes[] = $groupe;
            }
        }

        return $groupes;
    }

    /**
     * Réseaux autorisés pour ce compte. Vide = compte non filtré (profil non concerné, ou groupe
     * concerné mais sans liste configurée).
     *
     * @return list<string>
     */
    private function reseauxApplicables(UserInterface $user): array
    {
        $reseaux = [];
        foreach ($this->groupes($user) as $groupe) {
            foreach ($this->reseauxParGroupe[$groupe] ?? [] as $reseau) {
                $reseaux[] = $reseau;
            }
        }

        return array_values(array_unique($reseaux));
    }

    /**
     * @return list<string>
     */
    private function roles(UserInterface $user): array
    {
        // Les rôles portés par le compte, sans les regroupements déduits par la hiérarchie :
        // le filtrage suit le profil du compte, pas les droits qu'il ouvre.
        return array_values($user->getRoles());
    }
}

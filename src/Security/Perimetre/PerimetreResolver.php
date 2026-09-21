<?php

declare(strict_types=1);

namespace App\Security\Perimetre;

use App\Repository\EPCI_Repository;
use App\Repository\Structure_Repository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Construit le périmètre d'un compte (cf. Perimetre).
 *
 * Accepte des rôles et un nom de compte explicites : la commande d'export s'exécute hors requête HTTP,
 * sans utilisateur connecté. Les résolutions (structure, EPCI) sont mémorisées pour la durée du
 * processus, une requête HTTP pouvant interroger plusieurs fois le même périmètre.
 */
class PerimetreResolver
{
    /** @var array<string, Perimetre> */
    private array $memoire = [];

    public function __construct(
        private readonly Structure_Repository $structureRepository,
        private readonly EPCI_Repository $epciRepository,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @param iterable<string> $roles rôles bruts du compte (User::getRoles())
     */
    public function pour(iterable $roles, ?string $username): Perimetre
    {
        $roles = array_values(array_map('strval', is_array($roles) ? $roles : iterator_to_array($roles)));
        $username = (string) $username;
        $cle = $username . '|' . implode(',', $roles);

        return $this->memoire[$cle] ??= $this->construire($roles, $username);
    }

    /** Périmètre du compte connecté */
    public function pourUtilisateurConnecte(): Perimetre
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            return $this->pour([], '');
        }

        return $this->pour($user->getRoles(), method_exists($user, 'getUsername') ? $user->getUsername() : $user->getUserIdentifier());
    }

    /**
     * @param list<string> $roles
     */
    private function construire(array $roles, string $username): Perimetre
    {
        $identifiant = (int) substr($username, 1);

        // Résolues dès que le compte porte le rôle, comme le faisaient les règles
        $structureId = null;
        if (in_array('ROLE_CONSEILLER', $roles, true)) {
            $structure = $this->structureRepository->findByConseillerId($identifiant);
            $structureId = isset($structure['id']) ? (int) $structure['id'] : null;
        }

        $epciId = null;
        if (in_array('ROLE_EPCI', $roles, true)) {
            $epci = $this->epciRepository->findByContactId($identifiant);
            $epciId = isset($epci['id']) ? (int) $epci['id'] : null;
        }

        return new Perimetre($roles, $username, $structureId, $epciId);
    }
}

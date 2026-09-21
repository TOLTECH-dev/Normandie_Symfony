<?php

declare(strict_types=1);

namespace App\Tests\Security\Authorization;

use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Lecture des droits d'accès déclarés : routes, attributs des contrôleurs et hiérarchie des rôles.
 *
 * Les attributs citent des regroupements (ROLE_BO_*, cf. config/packages/security.yaml) : cette classe
 * les résout en profils réels, afin que les tests raisonnent en « qui accède », pas en noms techniques.
 */
final class AccessMatrix
{
    /** Profils réellement attribués à des comptes */
    public const PROFILS = [
        'ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_CLIENT', 'ROLE_INSTRUCTEUR', 'ROLE_INSTRUCTEUR_UP',
        'ROLE_CONSEILLER', 'ROLE_AUDITEUR', 'ROLE_RENOVATEUR', 'ROLE_EPCI', 'ROLE_TECHNIQUE',
        'ROLE_AUTOMATE', 'ROLE_MEMBER',
    ];

    /** @var array<string, list<string>> */
    private array $hierarchie;

    public function __construct(private string $projectDir)
    {
        $securite = Yaml::parseFile($this->projectDir . '/config/packages/security.yaml')['security'];
        $this->hierarchie = array_map(static fn ($implique): array => (array) $implique, $securite['role_hierarchy']);
    }

    /**
     * Routes d'un fichier : nom => [classe, méthode].
     *
     * @return array<string, array{string, string}>
     */
    public function routes(string $fichier): array
    {
        $routes = [];
        foreach (Yaml::parseFile($this->projectDir . '/config/routes/' . $fichier . '.yaml') ?? [] as $nom => $definition) {
            $controller = $definition['defaults']['_controller'] ?? null;
            if (is_string($controller) && str_contains($controller, '::')) {
                $routes[$nom] = explode('::', $controller);
            }
        }

        return $routes;
    }

    /**
     * Rôles cités par les attributs de la classe et de la méthode, regroupements compris.
     *
     * @return list<string>
     */
    public function rolesExiges(string $classe, string $methode): array
    {
        $reflexion = new ReflectionClass($classe);
        $attributs = $reflexion->getAttributes();
        if ($reflexion->hasMethod($methode)) {
            $attributs = array_merge($attributs, $reflexion->getMethod($methode)->getAttributes());
        }

        $expressions = [];
        foreach ($attributs as $attribut) {
            if (str_contains($attribut->getName(), 'Security') || str_contains($attribut->getName(), 'IsGranted')) {
                $expressions[] = (string) ($attribut->getArguments()[0] ?? '');
            }
        }

        preg_match_all('/ROLE_[A-Z_]+/', implode(' ', $expressions), $trouves);
        $roles = array_values(array_unique($trouves[0]));
        sort($roles);

        return $roles;
    }

    /**
     * Profils réels autorisés par ces rôles exigés (regroupements résolus par la hiérarchie).
     *
     * @param list<string> $rolesExiges
     *
     * @return list<string>
     */
    public function profilsAutorises(array $rolesExiges): array
    {
        $profils = [];
        foreach (self::PROFILS as $profil) {
            if ([] !== array_intersect($this->rolesAtteignables($profil), $rolesExiges)) {
                $profils[] = $profil;
            }
        }

        return $profils;
    }

    /**
     * Rôles atteignables depuis un profil, en suivant la hiérarchie.
     *
     * @return list<string>
     */
    public function rolesAtteignables(string $profil): array
    {
        $vus = [];
        $pile = [$profil];
        while ([] !== $pile) {
            $courant = array_pop($pile);
            if (isset($vus[$courant])) {
                continue;
            }
            $vus[$courant] = true;
            foreach ($this->hierarchie[$courant] ?? [] as $suivant) {
                $pile[] = $suivant;
            }
        }

        return array_keys($vus);
    }

    /** Tous les rôles connus : profils réels et regroupements déclarés dans la hiérarchie */
    public function rolesConnus(): array
    {
        $roles = array_keys($this->hierarchie);
        foreach ($this->hierarchie as $impliques) {
            $roles = array_merge($roles, $impliques);
        }

        return array_values(array_unique($roles));
    }
}

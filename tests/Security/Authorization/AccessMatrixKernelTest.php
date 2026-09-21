<?php

declare(strict_types=1);

namespace App\Tests\Security\Authorization;

use App\Entity\User;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Matrice des accès « écran × profil » calculée par le moteur de sécurité réel de Symfony : règles
 * access_control, hiérarchie des rôles et évaluation des attributs #[Security] des contrôleurs.
 *
 * Elle est comparée à une référence enregistrée (fixtures/matrice_acces.json). Tout droit qui change
 * fait échouer le test en montrant l'écart. Après une décision métier (matrice validée par la Région),
 * régénérer la référence volontairement :
 *
 *     MATRICE_ACCES_MAJ=1 php bin/phpunit tests/Security/Authorization/AccessMatrixKernelTest.php
 *
 * Aucune base de données n'est nécessaire : les droits sont décidés avant tout accès aux données. Le
 * périmètre des données (quels dossiers un conseiller voit) relève d'autres tests.
 */
class AccessMatrixKernelTest extends KernelTestCase
{
    private const REFERENCE = __DIR__ . '/fixtures/matrice_acces.json';

    private const PROFILS = [
        'ANONYME', 'ROLE_MEMBER', 'ROLE_CONSEILLER', 'ROLE_AUDITEUR', 'ROLE_RENOVATEUR', 'ROLE_EPCI',
        'ROLE_TECHNIQUE', 'ROLE_INSTRUCTEUR', 'ROLE_INSTRUCTEUR_UP', 'ROLE_CLIENT', 'ROLE_ADMIN',
        'ROLE_SUPER_ADMIN', 'ROLE_AUTOMATE',
    ];

    public function testLesDroitsEffectifsCorrespondentALaReference(): void
    {
        $matrice = $this->calculerMatrice();

        if ('1' === getenv('MATRICE_ACCES_MAJ') || !is_file(self::REFERENCE)) {
            file_put_contents(self::REFERENCE, json_encode($matrice, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            self::markTestSkipped('Référence des droits (ré)générée : relire le fichier puis relancer le test.');
        }

        $reference = json_decode((string) file_get_contents(self::REFERENCE), true, 512, JSON_THROW_ON_ERROR);

        $ecarts = [];
        foreach ($reference + $matrice as $route => $ignore) {
            $attendu = $reference[$route] ?? null;
            $obtenu = $matrice[$route] ?? null;
            if ($attendu !== $obtenu) {
                $ecarts[] = sprintf(
                    "%s\n    référence : %s\n    obtenu    : %s",
                    $route,
                    null === $attendu ? '(écran absent)' : implode(', ', $attendu),
                    null === $obtenu ? '(écran absent)' : implode(', ', $obtenu)
                );
            }
        }

        self::assertSame([], $ecarts, "Les droits d'accès ont changé :\n" . implode("\n", $ecarts));
    }

    /**
     * @return array<string, list<string>> route => profils autorisés
     */
    private function calculerMatrice(): array
    {
        self::bootKernel();
        $conteneur = static::getContainer();
        $accessMap = $conteneur->get('security.access_map');
        $decisionManager = $conteneur->get('security.access.decision_manager');
        $tokenStorage = $conteneur->get('security.token_storage');
        $authorizationChecker = $conteneur->get('security.authorization_checker');

        $matrice = [];
        foreach ($conteneur->get('router')->getRouteCollection() as $nom => $route) {
            $controller = $route->getDefault('_controller');
            if (!is_string($controller) || !str_starts_with($controller, 'App') || !str_contains($controller, '::')) {
                continue;
            }
            [$classe, $methode] = explode('::', $controller);
            if (!class_exists($classe)) {
                continue; // route cassée, suivie par RouteAccessCoverageTest
            }

            $chemin = (string) preg_replace('/\{[^}]+\}/', '1', $route->getPath());
            $requete = Request::create($chemin);
            [$exigences] = $accessMap->getPatterns($requete);
            $expressions = $this->expressions($classe, $methode);

            $autorises = [];
            foreach (self::PROFILS as $profil) {
                $jeton = $this->jeton($profil, str_starts_with($chemin, '/admin') ? 'backoffice' : 'main');
                $tokenStorage->setToken($jeton);

                // Même appel que Symfony\Component\Security\Http\Firewall\AccessListener (plusieurs rôles admis)
                $passeAccessControl = empty($exigences) || $decisionManager->decide($jeton, $exigences, $requete, true);
                $passeAttributs = true;
                foreach ($expressions as $expression) {
                    $passeAttributs = $passeAttributs && $authorizationChecker->isGranted(new Expression($expression));
                }
                if ($passeAccessControl && $passeAttributs) {
                    $autorises[] = $profil;
                }
            }
            $matrice[$nom] = $autorises;
        }
        $tokenStorage->setToken(null);
        ksort($matrice);

        return $matrice;
    }

    private function jeton(string $profil, string $pareFeu): TokenInterface
    {
        if ('ANONYME' === $profil) {
            return new NullToken();
        }

        $utilisateur = new User();
        $utilisateur->setUsername('matrice-' . strtolower($profil));
        $utilisateur->setRoles([$profil]);

        return new UsernamePasswordToken($utilisateur, $pareFeu, $utilisateur->getRoles());
    }

    /**
     * Expressions des attributs #[Security] de la classe puis de la méthode.
     *
     * @return list<string>
     */
    private function expressions(string $classe, string $methode): array
    {
        $reflexion = new ReflectionClass($classe);
        $attributs = $reflexion->getAttributes();
        if ($reflexion->hasMethod($methode)) {
            $attributs = array_merge($attributs, $reflexion->getMethod($methode)->getAttributes());
        }

        $expressions = [];
        foreach ($attributs as $attribut) {
            if (str_ends_with($attribut->getName(), 'Security')) {
                $expressions[] = (string) ($attribut->getArguments()[0] ?? '');
            }
        }

        return $expressions;
    }
}

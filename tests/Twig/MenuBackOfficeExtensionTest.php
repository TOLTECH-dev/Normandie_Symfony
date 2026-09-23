<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\MenuBackOfficeExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Structure du menu de l'espace partenaire : elle alimente la barre latérale et l'en-tête des vues
 * (fil d'Ariane, icône, bouton « Retour »). Une route mal orthographiée casserait la page entière,
 * d'où ce contrôle contre le routeur réel.
 */
class MenuBackOfficeExtensionTest extends KernelTestCase
{
    public function testToutesLesRoutesCiteesExistent(): void
    {
        self::bootKernel();
        /** @var RouterInterface $routeur */
        $routeur = static::getContainer()->get(RouterInterface::class);
        $connues = array_keys($routeur->getRouteCollection()->all());

        $inconnues = [];
        foreach ($this->sections() as $section) {
            foreach ($section['entrees'] as $entree) {
                foreach (array_merge([$entree['route']], $entree['actives']) as $route) {
                    if (!\in_array($route, $connues, true)) {
                        $inconnues[] = $section['titre'] . ' / ' . $entree['libelle'] . ' : ' . $route;
                    }
                }
            }
        }

        self::assertSame([], $inconnues, "Routes inconnues du routeur :\n" . implode("\n", $inconnues));
    }

    public function testChaqueEntreePorteUneIconeEtSonCode(): void
    {
        foreach ($this->sections() as $section) {
            foreach ($section['entrees'] as $entree) {
                $repere = $section['titre'] . ' / ' . $entree['libelle'];
                self::assertStringStartsWith('glyphicon-', $entree['icone'], $repere);
                self::assertMatchesRegularExpression('/^\\\\[0-9a-f]{3,4}$/', $entree['icone_code'], $repere);
                self::assertNotEmpty($entree['roles'], $repere);
            }
        }
    }

    public function testUneRouteDeDetailDesigneLEntreeDeSaListe(): void
    {
        $extension = $this->extensionPourLaRoute('demande_view');
        $entree = $extension->entreeCourante();

        self::assertNotNull($entree);
        self::assertSame('Consultation des dossiers', $entree['libelle']);
        self::assertSame('Dossiers', $entree['section']);
        self::assertFalse($entree['est_la_page'], 'Écran de détail : le bouton « Retour » ramène à la liste');
    }

    public function testUneRouteDeListeSeDesigneElleMeme(): void
    {
        $entree = $this->extensionPourLaRoute('remboursement_list')->entreeCourante();

        self::assertNotNull($entree);
        self::assertTrue($entree['est_la_page']);
        self::assertSame('Aides et paiements', $entree['section']);
    }

    public function testUneRouteHorsMenuNaPasDEntree(): void
    {
        self::assertNull($this->extensionPourLaRoute('admin_user_login')->entreeCourante());
        self::assertNull($this->extensionPourLaRoute('')->entreeCourante());
    }

    private function extensionPourLaRoute(string $route): MenuBackOfficeExtension
    {
        $pile = new RequestStack();
        $requete = Request::create('/admin/peu-importe');
        if ('' !== $route) {
            $requete->attributes->set('_route', $route);
        }
        $pile->push($requete);

        return new MenuBackOfficeExtension($this->createMock(AuthorizationCheckerInterface::class), $pile);
    }

    /**
     * @return list<array{titre: string, entrees: list<array<string, mixed>>}>
     */
    private function sections(): array
    {
        $methode = new \ReflectionMethod(MenuBackOfficeExtension::class, 'sections');
        $methode->setAccessible(true);

        return $methode->invoke(null);
    }
}

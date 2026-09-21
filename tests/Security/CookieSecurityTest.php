<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Vulnérabilité 2 / recommandation 6 de l'audit ALMOND : options de sécurité des cookies.
 * Le cookie de session est HttpOnly, SameSite et Secure (imposé en production), et aucun
 * cookie « Se souvenir de moi » n'est émis (il survivait à la déconnexion).
 */
class CookieSecurityTest extends TestCase
{
    private const CONFIG = __DIR__ . '/../../config/packages/';

    public function testLeCookieDeSessionPorteLesOptionsDeSecurite(): void
    {
        $session = $this->config('framework.yaml')['framework']['session'];

        self::assertTrue($session['cookie_httponly']);
        self::assertSame('lax', $session['cookie_samesite'], '« strict » casserait le retour FranceConnect et les liens reçus par e-mail');
        self::assertSame('auto', $session['cookie_secure']);
        self::assertSame(0, $session['cookie_lifetime'], 'Cookie supprimé à la fermeture du navigateur');
    }

    public function testLeCookieDeSessionEstSecureEnProduction(): void
    {
        $framework = $this->config('framework.yaml');

        self::assertTrue($framework['when@prod']['framework']['session']['cookie_secure']);
    }

    public function testAucunFirewallNEmetDeCookieSeSouvenirDeMoi(): void
    {
        $firewalls = $this->config('security.yaml')['security']['firewalls'];

        foreach (['backoffice', 'main'] as $nom) {
            self::assertArrayHasKey($nom, $firewalls);
            self::assertArrayNotHasKey('remember_me', $firewalls[$nom], "Firewall $nom");
            self::assertArrayHasKey('logout', $firewalls[$nom], "Firewall $nom : la déconnexion invalide la session");
            self::assertNotFalse($firewalls[$nom]['logout']['invalidate_session'] ?? true, "Firewall $nom");
        }
    }

    public function testLesPagesDeConnexionNeProposentPlusLaCase(): void
    {
        foreach (['BackOffice/Security/login.html.twig', 'Main/Security/login.html.twig'] as $gabarit) {
            self::assertStringNotContainsString('_remember_me', file_get_contents(__DIR__ . '/../../templates/' . $gabarit), $gabarit);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function config(string $fichier): array
    {
        return Yaml::parseFile(self::CONFIG . $fichier, Yaml::PARSE_CUSTOM_TAGS);
    }
}

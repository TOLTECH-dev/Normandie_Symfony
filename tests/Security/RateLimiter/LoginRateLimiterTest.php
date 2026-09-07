<?php

namespace App\Tests\Security\RateLimiter;

use App\Security\RateLimiter\LoginRateLimiter;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

/**
 * Couvre les scénarios d'attaque de la vulnérabilité V3 du rapport ALMOND NOR005-22.
 *
 * Le limiteur est sollicité directement, sans passer par le firewall : ces tests ne
 * dépendent donc ni de la base de données ni d'un utilisateur existant, ce qui reflète
 * le fonctionnement réel (login_throttling compte aussi les tentatives portant sur des
 * identifiants inconnus).
 */
class LoginRateLimiterTest extends KernelTestCase
{
    private const USERNAME = 'victime@example.org';
    private const IP = '203.0.113.10';

    private RequestRateLimiterInterface $limiter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->limiter = self::getContainer()->get(LoginRateLimiter::class);

        // Les compteurs vivent dans un pool de cache et survivent d'une exécution à
        // l'autre : on repart d'un état vierge à chaque test. Le pool est celui déclaré
        // par config/packages/rate_limiter.yaml, pas le "cache.rate_limiter" par défaut.
        /** @var CacheItemPoolInterface $pool */
        $pool = self::getContainer()->get('cache.security_rate_limiter');
        $pool->clear();
    }

    /**
     * Le scénario que le mécanisme précédent laissait passer : un botnet qui change
     * d'IP à chaque tentative pour pilonner un seul compte.
     */
    public function testAttaqueDistribueeSurUnSeulCompteEstBloquee(): void
    {
        $seuil = $this->seuil('username');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->assertTrue(
                $this->tenter(self::USERNAME, $this->ip($i))->isAccepted(),
                sprintf('La tentative %d aurait dû être acceptée.', $i)
            );
        }

        $this->assertFalse(
            $this->tenter(self::USERNAME, $this->ip($seuil + 1))->isAccepted(),
            'Le compte doit être verrouillé au-delà du seuil, même si chaque tentative provient d\'une IP différente.'
        );
    }

    /**
     * Bruteforce ciblé classique : même compte, même origine.
     */
    public function testAttaqueCibleeDepuisUneSeuleIpEstBloquee(): void
    {
        $seuil = $this->seuil('username_ip');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->assertTrue($this->tenter(self::USERNAME, self::IP)->isAccepted());
        }

        $this->assertFalse($this->tenter(self::USERNAME, self::IP)->isAccepted());
    }

    /**
     * Attaque en largeur : une origine unique qui balaie de nombreux comptes.
     */
    public function testBalayageDeComptesDepuisUneSeuleIpEstBloque(): void
    {
        $seuil = $this->seuil('ip');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->assertTrue($this->tenter(sprintf('compte%d@example.org', $i), self::IP)->isAccepted());
        }

        $this->assertFalse($this->tenter('compte-de-trop@example.org', self::IP)->isAccepted());
    }

    /**
     * Une connexion réussie doit repartir d'une ardoise vierge : c'est ce que fait
     * LoginThrottlingListener::onSuccessfulLogin() via reset().
     */
    public function testUneConnexionReussieRemetLesCompteursAZero(): void
    {
        $seuil = $this->seuil('username_ip');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->tenter(self::USERNAME, self::IP);
        }
        $this->assertFalse($this->tenter(self::USERNAME, self::IP)->isAccepted());

        $this->limiter->reset($this->requete(self::USERNAME, self::IP));

        $this->assertTrue($this->tenter(self::USERNAME, self::IP)->isAccepted());
    }

    /**
     * L'identifiant est normalisé avant d'être compté : varier la casse ne doit pas
     * ouvrir un nouveau quota.
     */
    public function testLaCasseDeLIdentifiantNeContournePasLeCompteur(): void
    {
        $seuil = $this->seuil('username');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->tenter(0 === $i % 2 ? strtoupper(self::USERNAME) : self::USERNAME, $this->ip($i));
        }

        $this->assertFalse($this->tenter(strtoupper(self::USERNAME), $this->ip($seuil + 1))->isAccepted());
    }

    private function tenter(string $username, string $ip)
    {
        return $this->limiter->consume($this->requete($username, $ip));
    }

    private function requete(string $username, string $ip): Request
    {
        $request = Request::create('/login', Request::METHOD_POST, [], [], [], ['REMOTE_ADDR' => $ip]);
        // Attribut posé par LoginThrottlingListener juste avant l'appel au limiteur.
        $request->attributes->set(Security::LAST_USERNAME, $username);

        return $request;
    }

    private function seuil(string $compteur): int
    {
        return (int) self::getContainer()->getParameter(sprintf('security_login_%s_max_attempts', $compteur));
    }

    private function ip(int $rang): string
    {
        return sprintf('198.51.100.%d', $rang % 256);
    }
}

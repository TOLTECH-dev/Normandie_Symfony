<?php

namespace App\Tests\Security\RateLimiter;

use App\Security\RateLimiter\PublicFormRateLimiter;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Couvre l'extension de la recommandation R7 du rapport ALMOND NOR005-22 aux formulaires
 * publics autres que l'authentification : « Il est recommandé de mettre en place la
 * protection anti-bruteforce sur tous les formulaires d'une application web ».
 *
 * Le limiteur est sollicité directement, sans passer par les contrôleurs : ces tests ne
 * dépendent donc ni de la base de données ni d'un utilisateur existant, ce qui reflète le
 * fonctionnement réel (les identifiants inconnus sont comptés comme les autres).
 */
class PublicFormRateLimiterTest extends KernelTestCase
{
    private const IDENTIFIANT = 'victime@example.org';
    private const IP = '203.0.113.10';

    private PublicFormRateLimiter $limiter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->limiter = self::getContainer()->get(PublicFormRateLimiter::class);

        // Les compteurs vivent dans un pool de cache et survivent d'une exécution à
        // l'autre : on repart d'un état vierge à chaque test. Le pool est celui déclaré
        // par config/packages/rate_limiter.yaml, pas le "cache.rate_limiter" par défaut.
        /** @var CacheItemPoolInterface $pool */
        $pool = self::getContainer()->get('cache.security_rate_limiter');
        $pool->clear();
    }

    /**
     * Mail-bombing / balayage d'identifiants depuis une même origine.
     */
    public function testDemandesDeReinitialisationDepuisUneSeuleIpSontBloquees(): void
    {
        $seuil = $this->seuil('resetting_request_ip');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->assertTrue(
                $this->demanderReinitialisation(sprintf('compte%d@example.org', $i), self::IP)->isAccepted(),
                sprintf('La demande %d aurait dû être acceptée.', $i)
            );
        }

        $this->assertFalse(
            $this->demanderReinitialisation('compte-de-trop@example.org', self::IP)->isAccepted()
        );
    }

    /**
     * Harcèlement d'une boîte mail donnée depuis des origines multiples : c'est le
     * pendant, pour ce formulaire, du compteur par identifiant seul de LoginRateLimiter.
     */
    public function testDemandesDistribueesSurUnSeulIdentifiantSontBloquees(): void
    {
        $seuil = $this->seuil('resetting_request_username');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->assertTrue(
                $this->demanderReinitialisation(self::IDENTIFIANT, $this->ip($i))->isAccepted(),
                sprintf('La demande %d aurait dû être acceptée.', $i)
            );
        }

        $this->assertFalse(
            $this->demanderReinitialisation(self::IDENTIFIANT, $this->ip($seuil + 1))->isAccepted(),
            'Un identifiant doit être protégé même si chaque demande provient d\'une IP différente.'
        );
    }

    /**
     * L'identifiant est normalisé avant d'être compté : varier la casse ou ajouter des
     * espaces ne doit pas ouvrir un nouveau quota.
     */
    public function testLaCasseEtLesEspacesNeContournentPasLeCompteur(): void
    {
        $seuil = $this->seuil('resetting_request_username');

        for ($i = 1; $i <= $seuil; $i++) {
            $variante = 0 === $i % 2 ? '  ' . strtoupper(self::IDENTIFIANT) . ' ' : self::IDENTIFIANT;
            $this->demanderReinitialisation($variante, $this->ip($i));
        }

        $this->assertFalse(
            $this->demanderReinitialisation(strtoupper(self::IDENTIFIANT), $this->ip($seuil + 1))->isAccepted()
        );
    }

    /**
     * Le formulaire d'inscription : création de comptes en masse depuis une même origine.
     */
    public function testInscriptionsRepeteesDepuisUneSeuleIpSontBloquees(): void
    {
        $seuil = $this->seuil('registration_ip');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->assertTrue($this->limiter->consumeRegistration($this->requete(self::IP))->isAccepted());
        }

        $this->assertFalse($this->limiter->consumeRegistration($this->requete(self::IP))->isAccepted());
    }

    /**
     * La page de saisie du nouveau mot de passe (devinette de jeton, forçage de la
     * politique de mot de passe).
     */
    public function testAccesRepetesAuLienDeReinitialisationSontBloques(): void
    {
        $seuil = $this->seuil('resetting_reset_ip');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->assertTrue($this->limiter->consumeResettingReset($this->requete(self::IP))->isAccepted());
        }

        $this->assertFalse($this->limiter->consumeResettingReset($this->requete(self::IP))->isAccepted());
    }

    /**
     * Les compteurs sont cloisonnés par formulaire : saturer l'inscription ne doit pas
     * fermer la réinitialisation de mot de passe, et inversement.
     */
    public function testLesFormulairesNePartagentPasLeursCompteurs(): void
    {
        $seuil = $this->seuil('registration_ip');

        for ($i = 1; $i <= $seuil + 1; $i++) {
            $this->limiter->consumeRegistration($this->requete(self::IP));
        }

        $this->assertFalse($this->limiter->consumeRegistration($this->requete(self::IP))->isAccepted());
        $this->assertTrue($this->demanderReinitialisation(self::IDENTIFIANT, self::IP)->isAccepted());
    }

    /**
     * Le message est identique quel que soit le formulaire et ne comporte aucune donnée
     * métier : il ne permet pas de déduire l'existence d'un compte (bonne pratique
     * relevée par l'audit au §3.2.2).
     */
    public function testLeMessageDeBlocageNeRevelePasDInformation(): void
    {
        $seuil = $this->seuil('resetting_request_username');

        for ($i = 1; $i <= $seuil; $i++) {
            $this->demanderReinitialisation(self::IDENTIFIANT, $this->ip($i));
        }

        $refus = $this->demanderReinitialisation(self::IDENTIFIANT, $this->ip($seuil + 1));
        $message = PublicFormRateLimiter::tooManyAttemptsMessage($refus);

        $this->assertStringNotContainsString(self::IDENTIFIANT, $message);
        $this->assertMatchesRegularExpression('/^Trop de tentatives ont été effectuées\. Merci de réessayer dans \d+ minutes?\.$/u', $message);
    }

    private function demanderReinitialisation(string $identifiant, string $ip)
    {
        return $this->limiter->consumeResettingRequest($this->requete($ip), $identifiant);
    }

    private function requete(string $ip): Request
    {
        return Request::create('/resetting/send-email', Request::METHOD_POST, [], [], [], ['REMOTE_ADDR' => $ip]);
    }

    private function seuil(string $compteur): int
    {
        return (int) self::getContainer()->getParameter(sprintf('security_%s_max_attempts', $compteur));
    }

    private function ip(int $rang): string
    {
        return sprintf('198.51.100.%d', $rang % 256);
    }
}

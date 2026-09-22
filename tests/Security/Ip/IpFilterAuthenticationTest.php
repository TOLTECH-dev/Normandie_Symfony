<?php

declare(strict_types=1);

namespace App\Tests\Security\Ip;

use App\Entity\User;
use App\Security\Ip\IpFilterPolicy;
use App\Security\Ip\IpFilterSubscriber;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * Reco-18 : refus à la connexion (avant l'envoi du code de double authentification) puis à chaque
 * requête du back-office, pour les sessions ouvertes ailleurs.
 */
class IpFilterAuthenticationTest extends TestCase
{
    private const RESEAUX = ['region' => ['195.10.20.0/24']];
    private const ROLES = ['region' => ['ROLE_CLIENT']];

    public function testLaConnexionEstRefuseeDepuisUnReseauNonAutorise(): void
    {
        $this->expectException(BadCredentialsException::class);

        $this->userChecker('81.250.1.1')->checkPreAuth($this->compte());
    }

    public function testLaConnexionEstAcceptéeDepuisLeReseauDeLOrganisme(): void
    {
        $this->userChecker('195.10.20.34')->checkPreAuth($this->compte());

        $this->addToAssertionCount(1);
    }

    public function testLeFrontOfficeNEstPasFiltre(): void
    {
        $this->userChecker('81.250.1.1', '/member/mon-espace')->checkPreAuth($this->compte());

        $this->addToAssertionCount(1);
    }

    public function testEnConsoleAucunFiltrage(): void
    {
        $checker = new UserChecker($this->politique(), new RequestStack(), new NullLogger());
        $checker->checkPreAuth($this->compte());

        $this->addToAssertionCount(1);
    }

    public function testUnCompteDesactiveResteRefuse(): void
    {
        $compte = $this->compte();
        $compte->setEnabled(false);

        $this->expectExceptionMessage('Le compte est désactivé.');

        $this->userChecker('195.10.20.34')->checkPreAuth($compte);
    }

    public function testUneSessionPoursuivieAilleursEstRefusee(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->declencherRequete('/admin/dossiers', '81.250.1.1');
    }

    public function testUneSessionDepuisLeReseauAutoriseEstServie(): void
    {
        $evenement = $this->declencherRequete('/admin/dossiers', '195.10.20.34');

        self::assertFalse($evenement->hasResponse());
    }

    public function testLaPageDeConnexionResteAccessible(): void
    {
        $evenement = $this->declencherRequete('/admin/bo_login', '81.250.1.1', 'admin_user_login');

        self::assertFalse($evenement->hasResponse(), 'Sans cela, un compte filtré ne pourrait plus se déconnecter');
    }

    public function testUneRequeteAjaxRecoitDuJson(): void
    {
        $evenement = $this->declencherRequete('/admin/dossiers/liste', '81.250.1.1', null, true);

        self::assertNotNull($evenement->getResponse());
        self::assertSame(403, $evenement->getResponse()->getStatusCode());
    }

    private function declencherRequete(string $chemin, string $ip, ?string $route = null, bool $ajax = false): RequestEvent
    {
        $requete = Request::create($chemin, 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
        if (null !== $route) {
            $requete->attributes->set('_route', $route);
        }
        if ($ajax) {
            $requete->headers->set('X-Requested-With', 'XMLHttpRequest');
        }

        $jetons = new TokenStorage();
        $jetons->setToken(new UsernamePasswordToken($this->compte(), 'backoffice', ['ROLE_CLIENT']));

        $evenement = new RequestEvent($this->createMock(HttpKernelInterface::class), $requete, HttpKernelInterface::MAIN_REQUEST);
        (new IpFilterSubscriber($jetons, $this->politique(), new NullLogger()))->onKernelRequest($evenement);

        return $evenement;
    }

    private function userChecker(string $ip, string $chemin = '/admin/dossiers'): UserChecker
    {
        $pile = new RequestStack();
        $pile->push(Request::create($chemin, 'GET', [], [], [], ['REMOTE_ADDR' => $ip]));

        return new UserChecker($this->politique(), $pile, new NullLogger());
    }

    private function politique(): IpFilterPolicy
    {
        return new IpFilterPolicy(self::RESEAUX, self::ROLES);
    }

    private function compte(): User
    {
        $compte = new User();
        $compte->setUsername('test.client');
        $compte->setRoles(['ROLE_CLIENT']);
        $compte->setEnabled(true);

        return $compte;
    }
}

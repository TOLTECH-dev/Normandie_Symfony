<?php

namespace App\Tests\Security\Password;

use App\Entity\User;
use App\Security\Password\PasswordExpirationPolicy;
use App\Security\Password\PasswordExpirationSubscriber;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * Un mot de passe expiré limite le back-office à la page de changement et à la déconnexion
 * (Reco-15).
 */
class PasswordExpirationSubscriberTest extends TestCase
{
    private const CHANGE_URL = '/admin/mon-compte/mot-de-passe';

    public function testUnMotDePasseExpireRedirigeVersLaPageDeChangement(): void
    {
        $request = $this->request('/admin/demandes', 'demande_list_all');
        $event = $this->dispatch($request, $this->token($this->user(null)));

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(self::CHANGE_URL, $response->getTargetUrl());
        $this->assertNotEmpty($request->getSession()->getFlashBag()->peek('warning'));
    }

    public function testLaPageDeChangementEtLaDeconnexionRestentAccessibles(): void
    {
        foreach (['admin_password_change' => self::CHANGE_URL, 'admin_user_logout' => '/admin/bo_logout'] as $route => $path) {
            $event = $this->dispatch($this->request($path, $route), $this->token($this->user(null)));

            $this->assertNull($event->getResponse(), $route);
        }
    }

    public function testUnMotDePasseValideNEstPasBloque(): void
    {
        $event = $this->dispatch($this->request('/admin/demandes', 'demande_list_all'), $this->token($this->user(new \DateTime())));

        $this->assertNull($event->getResponse());
    }

    public function testRienNEstFaitPendantLaDoubleAuthentification(): void
    {
        $twoFactorToken = new TwoFactorToken($this->token($this->user(null)), null, 'backoffice', ['email_code']);
        $event = $this->dispatch($this->request('/admin/2fa', '2fa_login'), $twoFactorToken);

        $this->assertNull($event->getResponse());
    }

    public function testLeFrontOfficeNEstPasConcerne(): void
    {
        $event = $this->dispatch($this->request('/member/tableau-de-bord', 'fo_dashboard'), $this->token($this->user(null)));

        $this->assertNull($event->getResponse());
    }

    public function testUneRequeteAjaxRecoitUneErreur403(): void
    {
        $request = $this->request('/admin/demandes/liste', 'demande_list_ajax');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->dispatch($request, $this->token($this->user(null)))->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
    }

    private function dispatch(Request $request, ?TokenInterface $token): RequestEvent
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn(self::CHANGE_URL);

        $subscriber = new PasswordExpirationSubscriber(
            $tokenStorage,
            new PasswordExpirationPolicy(new RoleHierarchy([]), ['ROLE_ADMIN'], 12, 30),
            $router
        );

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        return $event;
    }

    private function request(string $path, string $route): Request
    {
        $request = Request::create($path);
        $request->attributes->set('_route', $route);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'backoffice', $user->getRoles());
    }

    /**
     * Administrateur dont le mot de passe a été changé à la date indiquée (null : expiré).
     */
    private function user(?\DateTime $passwordChangedAt): User
    {
        $user = new User();
        $user->setUsername('admin-de-test');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPasswordChangedAt($passwordChangedAt);

        return $user;
    }
}

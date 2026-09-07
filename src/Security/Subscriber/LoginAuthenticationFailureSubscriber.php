<?php

namespace App\Security\Subscriber;

use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Suit les échecs d'authentification à des fins d'affichage du captcha et de traçabilité.
 *
 * Le blocage lui-même n'est plus assuré ici : il est délégué à "login_throttling"
 * (cf. config/packages/security.yaml et App\Security\RateLimiter\LoginRateLimiter),
 * qui rejette la tentative avant même que cet événement ne soit atteint.
 */
class LoginAuthenticationFailureSubscriber implements EventSubscriberInterface
{
    private CacheItemPoolInterface $redisAdapter;
    private ParameterBagInterface $parameterBag;
    private EntityManagerInterface $entityManager;
    private UserService $userService;

    public function __construct(
        CacheItemPoolInterface $redisAdapter,
        ParameterBagInterface $parameterBag,
        EntityManagerInterface $entityManager,
        UserService $userService,
    )
    {
        $this->redisAdapter = $redisAdapter;
        $this->parameterBag = $parameterBag;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
    }

    public static function getSubscribedEvents()
    {
        return [
            LoginFailureEvent::class => 'onAuthenticationFailure',
        ];
    }

    /**
     * @throws \DateMalformedStringException
     * @throws InvalidArgumentException
     */
    public function onAuthenticationFailure(LoginFailureEvent $event)
    {
        // Un rejet par login_throttling n'est pas une tentative supplémentaire : la
        // comptabiliser ferait grimper les compteurs sans que le mot de passe ait été
        // testé, et rendrait les seuils illisibles.
        if ($event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            return;
        }

        $request = $event->getRequest();
        $username = $request->request->get('_username');

        // Compteur d'échecs par IP : c'est lui qui déclenche l'affichage du captcha
        // (cf. FailureAttemptVerificationSubscriber et App\Security\SecurityController).
        $cachedUserData = $this->redisAdapter->getItem(hash('sha512', $request->getClientIp()));
        $list = ($cachedUserData->isHit()) ? $cachedUserData->get() : [];

        $list[$username] = isset($list[$username]) ? $list[$username] + 1 : 1;

        $cachedUserData->set($list);
        $cachedUserData->expiresAt(new \DateTime($this->parameterBag->get('security_time_expire_user_ip')));
        $this->redisAdapter->save($cachedUserData);

        // Compteur en base, conservé pour la traçabilité et l'exploitation côté back-office.
        // Il n'est jamais remis à zéro sur échec : seule une connexion réussie le réinitialise
        // (cf. les deux LoginSuccessHandler).
        $user = $this->userService->findByUsername($username);

        if ($user instanceof User && true === $user->isEnabled()) {
            $user->setCountFailedConnection(($user->getCountFailedConnection() ?? 0) + 1);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
    }
}

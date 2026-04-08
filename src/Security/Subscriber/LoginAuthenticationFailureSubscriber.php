<?php

namespace App\Security\Subscriber;

use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginAuthenticationFailureSubscriber implements EventSubscriberInterface
{
    private CacheItemPoolInterface $redisAdapter;
    private ?Request $request;
    private ParameterBagInterface $parameterBag;
    private EntityManagerInterface $entityManager;
    private UserService $userService;

    public function __construct(
        CacheItemPoolInterface $redisAdapter,
        RequestStack $requestStack,
        ParameterBagInterface $parameterBag,
        EntityManagerInterface $entityManager,
        UserService $userService,
    )
    {
        $this->redisAdapter = $redisAdapter;
        $this->request = $requestStack->getCurrentRequest();
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
     * @throws \DateMalformedIntervalStringException
     */
    public function onAuthenticationFailure(LoginFailureEvent $event)
    {
        $username = $event->getRequest()->request->get("_username");

        // Gestion du compteur par IP (Redis)
        $cachedUserData = $this->redisAdapter->getItem(hash('sha512', $this->request->getClientIp()));
        $list = ($cachedUserData->isHit()) ? $cachedUserData->get() : [];

        $list[$username] = isset($list[$username]) ? $list[$username] + 1 : 1;

        $cachedUserData->set($list);
        $cachedUserData->expiresAt(new \DateTime($this->parameterBag->get('security_time_expire_user_ip')));
        $this->redisAdapter->save($cachedUserData);

        // --- 2) Compteur BDD et verrou temporaire Redis ---
        $user = $this->userService->findByUsername($username);

        if ($user instanceof User) {
            // Incrémentation du compteur BDD
            $currentFailed = $user->getCountFailedConnection() ?? 0;

            if (true === $user->isEnabled()) {
                // ici on incrémente "countFailedConnection" côté utilisateur
                $user->setCountFailedConnection($currentFailed + 1);
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // Vérification seuil et lock Redis: Si dépassement, crée un verrou temporaire Redis
                if ($user->getCountFailedConnection() >= $this->parameterBag->get('security_max_login_failure_attempts')) {
                    $lockKey = hash('sha512', $this->request->getClientIp()) . '-lock';

                    $cachedLock = $this->redisAdapter->getItem($lockKey);
                    $cachedLock->set(true);
                    $cachedLock->expiresAt(
                        new \DateTime($this->parameterBag->get('security_lockout_duration'))
                    ); // ex: '+5 minutes'
                    $this->redisAdapter->save($cachedLock);

                    $user->setCountFailedConnection(0);
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }
            }
        }

    }
}
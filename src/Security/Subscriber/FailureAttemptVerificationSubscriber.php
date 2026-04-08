<?php

namespace App\Security\Subscriber;

use App\Repository\UserRepository;
use App\Security\SecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class FailureAttemptVerificationSubscriber implements EventSubscriberInterface
{
    private RouterInterface $router;
    private SecurityService $securityService;
    private ParameterBagInterface $parameterBag;
    private CacheItemPoolInterface $redisAdapter;
    private ?Request $request;
    private EntityManagerInterface $entityManager;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private UserRepository $userRepository;

    public function __construct(
        RouterInterface $router,
        SecurityService $securityService,
        ParameterBagInterface $parameterBag,
        CacheItemPoolInterface $redisAdapter,
        RequestStack $requestStack,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserRepository $userRepository
    )
    {
        $this->router = $router;
        $this->securityService = $securityService;
        $this->parameterBag = $parameterBag;
        $this->redisAdapter = $redisAdapter;
        $this->request = $requestStack->getCurrentRequest();
        $this->entityManager = $entityManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->userRepository = $userRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @throws \DateMalformedStringException
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        $arrayRouteToCheck = ['user_login', 'admin_user_login'];

        if ($request->isMethod(Request::METHOD_POST)) {
            $routeInfos = $this->router->matchRequest($request);

            if (isset($routeInfos['_route']) && in_array($routeInfos['_route'], $arrayRouteToCheck)) {

                $token = new CsrfToken('authenticate', $request->request->get('_csrf_token'));
                $user = $this->userRepository->findOneBy(['username' => $request->request->get('_username')]);

                // Récupération du nombre de tentatives par IP
                $cachedUserData = $this->redisAdapter->getItem(hash('sha512', $this->request->getClientIp()));
                $list = ($cachedUserData->isHit()) ? $cachedUserData->get() : [];

                // Affiche le captcha si dépassement
                if (!empty($list) && $this->parameterBag->get('security_max_login_failure_attempts') <= (array_sum($list)+1)) {
                    $cachedUserDataIsCaptcha = $this->redisAdapter->getItem(hash('sha512', $this->request->getClientIp()) . '-is-captcha');
                    $cachedUserDataIsCaptcha->set(true);
                    $cachedUserDataIsCaptcha->expiresAt(new \DateTime($this->parameterBag->get('security_time_expire_user_show_recaptcha')));
                    $this->redisAdapter->save($cachedUserDataIsCaptcha);
                }

                $cachedUserDataIsCaptcha = $this->redisAdapter->getItem(hash('sha512', $this->request->getClientIp()) . '-is-captcha');
                $cachedUserDataIsCaptcha = ($cachedUserDataIsCaptcha->isHit()) ? $cachedUserDataIsCaptcha->get() : false;

                $captchaResponse = $request->get('g-recaptcha-response');
                $isCaptchaOk = isset($captchaResponse) && $this->securityService->captchaVerify($request);

                if ((isset($captchaResponse) && (empty($captchaResponse) || !$isCaptchaOk))
                    || (true == $cachedUserDataIsCaptcha && (!isset($captchaResponse) || !$isCaptchaOk))
                ) {
                    /**
                     * ------------------------------------------------------------------
                     * AJOUT : ici on incrémente "countFailedConnection" côté utilisateur
                     * car l'événement "LoginAuthenticationFailure" n'est pas appelé pour l'incrémenter.
                     * ------------------------------------------------------------------
                     */
                    if (!empty($user) && $this->csrfTokenManager->isTokenValid($token) && (true === $user->isEnabled())) {
                        $user->setCountFailedConnection($user->getCountFailedConnection() + 1);
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

                    $session->getFlashBag()->add('danger', 'Captcha obligatoire');
                    $currentDomain = $request->getHost();
                    $routeName = 'user_login';

                    if ($this->parameterBag->get('url_bo') == $currentDomain) {
                        $routeName = 'admin_user_login';
                    }

                    $response = new RedirectResponse($this->router->generate($routeName));
                    $event->setResponse($response);
                }
            }
        }

    }
}
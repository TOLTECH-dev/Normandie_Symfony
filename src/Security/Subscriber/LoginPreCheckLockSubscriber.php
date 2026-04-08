<?php

namespace App\Security\Subscriber;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class LoginPreCheckLockSubscriber implements EventSubscriberInterface
{
    private CacheItemPoolInterface $redisAdapter;
    private ParameterBagInterface $parameterBag;
    private RouterInterface $router;

    public function __construct(
        CacheItemPoolInterface $redisAdapter,
        ParameterBagInterface $parameterBag,
        RouterInterface $router
    )
    {
        $this->redisAdapter = $redisAdapter;
        $this->parameterBag = $parameterBag;
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    /**
     * @param RequestEvent $event
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // On cible uniquement le formulaire de login
        $loginRoutes = ['user_login', 'admin_user_login'];

        if ($request->isMethod('POST') && in_array($request->attributes->get('_route'), $loginRoutes)) {

            $ip = $request->getClientIp();
            $lockKey = hash('sha512', $ip) . '-lock';
            $cachedLock = $this->redisAdapter->getItem($lockKey);

            if ($cachedLock->isHit() && $cachedLock->get() === true) {
                // Flash message générique
                $request->getSession()->getFlashBag()->add(
                    'danger',
                    'Impossible de traiter la demande pour le moment.'
                );

                // Redirection vers la page login
                $routeName = ($_SERVER["SERVER_NAME"] === $this->parameterBag->get('url_bo'))
                    ? 'admin_user_login'
                    : 'user_login';

                $event->setResponse(new RedirectResponse($this->router->generate($routeName)));
            }
        }
    }
}
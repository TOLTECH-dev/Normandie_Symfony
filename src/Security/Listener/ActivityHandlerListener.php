<?php

namespace App\Security\Listener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;

class ActivityHandlerListener
{
    private RequestStack $requestStack;
    private RouterInterface $router;
    private int $maxIdleTime;

    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        int $maxIdleTime
    ) {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->maxIdleTime = $maxIdleTime;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (HttpKernelInterface::MAIN_REQUEST !== $event->getRequestType()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        $session = $this->requestStack->getSession();
        $session->start();
        $metadataBag = $session->getMetadataBag();
        $lastUsed = $metadataBag->getLastUsed();

        if ($this->maxIdleTime > 0) {
            $lapse = time() - $lastUsed;
            if ($lapse > $this->maxIdleTime) {
                $event->setResponse(new RedirectResponse($this->router->generate($this->getLogoutRoute($path))));
            }
        }
    }

    private function getLogoutRoute(string $path): string
    {
        return str_starts_with($path, '/admin') ? 'admin_user_logout' : 'user_logout';
    }
}

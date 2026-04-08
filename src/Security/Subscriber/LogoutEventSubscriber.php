<?php

namespace App\Security\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutEventSubscriber implements EventSubscriberInterface
{
    private string $urlBo;
    private string $urlFo;
    private string $logoutRedirectionBo;
    private string $logoutRedirectionFo;

    public function __construct(
        string $urlBo,
        string $urlFo,
        string $logoutRedirectionBo,
        string $logoutRedirectionFo
    )
    {
        $this->urlBo = $urlBo;
        $this->urlFo = $urlFo;
        $this->logoutRedirectionBo = $logoutRedirectionBo;
        $this->logoutRedirectionFo = $logoutRedirectionFo;
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => 'onLogout'];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $currentDomain = $request->getHost();

        if ($currentDomain === $this->urlBo) {
            $redirectUrl = $this->logoutRedirectionBo;
        } elseif ($currentDomain === $this->urlFo) {
            $redirectUrl = $this->logoutRedirectionFo;
        } else {
            $redirectUrl = $this->logoutRedirectionFo;
        }

        $event->setResponse(new RedirectResponse($redirectUrl));
    }
}
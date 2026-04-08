<?php

namespace App\Security\Subscriber;

use App\Event\UserRegistrationSuccessEvent;
use App\Security\SecurityService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class UserRegisterSubscriber implements EventSubscriberInterface
{
    private TokenGeneratorInterface $tokenGenerator;
    private RequestStack $requestStack;
    private RouterInterface $router;
    private SecurityService $securityService;

    public function __construct(
        TokenGeneratorInterface $tokenGenerator,
        RouterInterface $router,
        SecurityService $securityService,
        RequestStack $requestStack
    )
    {
        $this->tokenGenerator = $tokenGenerator;
        $this->router = $router;
        $this->securityService = $securityService;
        $this->requestStack = $requestStack;
    }


    public static function getSubscribedEvents(): array
    {
        return [
            UserRegistrationSuccessEvent::class => 'onUserRegister',
        ];
    }

    public function onUserRegister(UserRegistrationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (null === $user->getConfirmationToken()) {
            $user->setConfirmationToken($this->tokenGenerator->generateToken());
        }

        $this->securityService->sendConfirmationEmail($user);

        $session = $this->requestStack->getSession();
        $session->set('user_send_confirmation_email/email', $user->getEmail());

        $event->setResponse(new RedirectResponse($this->router->generate('user_registration_check_email')));
    }
}
<?php

namespace App\Security;

use App\Entity\User;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use ReCaptcha\ReCaptcha;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class SecurityService
{
    private MailerService $mailerService;
    private RouterInterface $router;
    private TokenGeneratorInterface $tokenGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(
        MailerService $mailerService,
        RouterInterface $router,
        TokenGeneratorInterface $tokenGenerator,
        EntityManagerInterface $entityManager
    )
    {
        $this->mailerService = $mailerService;
        $this->router = $router;
        $this->tokenGenerator = $tokenGenerator;
        $this->entityManager = $entityManager;
    }

    public function captchaVerify(Request $request): bool
    {
        $recaptcha = new ReCaptcha($_ENV['GOOGLE_RECAPTCHA_SECRET']);
        $response = $recaptcha->verify(
            $request->request->get('g-recaptcha-response'),
            $request->getClientIp()
        );

        return $response->isSuccess();
    }

    public function sendConfirmationEmail(User $user): void
    {
        $context = [
            'user' => $user,
            'confirmationUrl' => $this->router->generate(
                'user_registration_confirm', ['token' => $user->getConfirmationToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];
        $subject = 'Bienvenue ' . $user->getUsername();

        $this->mailerService->sendTemplateEmail(
            $user->getEmail(),
            $subject,
            'Email/registration.html.twig',
            $context
        );
    }

    public function handleSendResettingEmail(?User $user, string $ttl): void
    {
        if (null === $user || (!$user->getConfirmationToken() && !$user->isEnabled()) || $user->isFranceConnect()) {
            return;
        }

        if (!$user->isPasswordRequestNonExpired($ttl)) {
            if (null === $user->getConfirmationToken()) {
                $token = $this->tokenGenerator->generateToken();
                $user->setConfirmationToken($token);
            }

            $this->sendResettingEmail($user);
            $user->setPasswordRequestedAt(new \DateTime());
            $this->entityManager->flush();
        }
    }

    private function sendResettingEmail(User $user): void
    {
        $url = $this->router->generate('user_resetting_reset', ['token' => $user->getConfirmationToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        $context = [
            'user' => $user,
            'confirmationUrl' => $url,
        ];

        $this->mailerService->sendTemplateEmail(
            $user->getEmail(),
            'Réinitialisation de votre mot de passe',
            'Email/reset_password.html.twig',
            $context
        );
    }

}
<?php

namespace App\Controller;

use App\Form\ResetPasswordType;
use App\Security\SecurityService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResettingController extends AbstractController
{
    public function request(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('fo_dashboard');
        }

        $nomDomaine_current = $_SERVER["SERVER_NAME"];
        $nomDomaine_fo = $this->getParameter('url_fo');
        $nomDomaine_bo = $this->getParameter('url_bo');

        if ($nomDomaine_fo == $nomDomaine_current) {
            $var = 'adresse email';
        } elseif ($nomDomaine_bo == $nomDomaine_current) {
            $var = 'login';
        } else {
            $var = 'adresse email';
        }

        return $this->render('Main/Security/Resetting/request.html.twig', [
            'recaptcha_client_key' => $this->getParameter('google_recaptcha_site_key'),
            'string' => $var
        ]);
    }

    public function sendEmail(
        Request         $request,
        SecurityService $securityService,
        UserService     $userService
    ): Response
    {
        if ($securityService->captchaVerify($request)) {
            $username = $request->request->get('username');
            $user = $userService->findByUsername(trim($username));
            $securityService->handleSendResettingEmail($user, $this->getParameter('resetting_retry_ttl'));

            return $this->redirectToRoute('user_resetting_check_email', ['username' => $username]);
        } else {
            $this->addFlash(
                'danger',
                'Captcha obligatoire'
            );

            return $this->redirectToRoute('user_resetting_request');
        }
    }

    public function reset(Request $request, UserService $userService, $token): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('fo_dashboard');
        }

        $user = $userService->findByConfirmationToken($token);

        if (null === $user) {
            $this->addFlash(
                'danger',
                'Le lien a expiré.'
            );

            return $this->redirectToRoute('user_login');
        }

        $ttl = $this->getParameter('resetting_retry_ttl');

        if (!$user->isPasswordRequestNonExpired($ttl)) {
            return $this->redirectToRoute('user_resetting_request');
        }

        $form = $this->createForm(ResetPasswordType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userService->handlePasswordReset($user);

            $this->addFlash(
                'success',
                'Le mot de passe a été réinitialisé avec succès.'
            );

            $nomDomaine_current = $_SERVER["SERVER_NAME"];
            $nomDomaine_bo = $this->getParameter('url_bo');

            $route = $nomDomaine_current === $nomDomaine_bo
                ? 'admin_user_login'
                : 'user_login';

            return $this->redirectToRoute($route);
        }

        return $this->render('Main/Security/Resetting/reset.html.twig', [
            'form' => $form->createView(),
            'token' => $token
        ]);
    }

    public function checkEmail(Request $request): Response
    {
        $username = $request->query->get('username');

        if (empty($username)) {
            return $this->redirectToRoute('fos_user_resetting_request');
        }

        return $this->render('Main/Security/Resetting/check_email.html.twig', [
            'tokenLifetime' => ceil($this->getParameter('resetting_retry_ttl') / 3600),
        ]);
    }

}
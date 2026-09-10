<?php

namespace App\Controller;

use App\Form\ResetPasswordType;
use App\Security\RateLimiter\PublicFormRateLimiter;
use App\Security\SecurityService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResettingController extends AbstractController
{
    /**
     * Marqueur de session réservant la page de confirmation d'envoi au parcours de demande.
     */
    private const SESSION_CHECK_EMAIL = '_resetting_check_email';

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
            $var = 'identifiant';
        } else {
            $var = 'adresse email';
        }

        return $this->render('Main/Security/Resetting/request.html.twig', [
            'recaptcha_client_key' => $this->getParameter('google_recaptcha_site_key'),
            'string' => $var,
            'isBackOffice' => $this->isBackOfficeDomain()
        ]);
    }

    public function sendEmail(
        Request               $request,
        SecurityService       $securityService,
        UserService           $userService,
        PublicFormRateLimiter $rateLimiter
    ): Response
    {
        $username = (string) $request->request->get('username');

        // Anti-bruteforce (R7) : le compteur est consommé avant toute recherche en base, et
        // sur l'identifiant saisi, pour que le formulaire se comporte de la même manière que
        // le compte existe ou non.
        $rateLimit = $rateLimiter->consumeResettingRequest($request, $username);

        if (!$rateLimit->isAccepted()) {
            $this->addFlash('danger', PublicFormRateLimiter::tooManyAttemptsMessage($rateLimit));

            return $this->redirectToRoute('user_resetting_request');
        }

        if ($securityService->captchaVerify($request)) {
            $user = $userService->findByUsername(trim($username));
            $securityService->handleSendResettingEmail($user, $this->getParameter('resetting_retry_ttl'));

            // L'identifiant ne doit pas figurer dans l'URL de redirection (historique du
            // navigateur, journaux du serveur web) : un marqueur de session suffit.
            $request->getSession()->set(self::SESSION_CHECK_EMAIL, true);

            return $this->redirectToRoute('user_resetting_check_email');
        } else {
            $this->addFlash(
                'danger',
                'Captcha obligatoire'
            );

            return $this->redirectToRoute('user_resetting_request');
        }
    }

    public function reset(
        Request               $request,
        UserService           $userService,
        PublicFormRateLimiter $rateLimiter,
        $token
    ): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('fo_dashboard');
        }

        // Anti-bruteforce (R7) : couvre aussi bien les accès répétés au lien (devinette de
        // jeton) que le forçage de la politique de mot de passe, d'où une consommation dès
        // l'entrée dans l'action et non à la seule soumission du formulaire.
        $rateLimit = $rateLimiter->consumeResettingReset($request);

        if (!$rateLimit->isAccepted()) {
            $this->addFlash('danger', PublicFormRateLimiter::tooManyAttemptsMessage($rateLimit));

            return $this->redirectToRoute('user_resetting_request');
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

            return $this->redirectToRoute($this->isBackOfficeDomain() ? 'admin_user_login' : 'user_login');
        }

        return $this->render('Main/Security/Resetting/reset.html.twig', [
            'form' => $form->createView(),
            'token' => $token,
            'isBackOffice' => $this->isBackOfficeDomain(),
        ]);
    }

    public function checkEmail(Request $request): Response
    {
        // Page réservée au parcours de demande : un accès direct renvoie au formulaire.
        if (!$request->getSession()->get(self::SESSION_CHECK_EMAIL, false)) {
            return $this->redirectToRoute('user_resetting_request');
        }

        return $this->render('Main/Security/Resetting/check_email.html.twig', [
            'tokenLifetime' => ceil($this->getParameter('resetting_retry_ttl') / 3600),
            'isBackOffice' => $this->isBackOfficeDomain(),
        ]);
    }

    /**
     * Les pages de réinitialisation sont servies sur les deux domaines : l'espace affiché et
     * les liens de retour dépendent du domaine courant.
     */
    private function isBackOfficeDomain(): bool
    {
        return $_SERVER["SERVER_NAME"] == $this->getParameter('url_bo');
    }

}

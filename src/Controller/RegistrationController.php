<?php

namespace App\Controller;

use App\Entity\User;
use App\Event\UserRegistrationSuccessEvent;
use App\Form\RegistrationFormType;
use App\Security\SecurityService;
use App\Service\DemandeServiceBO;
use App\Service\UserService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class RegistrationController extends AbstractController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function register(
        Request                  $request,
        DemandeServiceBO         $demandeServiceBO,
        SecurityService          $securityService,
        EventDispatcherInterface $eventDispatcher
    ): Response
    {
        if (empty($demandeServiceBO->checkIsOkDemandeCreateActionByDate())) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                'L\'inscription est impossible'
            );

            return $this->redirectToRoute('user_login');
        }

        /*
        // POUR MAINTENANCE => REDIRECTION TEMPORAIRE
        return new RedirectResponse($this->generateUrl('fos_user_security_login'));
        */

        if ($this->getUser()) {
            return new RedirectResponse($this->generateUrl('fo_dashboard'));
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if (!$securityService->captchaVerify($request)) {
                $this->addFlash('danger', 'Captcha obligatoire');
                return $this->redirectToRoute('user_registration');
            }

            $event = new UserRegistrationSuccessEvent($user);
            $eventDispatcher->dispatch($event);

            $this->userService->registerUser($user, $form->get('password')->getData());

            $eventResponse = $event->getResponse();

            if (null === $eventResponse) {
                return $this->redirectToRoute('user_register_confirmed');
            }

            return $eventResponse;
        }

        return $this->render('Main/Security/Registration/register.html.twig', [
            'form' => $form->createView(),
            'recaptcha_client_key' => $this->getParameter('google_recaptcha_site_key'),
        ]);
    }

    public function checkEmail(Request $request): Response
    {
        $session = $request->getSession();
        $email = $session->get('user_send_confirmation_email/email');

        if (empty($email)) {
            return $this->redirectToRoute('user_registration');
        }

        $session->remove('user_send_confirmation_email/email');
        $user = $this->userService->findByEmail($email);

        if (null === $user) {
            throw new NotFoundHttpException(sprintf('The user with email "%s" does not exist', $email));
        }

        return $this->render('Main/Security/Registration/check_email.html.twig', [
            'user' => $user,
        ]);
    }

    public function confirm(string $token): Response
    {
        $user = $this->userService->findByConfirmationToken($token);

        if (null === $user) {
            throw new NotFoundHttpException(sprintf('The user with confirmation token "%s" does not exist', $token));
        }

        $this->userService->confirmUser($user);
        $this->addFlash('success', 'Votre compte a été activé avec succès.');

        return $this->redirectToRoute('user_login');
    }

    public function registerConfirmed(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        return $this->render('Main/Security/Registration/confirmed.html.twig', [
            'user' => $user,
        ]);
    }

}
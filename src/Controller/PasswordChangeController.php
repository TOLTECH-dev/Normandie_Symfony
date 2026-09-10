<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Security\Password\PasswordChangeService;
use App\Security\Password\PasswordExpirationPolicy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Renouvellement du mot de passe par l'utilisateur connecté au back-office (Reco-15, ANSSI PG-078
 * R7) : à tout moment depuis l'en-tête, ou imposé à l'expiration (PasswordExpirationSubscriber).
 */
class PasswordChangeController extends AbstractController
{
    /**
     * Au-delà de ce nombre d'erreurs sur le mot de passe actuel, la session est fermée : la page ne
     * doit pas permettre de deviner le mot de passe d'une session laissée ouverte.
     */
    private const MAX_CURRENT_PASSWORD_FAILURES = 5;
    private const SESSION_FAILURES_KEY = '_password_change_failures';

    public function change(
        Request $request,
        PasswordChangeService $passwordChangeService,
        PasswordExpirationPolicy $passwordExpirationPolicy,
        TokenStorageInterface $tokenStorage
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();
        $homeRoute = \in_array(User::PARAM_ROLE_INSTRUCTEUR_UP, $user->getRoles(), true) ? 'remboursement_list' : 'demande_list_all';

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->get('currentPassword')->getErrors()->count() > 0) {
                $failures = (int) $session->get(self::SESSION_FAILURES_KEY, 0) + 1;
                $session->set(self::SESSION_FAILURES_KEY, $failures);

                if ($failures >= self::MAX_CURRENT_PASSWORD_FAILURES) {
                    $tokenStorage->setToken(null);
                    $session->invalidate();
                    $this->addFlash('danger', 'Trop d\'erreurs de saisie du mot de passe actuel : veuillez vous reconnecter.');

                    return $this->redirectToRoute('admin_user_login');
                }
            } elseif ($form->isValid()) {
                $plainPassword = (string) $form->get('newPassword')->getData();
                $errors = $passwordChangeService->validate($user, $plainPassword);

                foreach ($errors as $error) {
                    $form->get('newPassword')->addError(new FormError($error));
                }

                if ([] === $errors) {
                    // L'utilisateur du jeton est l'objet modifié : la session reste valide avec la
                    // nouvelle empreinte, les autres sessions du compte sont déconnectées.
                    $passwordChangeService->change($user, $plainPassword);
                    $session->remove(self::SESSION_FAILURES_KEY);
                    $this->addFlash('success', 'Votre mot de passe a été modifié.');

                    return $this->redirectToRoute($homeRoute);
                }
            }
        }

        return $this->render('BackOffice/Security/password_change.html.twig', [
            'form'           => $form->createView(),
            'isExpired'      => $passwordExpirationPolicy->isExpired($user),
            'expirationDate' => $passwordExpirationPolicy->getExpirationDate($user),
            'homeRoute'      => $homeRoute,
        ]);
    }
}

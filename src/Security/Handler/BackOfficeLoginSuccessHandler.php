<?php

namespace App\Security\Handler;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Class LoginSuccessHandler
 *
 * Gestion de la redirection après succès d'authentification et réinitialisation du compteur
 * de tentatives échouées.
 */
class BackOfficeLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * LoginSuccessHandler constructor.
     * @param RouterInterface $router
     * @param EntityManagerInterface $em
     */
    public function __construct(RouterInterface $router, EntityManagerInterface $em)
    {
        $this->router = $router;
        $this->em = $em;
    }

    /**
     * Méthode appelée après authentification réussie
     *
     * @param Request $request
     * @param TokenInterface $token
     * @return RedirectResponse
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        $roles = $token->getRoleNames();

        // Réinitialisation du compteur de tentatives échouées en BDD si l'utilisateur est un User FOS
        $user = $token->getUser();
        if ($user instanceof User) {
            // Au départ countFailedConnection peut être null
            $user->setCountFailedConnection(0);
            $this->em->persist($user);
            $this->em->flush();
        }

        // Redirection selon le rôle de l'utilisateur
        if (in_array('ROLE_INSTRUCTEUR_UP', $roles, true)) {
            // CAS : redirection vers la liste des remboursements
            $response = new RedirectResponse($this->router->generate('remboursement_list'));
        } elseif (
            in_array('ROLE_CONSEILLER', $roles, true) ||
            in_array('ROLE_INSTRUCTEUR', $roles, true) ||
            in_array('ROLE_AUDITEUR', $roles, true) ||
            in_array('ROLE_RENOVATEUR', $roles, true) ||
            in_array('ROLE_EPCI', $roles, true) ||
            in_array('ROLE_CLIENT', $roles, true) ||
            in_array('ROLE_ADMIN', $roles, true) ||
            in_array('ROLE_TECHNIQUE', $roles, true) ||
            in_array('ROLE_SUPER_ADMIN', $roles, true)
        ) {
            // CAS : redirection vers la liste des demandes
            $response = new RedirectResponse($this->router->generate('demande_list_all'));
        } elseif (in_array('ROLE_MEMBER', $roles, true)) {
            // Cas membre : droits insuffisants, redirection frontoffice
            $request->getSession()->getFlashBag()->add('danger', 'Droits insuffisants.');
            $response = new RedirectResponse($this->router->generate('user_logout'));
        } else {
            // Cas utilisateur non autorisé
            $request->getSession()->getFlashBag()->add('danger', 'Identifiants non autorisés.');
            $response = new RedirectResponse($this->router->generate('admin_user_logout'));
        }

        return $response;
    }
}

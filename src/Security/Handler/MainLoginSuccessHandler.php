<?php

namespace App\Security\Handler;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class MainLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    protected RouterInterface $router;
    protected EntityManagerInterface $em;

    /**
     * LoginSuccessHandler constructor.
     * @param Router $router
     * @param EntityManagerInterface $em
     */
    public function __construct(RouterInterface $router, EntityManagerInterface $em)
    {
        $this->router = $router;
        $this->em = $em;
    }

    /**
     * @param Request $request
     * @param TokenInterface $token
     * @return RedirectResponse
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        // URL for redirect the user to where they were before the login process begun if you want.
        // $referer_url = $request->headers->get('referer');

        // On récupère la liste des rôles d'un utilisateur
        $roles = $token->getRoleNames();
        /**
         * @var User|null $user
         */
        $user = $token->getUser();

        if ($user instanceof User) {
            $user->setLastLogin(new \DateTime());
            $this->em->persist($user);
            $this->em->flush();
        }

        if (
            in_array('ROLE_CONSEILLER', $roles, true) ||
            in_array('ROLE_INSTRUCTEUR', $roles, true) ||
            in_array('ROLE_INSTRUCTEUR_UP', $roles, true) ||
            in_array('ROLE_AUDITEUR', $roles, true) ||
            in_array('ROLE_RENOVATEUR', $roles, true) ||
            in_array('ROLE_EPCI', $roles, true) ||
            in_array('ROLE_CLIENT', $roles, true) ||
            in_array('ROLE_ADMIN', $roles, true) ||
            in_array('ROLE_TECHNIQUE', $roles, true) ||
            in_array('ROLE_SUPER_ADMIN', $roles, true)
        ) {
            // S'il s'agit d'un admin ou d'un super admin on le redirige vers le backoffice
            $request->getSession()->getFlashBag()->add(
                'danger',
                'Droits insuffisants.'
            );

            $response = new RedirectResponse($this->router->generate('admin_user_logout'));
        } elseif (in_array('ROLE_MEMBER', $roles, true) && !$user?->isFranceConnect()) {
            // sinon, s'il s'agit d'un membre on le redirige vers le frontoffice
            $response = new RedirectResponse($this->router->generate('fo_dashboard'));

        } else {
            // sinon il s'agit d'un user
            $request->getSession()->getFlashBag()->add(
                'danger',
                'Identifiants non autorisés.'
            );

            $referer_url = $request->headers->get('referer');
            $response = new RedirectResponse($referer_url);
        }

        return $response;
    }

}

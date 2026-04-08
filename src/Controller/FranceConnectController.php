<?php

namespace App\Controller;

use App\Service\FranceConnectService;
use KleeGroup\FranceConnectBundle\Manager\ContextServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;


class FranceConnectController extends AbstractController
{
    public function __construct(
        private readonly ContextServiceInterface $contextService,
        private readonly FranceConnectService $franceConnectService,
        private readonly Security $security
    ) {
    }

    /**
     * Callback action après l'authentification FranceConnect
     */
    public function callbackAction(Request $request): RedirectResponse
    {
        // The request REST POST /api/v1/token is included in getUserInfo
        $this->contextService->getUserInfo($request->query->all());

        return $this->redirectToRoute('franceconnect_check');
    }

    /**
     * Vérification et connexion de l'utilisateur FranceConnect
     */
    public function checkAction(Request $request): RedirectResponse|Response
    {
        $token = $this->security->getToken();
        $identity = $token->getAttributes()['identity'] ?? null;

         $returnCheckCreateAndConnect = $this->franceConnectService->check($identity['email'], $identity['given_name'], $identity['family_name']);

        $msgAlert = null;
        if ($returnCheckCreateAndConnect === 'isCreateViaFranceConnectForbidden') {
            $msgAlert = 'L\'inscription est impossible';
        } elseif ($returnCheckCreateAndConnect === 'isConnectViaFranceConnectForbidden') {
            $msgAlert = 'La connexion est impossible';
        }

        if ($msgAlert !== null) {
            $this->addFlash('danger', $msgAlert);
            return $this->redirectToRoute('app_login');
        }

        if ($returnCheckCreateAndConnect === true) {
            // Le User est connecté (après l'avoir créé ou non si existait déjà)
            return $this->redirectToRoute('fo_dashboard');
        }

        // Redirection par défaut
        return $this->redirectToRoute('fo_dashboard');
    }

}
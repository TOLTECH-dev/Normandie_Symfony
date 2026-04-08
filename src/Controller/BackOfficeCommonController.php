<?php

namespace App\Controller;

use App\Utils\DefaultServiceUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;


class BackOfficeCommonController extends AbstractController
{
    /**
     * @param $route
     * @param $routeParams
     * @return Response|null
     */
    public function layoutHeader($route, $routeParams = [])
    {
        $dataMenuConseiller = [];
        $beneficiaireId = !empty($routeParams['beneficiaireId']) ? $routeParams['beneficiaireId'] : null;

        $user = $this->getUser();
        if (null !== $user) {
            $username = $user;
        } else {
            $username = "";
        }

        $_SESSION['login'] = $username;

        $arrayRouteExplode = explode('_', $route);

        // Pour la gestion du MENU DU HAUT
        if (DefaultServiceUtils::PREFIX_PATHNAME_ASSISTANT_BENEFICIAIRE === $arrayRouteExplode[0]
            && !in_array($route, DefaultServiceUtils::$routesToIgnoreForMenuConseiller)
        ) {
            $dataMenuConseiller['isMenuMonCompte'] = 0;
            $dataMenuConseiller['isMenuLogement'] = 0;
            $dataMenuConseiller['isMenuDemandes'] = 0;

            /* /////////////////////////////////////////////////////////////////
                  MENU CONSEILLER (GESTION BENEFICIAIRE ACTIONS) A AFFICHER
            ///////////////////////////////////////////////////////////////// */

            $dataMenuConseiller['beneficiaireId'] = $beneficiaireId;
            switch ($arrayRouteExplode[1]) {
                case 'beneficiaire':
                    $dataMenuConseiller['isMenuMonCompte'] = 1;
                    break;
                case 'logement':
                    $dataMenuConseiller['isMenuLogement'] = 1;
                    break;
                case 'demande':
                    $dataMenuConseiller['isMenuDemandes'] = 1;
                    break;
            }
        }

        return $this->render('BackOffice/Common/header.html.twig', [
            'route'              => $route,
            'dataMenuConseiller' => $dataMenuConseiller
        ]);
    }

    /**
     * Display layout's footer
     * @return Response
     */
    public function layoutFooter()
    {
        return $this->render('BackOffice/Common/footer.html.twig');
    }
}


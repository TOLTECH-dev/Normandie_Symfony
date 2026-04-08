<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class HomePageController extends AbstractController
{
    /**
     * @return Response
     */
    public function index(): Response
    {
        $nomDomaine_current = $_SERVER["SERVER_NAME"];
        $nomDomaine_fo = $this->getParameter('url_fo');
        $nomDomaine_bo = $this->getParameter('url_bo');

        if ($nomDomaine_fo == $nomDomaine_current) {
            return $this->redirectToRoute('user_login', array());
        } elseif ($nomDomaine_bo == $nomDomaine_current) {
            return $this->redirectToRoute('admin_user_login', array());
        } else {
            return $this->redirectToRoute('user_login', array());
        }
    }

}
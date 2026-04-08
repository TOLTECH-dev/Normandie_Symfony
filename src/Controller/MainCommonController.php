<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;


class MainCommonController extends AbstractController
{
    /**
     * Display layout's header
     * @return Response
     */
    public function layoutHeader()
    {
        $nomDomaine_current = $_SERVER["SERVER_NAME"];
        $nomDomaine_fo = $this->getParameter('url_fo');
        $nomDomaine_bo = $this->getParameter('url_bo');

        $title = '';
        if ($nomDomaine_fo == $nomDomaine_current) {
            $title = 'fo';
        } elseif ($nomDomaine_bo == $nomDomaine_current) {
            $title = 'bo';
        } else {
            $title = 'fo';
        }

        return $this->render('Main/Common/header.html.twig', [
            'title' => $title
        ]);
    }

    /**
     * Display layout's footer
     * @return Response
     */
    public function layoutFooter()
    {
        return $this->render('Main/Common/header.html.twig');
    }
}

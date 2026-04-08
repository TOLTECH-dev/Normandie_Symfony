<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use Symfony\Component\HttpFoundation\Response;

class FrontOfficeCommonController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Display layout's header
     * @return Response
     */
    public function layoutHeader($path)
    {
        $user = $this->getUser();
        if (null !== $user) {
            $username = $user;
        } else {
            $username = "";
        }

        $_SESSION['login'] = $username;

        /* /////////////////////////////////////////////////////////////////
                            GET BENEFICIAIRE BY USER
        ///////////////////////////////////////////////////////////////// */
        $userId = $this->getUser()?->getId();
        $beneficiaireId = '';

        if (null == $userId) {
            $beneficiaire = null;
            $listDemande = [];
        } else {
            $repo = $this->em->getRepository(Beneficiaire::class);
            $beneficiaire = $repo->findOneBy([
                'user_id' => $userId
            ]);

            if ($beneficiaire) $beneficiaireId = $beneficiaire->getId();
            else $beneficiaireId = '0';

            /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE BY BENEFICIAIRE
            ///////////////////////////////////////////////////////////////// */
            $repo_demande = $this->em->getRepository(Demande_::class);
            $listDemande = $repo_demande->findBy([
                'beneficiaire_id' => $beneficiaireId
            ]);
        }

        return $this->render('FrontOffice/Common/header.html.twig', [
            'beneficiaireId' => $beneficiaireId,
            'user_id'        => $userId,
            'path'           => $path,
            'countDemande'   => count($listDemande)
        ]);
    }

    /**
     * Display layout's footer
     * @return Response
     */
    public function layoutFooter()
    {
        return $this->render('FrontOffice/Common/footer.html.twig');
    }

}
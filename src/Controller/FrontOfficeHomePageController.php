<?php

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class FrontOfficeHomePageController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    public function index(): Response
    {
        $user = $this->getUser();
        $userId = $user?->getId();

        $session = $this->requestStack->getSession();
        $session->set('login', $user ?? "");

        /* /////////////////////////////////////////////////////////////////
                            GET BENEFICIAIRE BY USER
        ///////////////////////////////////////////////////////////////// */
        if (null == $userId) {
            $beneficiaire = null;
        } else {
            $repo = $this->entityManager->getRepository(Beneficiaire::class);
            $beneficiaire = $repo->findOneBy(array(
                'user_id' => $userId
            ));
        }

        /* /////////////////////////////////////////////////////////////////
                                GET REDIRECTION
        ///////////////////////////////////////////////////////////////// */
        if (null == $beneficiaire) {
            return $this->redirectToRoute('beneficiaire_add', array(
                'userId' => $userId
            ));
        } else {
            /* /////////////////////////////////////////////////////////////////
                                GET ALL DEMANDE
            ///////////////////////////////////////////////////////////////// */
            $repo = $this->entityManager->getRepository(Demande_::class);
            $demande = $repo->findByBeneficiaire(
                $beneficiaire->getId(),
                $this->getParameter('production_travauxNiveau_BBC1'),
                $this->getParameter('production_travauxNiveau_BBC2')
            );

            if (!empty($demande)) {
                return $this->redirectToRoute('demande_list_fo', array(
                    'beneficiaireId' => $beneficiaire->getId()
                ));
            } else {
                return $this->redirectToRoute('logement_list', array(
                    'beneficiaireId' => $beneficiaire->getId()
                ));
            }
        }
    }

}
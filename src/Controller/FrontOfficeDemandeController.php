<?php

namespace App\Controller;

use App\Entity\Demande_statut;
use App\Service\DemandeServiceFO;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;

class FrontOfficeDemandeController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserService $userService;
    private DemandeServiceFO $demandeServiceFO;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserService            $userService,
        DemandeServiceFO       $demandeServiceFO

    )
    {
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->demandeServiceFO = $demandeServiceFO;
    }

    /**
     * @param $beneficiaireId
     * @return Response
     */
    public function list($beneficiaireId)
    {

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireRepository = $this->entityManager->getRepository(Beneficiaire::class);
        /**
         * @var Beneficiaire $beneficiaire
         */
        $beneficiaire = $beneficiaireRepository->find($beneficiaireId);

        /* *****************************************************************
                              U S E R   S E C U R I T Y
        ***************************************************************** */
        $this->userService->checkUserSecurity($this->getUser()->getId(), $beneficiaire->getUserId());

        /* /////////////////////////////////////////////////////////////////
                                GET ALL DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demande_Repository = $this->entityManager->getRepository(Demande_::class);
        /**
         * @var Demande_ $demande
         */
        $demande = $demande_Repository->findByBeneficiaire(
            $beneficiaireId,
            $this->getParameter('production_travauxNiveau_BBC1'),
            $this->getParameter('production_travauxNiveau_BBC2')
        );

        return $this->render('FrontOffice/Demande/list.html.twig', [
            'demande' => $demande
        ]);
    }

    /**
     * @param $demandeId
     * @return Response
     */
    public function generateFicheLiaison($demandeId)
    {
        /* /////////////////////////////////////////////////////////////////
                            GET DATA FOR FICHE LIAISON
        ///////////////////////////////////////////////////////////////// */
        $demande_Repository = $this->entityManager->getRepository(Demande_::class);
        $dataFicheLiaison = $demande_Repository->findDataFicheLiaison($demandeId);

        $demande = $demande_Repository->find($demandeId);

        /* /////////////////////////////////////////////////////////////////
                     CHECK DEMANDE ACCESS CONTROLE BY DEMANDE STATUT
        ///////////////////////////////////////////////////////////////// */
        if ($demande->getStatutId() != Demande_statut::STATUS_14) {
            throw new AccessDeniedHttpException();
        }

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireRepository = $this->entityManager->getRepository(Beneficiaire::class);
        /**
         * @var Beneficiaire $beneficiaire
         */
        $beneficiaire = $beneficiaireRepository->find($demande->getBeneficiaireId());

        /* *****************************************************************
                            S E C U R I T Y    U S E R
        ***************************************************************** */
        $this->userService->checkUserSecurity($this->getUser()->getId(), $beneficiaire->getUserId());

        /* /////////////////////////////////////////////////////////////////
                                EXPORT PDF
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceFO->createFicheLiaison($dataFicheLiaison);

        return new Response(200);
    }

    /**
     * @param int $demandeId
     * @return Response
     * @throws \Exception
     */
    public function viewStep($demandeId = 0)
    {
        $demande_Repository = $this->entityManager->getRepository(Demande_::class);

        /* /////////////////////////////////////////////////////////////////
                            GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $viewStep = '';
        $production_travauxNiveau_BBC2 = $this->getParameter('production_travauxNiveau_BBC2');
        if (0 != $demandeId) {
            $demande = $demande_Repository->findEtatAvancement($demandeId, $production_travauxNiveau_BBC2);
            if ($demande) {
                $viewStep = $this->renderView('FrontOffice/Demande/view_step.html.twig', [
                    'demande' => $demande
                ]);
            } else {
                throw new \Exception("La demande n'existe pas.");
            }
        }

        return new Response($viewStep, 200);
    }
}
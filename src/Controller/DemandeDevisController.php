<?php

namespace App\Controller;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux_devis;
use App\Form\Demande_travaux_devisType;
use App\Service\DemandeTravauxDevisService;

class DemandeDevisController extends AbstractController
{
    private DemandeTravauxDevisService $demandeTravauxDevisService;

    public function __construct(DemandeTravauxDevisService $demandeTravauxDevisService)
    {
        $this->demandeTravauxDevisService = $demandeTravauxDevisService;
    }

    /**
     * @param Request $request
     * @param string $beneficiaireId
     * @param string $logementId
     * @param string $demandeId
     * @return Response
     * @throws NonUniqueResultException
     */
    public function add(Request $request, string $beneficiaireId, string $logementId, string $demandeId): Response
    {
        $dataForAddAction = $this->demandeTravauxDevisService->getDataForAddAction(
            $request,
            true,
            $beneficiaireId,
            $logementId,
            $demandeId,
            $this->getUser()->getId()
        );

        if (!empty($dataForAddAction['isRedirectToRoute'])) {
            return $this->redirectToRoute(
                $dataForAddAction['routeName'],
                $dataForAddAction['routeParams']
            );
        }

        $beneficiaire = $dataForAddAction['beneficiaire'];
        $devis = $dataForAddAction['devis'];
        $logement = $dataForAddAction['logement'];
        $demandeAuditE = $dataForAddAction['demandeAuditE'];
        $demandeTravaux = $dataForAddAction['demandeTravaux'];
        $instruction = $dataForAddAction['instruction'];
        $remboursement = $dataForAddAction['remboursement'];

        $form = $this->createForm(Demande_travaux_devisType::class, $devis, [
            'trait_choices' => $dataForAddAction['formOption']
        ]);
        $form->remove('instructionDossierConforme');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForAddActionSubmitted = $this->demandeTravauxDevisService->manageAndGetDataForAddActionSubmitted(
                $request,
                true,
                $beneficiaire,
                $logement,
                $demandeId,
                $demandeTravaux,
                $devis,
                $instruction,
                $remboursement,
                $this->getUser()->getRoles()
            );

            if (!empty($dataForAddActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForAddActionSubmitted['routeName'],
                    $dataForAddActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('FrontOffice/Demande/Devis/add.html.twig', [
            'form' => $form->createView(),
            'devis' => $devis,
            'travaux' => $demandeTravaux,
            'auditE' => (!empty($demandeAuditE) && $demandeAuditE->getStatutId() != Demande_statut::STATUS_15) ? $demandeAuditE : null,
            'auditeur' => $dataForAddAction['auditeur'],
            'informationANAH' => $dataForAddAction['informationANAH'],
            'remboursement' => $remboursement,
            'montantTravauxNiveau3BBC' => $dataForAddAction['montantTravauxNiveau3BBC']
        ]);
    }

    /**
     * @param string $devisId
     * @return Response|null
     * @throws Exception
     */
    public function view(string $devisId): ?Response
    {
        $dataViewAction = $this->demandeTravauxDevisService->getDataForViewAction(
            true,
            $devisId,
            $this->getUser()->getId()
        );

        return $this->render('FrontOffice/Demande/Devis/view.html.twig', [
            'devis' => $dataViewAction['rowDevis'],
            'demande' => $dataViewAction['rowDemandeTravaux'],
            'devis_upload' => $dataViewAction['devis_upload'],
            'isNotEligible' => $dataViewAction['isNotEligible'],
            'arrayDemandeTypeNiveau' => array_flip(Demande_travaux_devis::$arrayDemandeTypeNiveau),
            'demandeTravauxDevisInstance' => new Demande_travaux_devis()
        ]);
    }

    /**
     * @param Request $request
     * @param string $devisId
     * @return Response|null
     * @throws Exception
     * @throws NonUniqueResultException
     */
    public function edit(Request $request, string $devisId): ?Response
    {
        $dataForEditAction = $this->demandeTravauxDevisService->getDataForEditAction(
            true,
            $devisId,
            $this->getUser()->getId()
        );

        // On recupere les objets à jour
        $beneficiaire = $dataForEditAction['beneficiaire'];
        $devis = $dataForEditAction['devis'];
        $logement = $dataForEditAction['logement'];
        $demandeAuditE = $dataForEditAction['demandeAuditE'];
        $demandeTravaux = $dataForEditAction['demandeTravaux'];
        $instruction = $dataForEditAction['instruction'];

        $form = $this->createForm(Demande_travaux_devisType::class, $devis, [
            'trait_choices' => $dataForEditAction['formOption']
        ]);
        $form->remove('instructionDossierConforme');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForEditActionSubmitted = $this->demandeTravauxDevisService->manageAndGetDataForEditActionSubmitted(
                $request,
                true,
                $beneficiaire,
                $logement,
                $demandeTravaux,
                $devis,
                $instruction,
                $this->getUser()->getRoles()
            );

            if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForEditActionSubmitted['routeName'],
                    $dataForEditActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('FrontOffice/Demande/Devis/edit.html.twig', [
            'form' => $form->createView(),
            'devis' => $devis,
            'travaux' => $demandeTravaux,
            'auditE' => (!empty($demandeAuditE) && $demandeAuditE->getStatutId() != Demande_statut::STATUS_15) ? $demandeAuditE : null,
            'auditeur' => $dataForEditAction['auditeur'],
            'informationANAH' => $dataForEditAction['informationANAH'],
            'montantTravauxNiveau3BBC' => $dataForEditAction['montantTravauxNiveau3BBC']
        ]);
    }

}
<?php

namespace App\Controller;

use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Demande_;
use App\Form\Demande_Type;
use App\Service\DemandeTravauxService;


class DemandeTravauxController extends AbstractController
{
    private DemandeTravauxService $demandeTravauxService;

    public function __construct(DemandeTravauxService $demandeTravauxService)
    {
        $this->demandeTravauxService = $demandeTravauxService;
    }

    /**
     * @param Request $request
     * @param string $beneficiaireId
     * @param string $logementId
     * @return RedirectResponse|Response|null
     * @throws Exception
     */
    public function add(Request $request, string $beneficiaireId, string $logementId)
    {
        $dataForAddAction = $this->demandeTravauxService->getDataForAddAction(
            $request,
            true,
            $beneficiaireId,
            $logementId,
            $this->getUser()
        );

        if (!empty($dataForAddAction['isRedirectToRoute'])) {
            return $this->redirectToRoute(
                $dataForAddAction['routeName'],
                $dataForAddAction['routeParams']
            );
        }

        $beneficiaire = $dataForAddAction['beneficiaire'];
        $logement = $dataForAddAction['logement'];
        $demande = $dataForAddAction['demande'];
        $auditE = $dataForAddAction['auditE'];
        $remboursementAuditStatutId = $dataForAddAction['remboursementAuditStatutId'];
        $form = $this->createForm(Demande_Type::class, $demande, [
            'trait_choices' => $dataForAddAction['formOption']
        ]);
        $form->remove('demande_auditEnergie');
        $form->remove('demande_auditNumerique');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForAddActionSubmitted = $this->demandeTravauxService->manageAndGetDataForAddActionSubmitted(
                $request,
                true,
                $beneficiaire,
                $logement,
                $demande,
                $this->getUser()->getRoles(),
                $auditE
            );
            if (!empty($dataForAddActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForAddActionSubmitted['routeName'],
                    $dataForAddActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('FrontOffice/Demande/Travaux/add.html.twig', [
            'form' => $form->createView(),
            'beneficiaire' => $beneficiaire,
            'logement' => $logement,
            'auditE' => $auditE,
            'travaux' => $demande,
            'remboursementAuditStatutId' => $remboursementAuditStatutId
        ]);
    }

    /**
     * @param string $demandeId
     * @return Response|null
     * @throws Exception
     */
    public function view(string $demandeId)
    {
        $dataViewAction = $this->demandeTravauxService->getDataForViewAction(
            true,
            $demandeId,
            $this->getUser()
        );

        return $this->render('FrontOffice/Demande/Travaux/view.html.twig', [
            'demande' => $dataViewAction['rowDemande'],
            'beneficiaireTypeKey' => $dataViewAction['beneficiaireTypeKey']
        ]);
    }

    /**
     * @param Request $request
     * @param $demandeId
     * @return RedirectResponse|Response|null
     * @throws Exception
     */
    public function edit(Request $request, string $demandeId)
    {
        $dataForEditAction = $this->demandeTravauxService->getDataForEditAction(
            true,
            $demandeId,
            $this->getUser()
        );

        // On recupere les objets à jour
        $beneficiaire = $dataForEditAction['beneficiaire'];
        $logement = $dataForEditAction['logement'];
        /**
         * @var Demande_ $demande
         */
        $demande = $dataForEditAction['demande'];
        $auditE = $dataForEditAction['auditE'];
        $remboursementAuditStatutId = $dataForEditAction['remboursementAuditStatutId'];

        $form = $this->createForm(Demande_Type::class, $demande, [
            'trait_choices' => $dataForEditAction['formOption']
        ]);
        $form->remove('demande_auditEnergie');
        $form->remove('demande_auditNumerique');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForEditActionSubmitted = $this->demandeTravauxService->manageAndGetDataForEditActionSubmitted(
                $request,
                true,
                $beneficiaire,
                $logement,
                $demande,
                $this->getUser()->getRoles(),
                $auditE,
                $dataForEditAction['nbPersFoyerOld'],
                $dataForEditAction['revenuFoyerOld']
            );

            if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForEditActionSubmitted['routeName'],
                    $dataForEditActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('FrontOffice/Demande/Travaux/edit.html.twig', [
            'form' => $form->createView(),
            'beneficiaire' => $beneficiaire,
            'logement' => $logement,
            'auditE' => $auditE,
            'travaux' => $demande,
            'remboursementAuditStatutId' => $remboursementAuditStatutId,
            'demandeConseillerId' => $demande->getDemandeTravaux()->getConseillerId()
        ]);
    }
}

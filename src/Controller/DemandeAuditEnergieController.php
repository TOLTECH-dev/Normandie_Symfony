<?php

namespace App\Controller;

use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Demande_;
use App\Form\Demande_Type;
use App\Service\DemandeAuditEnergieService;

class DemandeAuditEnergieController extends AbstractController
{
    private DemandeAuditEnergieService $demandeAuditEnergieService;

    public function __construct(DemandeAuditEnergieService $demandeAuditEnergieService)
    {
        $this->demandeAuditEnergieService = $demandeAuditEnergieService;
    }

    /**
     * @param Request $request
     * @param $beneficiaireId
     * @param $logementId
     * @param $type
     * @return RedirectResponse|Response|null
     * @throws Exception
     */
    public function add(
        Request $request,
                $beneficiaireId,
                $logementId,
                $type
    )
    {
        $dataForAddAction = $this->demandeAuditEnergieService->getDataForAddAction(
            $request,
            true,
            $beneficiaireId,
            $logementId,
            $type,
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
        $demandeTypeLabel = $dataForAddAction['demandeTypeLabel'];

        $form = $this->createForm(Demande_Type::class, $demande, [
            'trait_choices' => $dataForAddAction['formOption']
        ]);
        $form->remove('demande_auditNumerique');
        $form->remove('demande_travaux');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForAddActionSubmitted = $this->demandeAuditEnergieService->manageAndGetDataForAddActionSubmitted(
                $request,
                true,
                $beneficiaire,
                $logement,
                $demande,
                $type,
                $this->getUser()->getRoles()
            );
            if (!empty($dataForAddActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForAddActionSubmitted['routeName'],
                    $dataForAddActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('FrontOffice/Demande/AuditEnergie/add.html.twig', [
            'form' => $form->createView(),
            'auditE' => $demande,
            'beneficiaire' => $beneficiaire,
            'logement' => $logement,
            'demandeTypeLabel' => $demandeTypeLabel
        ]);
    }

    /**
     * @param $demandeId
     * @return Response|null
     * @throws Exception
     */
    public function view($demandeId)
    {
        $dataViewAction = $this->demandeAuditEnergieService->getDataForViewAction(
            true,
            $demandeId,
            $this->getUser()
        );

        return $this->render('FrontOffice/Demande/AuditEnergie/view.html.twig', [
            'demande' => $dataViewAction['rowDemande'],
            'demandeTypeLabel' => Demande_::$demandeType[$dataViewAction['rowDemande']['demandeType']],
            'beneficiaireTypeKey' => $dataViewAction['beneficiaireTypeKey']
        ]);
    }

    /**
     * @param Request $request
     * @param $demandeId
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function edit(Request $request, $demandeId)
    {
        $dataForEditAction = $this->demandeAuditEnergieService->getDataForEditAction(
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
        $form = $this->createForm(Demande_Type::class, $demande, [
            'trait_choices' => $dataForEditAction['formOption']
        ]);
        $form->remove('demande_auditNumerique');
        $form->remove('demande_travaux');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForEditActionSubmitted = $this->demandeAuditEnergieService->manageAndGetDataForEditActionSubmitted(
                $request,
                true,
                $beneficiaire,
                $logement,
                $demande,
                $this->getUser()->getRoles(),
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

        return $this->render('FrontOffice/Demande/AuditEnergie/edit.html.twig', [
            'form' => $form->createView(),
            'auditE' => $demande,
            'demandeTypeLabel' => $demande->getTypeLabel(),
            'beneficiaire' => $beneficiaire,
            'logement' => $logement,
            'demandeConseillerId' => $demande->getDemandeAuditEnergie()->getConseillerId()
        ]);
    }

}
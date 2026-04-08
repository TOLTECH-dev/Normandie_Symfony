<?php

namespace App\Controller;

use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Entity\Demande_;
use App\Form\Demande_Type;
use App\Service\DemandeAuditNumeriqueService;


class DemandeAuditNumeriqueController extends AbstractController
{
    private DemandeAuditNumeriqueService $demandeAuditNumeriqueService;

    public function __construct(DemandeAuditNumeriqueService $demandeAuditNumeriqueService)
    {
        $this->demandeAuditNumeriqueService = $demandeAuditNumeriqueService;
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
    ) {
        $dataForAddAction = $this->demandeAuditNumeriqueService->getDataForAddAction(
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
        $demande = $dataForAddAction['demande'];
        $demandeTypeLabel = $dataForAddAction['demandeTypeLabel'];
        $auditE = $dataForAddAction['auditE'];
        $isDoublon = $dataForAddAction['isDoublon'];

        /* /////////////////////////////////////////////////////////////////
                                    GET FORM
        ///////////////////////////////////////////////////////////////// */

        $form = $this->createForm(Demande_Type::class, $demande, [
            'trait_choices' => $dataForAddAction['formOption']
        ]);
        $form->remove('demande_auditEnergie');
        $form->remove('demande_travaux');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForAddActionSubmitted = $this->demandeAuditNumeriqueService->manageAndGetDataForAddActionSubmitted(
                $request,
                true,
                $beneficiaire,
                $logementId,
                $demande,
                $type,
                $this->getUser()->getRoles(),
                $isDoublon,
                $auditE
            );
            if (!empty($dataForAddActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForAddActionSubmitted['routeName'],
                    $dataForAddActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('FrontOffice/Demande/AuditNumerique/add.html.twig', [
            'form'             => $form->createView(),
            'auditN'           => $demande,
            'demandeTypeLabel' => $demandeTypeLabel,
            'formDisplay'      => $dataForAddAction['formDisplay'],
            'isDoublon'        => $isDoublon,
            'logementId'       => $logementId,
            'beneficaireId'    => $beneficiaireId
        ]);
    }

    /**
     * @param $demandeId
     * @return Response|null
     * @throws Exception
     */
    public function view($demandeId)
    {
        $dataViewAction = $this->demandeAuditNumeriqueService->getDataForViewAction(
            true,
            $demandeId,
            $this->getUser()
        );

        return $this->render('FrontOffice/Demande/AuditNumerique/view.html.twig', [
            'demande'          => $dataViewAction['rowDemande'],
            'demandeTypeLabel' => Demande_::$demandeType[$dataViewAction['rowDemande']['demandeType']]
        ]);
    }

    /**
     * @param Request $request
     * @param $demandeId
     * @return RedirectResponse|Response|null
     * @throws Exception
     */
    public function edit(Request $request, $demandeId)
    {
        $dataForEditAction = $this->demandeAuditNumeriqueService->getDataForEditAction(
            true,
            $demandeId,
            $this->getUser()
        );

        // On recupere les objets à jour
        $beneficiaire = $dataForEditAction['beneficiaire'];
        $demande = $dataForEditAction['demande'];
        $isDoublon = $dataForEditAction['isDoublon'];

        /* /////////////////////////////////////////////////////////////////
                                    GET FORM
        ///////////////////////////////////////////////////////////////// */
        $form = $this->createForm(Demande_Type::class, $demande, [
            'trait_choices' => $dataForEditAction['formOption']
        ]);
        $form->remove('demande_auditEnergie');
        $form->remove('demande_travaux');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForEditActionSubmitted = $this->demandeAuditNumeriqueService->manageAndGetDataForEditActionSubmitted(
                $request,
                true,
                $beneficiaire,
                $demande,
                $this->getUser()->getRoles(),
                $isDoublon
            );
            if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForEditActionSubmitted['routeName'],
                    $dataForEditActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('FrontOffice/Demande/AuditNumerique/edit.html.twig', [
            'form'             => $form->createView(),
            'auditN'           => $demande,
            'demandeTypeLabel' => $demande->getTypeLabel(),
            'formDisplay'      => $dataForEditAction['formDisplay'],
            'isDoublon'        => $isDoublon
        ]);
    }
}

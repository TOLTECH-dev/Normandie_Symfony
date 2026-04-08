<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\BeneficiaireType;
use App\Form\LogementType;
use App\Form\Demande_Type;
use App\Form\Demande_travaux_devisType;
use App\Service\DemandeTravauxService;
use App\Service\DemandeAuditEnergieService;
use App\Service\DemandeTravauxDevisService;
use App\Service\DemandeAuditNumeriqueService;
use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux_devis;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Entity\User;
use App\Repository\BeneficiaireRepository;
use App\Repository\Structure_Repository;
use App\Service\BeneficiaireService;
use App\Service\LogementService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\Demande_Repository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

#[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_ADMIN')")]
class ConseillerController extends AbstractController
{

/* /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                                                PARTIE BENEFICIAIRE
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////// */


    /**
     * @throws Exception
     */
    public function beneficiaireList(
        BeneficiaireRepository $beneficiaireRepository,
        Structure_Repository $structure_Repository
    ): Response {
        /* /////////////////////////////////////////////////////////////////
                                GET LIST BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $andWhereByRole = '';
        $userRole = $this->getUser()->getRoles();
        if (User::PARAM_ROLE_CONSEILLER === $userRole[0]) {
            $conseillerId = (int)substr($this->getUser()->getUsername(), 1);
            $structureId = $structure_Repository->findByConseillerId($conseillerId);
            $andWhereByRole = " AND b.structure_rattachement_id = '" . $structureId['id'] . "' ";
        }

        /* /////////////////////////////////////////////////////////////////
                                GET COUNT BENEFICIAIRES
        ///////////////////////////////////////////////////////////////// */
        $recordsTotal = $beneficiaireRepository->countAllForAssistanceBeneficiaire($andWhereByRole);

        return $this->render('BackOffice/Conseiller/list.html.twig', [
            'recordsTotal' => $recordsTotal
        ]);
    }

    /**
     * @throws Exception
     */
    public function beneficiaireListAjax(
        Request $request,
        BeneficiaireRepository $beneficiaireRepository,
        Structure_Repository $structure_Repository,
        BeneficiaireService $beneficiaireService
    ): JsonResponse {
        $andWhereByRole = '';
        $userRole = $this->getUser()->getRoles();
        if (User::PARAM_ROLE_CONSEILLER === $userRole[0]) {
            $conseillerId = (int)substr($this->getUser()->getUsername(), 1);
            $structureId = $structure_Repository->findByConseillerId($conseillerId);
            $andWhereByRole = " AND b.structure_rattachement_id = '" . $structureId['id'] . "' ";
        }

        /* /////////////////////////////////////////////////////////////////
                                GET AJAX DATA
        ///////////////////////////////////////////////////////////////// */
        $dataForListAjax = $beneficiaireService->getDataForListAjaxAssistanceBeneficiaire($request);
        $andWhere = $andWhereByRole . $dataForListAjax['andWhere'];

        $postData = $request->request->all();
        if (!empty($postData)) {
            $recordsTotal = $beneficiaireRepository->countAllForAssistanceBeneficiaire($andWhere);

            /* START of search */
            if (!empty($postData['search']['value'] ?? null)) {
                /* /////////////////////////////////////////////////////////////////
                                        GLOBAL SEARCH
                ///////////////////////////////////////////////////////////////// */

                // Search filtered result with limit and orderBy clauses
                $data = $beneficiaireRepository->findAllAjaxForAssistanceBeneficiaire(
                    $dataForListAjax['orderBy'],
                    $dataForListAjax['orderType'],
                    $dataForListAjax['start'],
                    $dataForListAjax['length'],
                    $andWhere
                );
                $recordsFiltered = $beneficiaireRepository->countAllForAssistanceBeneficiaire($andWhere);
            } else {
                /* /////////////////////////////////////////////////////////////////
                                        DEFAULT SEARCH
                ///////////////////////////////////////////////////////////////// */

                // Search all result with limit and orderBy clauses
                $data = $beneficiaireRepository->findAllAjaxForAssistanceBeneficiaire(
                    $dataForListAjax['orderBy'],
                    $dataForListAjax['orderType'],
                    $dataForListAjax['start'],
                    $dataForListAjax['length'],
                    $andWhere
                );
                $recordsFiltered = $recordsTotal;
            }
            /* END of search */

            $response = [
                "draw"            => intval($dataForListAjax['draw']),
                "recordsTotal"    => $recordsTotal,
                "recordsFiltered" => $recordsFiltered,
                "data"            => $data
            ];

            return new JsonResponse($response);
        }

        return new JsonResponse([]);
    }

    /**
     * @param Request $request
     * @param BeneficiaireService $beneficiaireService
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function beneficiaireAdd(
        Request $request,
        BeneficiaireService $beneficiaireService
    ): RedirectResponse|Response {
        $userId = $this->getUser()->getId();

        $dataForAddAction = $beneficiaireService->getDataForAddAction(
            $request,
            false,
            $this->getUser(),
            $userId
        );
        if (!empty($dataForAddAction['isRedirectToRoute'])) {
            return $this->redirectToRoute($dataForAddAction['routeName'], $dataForAddAction['routeParams']);
        }

        // On recupere les objets à jour
        $beneficiaire = $dataForAddAction['beneficiaire'];

        $form = $this->createForm(BeneficiaireType::class, $beneficiaire, [
            'trait_choices' => $dataForAddAction['formOption']
        ]);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $dataForAddActionSubmitted = $beneficiaireService->manageAndGetDataForAddActionSubmitted(
                $request,
                false,
                $beneficiaire,
                $userId
            );
            if (!empty($dataForAddActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForAddActionSubmitted['routeName'],
                    $dataForAddActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('BackOffice/Conseiller/Beneficiaire/add.html.twig', [
            'form'         => $form->createView(),
            'beneficiaire' => $beneficiaire,
            'user_nom'     => $dataForAddAction['userNom'],
            'user_prenom'  => $dataForAddAction['userPrenom'],
            'user_email'   => $dataForAddAction['userEmail']
        ]);
    }


    /**
     * @throws Exception
     */
    public function beneficiaireView(
        int $beneficiaireId,
        BeneficiaireService $beneficiaireService
    ): Response {
        $dataViewAction = $beneficiaireService->getDataForViewAction($beneficiaireId);

        /* /////////////////////////////////////////////////////////////////
                                GET DELETE FORM
        ///////////////////////////////////////////////////////////////// */
        $form_delete = $this->createFormBuilder()->getForm();

        return $this->render('BackOffice/Conseiller/Beneficiaire/view.html.twig', [
            'form_delete'          => $form_delete->createView(),
            'beneficiaire'         => $dataViewAction['beneficiaire'],
            'isEditBeneficiaire'   => $dataViewAction['isEditBeneficiaire'],
            'isDeleteBeneficiaire' => $dataViewAction['isDeleteBeneficiaire']
        ]);
    }
    /**
     * @throws Exception
     */
    public function beneficiaireEdit(
        Request $request,
        int $beneficiaireId,
        BeneficiaireService $beneficiaireService
    ): RedirectResponse|Response {
        $dataForEditAction = $beneficiaireService->getDataForEditAction(
            false,
            $beneficiaireId
        );
        // On recupere les objets à jour
        $beneficiaire = $dataForEditAction['beneficiaire'];

        $form = $this->createForm(BeneficiaireType::class, $beneficiaire, [
            'trait_choices' => $dataForEditAction['formOption']
        ]);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $dataForEditActionSubmitted = $beneficiaireService->manageAndGetDataForEditActionSubmitted(
                $request,
                false,
                $beneficiaire
            );
            if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForEditActionSubmitted['routeName'],
                    $dataForEditActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('BackOffice/Conseiller/Beneficiaire/edit.html.twig', [
            'form'         => $form->createView(),
            'beneficiaire' => $beneficiaire
        ]);
    }



    /* /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                                                PARTIE LOGEMENT
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////// */


    /**
     * @throws Exception
     */
    public function logementList(int $beneficiaireId, LogementService $logementService): Response
    {

        $dataForListAction = $logementService->getDataForListAction(
            false,
            $beneficiaireId,
            $this->getUser()->getId()
        );

        $dataForDemandeActionByLogement = $logementService->getDataForDemandeAction($dataForListAction);

        return $this->render('BackOffice/Conseiller/Logement/list.html.twig', [
            'list_logement'                  => $dataForListAction['list_logement'],
            'beneficiaireId'                 => $dataForListAction['beneficiaireId'],
            'dataForDemandeActionByLogement' => $dataForDemandeActionByLogement,
            'logementServiceData'            => $dataForListAction['logementServiceData'],
            'isShowDemandeCreateAction'      => $dataForListAction['isShowDemandeCreateAction'],
        ]);
    }

    /**
     * @param Request $request
     * @param int $beneficiaireId
     * @param LogementService $logementService
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function logementAdd(
        Request $request,
        int $beneficiaireId,
        LogementService $logementService
    ): RedirectResponse|Response {
        $dataForAddAction = $logementService->getDataForAddAction(
            $request,
            false,
            $beneficiaireId,
            $this->getUser()->getId()
        );

        // On recupere les objets à jour
        $logement = $dataForAddAction['logement'];
        $beneficiaire = $dataForAddAction['beneficiaire'];

        $form = $this->createForm(LogementType::class, $logement, [
            'trait_choices' => $dataForAddAction['formOption']
        ]);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForAddActionSubmitted = $logementService->manageAndGetDataForAddActionSubmitted(
                $request,
                false,
                $logement,
                $beneficiaire
            );
            if (!empty($dataForAddActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForAddActionSubmitted['routeName'],
                    $dataForAddActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('BackOffice/Conseiller/Logement/add.html.twig', [
            'form'         => $form->createView(),
            'logement'     => $logement,
            'beneficiaire' => $beneficiaire,
        ]);
    }

    /**
     * @param int $beneficiaireId
     * @param int $logementId
     * @param LogementService $logementService
     * @return Response
     * @throws Exception
     */
    public function logementView(
        int $beneficiaireId,
        int $logementId,
        LogementService $logementService
    ): Response {
        $dataViewAction = $logementService->getDataForViewAction($logementId);

        /* /////////////////////////////////////////////////////////////////
                                GET DELETE FORM
        ///////////////////////////////////////////////////////////////// */
        $form_delete = $this->createFormBuilder()->getForm();

        return $this->render('BackOffice/Conseiller/Logement/view.html.twig', [
            'form_delete'      => $form_delete->createView(),
            'logement'         => $dataViewAction['logement'],
            'isEditLogement'   => $dataViewAction['isEditLogement'],
            'isDeleteLogement' => $dataViewAction['isDeleteLogement'],
            'beneficiaireId'   => $beneficiaireId
        ]);
    }

    /**
     * @param Request $request
     * @param int $beneficiaireId
     * @param int $logementId
     * @param LogementService $logementService
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function logementEdit(
        Request $request,
        int $beneficiaireId,
        int $logementId,
        LogementService $logementService
    ): RedirectResponse|Response {

        $dataForEditAction = $logementService->getDataForEditAction(
            false,
            $logementId,
            $this->getUser()->getId()
        );

        // On recupere les objets à jour
        $logement = $dataForEditAction['logement'];
        $beneficiaire = $dataForEditAction['beneficiaire'];

        $form = $this->createForm(LogementType::class, $logement, [
            'trait_choices' => $dataForEditAction['formOption']
        ]);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForEditActionSubmitted = $logementService->manageAndGetDataForEditActionSubmitted(
                $request,
                false,
                $logement,
                $beneficiaire
            );
            if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForEditActionSubmitted['routeName'],
                    $dataForEditActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('BackOffice/Conseiller/Logement/edit.html.twig', [
            'form'         => $form->createView(),
            'logement'     => $logement,
            'beneficiaire' => $beneficiaire,
        ]);
    }

    /**
     * @param int $beneficiaireId
     * @param Demande_Repository $demandeRepository
     * @return Response
     * @throws Exception
     */
    public function demandeList(
        int $beneficiaireId,
        Demande_Repository $demandeRepository
    ): Response {
        /* /////////////////////////////////////////////////////////////////
                                GET ALL DEMANDE
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var Demande_ $demande
         */
        $demande = $demandeRepository->findByBeneficiaire(
            $beneficiaireId,
            $this->getParameter('production_travauxNiveau_BBC1'),
            $this->getParameter('production_travauxNiveau_BBC2')
        );

        return $this->render('BackOffice/Conseiller/demande/list.html.twig', [
            'demande' => $demande
        ]);
    }

    /* /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                                                PARTIE AUDIT ENERGIE
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////// */


    /**
     * @param Request $request
     * @param int $beneficiaireId
     * @param int $logementId
     * @param string $type
     * @param DemandeAuditEnergieService $demandeAuditEnergieService
     * @return Response
     * @throws Exception
     */
    public function demandeAuditEnergieAdd(
        Request $request,
        int $beneficiaireId,
        int $logementId,
        string $type,
        DemandeAuditEnergieService $demandeAuditEnergieService
    ): Response {

        $dataForAddAction = $demandeAuditEnergieService->getDataForAddAction(
            $request,
            false,
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

            $dataForAddActionSubmitted = $demandeAuditEnergieService->manageAndGetDataForAddActionSubmitted(
                $request,
                false,
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

        return $this->render('BackOffice/Conseiller/DemandeAuditEnergie/add.html.twig', [
            'form'             => $form->createView(),
            'auditE'           => $demande,
            'beneficiaire'     => $beneficiaire,
            'logement'         => $logement,
            'demandeTypeLabel' => $demandeTypeLabel
        ]);
    }

    /**
     * @throws Exception
     */
    public function demandeAuditEnergieView(
        int $beneficiaireId,
        int $demandeId,
        DemandeAuditEnergieService $demandeAuditEnergieService
    ): Response {
        $dataViewAction = $demandeAuditEnergieService->getDataForViewAction(
            false,
            $demandeId,
            $this->getUser()
        );

        return $this->render('BackOffice/Conseiller/DemandeAuditEnergie/view.html.twig', [
            'demande'             => $dataViewAction['rowDemande'],
            'demandeTypeLabel'    => Demande_::$demandeType[$dataViewAction['rowDemande']['demandeType']],
            'totalCommentaire'    => $dataViewAction['totalCommentaire'],
            'beneficiaireId'      => $beneficiaireId,
            'beneficiaireTypeKey' => $dataViewAction['beneficiaireTypeKey']
        ]);
    }

    /**
     * @throws Exception
     */
    public function demandeAuditEnergieEdit(
        Request $request,
        int $beneficiaireId,
        int $demandeId,
        DemandeAuditEnergieService $demandeAuditEnergieService
    ): RedirectResponse|Response {

        $dataForEditAction = $demandeAuditEnergieService->getDataForEditAction(
            false,
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

            $dataForEditActionSubmitted = $demandeAuditEnergieService->manageAndGetDataForEditActionSubmitted(
                $request,
                false,
                $beneficiaire,
                $logement,
                $demande,
                $this->getUser()->getRoles(),
                !empty($dataForEditAction['nbPersFoyerOld']) ? (int)$dataForEditAction['nbPersFoyerOld'] : null,
                !empty($dataForEditAction['revenuFoyerOld']) ? (int)$dataForEditAction['revenuFoyerOld'] : null
            );
            if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForEditActionSubmitted['routeName'],
                    $dataForEditActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('BackOffice/Conseiller/DemandeAuditEnergie/edit.html.twig', [
            'form'                => $form->createView(),
            'auditE'              => $demande,
            'demandeTypeLabel'    => $demande->getTypeLabel(),
            'beneficiaire'        => $beneficiaire,
            'logement'            => $logement,
            'demandeConseillerId' => $demande->getDemandeAuditEnergie()->getConseillerId()
        ]);
    }



    /* /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                                                PARTIE AUDIT NUMERIQUE
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////// */


    /**
     * @throws Exception
     */
    public function demandeAuditNumeriqueAdd(
        Request $request,
        int $beneficiaireId,
        int $logementId,
        string $type,
        DemandeAuditNumeriqueService $demandeAuditNumeriqueService
    ): RedirectResponse|Response {

        $dataForAddAction = $demandeAuditNumeriqueService->getDataForAddAction(
            $request,
            false,
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
            $dataForAddActionSubmitted = $demandeAuditNumeriqueService->manageAndGetDataForAddActionSubmitted(
                $request,
                false,
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

        return $this->render('BackOffice/Conseiller/DemandeAuditNumerique/add.html.twig', [
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
     * @throws Exception
     */
    public function demandeAuditNumeriqueView(
        int $beneficiaireId,
        int $demandeId,
        DemandeAuditNumeriqueService $demandeAuditNumeriqueService
    ): Response {

        $dataViewAction = $demandeAuditNumeriqueService->getDataForViewAction(
            false,
            $demandeId,
            $this->getUser()
        );

        return $this->render('BackOffice/Conseiller/DemandeAuditNumerique/view.html.twig', [
            'demande'          => $dataViewAction['rowDemande'],
            'demandeTypeLabel' => Demande_::$demandeType[$dataViewAction['rowDemande']['demandeType']],
            'totalCommentaire' => $dataViewAction['totalCommentaire'],
            'beneficiaireId'   => $beneficiaireId
        ]);
    }

    /**
     * @throws Exception
     */
    public function demandeAuditNumeriqueEdit(
        Request $request,
        int $beneficiaireId,
        int $demandeId,
        DemandeAuditNumeriqueService $demandeAuditNumeriqueService
    ): RedirectResponse|Response {

        $dataForEditAction = $demandeAuditNumeriqueService->getDataForEditAction(
            false,
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
            $dataForEditActionSubmitted = $demandeAuditNumeriqueService->manageAndGetDataForEditActionSubmitted(
                $request,
                false,
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

        return $this->render('BackOffice/Conseiller/DemandeAuditNumerique/edit.html.twig', [
            'form'             => $form->createView(),
            'auditN'           => $demande,
            'demandeTypeLabel' => $demande->getTypeLabel(),
            'formDisplay'      => $dataForEditAction['formDisplay'],
            'isDoublon'        => $isDoublon
        ]);
    }



    /* /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                                                PARTIE DEMANDE TRAVAUX
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////// */


    /**
     * @param Request $request
     * @param int $beneficiaireId
     * @param int $logementId
     * @param DemandeTravauxService $demandeTravauxService
     * @return Response
     * @throws Exception
     */
    public function demandeTravauxAdd(
        Request $request,
        int $beneficiaireId,
        int $logementId,
        DemandeTravauxService $demandeTravauxService
    ): Response {

        $dataForAddAction = $demandeTravauxService->getDataForAddAction(
            $request,
            false,
            (string)$beneficiaireId,
            (string) $logementId,
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
            $dataForAddActionSubmitted = $demandeTravauxService->manageAndGetDataForAddActionSubmitted(
                $request,
                false,
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

        return $this->render('BackOffice/Conseiller/DemandeTravaux/add.html.twig', [
            'form'                       => $form->createView(),
            'beneficiaire'               => $beneficiaire,
            'logement'                   => $logement,
            'auditE'                     => $auditE,
            'travaux'                    => $demande,
            'remboursementAuditStatutId' => $remboursementAuditStatutId
        ]);
    }

    /**
     * @throws Exception
     */
    public function demandeTravauxView(
        int $beneficiaireId,
        int $demandeId,
        DemandeTravauxService $demandeTravauxService
    ): Response {
        $dataViewAction = $demandeTravauxService->getDataForViewAction(
            false,
            (string)$demandeId,
            $this->getUser()
        );

        return $this->render('BackOffice/Conseiller/DemandeTravaux/view.html.twig', [
            'demande'             => $dataViewAction['rowDemande'],
            'totalCommentaire'    => $dataViewAction['totalCommentaire'],
            'beneficiaireId'      => $beneficiaireId,
            'beneficiaireTypeKey' => $dataViewAction['beneficiaireTypeKey']
        ]);
    }

    /**
     * @throws Exception
     */
    public function demandeTravauxEdit(
        Request $request,
        int $beneficiaireId,
        int $demandeId,
        DemandeTravauxService $demandeTravauxService
    ): RedirectResponse|Response {

        $dataForEditAction = $demandeTravauxService->getDataForEditAction(
            false,
            (string)$demandeId,
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
            $dataForEditActionSubmitted = $demandeTravauxService->manageAndGetDataForEditActionSubmitted(
                $request,
                false,
                $beneficiaire,
                $logement,
                $demande,
                $this->getUser()->getRoles(),
                $auditE,
                !empty($dataForEditAction['nbPersFoyerOld']) ? (int)$dataForEditAction['nbPersFoyerOld'] : null,
                !empty($dataForEditAction['revenuFoyerOld']) ? $dataForEditAction['revenuFoyerOld'] : null
            );

            if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForEditActionSubmitted['routeName'],
                    $dataForEditActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('BackOffice/Conseiller/DemandeTravaux/edit.html.twig', [
            'form'                       => $form->createView(),
            'beneficiaire'               => $beneficiaire,
            'logement'                   => $logement,
            'auditE'                     => $auditE,
            'travaux'                    => $demande,
            'remboursementAuditStatutId' => $remboursementAuditStatutId,
            'demandeConseillerId'        => $demande->getDemandeTravaux()->getConseillerId()
        ]);
    }



    /* /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                                                PARTIE DEMANDE TRAVAUX DEVIS
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////// */


    /**
     * @param Request $request
     * @param $beneficiaireId
     * @param $logementId
     * @param $demandeId
     * @return RedirectResponse|Response|null
     * @throws DBALException
     */
    /**
     * @throws Exception
     */
    public function demandeDevisAdd(
        Request $request,
        int $beneficiaireId,
        int $logementId,
        int $demandeId,
        DemandeTravauxDevisService $demandeTravauxDevisService
    ): RedirectResponse|Response {

        $dataForAddAction = $demandeTravauxDevisService->getDataForAddAction(
            $request,
            false,
            (string)$beneficiaireId,
            (string)$logementId,
            (string)$demandeId,
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
            $dataForAddActionSubmitted = $demandeTravauxDevisService->manageAndGetDataForAddActionSubmitted(
                $request,
                false,
                $beneficiaire,
                $logement,
                (string)$demandeId,
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

        return $this->render('BackOffice/Conseiller/DemandeTravauxDevis/add.html.twig', [
            'form'                     => $form->createView(),
            'devis'                    => $devis,
            'travaux'                  => $demandeTravaux,
            'auditE'                   => (!empty($demandeAuditE) && $demandeAuditE->getStatutId() != Demande_statut::STATUS_15) ? $demandeAuditE : null,
            'auditeur'                 => $dataForAddAction['auditeur'],
            'informationANAH'          => $dataForAddAction['informationANAH'],
            'remboursement'            => $remboursement,
            'montantTravauxNiveau3BBC' => $dataForAddAction['montantTravauxNiveau3BBC']
        ]);
    }

    /**
     * @throws Exception
     */
    public function demandeDevisView(
        int $beneficiaireId,
        int $devisId,
        DemandeTravauxDevisService $demandeTravauxDevisService
    ): Response {
        $dataViewAction = $demandeTravauxDevisService->getDataForViewAction(
            false,
            (string)$devisId,
            $this->getUser()->getId()
        );

        return $this->render('BackOffice/Conseiller/DemandeTravauxDevis/view.html.twig', [
            'devis'                       => $dataViewAction['rowDevis'],
            'demande'                     => $dataViewAction['rowDemandeTravaux'],
            'devis_upload'                => $dataViewAction['devis_upload'],
            'isNotEligible'               => $dataViewAction['isNotEligible'],
            'arrayDemandeTypeNiveau'      => array_flip(Demande_travaux_devis::$arrayDemandeTypeNiveau),
            'demandeTravauxDevisInstance' => new Demande_travaux_devis(),
            'beneficiaireId'              => $beneficiaireId
        ]);
    }

    /**
     * @throws Exception
     */
    public function demandeDevisEdit(
        Request $request,
        int $beneficiaireId,
        int $devisId,
        DemandeTravauxDevisService $demandeTravauxDevisService
    ): RedirectResponse|Response {

        $dataForEditAction = $demandeTravauxDevisService->getDataForEditAction(
            false,
            (string)$devisId,
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
            $dataForEditActionSubmitted = $demandeTravauxDevisService->manageAndGetDataForEditActionSubmitted(
                $request,
                false,
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

        return $this->render('BackOffice/Conseiller/DemandeTravauxDevis/edit.html.twig', [
            'form'                     => $form->createView(),
            'devis'                    => $devis,
            'travaux'                  => $demandeTravaux,
            'auditE'                   => (!empty($demandeAuditE) && $demandeAuditE->getStatutId() != Demande_statut::STATUS_15) ? $demandeAuditE : null,
            'auditeur'                 => $dataForEditAction['auditeur'],
            'informationANAH'          => $dataForEditAction['informationANAH'],
            'montantTravauxNiveau3BBC' => $dataForEditAction['montantTravauxNiveau3BBC']
        ]);
    }
}

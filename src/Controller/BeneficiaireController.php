<?php

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Service\AdminFormService;
use App\Utils\DefaultServiceUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Form\BeneficiaireType;
use App\Service\BeneficiaireService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BeneficiaireController extends AbstractController
{
    private BeneficiaireService $beneficiaireService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        BeneficiaireService $beneficiaireService,
        EntityManagerInterface $entityManager
    )
    {
        $this->beneficiaireService = $beneficiaireService;
        $this->entityManager = $entityManager;
    }

    /**
     * @param Request $request
     * @param string $userId
     * @return Response
     * @throws Exception
     */
    public function add(Request $request, string $userId): Response
    {
        $dataForAddAction = $this->beneficiaireService->getDataForAddAction(
            $request,
            true,
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
            $dataForAddActionSubmitted = $this->beneficiaireService->manageAndGetDataForAddActionSubmitted(
                $request,
                true,
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

        return $this->render('FrontOffice/Beneficiaire/add.html.twig', [
            'form' => $form->createView(),
            'beneficiaire' => $beneficiaire,
            'user_nom' => $dataForAddAction['userNom'],
            'user_prenom' => $dataForAddAction['userPrenom'],
            'user_email' => $dataForAddAction['userEmail']
        ]);
    }

    /**
     * @param string $beneficiaireId
     * @return Response
     * @throws Exception
     */
    public function view(string $beneficiaireId): Response
    {
        $dataViewAction = $this->beneficiaireService->getDataForViewAction($beneficiaireId);

        return $this->render('FrontOffice/Beneficiaire/view.html.twig', [
            'beneficiaire' => $dataViewAction['beneficiaire'],
            'isEditBeneficiaire' => $dataViewAction['isEditBeneficiaire']
        ]);
    }

    /**
     * @param Request $request
     * @param string $beneficiaireId
     * @return Response
     * @throws Exception
     */
    public function edit(Request $request, string $beneficiaireId): Response
    {
        $dataForEditAction = $this->beneficiaireService->getDataForEditAction(
            true,
            $beneficiaireId,
            $this->getUser()
        );
        // On recupere les objets à jour
        $beneficiaire = $dataForEditAction['beneficiaire'];

        $form = $this->createForm(BeneficiaireType::class, $beneficiaire, [
            'trait_choices' => $dataForEditAction['formOption']
        ]);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForEditActionSubmitted = $this->beneficiaireService->manageAndGetDataForEditActionSubmitted(
                $request,
                true,
                $beneficiaire
            );
            if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                return $this->redirectToRoute(
                    $dataForEditActionSubmitted['routeName'],
                    $dataForEditActionSubmitted['routeParams']
                );
            }
        }

        return $this->render('FrontOffice/Beneficiaire/edit.html.twig', [
            'form' => $form->createView(),
            'beneficiaire' => $beneficiaire,
            'user_nom' => $dataForEditAction['userNom'],
            'user_prenom' => $dataForEditAction['userPrenom'],
            'user_email' => $dataForEditAction['userEmail']
        ]);
    }

    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function listAll(Request $request, AdminFormService $formService) : Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET LIST BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo = $this->entityManager->getRepository(Beneficiaire::class);

        /* /////////////////////////////////////////////////////////////////
                                GET ASSIGN FORM
        ///////////////////////////////////////////////////////////////// */
        $form = $formService->assignStructureType();

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid())
        {
            if ($_POST['form']['beneficiaire_id']) {

                $beneficiaire = $repo->find($_POST['form']['beneficiaire_id']);

                $beneficiaire->setDateModif(new \Datetime());
                $beneficiaire->setAuteurModif($_SESSION['login']->getUsername());

                $beneficiaire->setStructureRattachementId(null);
                if ($_POST['form']['structure_rattachement_id']) {
                    $beneficiaire->setStructureRattachementId($_POST['form']['structure_rattachement_id']);
                }

                $beneficiaire->setConseillerRattachementId(null);
                if ($_POST['form']['conseiller_rattachement_id']) {
                    $beneficiaire->setConseillerRattachementId($_POST['form']['conseiller_rattachement_id']);
                }

                $this->entityManager->persist($beneficiaire);
                $this->entityManager->flush();
            }

            $request->getSession()->getFlashBag()->add(
                'success',
                'Le choix de la Structure a bien été mis à jour.'
            );

            return $this->redirectToRoute('beneficiaire_list_all');
        }

        /* /////////////////////////////////////////////////////////////////
                                GET COUNT BENEFICIAIRES
        ///////////////////////////////////////////////////////////////// */
        $recordsTotal = $repo->countAll();

        return $this->render('BackOffice/Beneficiaire/listAll.html.twig', [
            'recordsTotal' => $recordsTotal,
            'form'         => $form->createView()
        ]);
    }


    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function listAllAjax(): bool|JsonResponse
    {
        $repo = $this->entityManager->getRepository(Beneficiaire::class);

        /* /////////////////////////////////////////////////////////////////
                                GET AJAX DATA
        ///////////////////////////////////////////////////////////////// */
        $dataForListAjax = $this->beneficiaireService->getDataForListAjax();

        if (!empty($_POST) ) {
            $recordsTotal = $repo->countAll($dataForListAjax['andWhere']);

            /* START of search */
            if (!empty($_POST['search']['value'])) {
                /* /////////////////////////////////////////////////////////////////
                                        GLOBAL SEARCH
                ///////////////////////////////////////////////////////////////// */

                // Search filtered result with limit and orderBy clauses
                $data = $repo->findAllAjax(
                    $dataForListAjax['orderBy'],
                    $dataForListAjax['orderType'],
                    $dataForListAjax['start'],
                    $dataForListAjax['length'],
                    $dataForListAjax['andWhere']
                );
                $recordsFiltered = $repo->countAll($dataForListAjax['andWhere']);

            } else {
                /* /////////////////////////////////////////////////////////////////
                                        DEFAULT SEARCH
                ///////////////////////////////////////////////////////////////// */

                // Search all result with limit and orderBy clauses
                $data = $repo->findAllAjax(
                    $dataForListAjax['orderBy'],
                    $dataForListAjax['orderType'],
                    $dataForListAjax['start'],
                    $dataForListAjax['length'],
                    $dataForListAjax['andWhere']
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
        } else {
            return false;
        }
    }

    #[Security("is_granted('ROLE_ADMIN')")]
    public function delete(Request $request, string $beneficiaireId): Response
    {
        // SECURITY ACCESS
        if (true != $this->beneficiaireService->isBeneficiaireWithoutLogement($beneficiaireId)) {
            throw new AccessDeniedHttpException();
        }

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireRepository = $this->entityManager->getRepository(Beneficiaire::class);
        $beneficiaire = $beneficiaireRepository->find($beneficiaireId);

        /* /////////////////////////////////////////////////////////////////
                                GET DELETE FORM
        ///////////////////////////////////////////////////////////////// */
        $form = $this->createFormBuilder()
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($beneficiaire);
            $this->entityManager->flush();

            $request->getSession()->getFlashBag()->add(
                'success',
                'La fiche Bénéficiaire a bien été supprimée.'
            );
        }

        return $this->redirectToRoute(DefaultServiceUtils::PATHNAME_CONSEILLER_BENEFICIAIRE_LIST, []);
    }

}
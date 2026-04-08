<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LogementService;
use App\Entity\Logement;
use App\Entity\Demande_;
use App\Utils\DefaultServiceUtils;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Form\FormFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class LogementController extends AbstractController
{
    public function list(int $beneficiaireId, LogementService $logementService): Response
    {
        $dataForListAction = $logementService->getDataForListAction(
            true,
            $beneficiaireId,
            $this->getUser()->getId()
        );

        $dataForDemandeActionByLogement = $logementService->getDataForDemandeAction($dataForListAction);

        return $this->render('FrontOffice/Logement/list.html.twig', [
            'list_logement'                  => $dataForListAction['list_logement'],
            'beneficiaireId'                 => $dataForListAction['beneficiaireId'],
            'dataForDemandeActionByLogement' => $dataForDemandeActionByLogement,
            'logementServiceData'            => $dataForListAction['logementServiceData'],
            'isShowDemandeCreateAction'      => $dataForListAction['isShowDemandeCreateAction']
        ]);
    }
    public function add(Request $request, int $beneficiaireId, LogementService $logementService): Response
    {
        $dataForAddAction = $logementService->getDataForAddAction(
            $request,
            true,
            $beneficiaireId,
            $this->getUser()->getId()
        );

        // On recupere les objets à jour
        $logement = $dataForAddAction['logement'];
        $beneficiaire = $dataForAddAction['beneficiaire'];

        $form = $this->createForm(\App\Form\LogementType::class, $logement, [
            'trait_choices' => $dataForAddAction['formOption']
        ]);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $dataForAddActionSubmitted = $logementService->manageAndGetDataForAddActionSubmitted(
                $request,
                true,
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

        return $this->render('FrontOffice/Logement/add.html.twig', [
            'form'         => $form->createView(),
            'logement'     => $logement,
            'beneficiaire' => $beneficiaire,
        ]);
    }
    public function view(int $logementId, LogementService $logementService): Response
    {
        $dataViewAction = $logementService->getDataForViewAction($logementId);

        return $this->render('FrontOffice/Logement/view.html.twig', [
            'logement'         => $dataViewAction['logement'],
            'isEditLogement'   => $dataViewAction['isEditLogement'],
            'isDeleteLogement' => $dataViewAction['isDeleteLogement']
        ]);
    }
    public function edit(Request $request, int $logementId, LogementService $logementService): Response
    {
        $dataForEditAction = $logementService->getDataForEditAction(
            true,
            $logementId,
            $this->getUser()->getId()
        );

        // On recupere les objets à jour
        $logement = $dataForEditAction['logement'];
        $beneficiaire = $dataForEditAction['beneficiaire'];

        $form = $this->createForm(\App\Form\LogementType::class, $logement, [
            'trait_choices' => $dataForEditAction['formOption']
        ]);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $dataForEditActionSubmitted = $logementService->manageAndGetDataForEditActionSubmitted(
                $request,
                true,
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

        return $this->render('FrontOffice/Logement/edit.html.twig', [
            'form'         => $form->createView(),
            'logement'     => $logement,
            'beneficiaire' => $beneficiaire
        ]);
    }

    /**
     * @throws Exception
     */
    #[Security("is_granted('ROLE_ADMIN')")]
    public function delete(
        Request $request,
        int $logementId,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory
    ): RedirectResponse {
        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logementRepository = $entityManager->getRepository(Logement::class);
        $logement = $logementRepository->find($logementId);

        // SECURITY ACCESS
        $demandeRepository = $entityManager->getRepository(Demande_::class);
        $nombreDemandeLogementForDeleteDenied = $demandeRepository->findCountByBeneficiaireAndLogementForDeleteDenied(
            $logement->getBeneficiaireId(),
            $logementId
        );
        if (!empty($nombreDemandeLogementForDeleteDenied)) {
            throw new AccessDeniedHttpException();
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DELETE FORM
        ///////////////////////////////////////////////////////////////// */
        $form = $formFactory->create();

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $entityManager->remove($logement);
            $entityManager->flush();

            $request->getSession()->getFlashBag()->add(
                'success',
                'Le Logement a bien été supprimé.'
            );
        }

        return $this->redirectToRoute(DefaultServiceUtils::PATHNAME_CONSEILLER_BENEFICIAIRE_LIST, []);
    }
}

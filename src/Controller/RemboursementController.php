<?php

namespace App\Controller;

use App\Repository\Remboursement_Repository;
use App\Service\AdminFormService;
use App\Service\RemboursementService;
use App\Repository\Demande_Repository;
use App\Service\BeneficiaireService;
use App\Service\HistoriqueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class RemboursementController extends AbstractController
{
    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_INSTRUCTEUR_UP') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function list(
        Remboursement_Repository $remboursementRepository,
        RemboursementService $remboursementService,
        Request $request
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Utilisateur non valide');
        }
        $data = $remboursementRepository->findForListAjax(
            $remboursementService->getDataOptionRepository($user),
            null,
            null,
            null,
            null,
            ''
        );
        return $this->render('BackOffice/Remboursement/list.html.twig', [
            'isDataEmpty' => empty($data)
        ]);
    }

    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_INSTRUCTEUR_UP') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function listAjax(
        Request $request,
        Remboursement_Repository $remboursementRepository,
        RemboursementService $remboursementService
    ): JsonResponse|bool {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Utilisateur non valide');
        }
        $options = $remboursementService->getDataOptionRepository($user);
        $dataForListAjax = $remboursementService->getDataForListAjax(
            $options['production_travauxNiveau_BBC1'],
            $options['production_travauxNiveau_BBC2']
        );
        if ($request->isMethod('POST')) {
            $recordsTotal = $remboursementRepository->countForList($options, '');
            if (!empty($dataForListAjax["columnWhereTmp"])) {
                $data = $remboursementRepository->findForListAjax(
                    $options,
                    $dataForListAjax["orderBy"],
                    $dataForListAjax["orderType"],
                    $dataForListAjax["start"],
                    $dataForListAjax["length"],
                    $dataForListAjax["where"]
                );
                $recordsFiltered = $remboursementRepository->countForList(
                    $options,
                    $dataForListAjax["where"]
                );
            } else {
                $data = $remboursementRepository->findForListAjax(
                    $options,
                    $dataForListAjax["orderBy"],
                    $dataForListAjax["orderType"],
                    $dataForListAjax["start"],
                    $dataForListAjax["length"],
                    $dataForListAjax["where"]
                );
                $recordsFiltered = $recordsTotal;
            }
            $response = [
                "draw" => intval($dataForListAjax["draw"]),
                "recordsTotal" => $recordsTotal,
                "recordsFiltered" => $recordsFiltered,
                "data" => $data
            ];
            return new JsonResponse($response);
        }
        return false;
    }

    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function deny(
        Request $request,
        int $remboursementId,
        EntityManagerInterface $em,
        Demande_Repository $demandeRepository,
        Remboursement_Repository $remboursementRepository,
        AdminFormService $adminFormService,
        RemboursementService $remboursementService,
        BeneficiaireService $beneficiaireService,
        HistoriqueService $historiqueService
    ): RedirectResponse {
        $remboursement = $remboursementRepository->find($remboursementId);
        if (!$remboursement) {
            return $this->redirectToRoute('demande_list_all');
        }

        $form_deny = $adminFormService->denyRemboursementType();

        if ($request->isMethod('POST') && $form_deny->handleRequest($request)->isValid()) {
            $demande = $demandeRepository->find($remboursement->getDemandeId());
            $remboursement->setDateModif(new \DateTime());
            $remboursement->setAuteurModif($this->getUser()->getUsername() ?? '');
            $remboursement->setMotifRefus(htmlspecialchars($form_deny["motifRefus"]->getData()));
            $statut = $remboursementService->searchStatutRefus();
            $remboursement->setStatutId($statut);
            $em->persist($remboursement);
            $em->flush();
            $user = $this->getUser();
            if (!$user instanceof \App\Entity\User) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Utilisateur non valide');
            }
            $options = $remboursementService->getDataOptionRepository($user);
            $statutDescription = $remboursementService->findStatutDescriptionByRemboursement(
                $remboursement->getId(),
                $options['production_travauxNiveau_BBC1']
            );
            $remboursement->setStatutDescription($statutDescription);
            $em->persist($remboursement);
            $em->flush();
            $historiqueService->save(
                $remboursement->getDemandeId(),
                $remboursement->getStatutId(),
                $demande->getType() ?? '',
                $this->getUser()->getRoles() ?? [],
                true,
                'Remboursement refusé directement par la Région',
                $beneficiaireService->findBeneficiaireById($demande->getBeneficiaireId() ?? 0)->getEmail() ?? '',
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId()
            );
            $request->getSession()->getFlashBag()->add(
                'success',
                'Le remboursement du dossier n°' . $remboursement->getDemandeId() . ' a bien été refusé.'
            );
            return $this->redirectToRoute('demande_list_all');
        }
        return $this->redirectToRoute('demande_list_all');
    }

    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function export(
        RemboursementService $remboursementService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Utilisateur non valide');
        }
        $option = $remboursementService->getDataOptionRepository($user);
        $response = $remboursementService->export($option);
        return $response;
    }
}

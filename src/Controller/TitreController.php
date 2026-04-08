<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Titre;
use App\Repository\TitreRepository;
use App\Service\TitreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class TitreController extends AbstractController
{
    public function __construct(
        private readonly TitreRepository $titreRepository,
        private readonly TitreService $titreService,
        private readonly ParameterBagInterface $parameterBag,
    ) {}

    /**
     * Affiche la liste des titres
     */
    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function list(): Response
    {
        $recordsTotal = $this->titreRepository->countForList(null);

        return $this->render('BackOffice/Titre/list.html.twig', [
            'recordsTotal' => $recordsTotal
        ]);
    }


    /**
     * Récupère les données pour la liste AJAX
     */
    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function listAjax(Request $request): JsonResponse
    {
        try {
            if (!$request->isMethod('POST')) {
                return new JsonResponse([
                    "draw" => 0,
                    "recordsTotal" => 0,
                    "recordsFiltered" => 0,
                    "data" => []
                ]);
            }

            $productionTravauxNiveauBBC1 = (int)$this->parameterBag->get('production_travauxNiveau_BBC1');
            $productionTravauxNiveauBBC2 = (int)$this->parameterBag->get('production_travauxNiveau_BBC2');

            // Convertir les données POST via JSON pour éviter les objets Doctrine
            $postData = $request->request->all();

            $dataForListAjax = $this->titreService->getDataForListAjax(
                $productionTravauxNiveauBBC1,
                $productionTravauxNiveauBBC2,
                $postData
            );
            if (empty($dataForListAjax)) {
                return new JsonResponse([
                    "draw" => intval($postData['draw'] ?? 0),
                    "recordsTotal" => 0,
                    "recordsFiltered" => 0,
                    "data" => []
                ]);
            }

            // Ensure countResult is properly formatted as array
            $countResultTotal = $this->titreRepository->countForList([]);
            $recordsTotal = 0;
            if (is_array($countResultTotal) && isset($countResultTotal['countId'])) {
                $recordsTotal = (int)$countResultTotal['countId'];
            }

            $data = [];
            $recordsFiltered = 0;
            $searchValue = null;
            if (!empty($postData['search']) && is_array($postData['search']) && !empty($postData['search']['value'])) {
                $searchValue = $postData['search']['value'];
            }

            if (!empty($searchValue)) {
                // Search filtered result with limit and orderBy clauses
                $data = $this->titreRepository->findForListAjax(
                    $productionTravauxNiveauBBC1,
                    $productionTravauxNiveauBBC2,
                    $dataForListAjax["start"] ?? 0,
                    $dataForListAjax["length"] ?? 10,
                    $dataForListAjax["orderBy"] ?? null,
                    $dataForListAjax["orderType"] ?? null,
                    $dataForListAjax["arrayColumnWhere"] ?? []
                );
                $countResult = $this->titreRepository->countForList(
                    $dataForListAjax["arrayColumnWhere"] ?? []
                );
                $recordsFiltered = 0;
                if (is_array($countResult) && isset($countResult['countId'])) {
                    $recordsFiltered = (int)$countResult['countId'];
                }
            } elseif (!empty($dataForListAjax["columnWhereTmp"])) {

                // Search filtered result with limit and orderBy clauses
                $data = $this->titreRepository->findForListAjax(
                    $productionTravauxNiveauBBC1,
                    $productionTravauxNiveauBBC2,
                    $dataForListAjax["start"] ?? 0,
                    $dataForListAjax["length"] ?? 10,
                    $dataForListAjax["orderBy"] ?? null,
                    $dataForListAjax["orderType"] ?? null,
                    $dataForListAjax["arrayColumnWhere"] ?? []
                );
                $countResult = $this->titreRepository->countForList(
                    $dataForListAjax["arrayColumnWhere"] ?? []
                );
                $recordsFiltered = 0;
                if (is_array($countResult) && isset($countResult['countId'])) {
                    $recordsFiltered = (int)$countResult['countId'];
                }
            } else {
                // Search all result with limit and orderBy clauses
                $data = $this->titreRepository->findForListAjax(
                    $productionTravauxNiveauBBC1,
                    $productionTravauxNiveauBBC2,
                    $dataForListAjax["start"] ?? 0,
                    $dataForListAjax["length"] ?? 10,
                    $dataForListAjax["orderBy"] ?? null,
                    $dataForListAjax["orderType"] ?? null,
                    $dataForListAjax["arrayColumnWhere"] ?? []
                );
                $recordsFiltered = $recordsTotal;
            }
            /* END of search */

            // Force clean array conversion to remove any Token objects
            if (is_array($data) && !empty($data)) {
                $data = json_decode(json_encode($data), true) ?? [];
            } else {
                $data = [];
            }

            $response = [
                "draw"            => intval($dataForListAjax["draw"] ?? 0),
                "recordsTotal"    => $recordsTotal,
                "recordsFiltered" => $recordsFiltered,
                "data"            => $data
            ];

            return new JsonResponse($response);
        } catch (\Exception $e) {
            return new JsonResponse([
                "error" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "draw" => 0,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => []
            ], 500);
        }
    }

    /**
     * Exporte l'attestation de non réception
     */
    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function exportAttestationNonReception(int $titreId): RedirectResponse
    {
        $data = $this->titreRepository->findDataAttestationNonReception(
            $titreId,
            (int)$this->parameterBag->get('production_travauxNiveau_BBC1'),
            (int)$this->parameterBag->get('production_travauxNiveau_BBC2')
        );
        $this->titreService->createAttestationNonReception($data);

        return $this->redirectToRoute('titre_list', []);
    }
}

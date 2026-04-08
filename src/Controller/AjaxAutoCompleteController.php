<?php

namespace App\Controller;

use App\Entity\Logement;
use App\Entity\Partenaire_;
use App\Entity\Structure_;
use App\Service\StructureService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxAutoCompleteController extends AbstractController
{
    private StructureService $structureService;
    private EntityManagerInterface $entityManager;

    public function __construct(StructureService $structureService, EntityManagerInterface $entityManager)
    {
        $this->structureService = $structureService;
        $this->entityManager = $entityManager;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function updateListStructure(Request $request): JsonResponse
    {
        $listRecommanded = [];
        $listOthers =      [];

        $beneficiaireId = (int)$request->get('beneficiaireId');
        $logementId = (int)$request->get('logementId');
        $nombrePersonneFoyer = (int)$request->get('nombrePersonneFoyer');
        $revenuFiscalRef = (int)$request->get('revenuFiscalRef');
        $INSEE = (int)$request->get('insee');
        $type = (int)$request->get('type');
        $structureId = (int)$request->get('structureId');
        $isCreation = (int)$request->get('isCreation');

        if (
            true == is_int($type) &&
            true == is_int($beneficiaireId) &&
            true == is_int($logementId) &&
            true == is_int($nombrePersonneFoyer) &&
            true == is_int($revenuFiscalRef) &&
            true == is_int($INSEE) &&
            true == is_int($structureId)
        ) {
            switch ($type) {
                case 0: // case Beneficiaire
                    $listRecommanded = $this->structureService->searchAdvised(
                        null,
                        null,
                        $nombrePersonneFoyer,
                        $revenuFiscalRef,
                        $INSEE
                    );

                    if (empty($listRecommanded)) {
                        // liste recommandées vide => on garde dans Autres toutes les structures
                        $listOthers = $this->structureService->searchOther(
                            null,
                            null,
                            $nombrePersonneFoyer,
                            $revenuFiscalRef,
                            $INSEE,
                            $listRecommanded,
                            null
                        );
                    } else {
                        // cas liste recommandées existante

                        if (!empty($structureId)) {
                            // une structure est selectionnée => on garde dans "Autres" seulement structure selectionnée
                            $listOthers = $this->structureService->searchOther(
                                null,
                                null,
                                $nombrePersonneFoyer,
                                $revenuFiscalRef,
                                $INSEE,
                                $listRecommanded,
                                $structureId
                            );
                        } else {
                            // pas de structure selectionnée => ON garde seulement liste recommandées
                            $listOthers = [];
                        }
                    }
                    break;

                case 1: // cas demandes (audit, travaux, etc)
                case 2: // cas form "modifier les contacts" (demande view)
                    $listRecommanded = $this->structureService->searchAdvised(
                        $beneficiaireId,
                        $logementId,
                        $nombrePersonneFoyer,
                        $revenuFiscalRef,
                        null
                    );

                    if ($isCreation == '1') {
                        $listOthers = [];
                    } else {

                        if (2 === $type) {
                            // Si cas form "modifier les contacts" => On affiche tous les elements de "Autres'
                            $structureId = 0;
                        }
                        $listOthers = $this->structureService->searchOther(
                            $beneficiaireId,
                            $logementId,
                            $nombrePersonneFoyer,
                            $revenuFiscalRef,
                            null,
                            $listRecommanded,
                            $structureId
                        );
                    }
                    break;
            }
        }

        $response = new JsonResponse();
        $response->setData([
            'advisedStructureList' => $listRecommanded,
            'otherStructureList'   => $listOthers,
        ]);

        return $response;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateListConseiller(Request $request)
    {
        $structureId = (int)$request->get('structureId');

        if (true == is_int($structureId)) {
            $repo = $this->entityManager->getRepository(Structure_::class);
            $results_conseiller = $repo->search($structureId);
        } else {
            $results_conseiller = [];
        }

        $response = new JsonResponse();
        $response->setData([
            'conseillerList' => $results_conseiller
        ]);

        return $response;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateListPartenaire(Request $request)
    {
        $type = (int)$request->get('type');

        if (true == is_int($type)) {
            $repo = $this->entityManager->getRepository(Partenaire_::class);
            $results_partenaire = $repo->search($type);
        } else {
            $results_partenaire = [];
        }

        $response = new JsonResponse();
        $response->setData([
            'partenaireList' => $results_partenaire
        ]);

        return $response;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateListCity(Request $request): JsonResponse
    {
        $codePostal = (int)$request->get('codePostal');

        $results_city = [];
        if (is_int($codePostal) && $codePostal > 0) {
            $repo_logement = $this->entityManager->getRepository(Logement::class);
            $results_city = $repo_logement->searchByCodePostal($request->get('codePostal'));
        }
        return new JsonResponse([
            'cityList' => $results_city
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateListAddress(Request $request)
    {
        $insee = (int)$request->get('insee');

        if (true == is_int($insee)) {
            $repo_logement = $this->entityManager->getRepository(Logement::class);
            $results_address = $repo_logement->searchByINSEE($request->get('insee'));
        } else {
            $results_address = array();
        }

        $response = new JsonResponse();
        $response->setData(array(
            'addressList' => $results_address
        ));

        return $response;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSecurityForm(Request $request)
    {
        $request->getSession()->set('timestamp_logement', false);

        $response = new JsonResponse();
        return $response;
    }

}

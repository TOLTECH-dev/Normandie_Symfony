<?php

namespace App\Service;

use App\Entity\Structure_;
use App\Utils\DefaultServiceUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ANAHCritere;
use App\Repository\Structure_Repository;
use App\Repository\ANAHCritereRepository;


class StructureService
{
    /**
     * @var EntityManagerInterface
     */
    private $EM = null;

    /**
     * @var Structure_Repository
     */
    private $repo_structure;

    /**
     * @var ANAHCritereRepository
     */
    private $repo_ANAHCritere;

    const PLAFOND_INF_KEY = 'INF';
    const PLAFOND_SUP_KEY = 'SUP';


    public function __construct(
        EntityManagerInterface $EM
    ) {
        $this->EM = $EM;
        $this->repo_structure = $this->EM->getRepository(Structure_::class);
        $this->repo_ANAHCritere = $this->EM->getRepository(ANAHCritere::class);
    }



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $beneficiaireId
     * @param $logementId
     * @param $nombrePersonneFoyer
     * @param $revenuFiscalRef
     * @param $INSEE
     * @return array
     * @throws Exception
     */
    public function searchAdvised(
        $beneficiaireId = null,
        $logementId = null,
        $nombrePersonneFoyer = null,
        $revenuFiscalRef = null,
        $INSEE = null
    ) {
        $data = [];

        if ($beneficiaireId && $logementId) {  // case audit_energie / travaux
            $criteria = $this->repo_structure->findCriteria($beneficiaireId, $logementId);

            if (!$nombrePersonneFoyer && !$revenuFiscalRef) {
                // We take values of Beneficiaire
                $nombrePersonneFoyer = $criteria['nombrePersonneFoyer'];
                $revenuFiscalRef = $criteria['revenuFiscalRef'];
            }
            $INSEE = $criteria['insee'];
        }

        if ($nombrePersonneFoyer && $INSEE) {
            $plafond = $this->findPlafondANAH($nombrePersonneFoyer, $revenuFiscalRef);
            $orientationStructureResult = $this->repo_structure->findByOrientation($INSEE);

            if ($orientationStructureResult) {
                if (self::PLAFOND_INF_KEY == $plafond) {
                    if (!empty($orientationStructureResult['structureInferieurIdNomConcat'])) {
                        $data = $this->dataSelectOptionByGroupconcatString($orientationStructureResult['structureInferieurIdNomConcat']);
                    }
                } elseif (self::PLAFOND_SUP_KEY == $plafond) {
                    if (!empty($orientationStructureResult['structureSuperieurIdNomConcat'])) {
                        $data = $this->dataSelectOptionByGroupconcatString($orientationStructureResult['structureSuperieurIdNomConcat']);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param null $beneficiaireId
     * @param null $logementId
     * @param null $nombrePersonneFoyer
     * @param null $revenuFiscalRef
     * @param null $INSEE
     * @param array $advisedList
     * @param null $structureId
     * @return array
     * @throws Exception
     */
    public function searchOther(
        $beneficiaireId = null,
        $logementId = null,
        $nombrePersonneFoyer = null,
        $revenuFiscalRef = null,
        $INSEE = null,
        $advisedList = [],
        $structureId = null
    ) {
        if (empty($advisedList)) {
            $advisedList = $this->searchAdvised(
                $beneficiaireId,
                $logementId,
                $nombrePersonneFoyer,
                $revenuFiscalRef,
                $INSEE
            );
        }
        $otherList = $this->repo_structure->findOther(array_keys($advisedList));

        $data = [];
        foreach ($otherList as $object) {
            if (empty($structureId)) {
                $data[$object['s_id']] = $object['si_nom'];
            } else {
                if ($structureId == $object['s_id']) {
                    // Si la structure selectionnée en edition est dans le groupe "Autres"
                    // => On la selectionne "seulement elle" pour affichage
                    $data[$object['s_id']] = $object['si_nom'];
                }
            }
        }

        return $data;
    }

    /**
     * @param $groupconcatString
     * @param $separatorItems
     * @param $separatorIdLabel
     * @return array
     */
    public function dataSelectOptionByGroupconcatString(
        $groupconcatString,
        $separatorItems = DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS,
        $separatorIdLabel = DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS_ID_LABEL
    ) {
        $dataReturn = [];
        if (empty($groupconcatString))
            return [];

        $arrayItems = explode($separatorItems, $groupconcatString);
        foreach ($arrayItems as $item) {
            $arrayItemIdLabel = explode($separatorIdLabel, $item);
            if (!empty($arrayItemIdLabel)) {
                $dataReturn[$arrayItemIdLabel[0]] = $arrayItemIdLabel[1];
            }
        }
        return $dataReturn;
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $nombrePersonne
     * @param $revenu
     * @return string
     */
    private function findPlafondANAH($nombrePersonne, $revenu)
    {
        $nbPers = ($nombrePersonne) ? $nombrePersonne : 0;
        $revenu_ = ($revenu) ? $revenu : 0;
        $nombrePersonneLimit = 5; //  Limite à partir de laquelle il y a ensuite un supplement à prendre aussi en compte

        $quotient = floor($nbPers / $nombrePersonneLimit);
        $nombrePersonneSup = 0;

        if ($quotient != 0) {
            $nombrePersonneSup = $nbPers - $nombrePersonneLimit;
            $nbPers = $nombrePersonneLimit;
        }
        /**
         * @var ANAHCritere $ANAHCritere
         */
        $ANAHCritere = $this->repo_ANAHCritere->findOneBy([
            'nbPersonne' => $nbPers
        ]);
        $plafond = ($ANAHCritere) ? $ANAHCritere->getPlafondModeste() : 0;
        $supplement = ($ANAHCritere) ? $ANAHCritere->getSupplementModeste() : 0;

        $plafond = $plafond + ($supplement * $nombrePersonneSup);  // $plafond = 43297 + (5454 * 0) = 43297

        return ($revenu_ <= $plafond) ? self::PLAFOND_INF_KEY : self::PLAFOND_SUP_KEY;
    }
}

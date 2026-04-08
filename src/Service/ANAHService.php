<?php

namespace App\Service;

use App\Entity\ANAHCritere;
use App\Repository\ANAHCritereRepository;
use Doctrine\ORM\EntityManagerInterface;

class ANAHService
{
    /**
     * @var EntityManagerInterface
     */
    private $EM;

    /**
     * @var ANAHCritereRepository
     */
    private $repo_ANAHCritere;



    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->EM = $entityManager;
        $this->repo_ANAHCritere = $this->EM->getRepository(ANAHCritere::class);
    }



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $nbPersFoyer
     * @return int
     */
    public function findPlafond($nbPersFoyer)
    {
        $ANAH = 0;
        if ($nbPersFoyer > 0 && $nbPersFoyer <= 5) {

            /**
             * @var ANAHCritere $ANAHCritere
             */
            $ANAHCritere = $this->repo_ANAHCritere->findOneBy([
                'nbPersonne' => $nbPersFoyer
            ]);
            $ANAH = $ANAHCritere->getPlafondModeste();
        } elseif ($nbPersFoyer > 5) {
            /**
             * @var ANAHCritere $ANAHCritere
             */
            $ANAHCritere = $this->repo_ANAHCritere->findOneBy([
                'nbPersonne' => 5
            ]);
            $plafond_ANAH = $ANAHCritere->getPlafondModeste();
            $supplement_ANAH = $ANAHCritere->getSupplementModeste();

            $ANAH = $plafond_ANAH + ($supplement_ANAH*($nbPersFoyer-5));
        }

        return $ANAH;
    }

    /**
     * @param $situationProprietaire
     * @param $nbPersFoyer
     * @param $revenuReference
     * @return bool
     */
    public function checkPlafond($situationProprietaire, $nbPersFoyer, $revenuReference)
    {
        $ANAH = $this->findPlafond($nbPersFoyer);
        $isRevenuOk = ($revenuReference < ($ANAH*4));

        if (
            ('0' == $situationProprietaire && $isRevenuOk)
            || ('1' == $situationProprietaire && $isRevenuOk)
        ) {
            $response = true;
        } else {
            $response = false;
        }

        return $response;
    }

    /**
     * @param $nbPersFoyer
     * @param $revenuFiscal
     * @param $ANAHCritere
     * @return int|void|null
     */
    public function findTypeMenageCode($nbPersFoyer, $revenuFiscal, $ANAHCritere = [])
    {
        if (empty($nbPersFoyer) || !isset($revenuFiscal)) {
            return null;
        }

        $revenuFiscal = (integer)$revenuFiscal;

        if ($nbPersFoyer > 0 && $nbPersFoyer <= ANAHCritere::NOMBRE_PERSONNE_PARAMETRE_SUPPLEMENT) {
            if (!empty($ANAHCritere)) {
                $ANAHCriterePlafondTresModeste = $ANAHCritere[$nbPersFoyer][ANAHCritere::ANAHCritere_PLAFOND_TRES_MODESTE_KEY];
                $ANAHCriterePlafondModeste = $ANAHCritere[$nbPersFoyer][ANAHCritere::ANAHCritere_PLAFOND_MODESTE_KEY];
                $ANAHCriterePlafondIntermediaire =  $ANAHCritere[$nbPersFoyer][ANAHCritere::ANAHCritere_PLAFOND_INTERMEDIAIRE_KEY];
            } else {
                /**
                 * @var ANAHCritere $ANAHCritere
                 */
                $ANAHCritere = $this->repo_ANAHCritere->findOneBy([
                    'nbPersonne' => $nbPersFoyer
                ]);
                $ANAHCriterePlafondTresModeste =  $ANAHCritere->getPlafondTresModeste();
                $ANAHCriterePlafondModeste =  $ANAHCritere->getPlafondModeste();
                $ANAHCriterePlafondIntermediaire =  $ANAHCritere->getPlafondIntermediaire();
            }
        } elseif ($nbPersFoyer > ANAHCritere::NOMBRE_PERSONNE_PARAMETRE_SUPPLEMENT) {
            $nbPersFoyerParametreSupplement = ANAHCritere::NOMBRE_PERSONNE_PARAMETRE_SUPPLEMENT;
            if (!empty($ANAHCritere)) {
                $ANAHCritereSupplementTresModeste = $ANAHCritere[$nbPersFoyerParametreSupplement][ANAHCritere::ANAHCritere_SUPPLEMENT_TRES_MODESTE_KEY];
                $ANAHCritereSupplementModeste = $ANAHCritere[$nbPersFoyerParametreSupplement][ANAHCritere::ANAHCritere_SUPPLEMENT_MODESTE_KEY];
                $ANAHCritereSupplementIntermediaire = $ANAHCritere[$nbPersFoyerParametreSupplement][ANAHCritere::ANAHCritere_SUPPLEMENT_INTERMEDIAIRE_KEY];

                $ANAHCriterePlafondTresModeste = $ANAHCritere[$nbPersFoyerParametreSupplement][ANAHCritere::ANAHCritere_PLAFOND_TRES_MODESTE_KEY] + ($ANAHCritereSupplementTresModeste * ($nbPersFoyer - $nbPersFoyerParametreSupplement));
                $ANAHCriterePlafondModeste = $ANAHCritere[$nbPersFoyerParametreSupplement][ANAHCritere::ANAHCritere_PLAFOND_MODESTE_KEY] + ($ANAHCritereSupplementModeste * ($nbPersFoyer - $nbPersFoyerParametreSupplement));
                $ANAHCriterePlafondIntermediaire = $ANAHCritere[$nbPersFoyerParametreSupplement][ANAHCritere::ANAHCritere_PLAFOND_INTERMEDIAIRE_KEY] + ($ANAHCritereSupplementIntermediaire * ($nbPersFoyer - $nbPersFoyerParametreSupplement));
            } else {
                /**
                 * @var ANAHCritere $ANAHCritere
                 */
                $ANAHCritere = $this->repo_ANAHCritere->findOneBy([
                    'nbPersonne' => $nbPersFoyerParametreSupplement
                ]);

                $ANAHCriterePlafondTresModeste = $ANAHCritere->getPlafondTresModeste() + ($ANAHCritere->getSupplementTresModeste() * ($nbPersFoyer - $nbPersFoyerParametreSupplement));
                $ANAHCriterePlafondModeste = $ANAHCritere->getPlafondModeste() + ($ANAHCritere->getSupplementModeste() * ($nbPersFoyer - $nbPersFoyerParametreSupplement));
                $ANAHCriterePlafondIntermediaire = $ANAHCritere->getPlafondIntermediaire() + ($ANAHCritere->getSupplementIntermediaire() * ($nbPersFoyer - $nbPersFoyerParametreSupplement));
            }
        }

        if ($revenuFiscal <= $ANAHCriterePlafondTresModeste) {
            return ANAHCritere::TYPE_MENAGE_TRES_MODESTE_CODE;
        } elseif (
            $revenuFiscal > $ANAHCriterePlafondTresModeste
            && $revenuFiscal <= $ANAHCriterePlafondModeste
        ) {
            return ANAHCritere::TYPE_MENAGE_MODESTE_CODE;
        } elseif (
            $revenuFiscal > $ANAHCriterePlafondModeste
            && $revenuFiscal <= $ANAHCriterePlafondIntermediaire
        ) {
            return ANAHCritere::TYPE_MENAGE_INTERMEDIAIRE_CODE;
        } elseif ($revenuFiscal > $ANAHCriterePlafondIntermediaire) {
            return ANAHCritere::TYPE_MENAGE_SUPERIEUR_CODE;
        }
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/
}
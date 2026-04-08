<?php

namespace App\Service;

use App\Utils\DefaultUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Remboursement_statut;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux_devis;
use App\Entity\Logement;
use App\Repository\BeneficiaireRepository;
use App\Repository\Demande_Repository;
use App\Repository\LogementRepository;
use Twig\Environment;


class LogementService
{
    /**
     * @var AdminCoordonneeService
     */
    private $adminCoordonneeService;

    /**
     * @var DemandeServiceFO
     */
    private $demandeService;

    /**
     * @var DemandeServiceBO
     */
    private $demandeServiceBO;

    /**
     * @var EntityManagerInterface
     */
    private $EM = null;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var Environment
     */
    private $environment;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var BeneficiaireRepository
     */
    private $beneficiaireRepository;

    /**
     * @var Demande_Repository
     */
    private $demande_Repository;

    /**
     * @var LogementRepository
     */
    private $logementRepository;

    /**
     * Departements acceptés (ceux de Normandie)
     * @var array
     */
    private static $departementsOk = [
        14,
        27,
        50,
        61,
        76
    ];

    /**
     * @var array
     */
    public static $motifList = [
        self::MOTIF_ADRESSE_HORS_NORMANDIE => 'Le logement concerné n\'est pas situé sur le  territoire normand.',
        self::MOTIF_EST_LOCATAIRE => 'Vous êtes locataire.',
        self::MOTIF_HABITATION_MOINS_DE_15_ANS => 'Votre habitation a moins de 15 ans.',
        self::MOTIF_EST_COPROPRIETAIRE => 'Vous êtes co-propriétaire.',
        self::MOTIF_EST_DOUBLON => 'Une fiche pour ce logement a déjà été créée.',
    ];

    const STATUT_ELIGIBLE = '1 | eligible';
    const STATUT_REFUSE = '2 | refuse';

    const MOTIF_ADRESSE_HORS_NORMANDIE = 0;
    const MOTIF_EST_LOCATAIRE = 1;
    const MOTIF_HABITATION_MOINS_DE_15_ANS = 2;
    const MOTIF_EST_COPROPRIETAIRE = 3;
    const MOTIF_EST_DOUBLON = 4;


    public function __construct(
        AdminCoordonneeService $adminCoordonneeService,
        DemandeServiceFO       $demandeServiceFO,
        DemandeServiceBO       $demandeServiceBO,
        EntityManagerInterface $EM,
        UserService            $userService,
        Environment            $environment,
        MailerService          $mailerService
    ) {
        $this->adminCoordonneeService = $adminCoordonneeService;
        $this->demandeService = $demandeServiceFO;
        $this->demandeServiceBO = $demandeServiceBO;
        $this->EM = $EM;
        $this->userService = $userService;
        $this->environment = $environment;
        $this->mailerService = $mailerService;
        $this->beneficiaireRepository = $this->EM->getRepository(Beneficiaire::class);
        $this->demande_Repository = $this->EM->getRepository(Demande_::class);
        $this->logementRepository = $this->EM->getRepository(Logement::class);
    }



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $statut
     * @param $motif
     * @param $beneficiaireEmail
     * @return int
     */
    public function sendEmail(
        $statut,
        $motif,
        $beneficiaireEmail
    ) {
        if (!$beneficiaireEmail) {
            return 0;
        }

        $statutKey = DefaultUtils::getKey($statut);
        $data = $this->getEmailData($statutKey);
        $viewFilename = $data['viewFilename'];
        $subject = $data['subject'];
        $templatePath = 'FrontOffice/Logement/email/' . $viewFilename;
        $motifLabelArray = [];

        if ($motif) {
            $motifLabelArray = array_map(function ($constantMotif) {
                return self::$motifList[$constantMotif];
            }, $motif);
        }
        $templateData = $motifLabelArray ? ['motifLabelArray' => $motifLabelArray] : [];
        $body = $this->environment->render($templatePath, $templateData);

        $nbSent = $this->mailerService->sendGeneriqueEmail(
            $subject,
            $body,
            null,
            $beneficiaireEmail,
            null,
            'text/html',
            'UTF-8'
        );

        return $nbSent;
    }

    /**
     * @param $beneficiaireNom
     * @param $beneficiairePrenom
     * @param $logementNumeroRue
     * @param $logementComplementRue
     * @param $logementAdresse
     * @param $logementCodePostal
     * @param $logementVille
     * @param $situation
     * @param $typeLogement
     * @param $anneeConstruction
     * @param null $logementId
     * @return array
     * @throws Exception
     */
    public function findStatutMotif(
        $beneficiaireNom,
        $beneficiairePrenom,
        $logementNumeroRue,
        $logementComplementRue,
        $logementAdresse,
        $logementCodePostal,
        $logementVille,
        $situation,
        $typeLogement,
        $anneeConstruction,
        $logementId = null
    ) {

        $key = null;
        $statut = self::STATUT_ELIGIBLE;
        $motif = [];

        /* /////////////////////////////////////////////////////////////////
                             CREATE DUPLICATE KEY
        ///////////////////////////////////////////////////////////////// */
        $key = $beneficiaireNom . $beneficiairePrenom
            . $logementNumeroRue . $logementComplementRue . $logementAdresse . $logementCodePostal . $logementVille;
        $key = DefaultUtils::formatString($key, $charset = 'utf-8');
        $key = preg_replace('/\s/', '', $key);

        /* /////////////////////////////////////////////////////////////////
                             CHECK IF LOGEMENT IS DUPLICATE
        ///////////////////////////////////////////////////////////////// */
        $rowDoublon = $this->logementRepository->searchDuplicate($key, $logementId);
        $isDuplicateKey = count($rowDoublon) > 0;

        // check_1 : adresse située ou non hors Normandie
        $codePostal = trim($logementCodePostal);
        $departement = substr($codePostal, 0, 2);
        if (!in_array($departement, self::$departementsOk)) {
            $statut = self::STATUT_REFUSE;
            $motif[] = self::MOTIF_ADRESSE_HORS_NORMANDIE;
        }

        // check_2 : locataire
        if ('2' == DefaultUtils::getKey($situation)) {
            $statut = self::STATUT_REFUSE;
            $motif[] = self::MOTIF_EST_LOCATAIRE;
        }

        // check_3 : habitation moins de 15 ans
        if ('2' == DefaultUtils::getKey($anneeConstruction)) {
            $statut = self::STATUT_REFUSE;
            $motif[] = self::MOTIF_HABITATION_MOINS_DE_15_ANS;
        }

        // check_4 : habitation en copropriété
        if ('1' == DefaultUtils::getKey($typeLogement)) {
            $statut = self::STATUT_REFUSE;
            $motif[] = self::MOTIF_EST_COPROPRIETAIRE;
        }

        if ($isDuplicateKey === true) {
            $statut = self::STATUT_REFUSE;
            $motif[] = self::MOTIF_EST_DOUBLON;
        }

        return [
            'duplicateKey' => $key,
            'logementStatut' => $statut,
            'logementMotif' => $motif,
            'isDuplicateKey' => $isDuplicateKey
        ];
    }

    /**
     * @param $isFrontOffice
     * @param $beneficiaireId
     * @param $userId
     * @return array
     */
    public function getDataForListAction(
        $isFrontOffice,
        $beneficiaireId,
        $userId = null
    ) {
        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */

            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            /**
             * @var Beneficiaire $beneficiaire
             */
            $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);
            $this->userService->checkUserSecurity($userId, $beneficiaire->getUserId());
        }

        $logementServiceData = [
            'motifList' => self::$motifList,
            'statut_eligible' => self::STATUT_ELIGIBLE,
            'statut_refuse' => self::STATUT_REFUSE
        ];

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $list = null;
        if ($beneficiaireId) {
            $list = $this->logementRepository->findBy([
                'beneficiaire_id' => $beneficiaireId
            ]);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET ALL CUSTOM DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $countDemandeEnergie = $this->demandeService->getDataCountDemandeFilteredByLogement($beneficiaireId, Demande_::DEMANDE_AUDIT_ENERGIE_TYPE);
        $countDemandeNumerique = $this->demandeService->getDataCountDemandeFilteredByLogement($beneficiaireId, Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE);
        $countDemandeTravaux = $this->demandeService->getDataCountDemandeFilteredByLogement($beneficiaireId, Demande_::DEMANDE_TRAVAUX_TYPE);
        $countDemandeEnergieRegion = $this->demandeService->getDataCountDemandeFilteredByLogement($beneficiaireId, Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE);
        $countDemandeMiseAJourEnergie = $this->demandeService->getDataCountDemandeFilteredByLogement($beneficiaireId, Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE);

        return [
            'list_logement' => $list,
            'beneficiaireId' => $beneficiaireId,
            'countDemandeEnergie' => $countDemandeEnergie,
            'countDemandeNumerique' => $countDemandeNumerique,
            'countDemandeTravaux' => $countDemandeTravaux,
            'countDemandeEnergieRegion' => $countDemandeEnergieRegion,
            'countDemandeMiseAJourEnergie' => $countDemandeMiseAJourEnergie,
            'logementServiceData' => $logementServiceData,
            'isShowDemandeCreateAction' => !empty($this->demandeServiceBO->checkIsOkDemandeCreateActionByDate())
        ];
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param $beneficiaireId
     * @param $userId
     * @return array
     * @throws Exception
     */
    public function getDataForAddAction(
        Request $request,
        $isFrontOffice,
        $beneficiaireId,
        $userId = null
    ) {

        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'logement' => null,
            'beneficiaire' => null
        ];

        /**
         * @var Beneficiaire $beneficiaire
         */
        $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */

            /* *****************************************************************
                        S E C U R I T Y   R E T O U R   A R R I E R E
            ***************************************************************** */
            if (true == $request->getSession()->get('timestamp_logement')) {
                return DefaultUtils::getDataRedirectLogementListFO($beneficiaireId);
            }

            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            $this->userService->checkUserSecurity($userId, $beneficiaire->getUserId());
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = new Logement();

        $logement->setCodePostal($beneficiaire->getCodePostal());
        $logement->setNumeroRue($beneficiaire->getNumeroRue());
        $logement->setComplementRue($beneficiaire->getComplementNumeroRue());
        $logement->setINSEE($beneficiaire->getINSEE());
        $logement->setAdresse($beneficiaire->getNomRue());
        $logement->setVille($beneficiaire->getVille());
        $logement->setVilleId($beneficiaire->getVilleId());
        $logement->setComplement1($beneficiaire->getComplement1());
        $logement->setComplement2($beneficiaire->getComplement2());

        /* /////////////////////////////////////////////////////////////////
                            GET DATA FORM ANNEE CONSTRUCTION
        ///////////////////////////////////////////////////////////////// */
        $anneeConstruction = $this->logementRepository->searchAnneeConstruction();

        $array_anneeConstruction = [];
        foreach ($anneeConstruction as $item) {
            $array_anneeConstruction[$item['slug']] = $item['id'] . ' | ' . $item['annee'];
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $returnData['formOption'] = [
            'optionAnneeConstruction' => $array_anneeConstruction
        ];

        $returnData['logement'] = $logement;
        $returnData['beneficiaire'] = $beneficiaire;

        return $returnData;
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param Logement $logement
     * @param Beneficiaire $beneficiaire
     * @return array
     */
    public function manageAndGetDataForAddActionSubmitted(
        Request      $request,
        $isFrontOffice,
        Logement     $logement,
        Beneficiaire $beneficiaire
    ) {

        $logement->setBeneficiaireId($beneficiaire->getId());

        $logementData = $this->findStatutMotif(
            $beneficiaire->getNom(),
            $beneficiaire->getPrenom(),
            $logement->getNumeroRue(),
            $logement->getComplementRue(),
            $logement->getAdresse(),
            $logement->getCodePostal(),
            $logement->getVille(),
            $logement->getSituation(),
            $logement->getTypeLogement(),
            $logement->getAnneeConstruction(),
            null
        );

        $prefixFlashBag = ($isFrontOffice) ? 'Votre' : 'Le';
        if (true === $logementData['isDuplicateKey']) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                $prefixFlashBag . ' logement existe déjà.'
            );
        } else {
            /* /////////////////////////////////////////////////////////////////
                    SET DUPLICATE KEY, LOGEMENT STATUT AND MOTIF
            ///////////////////////////////////////////////////////////////// */
            $logement->setDuplicateKey($logementData['duplicateKey']);
            $logement->setStatut($logementData['logementStatut']);
            $logement->setMotif($logementData['logementMotif']);

            $this->EM->persist($logement);
            $this->EM->flush();

            /* /////////////////////////////////////////////////////////////////
                                    SEND EMAIL STATUS
            ///////////////////////////////////////////////////////////////// */
            // Send email for Beneficiaire with texte Logement OK => Eligible aide régionale OR
            // Send email for Beneficiaire with texte Logement NOT OK => Refus aide régionale
            if ($beneficiaire->getEmail()) {
                $this->sendEmail(
                    $logement->getStatut(),
                    $logement->getMotif(),
                    $beneficiaire->getEmail()
                );
            }

            /* /////////////////////////////////////////////////////////////////
                                    SET LOGEMENT COORDONNEE
            ///////////////////////////////////////////////////////////////// */
            $this->adminCoordonneeService->createCoordonnee($logement->getId(), AdminCoordonneeService::TYPE_LOGEMENT_CODE);

            $request->getSession()->getFlashBag()->add(
                'success',
                $prefixFlashBag . ' logement ' . $logement->getNom() . ' a bien été créé.'
            );
        }

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            $request->getSession()->set('timestamp_logement', true);

            return DefaultUtils::getDataRedirectLogementListFO($beneficiaire->getId());
        } else {
            return DefaultUtils::getDataRedirectConseillerLogementListBO($beneficiaire->getId());
        }
    }

    /**
     * @param $logementId
     * @return array
     * @throws Exception
     */
    public function getDataForViewAction($logementId)
    {
        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->logementRepository->find($logementId);

        $nombreDemandeLogementForEditDenied = $this->demande_Repository->findCountByBeneficiaireAndLogementForEditDenied(
            $logement->getBeneficiaireId(),
            $logementId
        );
        $isEditLogement = empty($nombreDemandeLogementForEditDenied);

        $nombreDemandeLogementForDeleteDenied = $this->demande_Repository->findCountByBeneficiaireAndLogementForDeleteDenied(
            $logement->getBeneficiaireId(),
            $logementId
        );
        $isDeleteLogement = empty($nombreDemandeLogementForDeleteDenied);

        return [
            'logement' => $logement,
            'isEditLogement' => $isEditLogement,
            'isDeleteLogement' => $isDeleteLogement
        ];
    }

    /**
     * @param $isFrontOffice
     * @param $logementId
     * @param $userId
     * @return array
     * @throws Exception
     */
    public function getDataForEditAction(
        $isFrontOffice,
        $logementId,
        $userId = null
    ) {

        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'logement' => null,
            'beneficiaire' => null
        ];

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->logementRepository->find($logementId);

        /* *****************************************************************
                             S E C U R I T Y   L O G E M E N T
        ***************************************************************** */
        $this->demandeService->checkEditLogementBeneficiaire($logement->getBeneficiaireId(), $logementId);

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaire = $this->beneficiaireRepository->find($logement->getBeneficiaireId());

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */

            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            $this->userService->checkUserSecurity($userId, $beneficiaire->getUserId());
        }

        /* /////////////////////////////////////////////////////////////////
                            GET DATA FORM ANNEE CONSTRUCTION
        ///////////////////////////////////////////////////////////////// */
        $anneeConstruction = $this->logementRepository->searchAnneeConstruction();

        $array_anneeConstruction = [];
        foreach ($anneeConstruction as $item) {
            $array_anneeConstruction[$item['slug']] = $item['id'] . ' | ' . $item['annee'];
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $returnData['formOption'] = [
            'optionAnneeConstruction' => $array_anneeConstruction
        ];

        $returnData['logement'] = $logement;
        $returnData['beneficiaire'] = $beneficiaire;

        return $returnData;
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param Logement $logement
     * @param Beneficiaire $beneficiaire
     * @return array
     * @throws Exception
     */
    public function manageAndGetDataForEditActionSubmitted(
        Request      $request,
        $isFrontOffice,
        Logement     $logement,
        Beneficiaire $beneficiaire
    ) {

        $logementData = $this->findStatutMotif(
            $beneficiaire->getNom(),
            $beneficiaire->getPrenom(),
            $logement->getNumeroRue(),
            $logement->getComplementRue(),
            $logement->getAdresse(),
            $logement->getCodePostal(),
            $logement->getVille(),
            $logement->getSituation(),
            $logement->getTypeLogement(),
            $logement->getAnneeConstruction(),
            $logement->getId()
        );

        $prefixFlashBag = ($isFrontOffice) ? 'Votre' : 'Le';
        if (true === $logementData['isDuplicateKey']) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                $prefixFlashBag . ' logement existe déjà.'
            );
        } else {
            $logement->setDateModif(new \Datetime());
            $logement->setAuteurModif($_SESSION['login']->getUsername());

            /* /////////////////////////////////////////////////////////////////
                        SET DUPLICATE KEY, LOGEMENT STATUT AND MOTIF
            ///////////////////////////////////////////////////////////////// */
            $logement->setDuplicateKey($logementData['duplicateKey']);
            $logement->setStatut($logementData['logementStatut']);
            $logement->setMotif($logementData['logementMotif']);

            $this->EM->persist($logement);
            $this->EM->flush();

            /* /////////////////////////////////////////////////////////////////
                                SEND EMAIL STATUS
            ///////////////////////////////////////////////////////////////// */
            // Send email for Beneficiaire with texte Logement OK => Elligible aide régionale OR
            // Send email for Beneficiaire with texte Logement KO => Refus aide régionale
            if ($beneficiaire->getEmail()) {
                $this->sendEmail(
                    $logement->getStatut(),
                    $logement->getMotif(),
                    $beneficiaire->getEmail()
                );
            }

            /* /////////////////////////////////////////////////////////////////
                                    SET LOGEMENT COORDONNEE
            ///////////////////////////////////////////////////////////////// */
            $this->adminCoordonneeService->createCoordonnee($logement->getId(), AdminCoordonneeService::TYPE_LOGEMENT_CODE);

            /* /////////////////////////////////////////////////////////////////
                        CLEAN INSTRUCTION ADMINISTRATIVE / TECHNIQUE
            ///////////////////////////////////////////////////////////////// */
            $demandeIdList = $this->demande_Repository->findByBeneficiaireAndLogementForEditActionSubmitted(
                $beneficiaire->getId(),
                $logement->getId()
            );
            $this->demandeService->cleanInstructionRestoreStatutAndHistorise($demandeIdList, $beneficiaire, $logement);

            $request->getSession()->getFlashBag()->add(
                'success',
                $prefixFlashBag . ' Logement ' . $logement->getNom() . ' a bien été modifié.'
            );
        }

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            return DefaultUtils::getDataRedirectLogementListFO($logement->getBeneficiaireId());
        } else {
            return DefaultUtils::getDataRedirectConseillerLogementListBO($beneficiaire->getId());
        }
    }

    /**
     * @param $dataForListAction
     * @return array
     */
    public function getDataForDemandeAction($dataForListAction)
    {
        $dataForDemandeActionByLogement = [];

        /* /////////////////////////////////////////////////////////////////
                           PARCOURS DES LOGEMENTS
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var Logement $logement
         */
        foreach ($dataForListAction['list_logement'] as $logement) {

            $logementId = $logement->getId();

            $dataForDemandeActionByLogement[$logementId] = [
                'logementStatutIsEligible' => false,
                'logementStatutIsRefuse' => false,
                'demandeAuditERegionExistanteIsMsg' => false,
                'demandeAuditERegionIsAddAction' => false,
                'demandeAuditERegionDemandeAuditEEnCoursIsMsg' => false,
                'demandeAuditERegionDemandeTravauxEnCoursIsMsg' => false,
                'demandeMiseAJourAuditEIsAddAction' => false,
                'demandeMiseAJourAuditEExistanteIsMsg' => false,
                'demandeTravauxExistanteIsMsg' => false,
                'demandeTravauxIsAddAction' => false,
                'demandeTravauxDemandeAuditEAttenteDateRemboursementIsMsg' => false,
            ];

            /* /////////////////////////////////////////////////////////////////
                                    AUDIT ENERGETIQUE
            ///////////////////////////////////////////////////////////////// */
            $nombreDemandeAuditE = 0;
            $nombreDateCPAuditE = 0;
            $statutAuditE = null;
            $statutRemboursementAuditE = null;
            $partenaireStatutEnabledAuditE = null;

            if (!empty($dataForListAction['countDemandeEnergie'][$logementId])) {
                $auditELogement = $dataForListAction['countDemandeEnergie'][$logementId];
                $arrayAuditE = explode('|', $auditELogement);

                if (!empty($arrayAuditE[1])) {
                    $nombreDateCPAuditE += 1;
                }
                if (!empty($arrayAuditE[2])) {
                    $statutAuditE = $arrayAuditE[2];
                }
                if (!empty($arrayAuditE[3])) {
                    $statutRemboursementAuditE = $arrayAuditE[3];
                }
                if (!empty($arrayAuditE[4])) {
                    $partenaireStatutEnabledAuditE = $arrayAuditE[4];
                }

                if (
                    empty($statutRemboursementAuditE)
                    || (!empty($statutRemboursementAuditE) && Remboursement_statut::STATUS_20 != $statutRemboursementAuditE)
                ) {
                    // Si le remoursement statut de la demandeAuditE est à "refusé", on n'increment pas le compteur dédié
                    // car il faut que l'on puisse créer alors une demande audit energetique Région
                    $nombreDemandeAuditE += 1;
                }
            }

            /* /////////////////////////////////////////////////////////////////
                                    AUDIT NUMERIQUE
            ///////////////////////////////////////////////////////////////// */
            $nombreDemandeAuditN = 0;
            if (!empty($dataForListAction['countDemandeNumerique'][$logementId])) {
                $nombreDemandeAuditN += 1;
            }

            /* /////////////////////////////////////////////////////////////////
                                        TRAVAUX
            ///////////////////////////////////////////////////////////////// */
            $nombreDemandeTravaux = 0;
            if (!empty($dataForListAction['countDemandeTravaux'][$logementId])) {
                $travauxLogement = $dataForListAction['countDemandeTravaux'][$logementId];
                $arrayTravaux = explode('|', $travauxLogement);

                // CAS OU L'ON INCREMENT LE NOMBRE DE DEMANDE TRAVAUX
                // POUR EVITER D'AFFICHER LE LIEN POUR REFAIRE UNE DEMANDE TRAVAUX (lorsque $nombreDemandeTravaux > 0)
                if (
                    (Remboursement_statut::STATUS_20 != $arrayTravaux[3] && Remboursement_statut::STATUS_22 != $arrayTravaux[3])
                    || (Remboursement_statut::STATUS_22 == $arrayTravaux[3]
                        && ($arrayTravaux[5] != Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_WITHOUT_SEPARATOR_VALUE)
                    )
                ) {
                    $nombreDemandeTravaux += 1;
                }
            }

            /* /////////////////////////////////////////////////////////////////
                                    AUDIT ENERGETIQUE REGION
            ///////////////////////////////////////////////////////////////// */
            $nombreDemandeAuditERegion = 0;
            $nombreDateCPAuditERegion = 0;
            $statutAuditERegion = null;
            $statutRemboursementAuditERegion = null;

            if (!empty($dataForListAction['countDemandeEnergieRegion'][$logementId])) {
                $auditERegionLogement = $dataForListAction['countDemandeEnergieRegion'][$logementId];
                $arrayAuditERegion = explode('|', $auditERegionLogement);

                $nombreDemandeAuditERegion += 1;

                if (!empty($arrayAuditERegion[1])) {
                    $nombreDateCPAuditERegion += 1;
                }
                if (!empty($arrayAuditERegion[2])) {
                    $statutAuditERegion = $arrayAuditERegion[2];
                }
                if (!empty($arrayAuditERegion[3])) {
                    $statutRemboursementAuditERegion = $arrayAuditERegion[3];
                }
            }

            /* /////////////////////////////////////////////////////////////////
                                    MISE A JOUR AUDIT ENERGETIQUE
            ///////////////////////////////////////////////////////////////// */
            $nombreDemandeMiseAJourAuditE = 0;
            $statutRemboursementMiseAJourAuditE = null;

            if (!empty($dataForListAction['countDemandeMiseAJourEnergie'][$logementId])) {
                $miseAJourAuditELogement = $dataForListAction['countDemandeMiseAJourEnergie'][$logementId];
                $arrayMiseAJourAuditE = explode('|', $miseAJourAuditELogement);

                $nombreDemandeMiseAJourAuditE += 1;

                if (!empty($arrayMiseAJourAuditE[2])) {
                    $statutMiseAJourAuditE = $arrayMiseAJourAuditE[2];
                }

                if (!empty($arrayMiseAJourAuditE[3])) {
                    $statutRemboursementMiseAJourAuditE = $arrayMiseAJourAuditE[3];
                }
            }

            if (explode(' | ', $logement->getStatut())[0] == explode(' | ', $dataForListAction['logementServiceData']['statut_eligible'])[0]) {
                $dataForDemandeActionByLogement[$logementId]['logementStatutIsEligible'] = true;
            } elseif (
                explode(' | ', $logement->getStatut())[0] == explode(' | ', $dataForListAction['logementServiceData']['statut_refuse'])[0]
                && !empty($logement->getMotif())
            ) {
                $dataForDemandeActionByLogement[$logementId]['logementStatutIsRefuse'] = true;
            }

            if (!empty($dataForListAction['isShowDemandeCreateAction'])) {
                if (!empty($dataForDemandeActionByLogement[$logementId]['logementStatutIsEligible'])) {

                    //                   init affichage lien ajout "Demande auditE Region Normandie" et demande "mise à jour audit energetique et scenarios"
                    //                   suivant auditeur demande "audit energetique et scenarios" actif/inatif

                    $isAddDemandeAuditERegionByDemandeMiseAJourAuditE = false;
                    $isAddDemandeMiseAJourAuditE = false;

                    if (
                        0 == $nombreDemandeMiseAJourAuditE
                        && $nombreDemandeAuditE > 0
                        && Remboursement_statut::STATUS_22 == $statutRemboursementAuditE
                    ) {
                        if ('0' == $partenaireStatutEnabledAuditE) {
                            $isAddDemandeAuditERegionByDemandeMiseAJourAuditE = true;
                        } elseif ('1' == $partenaireStatutEnabledAuditE) {
                            $isAddDemandeMiseAJourAuditE = true;
                        }
                    }

                    /* /////////////////////////////////////////////////////////////////
                            Règles Audit énergétique Région Normandie / Numérique
                    ///////////////////////////////////////////////////////////////// */
                    if ($nombreDemandeAuditERegion > 0) {
                        $dataForDemandeActionByLogement[$logementId]['demandeAuditERegionExistanteIsMsg'] = true;
                    } else {
                        if (
                            (0 == $nombreDemandeAuditE && 0 == $nombreDemandeTravaux)
                            || (true == $isAddDemandeAuditERegionByDemandeMiseAJourAuditE)
                        ) {
                            $dataForDemandeActionByLogement[$logementId]['demandeAuditERegionIsAddAction'] = true;
                        } else if ($nombreDemandeAuditE > 0) {
                            $dataForDemandeActionByLogement[$logementId]['demandeAuditERegionDemandeAuditEEnCoursIsMsg'] = true;
                        } elseif ($nombreDemandeTravaux > 0) {
                            $dataForDemandeActionByLogement[$logementId]['demandeAuditERegionDemandeTravauxEnCoursIsMsg'] = true;
                        }
                    }

                    /* /////////////////////////////////////////////////////////////////////////////////////
                       Règles Mise à jour Audit énergétique et scénarios / Audit énergétique et scénarios
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    if (true == $isAddDemandeMiseAJourAuditE) {
                        $dataForDemandeActionByLogement[$logementId]['demandeMiseAJourAuditEIsAddAction'] = true;
                    } elseif ($nombreDemandeMiseAJourAuditE > 0) {
                        $dataForDemandeActionByLogement[$logementId]['demandeMiseAJourAuditEExistanteIsMsg'] = true;
                    }

                    /* /////////////////////////////////////////////////////////////////
                                            Règles Travaux
                    ///////////////////////////////////////////////////////////////// */
                    if ($nombreDemandeTravaux > 0) {
                        $dataForDemandeActionByLogement[$logementId]['demandeTravauxExistanteIsMsg'] = true;
                    } else {
                        $statutsForCreateDemandeTravaux = [
                            Demande_statut::STATUS_8,
                            Demande_statut::STATUS_11,
                            Demande_statut::STATUS_12,
                            Demande_statut::STATUS_13,
                            Demande_statut::STATUS_14
                        ];

                        if (
                            (0 == $nombreDemandeAuditERegion || ($nombreDemandeAuditERegion && in_array($statutAuditERegion, $statutsForCreateDemandeTravaux)))
                            && (0 == $nombreDemandeMiseAJourAuditE || ($nombreDemandeMiseAJourAuditE && in_array($statutMiseAJourAuditE, $statutsForCreateDemandeTravaux)))
                        ) {
                            $dataForDemandeActionByLogement[$logementId]['demandeTravauxIsAddAction'] = true;
                        } else {
                            $dataForDemandeActionByLogement[$logementId]['demandeTravauxDemandeAuditEAttenteDateRemboursementIsMsg'] = true;
                        }
                    }
                }
            }
        }
        /* /////////////////////////////////////////////////////////////////
                        FIN PARCOURS DES LOGEMENTS
        ///////////////////////////////////////////////////////////////// */

        return $dataForDemandeActionByLogement;
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $statut
     * @return array
     */
    private function getEmailData($statut)
    {
        $data = [
            'viewFilename' => null,
            'subject' => null
        ];

        switch ($statut) {
            case '1':
                $data['viewFilename'] = 'statut_eligible.html.twig';
                $data['subject'] = 'Région Normandie - Demande d\'inscription "Chèque éco-énergie" enregistrée.';
                break;

            case '2':
                $data['viewFilename'] = 'statut_refuse.html.twig';
                $data['subject'] = 'Région Normandie - Demande d\'inscription "Chèque éco-énergie" refusée.';
                break;

            default:
                break;
        }

        return $data;
    }
}

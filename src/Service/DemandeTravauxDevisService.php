<?php

namespace App\Service;

use App\Entity\Demande_travaux_devis_upload;
use App\Utils\DefaultUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Entity\Instruction_;
use App\Entity\Remboursement_;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux_devis;
use App\Entity\Logement;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;


class DemandeTravauxDevisService extends DemandeServiceFO
{
    private UserService $userService;
    private string $projectDataPath;
    private string $productionTravauxNiveauBBC2;

    public function __construct(
        ANAHService            $ANAHService,
        EntityManagerInterface $entityManager,
        DemandeServiceBO       $demandeServiceBO,
        HistoriqueService      $historiqueService,
        RemboursementService   $remboursementService,
        TitreService           $titreService,
        TokenStorageInterface  $tokenStorage,
        ParameterBagInterface  $parameterBag,
        Environment            $environment,
        MailerService          $mailerService,
        UserService            $userService,
        string                 $projectDataPath,
        string                 $productionTravauxNiveauBBC2
    )
    {
        parent::__construct(
            $ANAHService,
            $entityManager,
            $demandeServiceBO,
            $historiqueService,
            $remboursementService,
            $titreService,
            $tokenStorage,
            $parameterBag,
            $environment,
            $mailerService
        );
        $this->userService = $userService;
        $this->projectDataPath = $projectDataPath;
        $this->productionTravauxNiveauBBC2 = $productionTravauxNiveauBBC2;
    }


    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param Request $request
     * @param bool $isFrontOffice
     * @param string $beneficiaireId
     * @param string $logementId
     * @param string $demandeId
     * @param int|null $userId
     * @return array
     * @throws NonUniqueResultException
     */
    public function getDataForAddAction(
        Request $request,
        bool    $isFrontOffice,
        string  $beneficiaireId,
        string  $logementId,
        string  $demandeId,
        ?int    $userId = null
    ): array
    {
        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'beneficiaire' => null,
            'logement' => null,
            'demandeAuditE' => null,
            'devis' => null,
            'demandeTravaux' => null,
            'instruction' => null,
            'remboursement' => null,
            'auditeur' => null,
            'informationANAH' => null
        ];

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            $this->userService->checkUserSecurity($userId, $beneficiaire->getUserId());
        }

        /* *****************************************************************
                    S E C U R I T Y   R E T O U R   A R R I E R E
        ***************************************************************** */
        if (true == $request->getSession()->get($logementId . 'timestamp_3')) {
            if ($isFrontOffice) {
                /* /////////////////////////////////////////////////////////////////
                                        COTE FRONT OFFICE
                ///////////////////////////////////////////////////////////////// */
                return DefaultUtils::getDataRedirectDemandeListFO($beneficiaireId);
            } else {
                return DefaultUtils::getDataRedirectConseillerDemandeListBO($beneficiaireId);
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->logementRepository->find($logementId);

        /* /////////////////////////////////////////////////////////////////
                        GET DEMANDE TRAVAUX / AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var Demande_ $demandeTravaux
         */
        $demandeTravaux = $this->demande_Repository->findOneBy([
            'logement_id' => $logementId,
            'type' => Demande_::DEMANDE_TRAVAUX_TYPE
        ],
            [
                'id' => 'DESC'
            ]
        );

        /**
         * @var Demande_ $demandeAuditEAuditERegion
         */
        $demandeAuditEAuditERegion = $this->demande_Repository->findOneBy([
            'logement_id' => $logementId,
            'type' => [Demande_::DEMANDE_AUDIT_ENERGIE_TYPE, Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE]
        ],
            [
                'id' => 'DESC'
            ]
        );
        $demandeAuditEAuditERegion = (!empty($demandeAuditEAuditERegion) && $demandeAuditEAuditERegion->getStatutId() != Demande_statut::STATUS_15) ? $demandeAuditEAuditERegion : null;

        /* /////////////////////////////////////////////////////////////////
                               GET REMBOURSEMENT
       ///////////////////////////////////////////////////////////////// */
        $remboursement = null;
        if (!empty($demandeAuditEAuditERegion)) {
            $remboursement = $this->remboursement_Repository->findOneBy([
                'demande_id' => $demandeAuditEAuditERegion->getId()
            ]);
        }

        /* /////////////////////////////////////////////////////////////////
                        GET AUDITEUR FROM AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
        if (!empty($demandeAuditEAuditERegion)) {
            $auditeur = $this->partenaire_Repository->find($demandeAuditEAuditERegion->getDemandeAuditEnergie()->getAuditeurId());
        } else {
            $auditeur = null;
        }

        /* /////////////////////////////////////////////////////////////////
                        GET INSTRUCTION DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var Instruction_ $instruction
         */
        $instruction = $this->instruction_Repository->findOneBy([
                'demande_id' => $demandeId
            ]
        );

        /* /////////////////////////////////////////////////////////////////
                        CALCUL REVENU FISCAL DE REFERENCE
        ///////////////////////////////////////////////////////////////// */
        $demandeTravaux_nbPersFoyer = $demandeTravaux->getDemandeTravaux()->getNbPersFoyer();
        $demandeTravaux_revenuReference = $demandeTravaux->getDemandeTravaux()->getRevenu3();

        $ANAH = $this->ANAHService->findPlafond($demandeTravaux_nbPersFoyer);

        $informationANAH = '';
        if ($demandeTravaux_revenuReference < $ANAH) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_INFERIEUR_ANAH_KEY;
        } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH * 2)) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_1_ET_2_FOIS_ANAH_KEY;
        } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH * 4)) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_2_ET_4_FOIS_ANAH_KEY;
        }

        $isDemandeTravauxAudit = (!empty($demandeTravaux->getDemandeTravaux()) && $demandeTravaux->getDemandeTravaux()->getAudit()) ? true : false;

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $devis = new Demande_travaux_devis();

        $rowLastDemandeTravauxDevisRemboursementTermine = $this->demande_Repository->findLastDemandeTravauxDevisRemboursementTermine($beneficiaireId, $logementId);
        $isLastDemandeTravauxRembourseSortieDePassoire = (!empty($rowLastDemandeTravauxDevisRemboursementTermine) && (Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE == $rowLastDemandeTravauxDevisRemboursementTermine['demandeTravauxDevisNiveau']));
        if (true === $isLastDemandeTravauxRembourseSortieDePassoire) {
            // On preselectionne niveau Renovation globale BBC (en creation)
            $devis->setNiveau(Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE);
        }

        $formOption['isShowDevisCustomColumns'] = false;  // isShowDevisCustomColumns (Biosource, ...)
        $formOption['ecoPTZBanque'] = $devis->getEcoPTZBanque();
        $formOption['autrePretBanque'] = $devis->getAutrePretBanque();
        $formOption['isDemandeTravauxAudit'] = $isDemandeTravauxAudit;
        $formOption['informationANAH'] = $informationANAH;
        // Is required niveau field Option
        $formOption['isRequiredNiveauFieldOption'] = false;
        $formOption['typeMaPrimeRenovNom'] = $devis->getTypeMaPrimeRenovNom();
//        $formOption['typeMaPrimeRenovSereniteNom'] = $devis->getTypeMaPrimeRenovSereniteNom();
        $formOption['isBanqueAccess'] = ($devis->getId() && !empty($devis->getIsBanqueAccess())) ? 1 : 0;
        $formOption['auditAlt'] = $devis->getAuditAlt();
        $formOption['niveau'] = $devis->getNiveau();
        $formOption['isLastDemandeTravauxRembourseSortieDePassoire'] = $isLastDemandeTravauxRembourseSortieDePassoire;

        /* /////////////////////////////////////////////////////////////////////////////////////
            RECUPERATION DU MONTANT Travaux Niveau3 BBC (Aide region)
        ///////////////////////////////////////////////////////////////////////////////////// */
        $montantTravauxNiveau3BBC = $this->titreService->getMontantTravauxNiveau3BBC($demandeAuditEAuditERegion);

        $returnData['formOption'] = $formOption;
        $returnData['beneficiaire'] = $beneficiaire;
        $returnData['logement'] = $logement;
        $returnData['demandeAuditE'] = $demandeAuditEAuditERegion;
        $returnData['demandeTravaux'] = $demandeTravaux;
        $returnData['devis'] = $devis;
        $returnData['instruction'] = $instruction;
        $returnData['remboursement'] = $remboursement;
        $returnData['auditeur'] = $auditeur;
        $returnData['montantTravauxNiveau3BBC'] = $montantTravauxNiveau3BBC;

        return $returnData;
    }

    /**
     * @param Request $request
     * @param bool $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @param Logement $logement
     * @param string $demandeId
     * @param Demande_|null $demandeTravaux
     * @param Demande_travaux_devis $devis
     * @param Instruction_|null $instruction
     * @param Remboursement_|null $remboursement
     * @param array $userRoles
     * @return array
     */
    public function manageAndGetDataForAddActionSubmitted(
        Request               $request,
        bool                  $isFrontOffice,
        Beneficiaire          $beneficiaire,
        Logement              $logement,
        string                $demandeId,
        ?Demande_             $demandeTravaux,
        Demande_travaux_devis $devis,
        Instruction_          $instruction = null,
        Remboursement_        $remboursement = null,
        array                 $userRoles = []
    ): array
    {
        $isDocument = false;

        $devis->setStatutInstruction('0');
        $devis->setBeneficiaireId($beneficiaire->getId());
        $devis->setLogementId($logement->getId());

        /* /////////////////////////////////////////////////////////////////
                                SET DEMANDE STATUS
        ///////////////////////////////////////////////////////////////// */
        if ($instruction) {
            $conformiteJP = explode(" | ", $instruction->getInstructionTravaux()->getJPconformite());
            $conformiteKBIS = explode(" | ", $instruction->getInstructionTravaux()->getKBISconformite());
            $conformiteAI = explode(" | ", $instruction->getInstructionTravaux()->getAIconformite());
        } else {
            $conformiteJP = ['0'];
            $conformiteKBIS = ['0'];
            $conformiteAI = ['0'];
            $instruction = null;
        }

        $instructionDevis = null;
        $ficheTechniqueStatut = null;
        $ficheTechniqueIsValidationConseiller = null;
        if ($demandeTravaux) {
            $demandeTravaux_justificatifPropriete = $demandeTravaux->getDemandeTravaux()->getJustificatifPropriete();
            $demandeTravaux_pieceComplement = $demandeTravaux->getDemandeTravaux()->getPieceComplement();
            $demandeTravaux_avisImposition = $demandeTravaux->getDemandeTravaux()->getAvisImposition();
            $demandeTravaux_justificatifProprieteAlt = $demandeTravaux->getDemandeTravaux()->getJustificatifProprieteAlt();
            $demandeTravaux_pieceComplementAlt = $demandeTravaux->getDemandeTravaux()->getPieceComplementAlt();
            $demandeTravaux_avisImpositionAlt = $demandeTravaux->getDemandeTravaux()->getAvisImpositionAlt();
            //$demandeTravaux_travauxDevisId = $demandeTravaux->getDemandeTravaux()->getTravauxDevisId();
            $demandeTravaux_travauxDevisId = '1';
            $demandeTravaux_audit = $demandeTravaux->getDemandeTravaux()->getAudit();
        } else {
            $demandeTravaux_justificatifPropriete = null;
            $demandeTravaux_pieceComplement = null;
            $demandeTravaux_avisImposition = null;
            $demandeTravaux_justificatifProprieteAlt = null;
            $demandeTravaux_pieceComplementAlt = null;
            $demandeTravaux_avisImpositionAlt = null;
            $demandeTravaux_travauxDevisId = null;
            $demandeTravaux_audit = null;
        }

        $statut = $this->searchStatutForDemandeTravauxAndDevis(
            $conformiteJP[0],
            $conformiteKBIS[0],
            $conformiteAI[0],
            $demandeTravaux_justificatifPropriete,
            $demandeTravaux_pieceComplement,
            $demandeTravaux_avisImposition,
            $demandeTravaux_justificatifProprieteAlt,
            $demandeTravaux_pieceComplementAlt,
            $demandeTravaux_avisImpositionAlt,
            $instruction,
            $demandeTravaux_travauxDevisId,
            $demandeTravaux_audit,
            $ficheTechniqueStatut,
            $beneficiaire->getType(),
            $instructionDevis,
            $ficheTechniqueIsValidationConseiller
        );
        $demandeTravaux->setStatutId($statut);

        /* /////////////////////////////////////////////////////////////////
                                COPY UPLOAD FILE - AUDIT
        ///////////////////////////////////////////////////////////////// */
        $remboursement_auditEnergie_depot = null;
        if ($remboursement && $remboursement->getRemboursementAuditEnergie()) {
            $remboursement_auditEnergie_depot = $remboursement->getRemboursementAuditEnergie()->getDepot();

            if ($remboursement_auditEnergie_depot) {
                $remboursement_auditEnergie_depot_webUrl = $remboursement_auditEnergie_depot->getAuditUrl();
                $remboursement_auditEnergie_depot_webAlt = $remboursement_auditEnergie_depot->getAuditAlt();

                if (!$devis->getAudit() && $remboursement_auditEnergie_depot_webUrl && $remboursement_auditEnergie_depot_webAlt) {
                    $devis->setAuditUrl($remboursement_auditEnergie_depot_webUrl);
                    $devis->setAuditAlt($remboursement_auditEnergie_depot_webAlt);
                    $isDocument = true;
                }
            }
        }

        // Mise à jour du Total devis en parcourant tous les devis montants
        $devis->setTotalDevis($this->findDemandeTravauxDevisTotalDevis($devis));

        $this->EM->persist($devis);
        $this->EM->flush();

        // Set Travaux Devis id dans Demande Travaux
        $demandeTravaux->getDemandeTravaux()->setTravauxDevisId($devis->getId());
        $this->EM->persist($demandeTravaux);
        $this->EM->flush();

        // MISE A JOUR DEMANDE STATUT DESCRIPTION
        $demandeTravaux->setStatutDescription($this->findStatutDescriptionByDemande($demandeTravaux->getId()));
        $this->EM->persist($demandeTravaux);
        $this->EM->flush();

        /* /////////////////////////////////////////////////////////////////
                            COPY UPLOAD FILE - AUDIT
        ///////////////////////////////////////////////////////////////// */
        if ($remboursement && $isDocument && $remboursement_auditEnergie_depot) {
            $devis_audit_path = $this->projectDataPath . $devis->audit_getUploadDir();

            if (!file_exists($devis_audit_path)) {
                mkdir($devis_audit_path, 0755, true);
            }

            copy($this->projectDataPath . $remboursement_auditEnergie_depot->audit_getWebPath(),
                $this->projectDataPath . $devis->audit_getWebPath()
            );
        }

        /* /////////////////////////////////////////////////////////////////
                                FILL UP HISTORIQUE
        ///////////////////////////////////////////////////////////////// */
        $suffixeHistoriqueAction = ($isFrontOffice) ? 'Bénéficiaire' : 'Conseiller';
        $this->historiqueService->save(
            $demandeId,
            $statut,
            Demande_::DEMANDE_TRAVAUX_TYPE,
            $userRoles,
            true,
            'Création demande Travaux Devis par le ' . $suffixeHistoriqueAction,
            $beneficiaire->getEmail(),
            $beneficiaire->getType(),
            $demandeTravaux->getDemandeTravaux()->getJustificatifProprieteAlt(),
            $demandeTravaux->getDemandeTravaux()->getPieceComplementAlt(),
            $demandeTravaux->getDemandeTravaux()->getAvisImpositionAlt()
        );

        $request->getSession()->set($logement->getId() . 'timestamp_3', true);
        $request->getSession()->getFlashBag()->add(
            'success',
            'Votre Travaux Devis a bien été pris en compte.'
        );

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            return DefaultUtils::getDataRedirectDemandeListFO($beneficiaire->getId());
        } else {
            return DefaultUtils::getDataRedirectConseillerDemandeListBO($beneficiaire->getId());
        }
    }

    /**
     * @param bool $isFrontOffice
     * @param string $devisId
     * @param int|null $userId
     * @return array
     * @throws Exception
     */
    public function getDataForViewAction(
        bool   $isFrontOffice,
        string $devisId,
        ?int   $userId = null
    ): array
    {

        /* /////////////////////////////////////////////////////////////////
                                    GET DEVIS
        ///////////////////////////////////////////////////////////////// */
        $rowDevis = $this->demande_travaux_devisRepository->findByIdCustom($devisId);

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            $this->userService->checkUserSecurity($userId, $rowDevis['beneficiaireUserId']);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        $rowDemandeTravaux = $this->demande_travauxRepository->findByIdCustom(
            $rowDevis['demandeId'],
            $this->productionTravauxNiveauBBC2
        );

        /* /////////////////////////////////////////////////////////////////
                        CALCUL REVENU FISCAL DE REFERENCE
        ///////////////////////////////////////////////////////////////// */
        $demandeTravaux_nbPersFoyer = $rowDemandeTravaux['demandeNbPersFoyer'];
        $demandeTravaux_revenuReference = $rowDemandeTravaux['demandeRevenuFoyer'];

        $ANAH = $this->ANAHService->findPlafond($demandeTravaux_nbPersFoyer);

        $informationANAH = '';
        if ($demandeTravaux_revenuReference < $ANAH) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_INFERIEUR_ANAH_KEY;
        } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH * 2)) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_1_ET_2_FOIS_ANAH_KEY;
        } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH * 4)) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_2_ET_4_FOIS_ANAH_KEY;
        }

        $isNotEligible = false;
        if (
            $demandeTravaux_revenuReference
            && $informationANAH == '2'
            && !$rowDemandeTravaux['demandeAudit']
        ) {
            $isNotEligible = true;
        }

        /* /////////////////////////////////////////////////////////////////
                                GET TRAVAUX DEVIS UPLOAD
        ///////////////////////////////////////////////////////////////// */
        $devis_upload = ($rowDevis) ? $this->demande_travaux_devis_uploadRepository->findAllCustomByDevisId($rowDevis['devisId']) : null;

        return [
            'rowDevis' => $rowDevis,
            'rowDemandeTravaux' => $rowDemandeTravaux,
            'devis_upload' => $devis_upload,
            'isNotEligible' => $isNotEligible,
        ];
    }

    /**
     * @param bool $isFrontOffice
     * @param string $devisId
     * @param int|null $userId
     * @return array
     * @throws Exception
     * @throws NonUniqueResultException
     */
    public function getDataForEditAction(
        bool   $isFrontOffice,
        string $devisId,
        ?int   $userId = null
    ): array
    {
        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'beneficiaire' => null,
            'logement' => null,
            'demandeAuditE' => null,
            'devis' => null,
            'demandeTravaux' => null,
            'instruction' => null,
            'auditeur' => null,
            'informationANAH' => null
        ];

        /* /////////////////////////////////////////////////////////////////
                                    GET DEVIS
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var Demande_travaux_devis $devis
         */
        $devis = $this->demande_travaux_devisRepository->find($devisId);

        /* *****************************************************************
                            S E C U R I T Y   D E V I S
        ***************************************************************** */
        $rowDateCPStatut = $this->demande_travaux_devisRepository->findDateCPStatutById($devisId);
        if (
            !empty($rowDateCPStatut['dateCPId'])
            || Demande_statut::STATUS_15 == $rowDateCPStatut['statutId']
            || !empty($devis->getInstructionDossierConforme())
        ) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }

        /* /////////////////////////////////////////////////////////////////
                                    GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireId = $devis->getBeneficiaireId();
        $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);

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
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var Logement $logement
         */
        $logement = $this->logementRepository->find($devis->getLogementId());

        /* /////////////////////////////////////////////////////////////////
                        GET DEMANDE TRAVAUX / AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var Demande_ $demandeTravaux
         */
        $demandeTravaux = $this->demande_Repository->findOneBy([
            'logement_id' => $logement->getId(),
            'type' => Demande_::DEMANDE_TRAVAUX_TYPE
        ],
            [
                'id' => 'DESC'
            ]
        );

        /**
         * @var Demande_ $demandeAuditEAuditERegion
         */
        $demandeAuditEAuditERegion = $this->demande_Repository->findOneBy([
            'logement_id' => $logement->getId(),
            'type' => [Demande_::DEMANDE_AUDIT_ENERGIE_TYPE, Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE]
        ],
            [
                'id' => 'DESC'
            ]
        );
        $demandeAuditEAuditERegion = (!empty($demandeAuditEAuditERegion) && $demandeAuditEAuditERegion->getStatutId() != Demande_statut::STATUS_15) ? $demandeAuditEAuditERegion : null;

        /* /////////////////////////////////////////////////////////////////
                        GET AUDITEUR FROM AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
        if ($devis->getAuditeurId()) {
            $auditeur = $this->partenaire_Repository->find($devis->getAuditeurId());
        } else {
            $auditeur = null;
        }

        /* /////////////////////////////////////////////////////////////////
                        GET INSTRUCTION DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var Instruction_ $instruction
         */
        $instruction = $this->instruction_Repository->findOneBy([
            'demande_id' => $demandeTravaux->getId()
        ]);

        /* /////////////////////////////////////////////////////////////////
                        CALCUL REVENU FISCAL DE REFERENCE
        ///////////////////////////////////////////////////////////////// */
        $demandeTravaux_nbPersFoyer = $demandeTravaux->getDemandeTravaux()->getNbPersFoyer();
        $demandeTravaux_revenuReference = $demandeTravaux->getDemandeTravaux()->getRevenu3();

        $ANAH = $this->ANAHService->findPlafond($demandeTravaux_nbPersFoyer);

        $informationANAH = '';
        if ($demandeTravaux_revenuReference < $ANAH) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_INFERIEUR_ANAH_KEY;
        } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH * 2)) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_1_ET_2_FOIS_ANAH_KEY;
        } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH * 4)) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_2_ET_4_FOIS_ANAH_KEY;
        }

        $isDemandeTravauxAudit = (!empty($demandeTravaux->getDemandeTravaux()) && $demandeTravaux->getDemandeTravaux()->getAudit()) ? true : false;
        $rowLastDemandeTravauxDevisRemboursementTermine = $this->demande_Repository->findLastDemandeTravauxDevisRemboursementTermine($beneficiaireId, $logement->getId());
        $isLastDemandeTravauxRembourseSortieDePassoire = (!empty($rowLastDemandeTravauxDevisRemboursementTermine) && (Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE == $rowLastDemandeTravauxDevisRemboursementTermine['demandeTravauxDevisNiveau']));
        if (true === $isLastDemandeTravauxRembourseSortieDePassoire) {
            // On preselectionne niveau Renovation globale BBC (en edition)
            $devis->setNiveau(Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $formOption['isShowDevisCustomColumns'] = false; // isShowDevisCustomColumns (Biosource, ...)
        $formOption['ecoPTZBanque'] = $devis->getEcoPTZBanque();
        $formOption['autrePretBanque'] = $devis->getAutrePretBanque();
        $formOption['isDemandeTravauxAudit'] = $isDemandeTravauxAudit;
        $formOption['informationANAH'] = $informationANAH;
        // Is required niveau field Option
        $formOption['isRequiredNiveauFieldOption'] = false;
        $formOption['typeMaPrimeRenovNom'] = $devis->getTypeMaPrimeRenovNom();
//        $formOption['typeMaPrimeRenovSereniteNom'] = $devis->getTypeMaPrimeRenovSereniteNom();
        $formOption['isBanqueAccess'] = !empty($devis->getIsBanqueAccess()) ? 1 : 0;
        $formOption['auditAlt'] = $devis->getAuditAlt();
        $formOption['niveau'] = $devis->getNiveau();
        $formOption['isLastDemandeTravauxRembourseSortieDePassoire'] = $isLastDemandeTravauxRembourseSortieDePassoire;

        /* /////////////////////////////////////////////////////////////////////////////////////
            RECUPERATION DU MONTANT Travaux Niveau3 BBC (Aide region)
        ///////////////////////////////////////////////////////////////////////////////////// */
        $montantTravauxNiveau3BBC = $this->titreService->getMontantTravauxNiveau3BBC($demandeAuditEAuditERegion);

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */

        $returnData['formOption'] = $formOption;
        $returnData['beneficiaire'] = $beneficiaire;
        $returnData['logement'] = $logement;
        $returnData['demandeAuditE'] = $demandeAuditEAuditERegion;
        $returnData['demandeTravaux'] = $demandeTravaux;
        $returnData['devis'] = $devis;
        $returnData['instruction'] = $instruction;
        $returnData['auditeur'] = $auditeur;
        $returnData['montantTravauxNiveau3BBC'] = $montantTravauxNiveau3BBC;

        return $returnData;
    }

    /**
     * @param Request $request
     * @param bool $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @param Logement $logement
     * @param Demande_|null $demandeTravaux
     * @param Demande_travaux_devis $devis
     * @param Instruction_|null $instruction
     * @param array $userRoles
     * @return array
     */
    public function manageAndGetDataForEditActionSubmitted(
        Request               $request,
        bool                  $isFrontOffice,
        Beneficiaire          $beneficiaire,
        Logement              $logement,
        ?Demande_              $demandeTravaux,
        Demande_travaux_devis $devis,
        Instruction_          $instruction = null,
        array                 $userRoles = []
    ): array
    {

        $devis->setDateModif(new \DateTime());
        $devis->setAuteurModif($_SESSION['login']->getUsername());

        /* /////////////////////////////////////////////////////////////////
                                SET DEMANDE STATUS
        ///////////////////////////////////////////////////////////////// */
        if ($instruction) {
            $conformiteJP = explode(" | ", $instruction->getInstructionTravaux()->getJPconformite());
            $conformiteKBIS = explode(" | ", $instruction->getInstructionTravaux()->getKBISconformite());
            $conformiteAI = explode(" | ", $instruction->getInstructionTravaux()->getAIconformite());
        } else {
            $conformiteJP = ['0'];
            $conformiteKBIS = ['0'];
            $conformiteAI = ['0'];
            $instruction = null;
        }

        $instructionDevis = null;
        $ficheTechniqueStatut = null;
        $ficheTechniqueIsValidationConseiller = null;
        if ($demandeTravaux) {
            $demandeTravaux_justificatifPropriete = $demandeTravaux->getDemandeTravaux()->getJustificatifPropriete();
            $demandeTravaux_pieceComplement = $demandeTravaux->getDemandeTravaux()->getPieceComplement();
            $demandeTravaux_avisImposition = $demandeTravaux->getDemandeTravaux()->getAvisImposition();
            $demandeTravaux_justificatifProprieteAlt = $demandeTravaux->getDemandeTravaux()->getJustificatifProprieteAlt();
            $demandeTravaux_pieceComplementAlt = $demandeTravaux->getDemandeTravaux()->getPieceComplementAlt();
            $demandeTravaux_avisImpositionAlt = $demandeTravaux->getDemandeTravaux()->getAvisImpositionAlt();
            $demandeTravaux_travauxDevisId = $demandeTravaux->getDemandeTravaux()->getTravauxDevisId();
            $demandeTravaux_audit = $demandeTravaux->getDemandeTravaux()->getAudit();
            if ($demandeTravaux_travauxDevisId) {
                $instructionDevis = $devis->getInstructionDossierConforme();

                if ($demandeTravaux->getDemandeTravaux()->getFicheTechniqueId()) {
                    /* /////////////////////////////////////////////////////////////////
                                                GET  FICHE TECHNIQUE
                    ///////////////////////////////////////////////////////////////// */
                    $ficheTechnique = $this->ficheTechniqueRepository->find($demandeTravaux->getDemandeTravaux()->getFicheTechniqueId());

                    if ($ficheTechnique) {
                        $ficheTechniqueStatut = $ficheTechnique->getStatutFicheTechnique();
                        $ficheTechniqueIsValidationConseiller = $ficheTechnique->getIsValidationConseiller();
                    }
                }
            }
        } else {
            $demandeTravaux_justificatifPropriete = null;
            $demandeTravaux_pieceComplement = null;
            $demandeTravaux_avisImposition = null;
            $demandeTravaux_justificatifProprieteAlt = null;
            $demandeTravaux_pieceComplementAlt = null;
            $demandeTravaux_avisImpositionAlt = null;
            $demandeTravaux_travauxDevisId = null;
            $demandeTravaux_audit = null;
        }

        $statut = $this->searchStatutForDemandeTravauxAndDevis(
            $conformiteJP[0],
            $conformiteKBIS[0],
            $conformiteAI[0],
            $demandeTravaux_justificatifPropriete,
            $demandeTravaux_pieceComplement,
            $demandeTravaux_avisImposition,
            $demandeTravaux_justificatifProprieteAlt,
            $demandeTravaux_pieceComplementAlt,
            $demandeTravaux_avisImpositionAlt,
            $instruction,
            $demandeTravaux_travauxDevisId,
            $demandeTravaux_audit,
            $ficheTechniqueStatut,
            $beneficiaire->getType(),
            $instructionDevis,
            $ficheTechniqueIsValidationConseiller
        );
        $demandeTravaux->setStatutId($statut);

        // Mise à jour du Total devis en parcourant tous les devis montants
        $devis->setTotalDevis($this->findDemandeTravauxDevisTotalDevis($devis));

        $this->EM->persist($devis);
        $this->EM->flush();

        // Set Travaux Devis id dans Demande Travaux
        $demandeTravaux->getDemandeTravaux()->setTravauxDevisId($devis->getId());
        $this->EM->persist($demandeTravaux);
        $this->EM->flush();

        // MISE A JOUR DEMANDE STATUT DESCRIPTION
        $demandeTravaux->setStatutDescription($this->findStatutDescriptionByDemande($demandeTravaux->getId()));
        $this->EM->persist($demandeTravaux);
        $this->EM->flush();

        /* /////////////////////////////////////////////////////////////////
                                FILL UP HISTORIQUE
        ///////////////////////////////////////////////////////////////// */
        $suffixeHistoriqueAction = ($isFrontOffice) ? 'Bénéficiaire' : 'Conseiller';
        $this->historiqueService->save(
            $demandeTravaux->getId(),
            $statut,
            Demande_::DEMANDE_TRAVAUX_TYPE,
            $userRoles,
            true,
            'Modification demande Travaux Devis par le ' . $suffixeHistoriqueAction,
            $beneficiaire->getEmail(),
            $beneficiaire->getType(),
            $demandeTravaux->getDemandeTravaux()->getJustificatifProprieteAlt(),
            $demandeTravaux->getDemandeTravaux()->getPieceComplementAlt(),
            $demandeTravaux->getDemandeTravaux()->getAvisImpositionAlt()
        );

        $request->getSession()->getFlashBag()->add(
            'success',
            'Votre Travaux Devis a bien été pris en compte.'
        );

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            return DefaultUtils::getDataRedirectDemandeListFO($beneficiaire->getId());
        } else {
            return DefaultUtils::getDataRedirectConseillerDemandeListBO($beneficiaire->getId());
        }
    }

    public function findDemandeTravauxDevisTotalDevis(Demande_travaux_devis $demandeTravauxDevis): float|int|string
    {
        $totalDevis = 0;

        /**
         * @var Demande_travaux_devis_upload $demandeTravauxDevisUpload
         */
        foreach ($demandeTravauxDevis->getDemandeTravauxDevisUpload() as $demandeTravauxDevisUpload) {
            if (!empty($demandeTravauxDevisUpload->getMontant())) {
                $totalDevis += $demandeTravauxDevisUpload->getMontant();
            }
        }

        return $totalDevis;
    }

    public static function findMontantRegionByNiveauAndBonification(?string $niveauAide, bool $isBonificationAide): int
    {

        $montantBonificationAide = Demande_travaux_devis::BONIFICATION_SUPPLEMENT_AIDE_REGION_MONTANT;
        $montantAideRegion = 0;

        $niveauAndMontantList = [
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE => Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_MONTANT,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE => Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_MONTANT,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE => Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_MONTANT,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE => Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_MONTANT,
        ];

        if (!empty($niveauAndMontantList[$niveauAide])) {
            $montantAideRegion = (integer)$niveauAndMontantList[$niveauAide];
        }

        if (!empty($isBonificationAide) && true === $isBonificationAide) {
            $montantAideRegion += (integer)$montantBonificationAide;
        }

        return $montantAideRegion;
    }


    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/
}

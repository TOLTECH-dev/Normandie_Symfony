<?php

namespace App\Service;

use App\Utils\DefaultUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Logement;
use App\Entity\User;

class DemandeTravauxService extends DemandeServiceFO
{
    private LogementService $logementService;
    private UserService $userService;

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
        LogementService        $logementService,
        UserService            $userService,
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
        $this->logementService = $logementService;
        $this->userService = $userService;
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
     * @param User|null $user
     * @return array
     * @throws Exception
     */
    public function getDataForAddAction(
        Request $request,
        bool    $isFrontOffice,
        string  $beneficiaireId,
        string  $logementId,
        ?User    $user = null
    )
    {

        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'beneficiaire' => null,
            'logement' => null,
            'demande' => null,
            'auditE' => null,
            'remboursementAuditStatutId' => null
        ];

        /* ///////////////////////////////////////////////////////////////////////
                        SEARCH IF IS DEMANDE CREATE POSSIBLE
        /////////////////////////////////////////////////////////////////////// */
        $dataForLogementListAction = $this->logementService->getDataForListAction(
            $isFrontOffice,
            $beneficiaireId,
            $user->getId()
        );
        $dataForDemandeActionByLogement = $this->logementService->getDataForDemandeAction($dataForLogementListAction)[$logementId] ? $this->logementService->getDataForDemandeAction($dataForLogementListAction)[$logementId] : null;
        if (empty($dataForDemandeActionByLogement['demandeTravauxIsAddAction'])) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                'La création d\'une demande Travaux est impossible'
            );

            if ($isFrontOffice) {
                /* /////////////////////////////////////////////////////////////////
                                        COTE FRONT OFFICE
                ///////////////////////////////////////////////////////////////// */
                return DefaultUtils::getDataRedirectLogementListFO($beneficiaireId);
            } else {
                return DefaultUtils::getDataRedirectConseillerLogementListBO($beneficiaireId);
            }
        }

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
            $this->userService->checkUserSecurity($user->getId(), $beneficiaire->getUserId());
        }

        /* *****************************************************************
                    S E C U R I T Y   R E T O U R   A R R I E R E
        ***************************************************************** */
        if (true == $request->getSession()->get($logementId . 'timestamp_2')) {

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
                     SEARCH IF IS DEMANDE ALREADY CREATED
        ///////////////////////////////////////////////////////////////// */
        $isCreated = $this->demande_Repository->findIsCreated(
            $beneficiaireId,
            $logementId,
            Demande_::DEMANDE_TRAVAUX_TYPE
        );
        if ($isCreated) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                'Une demande Travaux existe déjà pour ce logement.'
            );

            if ($isFrontOffice) {
                /* /////////////////////////////////////////////////////////////////
                                        COTE FRONT OFFICE
                ///////////////////////////////////////////////////////////////// */
                return DefaultUtils::getDataRedirectLogementListFO($beneficiaireId);
            } else {
                return DefaultUtils::getDataRedirectConseillerLogementListBO($beneficiaireId);
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->logementRepository->find($logementId);

        /* /////////////////////////////////////////////////////////////////
                            GET DEMANDE AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
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
                                GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursementAuditStatutId = '';
        if (!empty($demandeAuditEAuditERegion)) {
            $remboursement = $this->remboursement_Repository->findOneBy([
                'demande_id' => $demandeAuditEAuditERegion->getId()
            ]);
            $remboursementAuditStatutId = !empty($remboursement) ? $remboursement->getStatutId() : '';
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $formOption[] = $beneficiaire->getStructureRattachementId();
        $formOption[] = $beneficiaire->getConseillerRattachementId();
        $formOption[] = $beneficiaire->getAuditeurId();

        $demande = new Demande_();
        $demande->setAuteurCreation($user->getUsername());
        $demande->setAuteurModif($user->getUsername());

        $returnData['formOption'] = $formOption;
        $returnData['beneficiaire'] = $beneficiaire;
        $returnData['logement'] = $logement;
        $returnData['demande'] = $demande;
        $returnData['auditE'] = $demandeAuditEAuditERegion;
        $returnData['remboursementAuditStatutId'] = $remboursementAuditStatutId;

        return $returnData;
    }

    /**
     * @param Request $request
     * @param bool $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @param Logement $logement
     * @param Demande_ $demande
     * @param array $userRoles
     * @param Demande_|null $auditE
     * @return array
     * @throws Exception
     */
    public function manageAndGetDataForAddActionSubmitted(
        Request      $request,
        bool         $isFrontOffice,
        Beneficiaire $beneficiaire,
        Logement     $logement,
        Demande_     $demande,
        array        $userRoles = [],
        Demande_     $auditE = null
    ): array
    {

        $demande->setType(Demande_::DEMANDE_TRAVAUX_TYPE);
        $demande->setBeneficiaireId($beneficiaire->getId());
        $demande->setLogementId($logement->getId());

        if (isset($auditE) and Demande_statut::STATUS_15 != $auditE->getStatutId()) {
            /* /////////////////////////////////////////////////////////////////
                                    COPY UPLOAD FILE - JP
            ///////////////////////////////////////////////////////////////// */
            $JP_travaux = $demande->getDemandeTravaux()->getJustificatifPropriete();

            $JP_auditE_webPath = $auditE->getDemandeAuditEnergie()->justificatifPropriete_getWebPath();
            $JP_auditE_webUrl = $auditE->getDemandeAuditEnergie()->getJustificatifProprieteUrl();
            $JP_auditE_webAlt = $auditE->getDemandeAuditEnergie()->getJustificatifProprieteAlt();

            if (!is_object($JP_travaux) && isset($JP_auditE_webUrl) && isset($JP_auditE_webAlt)) {
                $demande->getDemandeTravaux()->setJustificatifProprieteUrl($JP_auditE_webUrl);
                $demande->getDemandeTravaux()->setJustificatifProprieteAlt($JP_auditE_webAlt);
            }

            /* /////////////////////////////////////////////////////////////////
                                    COPY UPLOAD FILE - KBIS
            ///////////////////////////////////////////////////////////////// */
            $KBIS_travaux = $demande->getDemandeTravaux()->getPieceComplement();

            $KBIS_auditE_webPath = $auditE->getDemandeAuditEnergie()->pieceComplement_getWebPath();
            $KBIS_auditE_webUrl = $auditE->getDemandeAuditEnergie()->getPieceComplementUrl();
            $KBIS_auditE_webAlt = $auditE->getDemandeAuditEnergie()->getPieceComplementAlt();

            if (!is_object($KBIS_travaux) && isset($KBIS_auditE_webUrl) && isset($KBIS_auditE_webAlt)) {
                $demande->getDemandeTravaux()->setPieceComplementUrl($KBIS_auditE_webUrl);
                $demande->getDemandeTravaux()->setPieceComplementAlt($KBIS_auditE_webAlt);
            }
        }

        $demandeTravaux_justificatifPropriete = null;
        $demandeTravaux_pieceComplement = null;
        $demandeTravaux_avisImposition = null;
        $demandeTravaux_justificatifProprieteAlt = null;
        $demandeTravaux_pieceComplementAlt = null;
        $demandeTravaux_avisImpositionAlt = null;
        $demandeTravaux_travauxDevisId = null;
        $demandeTravaux_audit = null;

        /* /////////////////////////////////////////////////////////////////
                                    SET STATUS
        ///////////////////////////////////////////////////////////////// */
        $statut = null;
        $envoiEmailDansHistoriqueSave = true;

        $participationSARE = $this->demande_Repository->findParticipationSAREByLogementId($logement->getId());

        /* /////////////////////////////////////////////////////////////////
                        CALCUL REVENU FISCAL DE REFERENCE
        ///////////////////////////////////////////////////////////////// */
        $arraySituation = explode(' | ', $logement->getSituation());
        $demandeTravaux_nbPersFoyer = $demande->getDemandeTravaux()->getNbPersFoyer();
        $demandeTravaux_revenuReference = $demande->getDemandeTravaux()->getRevenu3();

        $checkSAREDemandeTravaux = $this->checkSAREDemandeAuditEtTravaux(
            $auditE,
            $participationSARE,
            $demandeTravaux_nbPersFoyer,
            $demandeTravaux_revenuReference
        );

        if (empty($checkSAREDemandeTravaux)) {
            $statut = $this->searchStatutRefus();
            // On initie le Motif refus à celui de motif refus ANAH car si checkSAREDemandeAuditEtTravaux() a renovyé false
            //  alors nous sommes ici dû au "revenu fiscal de référence du foyer" dépassant le "plafond de l'Anah"
            $demande->setMotifRefus(self::MOTIF_REFUS_ANAH);
        } else {

            /* /////////////////////////////////////////////////////////////////
                            CALCUL REVENU FISCAL DE REFERENCE
            ///////////////////////////////////////////////////////////////// */

            $checkANAH = $this->ANAHService->checkPlafond(
                $arraySituation[0],
                $demandeTravaux_nbPersFoyer,
                $demandeTravaux_revenuReference
            );

            if (true == $checkANAH) {

                $instructionDevis = null;
                $ficheTechniqueStatut = null;
                $ficheTechniqueIsValidationConseiller = null;
                if ($demande) {
                    $demandeTravaux_justificatifPropriete = $demande->getDemandeTravaux()->getJustificatifPropriete();
                    $demandeTravaux_pieceComplement = $demande->getDemandeTravaux()->getPieceComplement();
                    $demandeTravaux_avisImposition = $demande->getDemandeTravaux()->getAvisImposition();
                    $demandeTravaux_justificatifProprieteAlt = $demande->getDemandeTravaux()->getJustificatifProprieteAlt();
                    $demandeTravaux_pieceComplementAlt = $demande->getDemandeTravaux()->getPieceComplementAlt();
                    $demandeTravaux_avisImpositionAlt = $demande->getDemandeTravaux()->getAvisImpositionAlt();
                    $demandeTravaux_travauxDevisId = $demande->getDemandeTravaux()->getTravauxDevisId();
                    $demandeTravaux_audit = $demande->getDemandeTravaux()->getAudit();

                    if ($demandeTravaux_travauxDevisId) {
                        $travauxDevis = $this->demande_travaux_devisRepository->find($demandeTravaux_travauxDevisId);
                        $instructionDevis = $travauxDevis->getInstructionDossierConforme();

                        if ($demande->getDemandeTravaux()->getFicheTechniqueId()) {
                            /* /////////////////////////////////////////////////////////////////
                                                        GET  FICHE TECHNIQUE
                            ///////////////////////////////////////////////////////////////// */
                            $ficheTechnique = $this->ficheTechniqueRepository->find($demande->getDemandeTravaux()->getFicheTechniqueId());

                            if ($ficheTechnique) {
                                $ficheTechniqueStatut = $ficheTechnique->getStatutFicheTechnique();
                                $ficheTechniqueIsValidationConseiller = $ficheTechnique->getIsValidationConseiller();
                            }
                        }
                    }
                }

                $statut = $this->searchStatutForDemandeTravauxAndDevis(
                    null,
                    null,
                    null,
                    $demandeTravaux_justificatifPropriete,
                    $demandeTravaux_pieceComplement,
                    $demandeTravaux_avisImposition,
                    $demandeTravaux_justificatifProprieteAlt,
                    $demandeTravaux_pieceComplementAlt,
                    $demandeTravaux_avisImpositionAlt,
                    null,
                    $demandeTravaux_travauxDevisId,
                    $demandeTravaux_audit,
                    $ficheTechniqueStatut,
                    $beneficiaire->getType(),
                    $instructionDevis,
                    $ficheTechniqueIsValidationConseiller
                );
            } else {
                $statut = $this->searchStatutRefus();
                $demande->setMotifRefus(DemandeServiceFO::MOTIF_REFUS_ANAH);
            }
        }

        if (!empty($statut)) {
            $demande->setStatutId($statut);
        }

        // MISE A JOUR DEMANDE TYPE MENAGE
        $this->setDemandeTypeMenage($demande, $beneficiaire, []);

        $this->EM->persist($demande);

        /* /////////////////////////////////////////////////////////////////
            UPDATE BENEFICIAIRE STRUCTURE AND CONSEILLER RATTACHEMENT
        ///////////////////////////////////////////////////////////////// */
        if ($beneficiaire) {
            $beneficiaire->setStructureRattachementId($demande->getDemandeTravaux()->getStructureId());
            $beneficiaire->setConseillerRattachementId($demande->getDemandeTravaux()->getConseillerId());
            $this->EM->persist($beneficiaire);
        }

        $this->EM->flush();

        // MISE A JOUR DEMANDE STATUT DESCRIPTION
        $demande->setStatutDescription($this->findStatutDescriptionByDemande($demande->getId()));
        $this->EM->persist($demande);
        $this->EM->flush();

        /* /////////////////////////////////////////////////////////////////
                            COPY UPLOAD FILE - JP + KBIS
        ///////////////////////////////////////////////////////////////// */
        if (isset($auditE) and Demande_statut::STATUS_15 != $auditE->getStatutId()) {
            $projectDir = $this->parameterBag->get('app_root_dossier_data_symfony');
            $JP_travaux_path = $projectDir . $demande->getDemandeTravaux()->justificatifPropriete_getUploadDir();
            $KBIS_travaux_path = $projectDir . $demande->getDemandeTravaux()->pieceComplement_getUploadDir();

            if (!file_exists($JP_travaux_path)) mkdir($JP_travaux_path, 0755, true);
            if (!file_exists($KBIS_travaux_path)) mkdir($KBIS_travaux_path, 0755, true);

            if (!is_object($JP_travaux) && isset($JP_auditE_webUrl) && isset($JP_auditE_webAlt)) {
                copy($projectDir . $JP_auditE_webPath,
                    $projectDir . $demande->getDemandeTravaux()->justificatifPropriete_getWebPath()
                );
            }

            if (!is_object($KBIS_travaux) && isset($KBIS_auditE_webUrl) && isset($KBIS_auditE_webAlt)) {
                copy($projectDir . $KBIS_auditE_webPath,
                    $projectDir . $demande->getDemandeTravaux()->pieceComplement_getWebPath()
                );
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                FILL UP HISTORIQUE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireEmail = $beneficiaire->getEmail();
        $suffixeHistoriqueAction = ($isFrontOffice) ? 'Bénéficiaire' : 'Conseiller';

        $historique = $this->historiqueService->save(
            $demande->getId(),
            $statut,
            Demande_::DEMANDE_TRAVAUX_TYPE,
            $userRoles,
            $envoiEmailDansHistoriqueSave,
            'Création demande Travaux par le ' . $suffixeHistoriqueAction,
            $beneficiaireEmail,
            $beneficiaire->getType(),
            $demande->getDemandeTravaux()->getJustificatifProprieteAlt(),
            $demande->getDemandeTravaux()->getPieceComplementAlt(),
            $demande->getDemandeTravaux()->getAvisImpositionAlt(),
            true
        );

        /* /////////////////////////////////////////////////////////////////
                ENVOI EMAIL + HISTORISATION EMAIL :
                REFUS DU A EPCI NON PARTICIPATION SARE
        ///////////////////////////////////////////////////////////////// */

// désactivé suite US refus demande motif
//        if (empty($checkSAREDemandeTravaux)) {
//            $this->sendEmailRefusNonParticipationSARE(
//                $historique,
//                $beneficiaireEmail,
//                $demande->getMotifRefus()
//            );
//        }

        $request->getSession()->set($logement->getId() . 'timestamp_2', true);
        $request->getSession()->getFlashBag()->add(
            'success',
            'Votre demande Travaux a bien été prise en compte.'
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
     * @param string $demandeId
     * @param User $user
     * @return array
     * @throws Exception
     */
    public function getDataForViewAction(
        bool          $isFrontOffice,
        string        $demandeId,
        UserInterface $user
    ): array
    {
        $totalCommentaire = null;

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $rowDemande = $this->demande_travauxRepository->findByIdCustom(
            $demandeId,
            $this->parameterBag->get('production_travauxNiveau_BBC2')
        );

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            $this->userService->checkUserSecurity($user->getId(), $rowDemande['beneficiaireUserId']);
        } else {
            $option = [
                'roles' => $user->getRoles(),
                'username' => $user->getUsername()
            ];

            /* /////////////////////////////////////////////////////////////////
                                CHECK DEMANDE ACCESS CONTROLE
            ///////////////////////////////////////////////////////////////// */
            $this->demandeServiceBO->checkAccesByRole($this->demande_Repository->find($demandeId), $option);

            /* /////////////////////////////////////////////////////////////////
                                    GET COUNT COMMENTAIRE
            ///////////////////////////////////////////////////////////////// */
            $totalCommentaire = $this->historique_Repository->countCommentaireByDemande($demandeId);
        }

        /**
         * @var Beneficiaire $beneficiaire
         */
        $beneficiaire = $this->beneficiaireRepository->find($rowDemande['beneficiaireId']);
        $beneficiaireType = $beneficiaire->getType();
        if (isset($beneficiaireType)) {
            $beneficiaireTypeKey = explode(' | ', $beneficiaire->getType())[0];
        }

        return [
            'rowDemande' => $rowDemande,
            'totalCommentaire' => $totalCommentaire,
            'beneficiaireTypeKey' => $beneficiaireTypeKey
        ];
    }

    /**
     * @param bool $isFrontOffice
     * @param string $demandeId
     * @param User $user
     * @param bool $isWithParamDemandeData
     * @return array
     */
    public function getDataForEditAction(
        bool          $isFrontOffice,
        string        $demandeId,
        UserInterface $user,
        bool          $isWithParamDemandeData = false
    ): array
    {
        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'beneficiaire' => null,
            'logement' => null,
            'demande' => null,
            'auditE' => null,
            'remboursementAuditStatutId' => null,
            'nbPersFoyerOld' => null,
            'revenuFoyerOld' => null
        ];

        /**
         * @var Demande_ $demande
         */
        $demande = $this->demande_Repository->find($demandeId);

        /* *****************************************************************
                            S E C U R I T Y    D E M A N D E
        ***************************************************************** */
        if (!in_array('ROLE_CLIENT', $user->getRoles())
            && !in_array('ROLE_ADMIN', $user->getRoles())
        ) {
            $this->checkEditDemande($demande);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireId = $demande->getBeneficiaireId();
        $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */

            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            $this->userService->checkUserSecurity($user->getId(), $beneficiaire->getUserId());
        } else {
            /* /////////////////////////////////////////////////////////////////
                                CHECK DEMANDE ACCESS CONTROLE
            ///////////////////////////////////////////////////////////////// */
            $option = [
                'roles' => $user->getRoles(),
                'username' => $user->getUsername()
            ];
            $this->demandeServiceBO->checkAccesByRole($this->demande_Repository->find($demandeId), $option);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->logementRepository->find($demande->getLogementId());

        /* /////////////////////////////////////////////////////////////////
                            GET DEMANDE AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
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
                                GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursementAuditStatutId = '';
        if (!empty($demandeAuditEAuditERegion)) {
            $remboursement = $this->remboursement_Repository->findOneBy([
                'demande_id' => $demandeAuditEAuditERegion->getId()
            ]);

            $remboursementAuditStatutId = !empty($remboursement) ? $remboursement->getStatutId() : '';
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $formOption[] = $demande->getDemandeTravaux()->getStructureId();
        $formOption[] = $demande->getDemandeTravaux()->getConseillerId();
        $formOption[] = $beneficiaire->getAuditeurId();

        $returnData['formOption'] = $formOption;
        $returnData['beneficiaire'] = $beneficiaire;
        $returnData['logement'] = $logement;
        $returnData['demande'] = $demande;
        $returnData['auditE'] = $demandeAuditEAuditERegion;
        $returnData['remboursementAuditStatutId'] = $remboursementAuditStatutId;

        // BESOIN POUR LE UPDATE INSTRUCTION
        $returnData['nbPersFoyerOld'] = $demande->getDemandeTravaux()->getNbPersFoyer();
        $returnData['revenuFoyerOld'] = trim($demande->getDemandeTravaux()->getRevenu3());

        if (!empty($isWithParamDemandeData)) {
            $returnData['demandeData'] = [
                'type' => $demande->getType(),
                'auditE' => $demandeAuditEAuditERegion,
                'travaux' => $demande,
            ];
        }

        return $returnData;
    }

    /**
     * @param Request $request
     * @param bool $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @param Logement $logement
     * @param Demande_ $demande
     * @param array $userRoles
     * @param Demande_|null $auditE
     * @param int|null $nbPersFoyerOld
     * @param int|null $revenuFoyerOld
     * @param array $arrayDemandeStatutKeep
     * @return array
     * @throws Exception
     */
    public function manageAndGetDataForEditActionSubmitted(
        Request      $request,
        bool         $isFrontOffice,
        Beneficiaire $beneficiaire,
        Logement     $logement,
        Demande_     $demande,
        array        $userRoles = [],
        Demande_     $auditE = null,
        ?int         $nbPersFoyerOld = null,
        ?string         $revenuFoyerOld = null,
        array        $arrayDemandeStatutKeep = []
    ): array
    {
        $isSendEmailRefusNonParticipationSARE = false;

        $demande->setDateModif(new \DateTime());
        $demande->setAuteurModif($_SESSION['login']->getUsername());

        /* /////////////////////////////////////////////////////////////////
                    CALCUL REVENU FISCAL DE REFERENCE
        ///////////////////////////////////////////////////////////////// */
        $arraySituation = explode(' | ', $logement->getSituation());
        $demandeTravaux_nbPersFoyer = $demande->getDemandeTravaux()->getNbPersFoyer();
        $demandeTravaux_revenuReference = $demande->getDemandeTravaux()->getRevenu3();

        $checkANAH = $this->ANAHService->checkPlafond(
            $arraySituation[0],
            $demandeTravaux_nbPersFoyer,
            $demandeTravaux_revenuReference
        );

        /* /////////////////////////////////////////////////////////////////
                                    SET STATUS
        ///////////////////////////////////////////////////////////////// */
        $demandeTravaux_justificatifPropriete = null;
        $demandeTravaux_pieceComplement = null;
        $demandeTravaux_avisImposition = null;
        $demandeTravaux_avisImpositionConjoint = null;
        $demandeTravaux_justificatifProprieteAlt = null;
        $demandeTravaux_pieceComplementAlt = null;
        $demandeTravaux_avisImpositionAlt = null;
        $demandeTravaux_travauxDevisId = null;
        $demandeTravaux_audit = null;

        $instructionDevis = null;
        $ficheTechniqueStatut = null;
        $ficheTechniqueIsValidationConseiller = null;
        if ($demande) {
            $demandeTravaux_justificatifPropriete = $demande->getDemandeTravaux()->getJustificatifPropriete();
            $demandeTravaux_pieceComplement = $demande->getDemandeTravaux()->getPieceComplement();
            $demandeTravaux_avisImposition = $demande->getDemandeTravaux()->getAvisImposition();
            $demandeTravaux_avisImpositionConjoint = $demande->getDemandeTravaux()->getAvisImpositionConjoint();
            $demandeTravaux_justificatifProprieteAlt = $demande->getDemandeTravaux()->getJustificatifProprieteAlt();
            $demandeTravaux_pieceComplementAlt = $demande->getDemandeTravaux()->getPieceComplementAlt();
            $demandeTravaux_avisImpositionAlt = $demande->getDemandeTravaux()->getAvisImpositionAlt();
            $demandeTravaux_travauxDevisId = $demande->getDemandeTravaux()->getTravauxDevisId();
            $demandeTravaux_audit = $demande->getDemandeTravaux()->getAudit();

            if ($demandeTravaux_travauxDevisId) {
                $travauxDevis = $this->demande_travaux_devisRepository->find($demandeTravaux_travauxDevisId);
                $instructionDevis = $travauxDevis->getInstructionDossierConforme();

                if ($demande->getDemandeTravaux()->getFicheTechniqueId()) {
                    /* /////////////////////////////////////////////////////////////////
                                                GET  FICHE TECHNIQUE
                    ///////////////////////////////////////////////////////////////// */
                    $ficheTechnique = $this->ficheTechniqueRepository->find($demande->getDemandeTravaux()->getFicheTechniqueId());

                    if ($ficheTechnique) {
                        $ficheTechniqueStatut = $ficheTechnique->getStatutFicheTechnique();
                        $ficheTechniqueIsValidationConseiller = $ficheTechnique->getIsValidationConseiller();
                    }
                }
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                UPDATE INSTRUCTION
        ///////////////////////////////////////////////////////////////// */
        $isNbPersFoyer = ((int)($demande->getDemandeTravaux()->getNbPersFoyer()) != $nbPersFoyerOld) ? true : false;
        $isRevenuFoyer = (trim($demande->getDemandeTravaux()->getRevenu3()) != $revenuFoyerOld) ? true : false;

        $instructionData = $this->updateInstruction(
            $demande->getId(),
            $demande->getType(),
            $beneficiaire->getType(),
            $demandeTravaux_justificatifPropriete,
            $demandeTravaux_pieceComplement,
            $demandeTravaux_avisImposition,
            $demandeTravaux_avisImpositionConjoint,
            $isNbPersFoyer,
            $isRevenuFoyer
        );

        if (empty($arrayDemandeStatutKeep)
            || (!empty($arrayDemandeStatutKeep) && !in_array($demande->getStatutId(), $arrayDemandeStatutKeep))
        ) {
            $participationSARE = $this->demande_Repository->findParticipationSAREByLogementId($demande->getLogementId());

            $checkSAREDemandeTravaux = $this->checkSAREDemandeAuditEtTravaux(
                $auditE,
                $participationSARE,
                $demandeTravaux_nbPersFoyer,
                $demandeTravaux_revenuReference
            );

            if (empty($checkSAREDemandeTravaux)) {
                $statut = $this->searchStatutRefus();
                // On initie le Motif refus à celui de motif refus ANAH car si checkSAREDemandeAuditEtTravaux() a renovyé false
                //  alors nous sommes ici dû au "revenu fiscal de référence du foyer" dépassant le "plafond de l'Anah"
                $demande->setMotifRefus(self::MOTIF_REFUS_ANAH);
            } else {
                if (true == $checkANAH) {
                    $statut = $this->searchStatutForDemandeTravauxAndDevis(
                        $instructionData['conformiteJP'],
                        $instructionData['conformiteKBIS'],
                        $instructionData['conformiteAI'],
                        $demandeTravaux_justificatifPropriete,
                        $demandeTravaux_pieceComplement,
                        $demandeTravaux_avisImposition,
                        $demandeTravaux_justificatifProprieteAlt,
                        $demandeTravaux_pieceComplementAlt,
                        $demandeTravaux_avisImpositionAlt,
                        $instructionData['instruction'],
                        $demandeTravaux_travauxDevisId,
                        $demandeTravaux_audit,
                        $ficheTechniqueStatut,
                        $beneficiaire->getType(),
                        $instructionDevis,
                        $ficheTechniqueIsValidationConseiller
                    );
                } else {
                    $statut = $this->searchStatutRefus();
                    $demande->setMotifRefus(DemandeServiceFO::MOTIF_REFUS_ANAH);
                }
            }

            $demande->setStatutId($statut);
        }

        // MISE A JOUR DEMANDE TYPE MENAGE
        $this->setDemandeTypeMenage($demande, $beneficiaire, []);

        $this->EM->persist($demande);

        /* /////////////////////////////////////////////////////////////////
            UPDATE BENEFICIAIRE STRUCTURE AND CONSEILLER RATTACHEMENT
        ///////////////////////////////////////////////////////////////// */
        if ($beneficiaire) {
            $beneficiaire->setStructureRattachementId($demande->getDemandeTravaux()->getStructureId());
            $beneficiaire->setConseillerRattachementId($demande->getDemandeTravaux()->getConseillerId());
            $this->EM->persist($beneficiaire);
        }

        $this->EM->flush();

        // MISE A JOUR DEMANDE STATUT DESCRIPTION
        $demande->setStatutDescription($this->findStatutDescriptionByDemande($demande->getId()));
        $this->EM->persist($demande);
        $this->EM->flush();

        /* /////////////////////////////////////////////////////////////////
                                FILL UP HISTORIQUE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireEmail = $beneficiaire->getEmail();
        if ($isFrontOffice) {
            $suffixeHistoriqueAction = 'le Bénéficiaire';
        } else {
            $suffixeHistoriqueAction = !empty($arrayDemandeStatutKeep) ? 'la Région' : 'le Conseiller';
        }

        $historique = $this->historiqueService->save(
            $demande->getId(),
            $statut,
            Demande_::DEMANDE_TRAVAUX_TYPE,
            $userRoles,
            true,
            'Modification demande Travaux par ' . $suffixeHistoriqueAction,
            $beneficiaireEmail,
            $beneficiaire->getType(),
            $demande->getDemandeTravaux()->getJustificatifProprieteAlt(),
            $demande->getDemandeTravaux()->getPieceComplementAlt(),
            $demande->getDemandeTravaux()->getAvisImpositionAlt(),
            true
        );

        /* /////////////////////////////////////////////////////////////////
                ENVOI EMAIL + HISTORISATION EMAIL :
                REFUS DU A EPCI NON PARTICIPATION SARE
        ///////////////////////////////////////////////////////////////// */

// désactivé suite US refus demande motif
//        if (!empty($isSendEmailRefusNonParticipationSARE)) {
//            $this->sendEmailRefusNonParticipationSARE(
//                $historique,
//                $beneficiaireEmail,
//                $demande->getMotifRefus()
//            );
//        }

        $request->getSession()->getFlashBag()->add(
            'success',
            'Votre demande Travaux a bien été modifiée.'
        );

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            return DefaultUtils::getDataRedirectDemandeListFO($beneficiaire->getId());
        } else {
            if (!empty($arrayDemandeStatutKeep)) {
                // coté liste demandes role client
                return DefaultUtils::getDataRedirectClientDemandeListBO();
            } else {
                // coté assistant beneficiaire role conseiller
                return DefaultUtils::getDataRedirectConseillerDemandeListBO($beneficiaire->getId());
            }
        }
    }

}
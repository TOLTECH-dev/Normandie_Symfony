<?php

namespace App\Service;

use App\Utils\DefaultUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Logement;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;

class DemandeAuditEnergieService extends DemandeServiceFO
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
     * @param $isFrontOffice
     * @param $beneficiaireId
     * @param $logementId
     * @param $type
     * @param $userId
     * @return array
     */
    public function getDataForAddAction(
        Request $request,
                $isFrontOffice,
                $beneficiaireId,
                $logementId,
                $type,
                $user = null
    )
    {

        $demandeTypeLabel = Demande_::$demandeType[$type];
        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'logement' => null,
            'beneficiaire' => null,
            'demande' => null,
            'demandeTypeLabel' => $demandeTypeLabel
        ];

        /* ///////////////////////////////////////////////////////////////////////
                        SEARCH IF IS DEMANDE CREATE POSSIBLE
        /////////////////////////////////////////////////////////////////////// */
        $dataForLogementListAction = $this->logementService->getDataForListAction(
            $isFrontOffice,
            $beneficiaireId,
            $user?->getId()
        );
        $dataForDemandeActionByLogement = $this->logementService->getDataForDemandeAction($dataForLogementListAction)[$logementId] ? $this->logementService->getDataForDemandeAction($dataForLogementListAction)[$logementId] : null;
        if (empty($dataForDemandeActionByLogement['demandeAuditERegionIsAddAction'])) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                'La création d\'une demande audit est impossible'
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

        $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            $this->userService->checkUserSecurity($user?->getId(), $beneficiaire->getUserId());
        }

        /* *****************************************************************
                    S E C U R I T Y   R E T O U R   A R R I E R E
        ***************************************************************** */
        if (true == $request->getSession()->get($logementId . 'timestamp_0')) {

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
        $isCreated = $this->demande_Repository->findIsCreated($beneficiaireId, $logementId, $type);
        if ($isCreated) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                'Une demande ' . $demandeTypeLabel . ' existe déjà pour ce logement.'
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
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        if ($beneficiaire) {
            $formOption[] = $beneficiaire->getStructureRattachementId();
            $formOption[] = $beneficiaire->getConseillerRattachementId();
            $formOption[] = $beneficiaire->getAuditeurId();
        } else {
            $formOption[] = '';
            $formOption[] = '';
            $formOption[] = '';
        }

        $demande = new Demande_();
        $demande->setAuteurCreation($user->getUsername());
        $demande->setAuteurModif($user->getUsername());

        $returnData['formOption'] = $formOption;
        $returnData['logement'] = $logement;
        $returnData['beneficiaire'] = $beneficiaire;
        $returnData['demande'] = $demande;

        return $returnData;
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @param Logement $logement
     * @param Demande_ $demande
     * @param $type
     * @param $userRoles
     * @return array
     * @throws Exception
     */
    public function manageAndGetDataForAddActionSubmitted(
        Request      $request,
                     $isFrontOffice,
        Beneficiaire $beneficiaire,
        Logement     $logement,
        Demande_     $demande,
                     $type,
                     $userRoles = []
    )
    {
        $demandeTypeLabel = Demande_::$demandeType[$type];

        $demande->setType($type);
        $demande->setBeneficiaireId($beneficiaire->getId());
        $demande->setLogementId($logement->getId());

        /* /////////////////////////////////////////////////////////////////
                                SET DEMANDE STATUS
        ///////////////////////////////////////////////////////////////// */
        $documentJP = $demande->getDemandeAuditEnergie()->getJustificatifPropriete();
        $documentKBIS = $demande->getDemandeAuditEnergie()->getPieceComplement();
        $documentAI = $demande->getDemandeAuditEnergie()->getAvisImposition();
        $documentJPAlt = $demande->getDemandeAuditEnergie()->getJustificatifProprieteAlt();
        $documentKBISAlt = $demande->getDemandeAuditEnergie()->getPieceComplementAlt();
        $documentAIAlt = $demande->getDemandeAuditEnergie()->getAvisImpositionAlt();
        $auditeur = $demande->getDemandeAuditEnergie()->getAuditeurId();

        $statut = null;
        $envoiEmailDansHistoriqueSave = true;

        $participationSARE = $this->demande_Repository->findParticipationSAREByLogementId($logement->getId());

        /* /////////////////////////////////////////////////////////////////
                        CALCUL REVENU FISCAL DE REFERENCE
        ///////////////////////////////////////////////////////////////// */
        $nbPersFoyer = $demande->getDemandeAuditEnergie()->getNbPersFoyer();
        $revenuReference = $demande->getDemandeAuditEnergie()->getRevenu3();

        $checkSAREDemandeAudit = $this->checkSAREDemandeAuditEtTravaux(
            null,
            $participationSARE,
            $nbPersFoyer,
            $revenuReference
        );

        if (empty($checkSAREDemandeAudit)) {
            $statut = $this->searchStatutRefus();
            // On initie le Motif refus à celui de motif refus ANAH car si checkSAREDemandeAuditEtTravaux() a renovyé false
            //  alors nous sommes ici dû au "revenu fiscal de référence du foyer" dépassant le "plafond de l'Anah"
            $demande->setMotifRefus(self::MOTIF_REFUS_ANAH);
        } else {
            $isDoublon = $this->checkDoublon(
                $type,
                $beneficiaire->getNom(),
                $beneficiaire->getPrenom(),
                $logement->getCodePostal(),
                $logement->getVille()
            );
            if (true == $isDoublon) {
                $statut = $this->searchStatutRefus();
                $demande->setMotifRefus(self::MOTIF_REFUS_DOUBLON);
            } else {
                $statut = $this->searchStatutForDemandeAuditEnergie(
                    null,
                    null,
                    null,
                    $documentJP,
                    $documentKBIS,
                    $documentAI,
                    $documentJPAlt,
                    $documentKBISAlt,
                    $documentAIAlt,
                    null,
                    $beneficiaire->getType(),
                    $auditeur
                );
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
            $beneficiaire->setStructureRattachementId($demande->getDemandeAuditEnergie()->getStructureId());
            $beneficiaire->setConseillerRattachementId($demande->getDemandeAuditEnergie()->getConseillerId());
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
        $demandeId = $demande->getId();
        $beneficiaireEmail = $beneficiaire->getEmail();
        $suffixeHistoriqueAction = ($isFrontOffice) ? 'Bénéficiaire' : 'Conseiller';

        $historique = $this->historiqueService->save(
            $demandeId,
            $statut,
            $type,
            $userRoles,
            $envoiEmailDansHistoriqueSave,
            'Création demande ' . $demandeTypeLabel . ' par le ' . $suffixeHistoriqueAction,
            $beneficiaireEmail,
            $beneficiaire->getType(),
            $demande->getDemandeAuditEnergie()->getJustificatifProprieteAlt(),
            $demande->getDemandeAuditEnergie()->getPieceComplementAlt(),
            $demande->getDemandeAuditEnergie()->getAvisImpositionAlt(),
            true
        );

        /* /////////////////////////////////////////////////////////////////
                ENVOI EMAIL + HISTORISATION EMAIL :
                REFUS DU A EPCI NON PARTICIPATION SARE
        ///////////////////////////////////////////////////////////////// */

        // désactivé suite US refus demande motif
//        if (empty($checkSAREDemandeAudit)) {
//            $this->sendEmailRefusNonParticipationSARE(
//                $historique,
//                $beneficiaireEmail,
//                $demande->getMotifRefus()
//            );
//        }

        $request->getSession()->set($logement->getId() . 'timestamp_0', true);
        $request->getSession()->getFlashBag()->add(
            'success',
            'Votre demande Audit Energie a bien été prise en compte.'
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
     * @param $isFrontOffice
     * @param $demandeId
     * @param User $user
     * @return array
     * @throws Exception
     */
    public function getDataForViewAction(
        $isFrontOffice,
        $demandeId,
        UserInterface $user
    )
    {

        $totalCommentaire = null;

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $rowDemande = $this->demande_auditEnergieRepository->findByIdCustom($demandeId);

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
     * @param $isFrontOffice
     * @param $demandeId
     * @param User $user
     * @param $isWithParamDemandeData
     * @return array
     */
    public function getDataForEditAction(
        $isFrontOffice,
        $demandeId,
        UserInterface $user,
        $isWithParamDemandeData = false
    )
    {

        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'logement' => null,
            'beneficiaire' => null,
            'demande' => null,
            'nbPersFoyerOld' => null,
            'revenuFoyerOld' => null,
            'demandeData' => null
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
            $this->demandeServiceBO->checkAccesByRole($demande, $option);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->logementRepository->find($demande->getLogementId());

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $formOption[] = $demande->getDemandeAuditEnergie()->getStructureId();
        $formOption[] = $demande->getDemandeAuditEnergie()->getConseillerId();
        $formOption[] = $demande->getDemandeAuditEnergie()->getAuditeurId();

        $returnData['formOption'] = $formOption;
        $returnData['logement'] = $logement;
        $returnData['beneficiaire'] = $beneficiaire;
        $returnData['demande'] = $demande;

        // BESOIN POUR LE UPDATE INSTRUCTION
        $returnData['nbPersFoyerOld'] = $demande->getDemandeAuditEnergie()->getNbPersFoyer();
        $returnData['revenuFoyerOld'] = trim($demande->getDemandeAuditEnergie()->getRevenu3());

        if (!empty($isWithParamDemandeData)) {
            $returnData['demandeData'] = [
                'type' => $demande->getType(),
                'auditE' => $demande
            ];
        }

        return $returnData;
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @param Logement $logement
     * @param Demande_ $demande
     * @param $userRoles
     * @param $nbPersFoyerOld
     * @param $revenuFoyerOld
     * @param $arrayDemandeStatutKeep
     * @return array
     * @throws Exception
     */
    public function manageAndGetDataForEditActionSubmitted(
        Request      $request,
                     $isFrontOffice,
        Beneficiaire $beneficiaire,
        Logement     $logement,
        Demande_     $demande,
                     $userRoles = [],
                     $nbPersFoyerOld = null,
                     $revenuFoyerOld = null,
                     $arrayDemandeStatutKeep = []
    )
    {
        $isSendEmailRefusNonParticipationSARE = false;

        $type = $demande->getType();
        $demande->setDateModif(new \DateTime());
        $demande->setAuteurModif($_SESSION['login']->getUsername());

        $documentJP = $demande->getDemandeAuditEnergie()->getJustificatifPropriete();
        $documentKBIS = $demande->getDemandeAuditEnergie()->getPieceComplement();
        $documentAI = $demande->getDemandeAuditEnergie()->getAvisImposition();
        $documentAIConjoint = $demande->getDemandeAuditEnergie()->getAvisImpositionConjoint();
        $documentJPAlt = $demande->getDemandeAuditEnergie()->getJustificatifProprieteAlt();
        $documentKBISAlt = $demande->getDemandeAuditEnergie()->getPieceComplementAlt();
        $documentAIAlt = $demande->getDemandeAuditEnergie()->getAvisImpositionAlt();
        $auditeur = $demande->getDemandeAuditEnergie()->getAuditeurId();

        /* /////////////////////////////////////////////////////////////////
                                UPDATE INSTRUCTION
        ///////////////////////////////////////////////////////////////// */
        $isNbPersFoyer = ((int)($demande->getDemandeAuditEnergie()->getNbPersFoyer()) != $nbPersFoyerOld) ? true : false;
        $isRevenuFoyer = (trim($demande->getDemandeAuditEnergie()->getRevenu3()) != $revenuFoyerOld) ? true : false;

        $instructionData = $this->updateInstruction(
            $demande->getId(),
            $demande->getType(),
            $beneficiaire->getType(),
            $documentJP,
            $documentKBIS,
            $documentAI,
            $documentAIConjoint,
            $isNbPersFoyer,
            $isRevenuFoyer
        );

        if (empty($arrayDemandeStatutKeep)
            || (!empty($arrayDemandeStatutKeep) && !in_array($demande->getStatutId(), $arrayDemandeStatutKeep))
        ) {
            /* /////////////////////////////////////////////////////////////////
                                    SET DEMANDE STATUS
            ///////////////////////////////////////////////////////////////// */
            $participationSARE = $this->demande_Repository->findParticipationSAREByLogementId($demande->getLogementId());

            /* /////////////////////////////////////////////////////////////////
                            CALCUL REVENU FISCAL DE REFERENCE
            ///////////////////////////////////////////////////////////////// */
            $nbPersFoyer = $demande->getDemandeAuditEnergie()->getNbPersFoyer();
            $revenuReference = $demande->getDemandeAuditEnergie()->getRevenu3();

            $checkSAREDemandeAudit = $this->checkSAREDemandeAuditEtTravaux(
                null,
                $participationSARE,
                $nbPersFoyer,
                $revenuReference
            );

            if (empty($checkSAREDemandeAudit)) {
                $statut = $this->searchStatutRefus();
                // On initie le Motif refus à celui de motif refus ANAH car si checkSAREDemandeAuditEtTravaux() a renovyé false
                //  alors nous sommes ici dû au "revenu fiscal de référence du foyer" dépassant le "plafond de l'Anah"
                $demande->setMotifRefus(self::MOTIF_REFUS_ANAH);
            } else {
                $isDoublon = $this->checkDoublon(
                    $type,
                    $beneficiaire->getNom(),
                    $beneficiaire->getPrenom(),
                    $logement->getCodePostal(),
                    $logement->getVille()
                );
                if (true != $isDoublon) {
                    $statut = $this->searchStatutForDemandeAuditEnergie(
                        $instructionData['conformiteJP'],
                        $instructionData['conformiteKBIS'],
                        $instructionData['conformiteAI'],
                        $documentJP,
                        $documentKBIS,
                        $documentAI,
                        $documentJPAlt,
                        $documentKBISAlt,
                        $documentAIAlt,
                        $instructionData['instruction'],
                        $beneficiaire->getType(),
                        $auditeur
                    );

                } else {
                    $statut = $this->searchStatutRefus();
                    $demande->setMotifRefus(DemandeServiceFO::MOTIF_REFUS_DOUBLON);
                }
            }

            $demande->setStatutId($statut);
        }

        $this->EM->persist($demande);

        /* /////////////////////////////////////////////////////////////////
            UPDATE BENEFICIAIRE STRUCTURE AND CONSEILLER RATTACHEMENT
        ///////////////////////////////////////////////////////////////// */
        if ($beneficiaire) {
            $beneficiaire->setStructureRattachementId($demande->getDemandeAuditEnergie()->getStructureId());
            $beneficiaire->setConseillerRattachementId($demande->getDemandeAuditEnergie()->getConseillerId());
            $this->EM->persist($beneficiaire);
        }

        $this->EM->flush();

        // MISE A JOUR DEMANDE TYPE MENAGE
        $this->setDemandeTypeMenage($demande, $beneficiaire, []);

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
            $type,
            $userRoles,
            true,
            'Modification demande ' . $demande->getTypeLabel() . ' par ' . $suffixeHistoriqueAction,
            $beneficiaireEmail,
            $beneficiaire->getType(),
            $demande->getDemandeAuditEnergie()->getJustificatifProprieteAlt(),
            $demande->getDemandeAuditEnergie()->getPieceComplementAlt(),
            $demande->getDemandeAuditEnergie()->getAvisImpositionAlt(),
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
            'Votre demande ' . $demande->getTypeLabel() . ' a bien été modifiée.'
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

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/
}

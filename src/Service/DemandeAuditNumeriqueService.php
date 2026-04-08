<?php

namespace App\Service;

use App\Utils\DefaultUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Partenaire_;
use App\Entity\Structure_;
use App\Entity\Structure_conseiller;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;

class DemandeAuditNumeriqueService extends DemandeServiceFO
{
    private LogementService $logementService;
    private UserService $userService;

    public function __construct(
        ANAHService $ANAHService,
        EntityManagerInterface $entityManager,
        DemandeServiceBO $demandeServiceBO,
        HistoriqueService $historiqueService,
        RemboursementService $remboursementService,
        TitreService $titreService,
        TokenStorageInterface $tokenStorage,
        ParameterBagInterface $parameterBag,
        Environment $environment,
        MailerService $mailerService,
        LogementService $logementService,
        UserService $userService
    )  {
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
     * @param UserInterface|null $user
     * @return array
     * @throws Exception
     */
    public function getDataForAddAction(
        Request $request,
                $isFrontOffice,
                $beneficiaireId,
                $logementId,
                $type,
        UserInterface $user = null
    ) {
        $demandeTypeLabel = Demande_::$demandeType[$type];
        $returnData = [
            'isRedirectToRoute' => false,
            'routeName'         => '',
            'routeParams'       => [],
            'formOption'        => [],
            'formDisplay'       => [],
            'beneficiaire'      => null,
            'demande'           => null,
            'auditE'            => null,
            'demandeTypeLabel'  => $demandeTypeLabel,
            'isDoublon'         => null
        ];

        // SECURITE : REDIRECTION TEMPORAIRE CAR SUPPRESSION DE LA CREATION AUDIT NUMERIQUE
        if (Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE == $type) {
            if ($isFrontOffice) {
                /* /////////////////////////////////////////////////////////////////
                                        COTE FRONT OFFICE
                ///////////////////////////////////////////////////////////////// */
                return DefaultUtils::getDataRedirectLogementListFO($beneficiaireId);
            }
            // COTÉ BO AUSSI REDIRECTION MAIS PARAMETRES DIFFERENTS
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

        /* ///////////////////////////////////////////////////////////////////////
                        SEARCH IF IS DEMANDE CREATE POSSIBLE
        /////////////////////////////////////////////////////////////////////// */
        $dataForLogementListAction = $this->logementService->getDataForListAction(
            $isFrontOffice,
            $beneficiaireId,
            $user?->getId()
        );
        $dataForDemandeActionByLogement = $this->logementService->getDataForDemandeAction($dataForLogementListAction)[$logementId] ? $this->logementService->getDataForDemandeAction($dataForLogementListAction)[$logementId] : null;
        if (empty($dataForDemandeActionByLogement['demandeMiseAJourAuditEIsAddAction'])) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                'La création d\'une demande ' . $demandeTypeLabel . ' est impossible'
            );

            if ($isFrontOffice) {
                /* /////////////////////////////////////////////////////////////////
                                        COTE FRONT OFFICE
                ///////////////////////////////////////////////////////////////// */
                return DefaultUtils::getDataRedirectLogementListFO($beneficiaireId);
            }
            return DefaultUtils::getDataRedirectConseillerLogementListBO($beneficiaireId);
        }

        /* *****************************************************************
                    S E C U R I T Y   R E T O U R   A R R I E R E
        ***************************************************************** */
        if (true == $request->getSession()->get($logementId . 'timestamp_1')) {

            if ($isFrontOffice) {
                /* /////////////////////////////////////////////////////////////////
                                        COTE FRONT OFFICE
                ///////////////////////////////////////////////////////////////// */
                return DefaultUtils::getDataRedirectDemandeListFO($beneficiaireId);
            }
            return DefaultUtils::getDataRedirectConseillerDemandeListBO($beneficiaireId);
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
            }
            return DefaultUtils::getDataRedirectConseillerLogementListBO($beneficiaireId);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->logementRepository->find($logementId);

        /* //////////////////////////////////////////////////////////////////
                             CHECK DOUBLON AUDIT ENERGIE
        /////////////////////////////////////////////////////////////////// */
        $isDoublon = $this->checkDoublon(
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            $beneficiaire->getNom(),
            $beneficiaire->getPrenom(),
            $logement->getCodePostal(),
            $logement->getVille()
        );

        /* /////////////////////////////////////////////////////////////////
                                GET AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $auditE = $this->demande_auditEnergieRepository->findOneByLogementAndType(
            $logementId,
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE
        );

        if (Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE == $type) {

            /* //////////////////////////////////////////////////////////////////////////
                  CHECK IF AUDITEUR IS ENABLED IN DEMANDE AUDIT ENERGETIQUE ET SCENARIOS
            /////////////////////////////////////////////////////////////////////////// */
            if (empty($auditE['partenaireStatutEnabled'])) {
                if ($isFrontOffice) {
                    /* /////////////////////////////////////////////////////////////////
                                            COTE FRONT OFFICE
                    ///////////////////////////////////////////////////////////////// */
                    return DefaultUtils::getDataRedirectLogementListFO($beneficiaireId);
                }
                return DefaultUtils::getDataRedirectConseillerLogementListBO($beneficiaireId);
            }
        }
        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $formOption = [];
        $formDisplay = [];
        if (true == $isDoublon) {
            $beneficiaireData = $this->beneficiaireRepository->findAllCustomById($beneficiaireId);

            // STRUCTURE
            if ($beneficiaireData['structureRattachementId'] && $beneficiaireData['structureIdentificationRattachementNom']) {
                $formOption[] = $beneficiaireData['structureRattachementId'];
                $formDisplay[] = $beneficiaireData['structureIdentificationRattachementNom'];
            } else {
                $formOption[] = '';
                $formDisplay[] = 'Non renseigné';
            }

            // CONSEILLER
            if ($beneficiaireData['structureConseillerRattachementId']
                && $beneficiaireData['structureConseillerRattachementNom']
                && $beneficiaireData['structureConseillerRattachementPrenom']) {
                $formOption[] = $beneficiaireData['structureConseillerRattachementId'];
                $formDisplay[] = $beneficiaireData['structureConseillerRattachementPrenom']
                    . ' ' . $beneficiaireData['structureConseillerRattachementNom'];
            } else {
                $formOption[] = '';
                $formDisplay[] = 'Non renseigné';
            }

            // AUDITEUR
            if ($beneficiaireData['partenaireIdentificationAuditeurId'] && $beneficiaireData['partenaireIdentificationAuditeur']) {
                $formOption[] = $beneficiaireData['partenaireIdentificationAuditeurId'];
                $formDisplay[] = $beneficiaireData['partenaireIdentificationAuditeur'];
            } else {
                $formOption[] = '';
                $formDisplay[] = 'Non renseigné';
            }
        } else {

            // Get structure name
            $structure = $this->structure_Repository->findSlugById($beneficiaire->getStructureRattachementId());

            // Get conseiller name
            $conseiller = $this->structure_conseillerRepository->findSlugById($beneficiaire->getConseillerRattachementId());

            // Get auditeur name
            /**
             * @var Partenaire_ $partenaire
             */
            $partenaire = (!empty($auditE['auditeur_id'])) ? $this->partenaire_Repository->find($auditE['auditeur_id']) : null;

            // STRUCTURE
            if (!empty($structure)) {
                $formOption[] = $structure['id'];
                $formDisplay[] = $structure['nom'];
            } else {
                $formOption[] = '';
                $formDisplay[] = 'Non renseigné';
            }

            // CONSEILLER
            if (!empty($conseiller)) {
                $formOption[] = $conseiller['id'];
                $formDisplay[] = $conseiller['prenom'].' '.$conseiller['nom'];
            } else {
                $formOption[] = '';
                $formDisplay[] = 'Non renseigné';
            }

            // AUDITEUR
            if ($partenaire) {
                $formOption[] = $partenaire->getId();
                if ('' != $partenaire->getPartenaireIdentification()) {
                    $formDisplay[] = $partenaire->getPartenaireIdentification()->getRaisonSociale();
                } else {
                    $formDisplay[] = 'Non renseigné';
                }
            } else {
                $formOption[] = '';
                $formDisplay[] = 'Non renseigné';
            }
        }

        $demande = new Demande_();
        $demande->setAuteurCreation($user->getUsername());
        $demande->setAuteurModif($user->getUsername());

        $returnData['formOption'] = $formOption;
        $returnData['formDisplay'] = $formDisplay;
        $returnData['beneficiaire'] = $beneficiaire;
        $returnData['demande'] = $demande;
        $returnData['auditE'] = $auditE;
        $returnData['isDoublon'] = $isDoublon;

        return $returnData;
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @param $logementId
     * @param Demande_ $demande
     * @param $type
     * @param $userRoles
     * @param $isDoublon
     * @param $auditE
     * @return array
     * @throws Exception
     */
    public function manageAndGetDataForAddActionSubmitted(
        Request $request,
                $isFrontOffice,
        Beneficiaire $beneficiaire,
        $logementId,
        Demande_ $demande,
        $type,
        $userRoles = [],
        $isDoublon = null,
        $auditE = null
    ) {
        $demandeTypeLabel = Demande_::$demandeType[$type];

        $demande->setType($type);
        $demande->setBeneficiaireId($beneficiaire->getId());
        $demande->setLogementId($logementId);

        /* /////////////////////////////////////////////////////////////////
                                SET DEMANDE STATUS
        ///////////////////////////////////////////////////////////////// */
        $statut = null;
        $envoiEmailDansHistoriqueSave = false;

        $participationSARE = $this->demande_Repository->findParticipationSAREByLogementId($logementId);
        if (empty($participationSARE)) {
            // SI EPCI NON PARTICIPATION SARE => DEMANDE REFUSEE
            $statut = $this->searchStatutRefus();
            $demande->setMotifRefus(DemandeServiceFO::MOTIF_REFUS_NON_PARTICIPATION_SARE);
        } else {
            $envoiEmailDansHistoriqueSave = true;
            $statut = (true == $isDoublon) ? $this->searchStatutForNoDateCP() : $this->searchStatutForDemandeAuditNumerique($auditE['dateCP_id'], $auditE['statut_id']);
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
            $beneficiaire->setStructureRattachementId($demande->getDemandeAuditNumerique()->getStructureId());
            $beneficiaire->setConseillerRattachementId($demande->getDemandeAuditNumerique()->getConseillerId());
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
        $suffixeHistoriqueAction = ($isFrontOffice) ? 'Bénéficiaire' : 'Conseiller';

        $historique = $this->historiqueService->save(
            $demande->getId(),
            $statut,
            $type,
            $userRoles,
            $envoiEmailDansHistoriqueSave,
            'Création demande ' . $demandeTypeLabel . ' par le ' . $suffixeHistoriqueAction,
            $beneficiaireEmail,
            $beneficiaire->getType(),
            null,
            null,
            null,
            true
        );

        /* /////////////////////////////////////////////////////////////////
                ENVOI EMAIL + HISTORISATION EMAIL :
                REFUS DU A EPCI NON PARTICIPATION SARE
        ///////////////////////////////////////////////////////////////// */

        if (empty($participationSARE)) {
            $this->sendEmailRefusNonParticipationSARE(
                $historique,
                $beneficiaireEmail,
                $demande->getMotifRefus()
            );
        }

        $request->getSession()->set($logementId . 'timestamp_1', true);
        $request->getSession()->getFlashBag()->add(
            'success',
            'Votre demande ' . $demandeTypeLabel. ' a bien été prise en compte.'
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
    ) {
        $totalCommentaire = null;

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $rowDemande = $this->demande_auditNumeriqueRepository->findByIdCustom($demandeId);

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
                'roles'    => $user->getRoles(),
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

        return [
            'rowDemande'       => $rowDemande,
            'totalCommentaire' => $totalCommentaire
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
    ) {
        $returnData = [
            'isRedirectToRoute' => false,
            'routeName'         => '',
            'routeParams'       => [],
            'formOption'        => [],
            'formDisplay'       => [],
            'beneficiaire'      => null,
            'demande'           => null,
            'isDoublon'         => null
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
                'roles'    => $user->getRoles(),
                'username' => $user->getUsername()
            ];
            $this->demandeServiceBO->checkAccesByRole($demande, $option);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->logementRepository->find($demande->getLogementId());

        /* //////////////////////////////////////////////////////////////////
                             CHECK DOUBLON AUDIT ENERGIE
        /////////////////////////////////////////////////////////////////// */
        $isDoublon = $this->checkDoublon(
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            $beneficiaire->getNom(),
            $beneficiaire->getPrenom(),
            $logement->getCodePostal(),
            $logement->getVille()
        );

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $formOption = [];
        $formDisplay = [];

        // Get structure name
        $structureId = $demande->getDemandeAuditNumerique()->getStructureId();
        /**
         * @var Structure_ $structure
         */
        $structure = !empty($structureId) ? $this->structure_Repository->find($structureId) : null;

        // Get conseiller name
        $conseillerId = $demande->getDemandeAuditNumerique()->getConseillerId();
        /**
         * @var Structure_conseiller $conseiller
         */
        $conseiller = !empty($conseillerId) ? $this->structure_conseillerRepository->find($conseillerId) : null;

        // Get auditeur name
        $partenaireId = $demande->getDemandeAuditNumerique()->getAuditeurId();
        /**
         * @var Partenaire_ $auditeur
         */
        $auditeur = !empty($partenaireId) ? $this->partenaire_Repository->find($partenaireId) : null;

        $formOption[] = $structureId;
        $formDisplay[] = ($structure && $structure->getStructureIdentification())  ? $structure->getStructureIdentification()->getNom() : 'Non renseigné';

        $formOption[] = $conseillerId;
        $formDisplay[] = ($conseiller) ? $conseiller->getPrenom() . ' ' . $conseiller->getNom() : 'Non renseigné';

        $formOption[] = $partenaireId;
        $formDisplay[] = ($auditeur && $auditeur->getPartenairEIdentification()) ? $auditeur->getPartenairEIdentification()->getRaisonSociale() : 'Non renseigné';

        $returnData['formOption'] = $formOption;
        $returnData['formDisplay'] = $formDisplay;
        $returnData['beneficiaire'] = $beneficiaire;
        $returnData['demande'] = $demande;
        $returnData['isDoublon'] = $isDoublon;
        $returnData['logement'] = $logement;

        if (!empty($isWithParamDemandeData)) {
            $returnData['demandeData'] = [
                'type'        => $demande->getType(),
                'auditN'      => $demande,
                'formDisplay' => $formDisplay,
                'isDoublon'   => $isDoublon
            ];
        }

        return $returnData;
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @param Demande_ $demande
     * @param $userRoles
     * @param $isDoublon
     * @param $arrayDemandeStatutKeep
     * @return array
     * @throws Exception
     */
    public function manageAndGetDataForEditActionSubmitted(
        Request $request,
                $isFrontOffice,
        Beneficiaire $beneficiaire,
        Demande_ $demande,
        $userRoles = [],
        $isDoublon = null,
        $arrayDemandeStatutKeep = []
    ) {

        $demande->setDateModif(new \DateTime());
        $demande->setAuteurModif($_SESSION['login']->getUsername());

        /* /////////////////////////////////////////////////////////////////
                                SET DEMANDE STATUS
        ///////////////////////////////////////////////////////////////// */
        // Get Audit Energie
        $auditE = $this->demande_auditEnergieRepository->findOneByLogementAndType(
            $demande->getLogementId(),
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE
        );

        if (empty($arrayDemandeStatutKeep)
            || (!empty($arrayDemandeStatutKeep) && !in_array($demande->getStatutId(), $arrayDemandeStatutKeep))
        ) {
            $statut = (true == $isDoublon) ? $this->searchStatutForNoDateCP() : $this->searchStatutForDemandeAuditNumerique($auditE['dateCP_id'], $auditE['statut_id']);
            if ($statut) {
                $demande->setStatutId($statut);
            }
        }

        // MISE A JOUR DEMANDE TYPE MENAGE
        $this->setDemandeTypeMenage($demande, $beneficiaire, []);

        $this->EM->persist($demande);

        /* /////////////////////////////////////////////////////////////////
            UPDATE BENEFICIAIRE STRUCTURE AND CONSEILLER RATTACHEMENT
        ///////////////////////////////////////////////////////////////// */
        if ($beneficiaire) {
            $beneficiaire->setStructureRattachementId($demande->getDemandeAuditNumerique()->getStructureId());
            $beneficiaire->setConseillerRattachementId($demande->getDemandeAuditNumerique()->getConseillerId());
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
        if ($isFrontOffice) {
            $suffixeHistoriqueAction = 'le Bénéficiaire';
        } else {
            $suffixeHistoriqueAction = !empty($arrayDemandeStatutKeep) ? 'la Région' : 'le Conseiller';
        }
        $this->historiqueService->save(
            $demande->getId(),
            $demande->getStatutId(),
            $demande->getType(),
            $userRoles,
            true,
            'Modification demande ' . $demande->getTypeLabel() . ' par ' . $suffixeHistoriqueAction,
            $beneficiaire->getEmail(),
            $beneficiaire->getType()
        );

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

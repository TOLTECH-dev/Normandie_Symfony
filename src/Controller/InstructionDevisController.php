<?php

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\Demande_travaux;
use App\Entity\Instruction_;
use App\Entity\Logement;
use App\Entity\Partenaire_;
use App\Entity\Structure_;
use App\Service\AdminService;
use App\Service\ANAHService;
use App\Service\DemandeServiceBO;
use App\Service\HistoriqueService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\FicheTechnique;
use App\Form\FicheTechniqueType;
use App\Service\TitreService;
use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux_devis;
use App\Form\Demande_travaux_devisType;
use App\Service\DemandeServiceFO;
use App\Entity\User;


class InstructionDevisController extends AbstractController
{
    private DemandeServiceFO $demandeServiceFO;
    private DemandeServiceBO $demandeServiceBO;
    private AdminService $adminService;
    private HistoriqueService $historiqueService;
    private TitreService $titreService;
    private ANAHService $ANAHService;
    private EntityManagerInterface $entityManager;
    
    public function __construct(
        DemandeServiceFO $demandeServiceFO,
        DemandeServiceBO $demandeServiceBO,
        AdminService $adminService,
        HistoriqueService $historiqueService,
        TitreService $titreService,
        ANAHService $ANAHService,
        EntityManagerInterface $entityManager
    )
    {
        $this->demandeServiceFO = $demandeServiceFO;
        $this->demandeServiceBO = $demandeServiceBO;
        $this->adminService = $adminService;
        $this->historiqueService = $historiqueService;
        $this->titreService = $titreService;
        $this->ANAHService = $ANAHService;
        $this->entityManager = $entityManager;
    }

    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_ADMIN')")]
    public function examine(Request $request, string $devisId, string $logementId) : Response
    {
        /* *****************************************************************
                S E C U R I T Y   D E V I S   (R E) E X A M I N E
        ***************************************************************** */
        $this->demandeServiceFO->checkInstructionTechniqueExamineReexamine(
            'devis',
            $devisId,
            $logementId,
            null
        );
        
        $option = array(
            'roles'     => $this->getUser()->getRoles(),
            'username'  => $this->getUser()->getUsername()
        );

        /* /////////////////////////////////////////////////////////////////
                                GET  DEVIS
        ///////////////////////////////////////////////////////////////// */
        $repo = $this->entityManager->getRepository(Demande_travaux_devis::class);
        $devis = $repo->find($devisId);

        /* /////////////////////////////////////////////////////////////////
                        GET DEMANDE TRAVAUX / AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->entityManager->getRepository(Demande_::class);
        /**
         * @var Demande_ $demandeTravaux
         */
        $demandeTravaux = $repo_demande->findOneBy(
            array(
                'logement_id' => $logementId,
                'type'        => Demande_::DEMANDE_TRAVAUX_TYPE
            ), array('id' => 'DESC')
        );
        $demandeAuditE = $repo_demande->findOneBy(
            array(
                'logement_id' => $logementId,
                'type'        => Demande_::DEMANDE_AUDIT_ENERGIE_TYPE
            ), array('id' => 'DESC')
        );

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesByRole($demandeTravaux, $option);

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE TRAVAUX ENTITY
        ///////////////////////////////////////////////////////////////// */
        $repo_demandeTravaux = $this->entityManager->getRepository(Demande_travaux::class);
        $demandeTravaux_entity = $repo_demandeTravaux->findOneBy(array(
            'id' => $demandeTravaux->getDemandeTravaux(),
        ));

        /* *****************************************************************
                                S E C U R I T Y
        ***************************************************************** */
        if ($this->adminService->isGranted('ROLE_CONSEILLER', $this->getUser())) {
            $userId_session = $_SESSION['login']->getUsername();
            $format_userId_session = substr($userId_session, 1);

            $repo_structure = $this->entityManager->getRepository(Structure_::class);
            $structure_id = $repo_structure->findByConseillerId((int)$format_userId_session);
            $user_id_current = $structure_id['id'];

            $userId_admin = $demandeTravaux_entity->getStructureId();

            $this->adminService->checkAdmin($user_id_current, $userId_admin);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireId = $devis->getBeneficiaireId();
        $repo_beneficiaire = $this->entityManager->getRepository(Beneficiaire::class);
        $beneficiaire = $repo_beneficiaire->findOneBy(array(
            'id' => $beneficiaireId
        ));

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $repo_logement = $this->entityManager->getRepository(Logement::class);
        $logement = $repo_logement->findOneBy(array(
            'id' => $logementId
        ));
        $logementSituation = $logement->getSituation();

        /* /////////////////////////////////////////////////////////////////
                                GET FICHE TECHNIQUE
        ///////////////////////////////////////////////////////////////// */
        $repo_ficheTechnique = $this->entityManager->getRepository(FicheTechnique::class);
        $ficheTechnique = $repo_ficheTechnique->findOneBy(array(
            'id' => $demandeTravaux_entity->getFicheTechniqueId(),
        ));

        /* /////////////////////////////////////////////////////////////////
                        GET AUDITEUR FROM AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $repo_partenaire = $this->entityManager->getRepository(Partenaire_::class);
        if ($devis->getAuditeurId()) $auditeur = $repo_partenaire->find($devis->getAuditeurId());
        else $auditeur = null;

        /* /////////////////////////////////////////////////////////////////
                        GET INSTRUCTION DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        $repo_instruction = $this->entityManager->getRepository(Instruction_::class);
        $instruction = $repo_instruction->findOneBy(array(
            'demande_id' => $demandeTravaux->getId()
        ));

        /* /////////////////////////////////////////////////////////////////
                        CALCUL REVENU FISCAL DE REFERENCE
        ///////////////////////////////////////////////////////////////// */
        $demandeTravaux_nbPersFoyer = $demandeTravaux->getDemandeTravaux()->getNbPersFoyer();
        $demandeTravaux_revenuReference = $demandeTravaux->getDemandeTravaux()->getRevenu3();

        $ANAH = $this->ANAHService->findPlafond($demandeTravaux_nbPersFoyer);


        $informationANAH = '';
        if ($demandeTravaux_revenuReference < $ANAH) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_INFERIEUR_ANAH_KEY;
        } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH*2)) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_1_ET_2_FOIS_ANAH_KEY;
        } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH*4)) {
            $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_2_ET_4_FOIS_ANAH_KEY;
        }

        $isDemandeTravauxAudit = (!empty($demandeTravaux->getDemandeTravaux()) && $demandeTravaux->getDemandeTravaux()->getAudit()) ? true : false;

        $rowLastDemandeTravauxDevisRemboursementTermine = $repo_demande->findLastDemandeTravauxDevisRemboursementTermine($beneficiaireId, $logementId);
        $isLastDemandeTravauxRembourseSortieDePassoire = (!empty($rowLastDemandeTravauxDevisRemboursementTermine) && (Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE == $rowLastDemandeTravauxDevisRemboursementTermine['demandeTravauxDevisNiveau']));

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $formOption['isShowDevisCustomColumns'] = true; // isShowDevisCustomColumns (Biosource, ...)
        $formOption['ecoPTZBanque'] = $devis->getEcoPTZBanque();
        $formOption['autrePretBanque'] = $devis->getAutrePretBanque();
        $formOption['isDemandeTravauxAudit'] = $isDemandeTravauxAudit;
        $formOption['informationANAH'] = $informationANAH;
        // Is required niveau field Option
        $formOption['isRequiredNiveauFieldOption'] = true;
        $formOption['typeMaPrimeRenovNom'] = $devis->getTypeMaPrimeRenovNom();
//        $formOption['typeMaPrimeRenovSereniteNom'] = $devis->getTypeMaPrimeRenovSereniteNom();
        $formOption['isBanqueAccess'] = !empty($devis->getIsBanqueAccess()) ? 1 : 0;
        $formOption['auditAlt'] = $devis->getAuditAlt();
        $formOption['niveau'] = $devis->getNiveau();
        $formOption['isLastDemandeTravauxRembourseSortieDePassoire'] = $isLastDemandeTravauxRembourseSortieDePassoire;

        $form = $this->createForm(Demande_travaux_devisType::class, $devis, [
            'trait_choices' => $formOption
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $devis->setDateModif(new \Datetime());
            $devis->setAuteurModif($_SESSION['login']->getUsername());

            $devis->setStatutInstruction('1');

            /* /////////////////////////////////////////////////////////////////
                                SET DEMANDE STATUT
            ///////////////////////////////////////////////////////////////// */
            if ($instruction) {
                $conformiteJP = explode(" | ", $instruction->getInstructionTravaux()->getJPconformite());
                $conformiteKBIS = explode(" | ", $instruction->getInstructionTravaux()->getKBISconformite());
                $conformiteAI = explode(" | ", $instruction->getInstructionTravaux()->getAIconformite());
            } else {
                $conformiteJP = array('0');
                $conformiteKBIS = array('0');
                $conformiteAI = array('0');
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

                    if ($ficheTechnique) {
                        $ficheTechniqueStatut = $ficheTechnique->getStatutFicheTechnique();
                        $ficheTechniqueIsValidationConseiller = $ficheTechnique->getIsValidationConseiller();
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
                $instructionDevis = null;
            }

            $statut = $this->demandeServiceFO->searchStatutForDemandeTravauxAndDevis(
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

            $this->entityManager->persist($devis);
            $this->entityManager->persist($demandeTravaux);
            $this->entityManager->flush();

            // MISE A JOUR DEMANDE STATUT DESCRIPTION
            $demandeTravaux->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demandeTravaux->getId()));
            $this->entityManager->persist($demandeTravaux);
            $this->entityManager->flush();

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $demandeId = $demandeTravaux->getId();
            $userRoles = $this->getUser()->getRoles();
            $beneficiaireEmail = $beneficiaire->getEmail();
            $beneficiaireType = $beneficiaire->getType();
            
            $this->historiqueService->save(
                $demandeId,
                $statut,
                Demande_::DEMANDE_TRAVAUX_TYPE,
                $userRoles,
                true,
                'Instruction de Travaux Devis',
                $beneficiaireEmail,
                $beneficiaireType,
                $demandeTravaux_justificatifProprieteAlt,
                $demandeTravaux_pieceComplementAlt,
                $demandeTravaux_avisImpositionAlt
            );

            $request->getSession()->getFlashBag()->add(
                'success',
                'Votre instruction Travaux Devis a bien été prise en compte.'
            );

            return $this->redirectToRoute('demande_devis_list', array());
        }

        /* /////////////////////////////////////////////////////////////////////////////////////
            RECUPERATION DU MONTANT Travaux Niveau3 BBC (Aide region)
        ///////////////////////////////////////////////////////////////////////////////////// */
        $montantTravauxNiveau3BBC = $this->titreService->getMontantTravauxNiveau3BBC($demandeAuditE);

        return $this->render('BackOffice/Demande/Devis/examine.html.twig', array(
            'form'                     => $form->createView(),
            'ficheTechnique'           => $ficheTechnique,
            'devis'                    => $devis,
            'travaux'                  => $demandeTravaux,
            'auditE'                   => (!empty($demandeAuditE) && $demandeAuditE->getStatutId() != Demande_statut::STATUS_15) ? $demandeAuditE : null,
            'auditeur'                 => $auditeur,
            'informationANAH'          => $informationANAH,
            'montantTravauxNiveau3BBC' => $montantTravauxNiveau3BBC
        ));
    }

    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function examineFicheTechnique(Request $request, string $devisId) : Response
    {
        /* ***************************************************************************
            S E C U R I T Y   F I C H E   T E C H N I Q U E   (R E) E X A M I N E
        *************************************************************************** */
        $this->demandeServiceFO->checkInstructionTechniqueExamineReexamine(
            'fiche_technique',
            $devisId,
            null,
            null
        );
        
        $option = array(
            'roles'     => $this->getUser()->getRoles(),
            'username'  => $this->getUser()->getUsername()
        );

        /* /////////////////////////////////////////////////////////////////
                                GET DEVIS
        ///////////////////////////////////////////////////////////////// */
        $repo = $this->entityManager->getRepository(Demande_travaux_devis::class);
        $devis = $repo->find($devisId);

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE TRAVAUX ENTITY
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->entityManager->getRepository(Demande_travaux::class);
        $demandeTravaux_entity = $repo_demande->findOneBy(array(
            'travauxDevis_id' => $devisId
        ));

        /* *****************************************************************
                                S E C U R I T Y
        ***************************************************************** */
        if (!$this->adminService->isGranted('ROLE_ADMIN', $this->getUser()) && !$this->adminService->isGranted('ROLE_CLIENT', $this->getUser())) {
            $userId_session = $_SESSION['login']->getUsername();
            $format_userId_session = substr($userId_session, 1);

            $userId_admin = -1;
            $user_id_current = -2;
            if ($this->adminService->isGranted('ROLE_AUDITEUR', $this->getUser())) {
                $user_id_current = (int)$format_userId_session;
                $userId_admin = $devis->getAuditeurId();
            } elseif ($this->adminService->isGranted('ROLE_CONSEILLER', $this->getUser())) {
                $repo_structure = $this->entityManager->getRepository(Structure_::class);
                $structure_id = $repo_structure->findByConseillerId((int)$format_userId_session);
                $user_id_current = $structure_id['id'];

                $userId_admin = $demandeTravaux_entity->getStructureId();
            }
            $this->adminService->checkAdmin($user_id_current, $userId_admin);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo_beneficiaire = $this->entityManager->getRepository(Beneficiaire::class);
        $beneficiaire = $repo_beneficiaire->findOneBy(array(
            'id' => $devis->getBeneficiaireId()
        ));

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $repo_logement = $this->entityManager->getRepository(Logement::class);
        $logement = $repo_logement->findOneBy(array(
            'id' => $devis->getLogementId()
        ));

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        $repo_demande_ = $this->entityManager->getRepository(Demande_::class);
        /**
         * @var Demande_ $demandeTravaux
         */
        $demandeTravaux = $repo_demande_->findOneBy(array(
            'demande_travaux' => $demandeTravaux_entity->getId()
        ));

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesByRole($demandeTravaux, $option);

        /* /////////////////////////////////////////////////////////////////
                         CHECK FICHE TECHNIQUE ACCESS BY STATUT
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesFicheTechniqueByStatut($demandeTravaux->getStatutId());

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $demandeAuditE = $repo_demande_->findOneBy(array(
            'logement_id' => $logement->getId(),
            'type'        => Demande_::DEMANDE_AUDIT_ENERGIE_TYPE
        ));

        /* /////////////////////////////////////////////////////////////////
                            GET INSTRUCTION DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        $repo_instruction = $this->entityManager->getRepository(Instruction_::class);
        $instruction = $repo_instruction->findOneBy(array(
            'demande_id' => $demandeTravaux->getId()
        ));

        /* /////////////////////////////////////////////////////////////////
                            GET DATA FORM FIELD PATHOLOGIE
        ///////////////////////////////////////////////////////////////// */
        $repo_ficheTechnique = $this->entityManager->getRepository(FicheTechnique::class);
        $pathologie = $repo_ficheTechnique->search('pathologie');
        $array_pathologie = array();
        foreach ($pathologie as $item) {
            $array_pathologie[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                            GET DATA FORM FIELD ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $energie = $repo_ficheTechnique->search('energie');

        $array_energie = array();
        foreach ($energie as $item) {
            $array_energie[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                            GET DATA FORM FIELD VENTILATION
        ///////////////////////////////////////////////////////////////// */
        $ventilation = $repo_ficheTechnique->search('ventilation');

        $array_ventilation = array();
        foreach ($ventilation as $item) {
            $array_ventilation[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $isShowBlocValidationConseiller = (
            in_array(User::PARAM_ROLE_ADMIN, $this->getUser()->getRoles())
            || in_array(User::PARAM_ROLE_CONSEILLER, $this->getUser()->getRoles())
        );
        $optionFicheTechnique = array(
            $array_pathologie,
            $array_energie,
            $array_ventilation,
            null,
            $isShowBlocValidationConseiller,
            FicheTechnique::EXAMINE_FICHE_TECHNIQUE_PART_DEMANDE
        );

        $formOption = array(
            'optionFicheTechnique'  => $optionFicheTechnique
        );

        $ficheTechnique = new FicheTechnique();

        $form = $this->createForm(FicheTechniqueType::class, $ficheTechnique, array(
            'trait_choices' => $formOption
        ));
        $form->remove('ficheTechnique_finChantier');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /* /////////////////////////////////////////////////////////////////
                                    SET DEVIS
            ///////////////////////////////////////////////////////////////// */
            if (
                true == $ficheTechnique->getFicheTechniqueInitial()->getInformationValidation() &&
                true == $ficheTechnique->getFicheTechniqueBBC()->getInformationValidation() &&
                true == $ficheTechnique->getFicheTechniquePrescription()->getInformationValidation()
            ) {
                $ficheTechnique->setStatutFicheTechnique('1');
            } else {
                $ficheTechnique->setStatutFicheTechnique('0');
            }

            /* /////////////////////////////////////////////////////////////////
                                    SET DEMANDE STATUT
            ///////////////////////////////////////////////////////////////// */
            if ($instruction && $instruction->getInstructionTravaux()) {
                $conformiteJP = explode(" | ", $instruction->getInstructionTravaux()->getJPconformite());
                $conformiteKBIS = explode(" | ", $instruction->getInstructionTravaux()->getKBISconformite());
                $conformiteAI = explode(" | ", $instruction->getInstructionTravaux()->getAIconformite());
            } else {
                $conformiteJP = array('0');
                $conformiteKBIS = array('0');
                $conformiteAI = array('0');
                $instruction = null;
            }

            $instructionDevis = null;
            $ficheTechniqueStatut = null;
            $ficheTechniqueIsValidationConseiller = null;
            if ($demandeTravaux_entity) {
                $demandeTravaux_justificatifPropriete = $demandeTravaux_entity->getJustificatifPropriete();
                $demandeTravaux_pieceComplement = $demandeTravaux_entity->getPieceComplement();
                $demandeTravaux_avisImposition = $demandeTravaux_entity->getAvisImposition();
                $demandeTravaux_justificatifProprieteAlt = $demandeTravaux_entity->getJustificatifProprieteAlt();
                $demandeTravaux_pieceComplementAlt = $demandeTravaux_entity->getPieceComplementAlt();
                $demandeTravaux_avisImpositionAlt = $demandeTravaux_entity->getAvisImpositionAlt();
                $demandeTravaux_travauxDevisId = $demandeTravaux_entity->getTravauxDevisId();
                $demandeTravaux_audit = $demandeTravaux_entity->getAudit();
                if ($demandeTravaux_travauxDevisId) {
                    $instructionDevis = $devis->getInstructionDossierConforme();

                    if ($ficheTechnique) {
                        $ficheTechniqueStatut = $ficheTechnique->getStatutFicheTechnique();
                        $ficheTechniqueIsValidationConseiller = $ficheTechnique->getIsValidationConseiller();
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

            $statut = $this->demandeServiceFO->searchStatutForDemandeTravauxAndDevis(
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

            $this->entityManager->persist($ficheTechnique);
            $this->entityManager->persist($devis);
            $this->entityManager->persist($demandeTravaux);
            $this->entityManager->flush();

            // MISE A JOUR DEMANDE STATUT DESCRIPTION
            $demandeTravaux->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demandeTravaux->getId()));
            $this->entityManager->persist($demandeTravaux);
            $this->entityManager->flush();

            // Set Fiche Technique id dans Demande Travaux
            $demandeTravaux_entity->setFicheTechniqueId($ficheTechnique->getId());
            $this->entityManager->persist($demandeTravaux_entity);
            $this->entityManager->flush();

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $userRoles = $this->getUser()->getRoles();
            $demandeId = $demandeTravaux->getId();
            $beneficiaireEmail = $beneficiaire->getEmail();
            $beneficiaireType = $beneficiaire->getType();
            
            $this->historiqueService->save(
                $demandeId,
                $statut,
                Demande_::DEMANDE_TRAVAUX_TYPE,
                $userRoles,
                true,
                'Instruction de la fiche technique',
                $beneficiaireEmail,
                $beneficiaireType,
                $demandeTravaux_justificatifProprieteAlt,
                $demandeTravaux_pieceComplementAlt,
                $demandeTravaux_avisImpositionAlt
            );

            $request->getSession()->getFlashBag()->add(
                'success',
                'Votre Fiche Technique a bien été prise en compte.'
            );

            return $this->redirectToRoute('demande_devis_list', array());
        }

        return $this->render('BackOffice/Demande/Devis/examineFicheTechnique.html.twig', array(
            'form'              => $form->createView(),
            'devis'             => $devis,
            'travaux'           => $demandeTravaux,
            'ficheTechnique'    => $ficheTechnique,
        ));
    }


    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function reexamineFicheTechnique(Request $request, string $devisId, string $ficheTechniqueId) : Response
    {
        /* ***************************************************************************
            S E C U R I T Y   F I C H E   T E C H N I Q U E   (R E) E X A M I N E
        *************************************************************************** */
        $this->demandeServiceFO->checkInstructionTechniqueExamineReexamine(
            'fiche_technique',
            $devisId,
            null,
            $ficheTechniqueId
        );
        
        $option = array(
            'roles'     => $this->getUser()->getRoles(),
            'username'  => $this->getUser()->getUsername()
        );

        /* /////////////////////////////////////////////////////////////////
                                GET FICHE TECHNIQUE
        ///////////////////////////////////////////////////////////////// */
        $repo = $this->entityManager->getRepository(FicheTechnique::class);
        /**
         * @var FicheTechnique $ficheTechnique
         */
        $ficheTechnique = $repo->find($ficheTechniqueId);

        /* /////////////////////////////////////////////////////////////////
                                GET DEVIS
        ///////////////////////////////////////////////////////////////// */
        $repo = $this->entityManager->getRepository(Demande_travaux_devis::class);
        $devis = $repo->find($devisId);

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE TRAVAUX ENTITY
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->entityManager->getRepository(Demande_travaux::class);
        $demandeTravaux_entity = $repo_demande->findOneBy(array(
            'travauxDevis_id' => $devisId
        ));

        /* *****************************************************************
                                S E C U R I T Y
        ***************************************************************** */
        if (!$this->adminService->isGranted('ROLE_ADMIN', $this->getUser()) && !$this->adminService->isGranted('ROLE_CLIENT', $this->getUser())) {
            $userId_session = $_SESSION['login']->getUsername();
            $format_userId_session = substr($userId_session, 1);

            $userId_admin = -1;
            $user_id_current = -2;
            if ($this->adminService->isGranted('ROLE_AUDITEUR', $this->getUser())) {
                $user_id_current = (int)$format_userId_session;
                $userId_admin = $devis->getAuditeurId();
            } elseif ($this->adminService->isGranted('ROLE_CONSEILLER', $this->getUser())) {
                $repo_structure = $this->entityManager->getRepository(Structure_::class);
                $structure_id = $repo_structure->findByConseillerId((int)$format_userId_session);
                $user_id_current = $structure_id['id'];

                $userId_admin = $demandeTravaux_entity->getStructureId();
            }
            $this->adminService->checkAdmin($user_id_current, $userId_admin);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo_beneficiaire = $this->entityManager->getRepository(Beneficiaire::class);
        $beneficiaire = $repo_beneficiaire->findOneBy(array(
            'id' => $devis->getBeneficiaireId()
        ));

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        $repo_demande_ = $this->entityManager->getRepository(Demande_::class);
        /**
         * @var Demande_ $demandeTravaux
         */
        $demandeTravaux = $repo_demande_->findOneBy(array(
            'demande_travaux' => $demandeTravaux_entity->getId()
        ));

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesByRole($demandeTravaux, $option);

        /* /////////////////////////////////////////////////////////////////
                         CHECK FICHE TECHNIQUE ACCESS BY STATUT
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesFicheTechniqueByStatut($demandeTravaux->getStatutId());

        /* /////////////////////////////////////////////////////////////////
                                GET INSTRUCTION DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        $repo_instruction = $this->entityManager->getRepository(Instruction_::class);
        $instruction = $repo_instruction->findOneBy(array(
            'demande_id' => $demandeTravaux->getId()
        ));

        /* /////////////////////////////////////////////////////////////////
                            GET DATA FORM FIELD PATHOLOGIE
        ///////////////////////////////////////////////////////////////// */
        $repo_ficheTechnique = $this->entityManager->getRepository(FicheTechnique::class);
        $pathologie = $repo_ficheTechnique->search('pathologie');

        $array_pathologie = array();
        foreach ($pathologie as $item) {
            $array_pathologie[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                            GET DATA FORM FIELD ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $energie = $repo_ficheTechnique->search('energie');

        $array_energie = array();
        foreach ($energie as $item) {
            $array_energie[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                            GET DATA FORM FIELD VENTILATION
        ///////////////////////////////////////////////////////////////// */
        $ventilation = $repo_ficheTechnique->search('ventilation');

        $array_ventilation = array();
        foreach ($ventilation as $item) {
            $array_ventilation[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $isShowBlocValidationConseiller = (
            in_array(User::PARAM_ROLE_ADMIN, $this->getUser()->getRoles())
            || in_array(User::PARAM_ROLE_CONSEILLER, $this->getUser()->getRoles())
        );
        $optionFicheTechnique = array(
            $array_pathologie,
            $array_energie,
            $array_ventilation,
            null,
            $isShowBlocValidationConseiller,
            FicheTechnique::EXAMINE_FICHE_TECHNIQUE_PART_DEMANDE
        );

        $formOption = array(
            'optionFicheTechnique'  => $optionFicheTechnique
        );

        $form = $this->createForm(FicheTechniqueType::class, $ficheTechnique, array(
            'trait_choices' => $formOption
        ));
        $form->remove('ficheTechnique_finChantier');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            /* /////////////////////////////////////////////////////////////////
                                    SET DEVIS
            ///////////////////////////////////////////////////////////////// */
            $devis->setDateModif(new \Datetime());
            $devis->setAuteurModif($_SESSION['login']->getUsername());

            if (
                true == $ficheTechnique->getFicheTechniqueInitial()->getInformationValidation() &&
                true == $ficheTechnique->getFicheTechniqueBBC()->getInformationValidation() &&
                true == $ficheTechnique->getFicheTechniquePrescription()->getInformationValidation()
            ) {
                $ficheTechnique->setStatutFicheTechnique('1');
            } else {
                $ficheTechnique->setStatutFicheTechnique('0');
            }

            /* /////////////////////////////////////////////////////////////////
                                    SET DEMANDE STATUT
            ///////////////////////////////////////////////////////////////// */
            if ($instruction && $instruction->getInstructionTravaux()) {
                $conformiteJP = explode(" | ", $instruction->getInstructionTravaux()->getJPconformite());
                $conformiteKBIS = explode(" | ", $instruction->getInstructionTravaux()->getKBISconformite());
                $conformiteAI = explode(" | ", $instruction->getInstructionTravaux()->getAIconformite());
            } else {
                $conformiteJP = array('0');
                $conformiteKBIS = array('0');
                $conformiteAI = array('0');
                $instruction = null;
            }

            $instructionDevis = null;
            $ficheTechniqueStatut = null;
            $ficheTechniqueIsValidationConseiller = null;
            if ($demandeTravaux_entity) {
                $demandeTravaux_justificatifPropriete = $demandeTravaux_entity->getJustificatifPropriete();
                $demandeTravaux_pieceComplement = $demandeTravaux_entity->getPieceComplement();
                $demandeTravaux_avisImposition = $demandeTravaux_entity->getAvisImposition();
                $demandeTravaux_justificatifProprieteAlt = $demandeTravaux_entity->getJustificatifProprieteAlt();
                $demandeTravaux_pieceComplementAlt = $demandeTravaux_entity->getPieceComplementAlt();
                $demandeTravaux_avisImpositionAlt = $demandeTravaux_entity->getAvisImpositionAlt();
                $demandeTravaux_travauxDevisId = $demandeTravaux_entity->getTravauxDevisId();
                $demandeTravaux_audit = $demandeTravaux_entity->getAudit();
                if ($demandeTravaux_travauxDevisId) {
                    $instructionDevis = $devis->getInstructionDossierConforme();
                    $ficheTechniqueStatut = $ficheTechnique->getStatutFicheTechnique();
                    $ficheTechniqueIsValidationConseiller = $ficheTechnique->getIsValidationConseiller();
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

            $statut = $this->demandeServiceFO->searchStatutForDemandeTravauxAndDevis(
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

            $this->entityManager->persist($ficheTechnique);
            $this->entityManager->persist($devis);
            $this->entityManager->persist($demandeTravaux);
            $this->entityManager->flush();

            // MISE A JOUR DEMANDE STATUT DESCRIPTION
            $demandeTravaux->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demandeTravaux->getId()));
            $this->entityManager->persist($demandeTravaux);
            $this->entityManager->flush();

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $userRoles = $this->getUser()->getRoles();
            $demandeId = $demandeTravaux->getId();
            $beneficiaireEmail = $beneficiaire->getEmail();
            $beneficiaireType = $beneficiaire->getType();
            
            $this->historiqueService->save(
                $demandeId,
                $statut,
                Demande_::DEMANDE_TRAVAUX_TYPE,
                $userRoles,
                true,
                'Instruction de la fiche technique',
                $beneficiaireEmail,
                $beneficiaireType,
                $demandeTravaux_justificatifProprieteAlt,
                $demandeTravaux_pieceComplementAlt,
                $demandeTravaux_avisImpositionAlt
            );

            $request->getSession()->getFlashBag()->add(
                'success',
                'Votre Fiche Technique a bien été modifiée.'
            );

            return $this->redirectToRoute('demande_devis_list', array());
        }

        return $this->render('BackOffice/Demande/Devis/examineFicheTechnique.html.twig', array(
            'form'              => $form->createView(),
            'devis'             => $devis,
            'travaux'           => $demandeTravaux,
            'ficheTechnique'    => $ficheTechnique,
        ));
    }
}

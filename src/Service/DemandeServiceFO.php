<?php

namespace App\Service;

use App\Entity\Beneficiaire;
use App\Entity\DateCP;
use App\Entity\Demande_;
use App\Entity\Demande_auditEnergie;
use App\Entity\Demande_auditNumerique;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux;
use App\Entity\Demande_travaux_devis;
use App\Entity\Demande_travaux_devis_upload;
use App\Entity\FicheTechnique;
use App\Entity\Historique_;
use App\Entity\Instruction_;
use App\Entity\Instruction_reason;
use App\Entity\Logement;
use App\Entity\Partenaire_;
use App\Entity\Remboursement_;
use App\Entity\Structure_;
use App\Entity\Structure_conseiller;
use App\Entity\Titre;
use App\Repository\BeneficiaireRepository;
use App\Repository\DateCPRepository;
use App\Repository\Demande_auditEnergieRepository;
use App\Repository\Demande_auditNumeriqueRepository;
use App\Repository\Demande_Repository;
use App\Repository\Demande_travaux_devis_uploadRepository;
use App\Repository\Demande_travaux_devisRepository;
use App\Repository\Demande_travauxRepository;
use App\Repository\FicheTechniqueRepository;
use App\Repository\Historique_Repository;
use App\Repository\Instruction_Repository;
use App\Repository\LogementRepository;
use App\Repository\Partenaire_Repository;
use App\Repository\Remboursement_Repository;
use App\Repository\Structure_conseillerRepository;
use App\Repository\Structure_Repository;
use App\Repository\TitreRepository;
use App\Utils\DefaultServiceUtils;
use App\Utils\DefaultUtils;
use Doctrine\ORM\EntityManagerInterface;
use setasign\Fpdi\Fpdi;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class DemandeServiceFO
{

    /**
     * @var ANAHService
     */
    protected $ANAHService;

    /**
     * @var EntityManagerInterface $EM
     */
    protected $EM = null;

    /**
     * @var DemandeServiceBO
     */
    protected $demandeServiceBO;

    /**
     * @var HistoriqueService
     */
    protected $historiqueService;

    /**
     * @var RemboursementService
     */
    protected $remboursementService;

    /**
     * @var TitreService
     */
    protected $titreService;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var MailerService
     */
    protected $mailerService;

    /**
     * @var BeneficiaireRepository
     */
    protected $beneficiaireRepository;

    /**
     * @var DateCPRepository
     */
    protected $dateCPRepository;

    /**
     * @var Demande_Repository
     */
    protected $demande_Repository;

    /**
     * @var Demande_auditEnergieRepository
     */
    protected $demande_auditEnergieRepository;

    /**
     * @var Demande_auditNumeriqueRepository
     */
    protected $demande_auditNumeriqueRepository;

    /**
     * @var Demande_travauxRepository
     */
    protected $demande_travauxRepository;

    /**
     * @var Demande_travaux_devisRepository
     */
    protected $demande_travaux_devisRepository;

    /**
     * @var Demande_travaux_devis_uploadRepository
     */
    protected $demande_travaux_devis_uploadRepository;

    /**
     * @var FicheTechniqueRepository
     */
    protected $ficheTechniqueRepository;

    /**
     * @var Historique_Repository
     */
    protected $historique_Repository;

    /**
     * @var Instruction_Repository
     */
    protected $instruction_Repository;

    /**
     * @var LogementRepository
     */
    protected $logementRepository;

    /**
     * @var Partenaire_Repository
     */
    protected $partenaire_Repository;

    /**
     * @var Remboursement_Repository
     */
    protected $remboursement_Repository;

    /**
     * @var Structure_Repository
     */
    protected $structure_Repository;

    /**
     * @var Structure_conseillerRepository
     */
    protected $structure_conseillerRepository;

    /**
     * @var TitreRepository
     */
    protected $titreRepository;

    const TAG_DOCUMENT_MANQUANT = '##MOTIF_DOC_MANQUANT##';
    const TAG_NON_CONFORME      = '##MOTIF_NON_CONFORME##';
    const TAG_REFUS             = '##MOTIF_REFUS##';

    const MOTIF_REFUS_NON_PARTICIPATION_SARE = 'Votre intercommunalité ne finance pas le Service d\'Accompagnement à la Rénovation Energétique (SARE),';
    const MOTIF_REFUS_ANAH                   = 'Vous n\'avez pas souhaité vous engager avec l\'Anah ou bien votre revenu fiscal de référence du foyer dépasse le plafond de l\'Anah';
    const MOTIF_REFUS_DOUBLON                = 'Une demande d\'audit énergétique et scénarios pour ce logement a déjà été créée';



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
        MailerService $mailerService
    ) {
        $this->ANAHService = $ANAHService;
        $this->EM = $entityManager;
        $this->demandeServiceBO = $demandeServiceBO;
        $this->historiqueService = $historiqueService;
        $this->remboursementService = $remboursementService;
        $this->titreService = $titreService;
        $this->tokenStorage = $tokenStorage;
        $this->parameterBag = $parameterBag;
        $this->environment = $environment;
        $this->mailerService = $mailerService;

        $this->beneficiaireRepository = $this->EM->getRepository(Beneficiaire::class);
        $this->dateCPRepository = $this->EM->getRepository(DateCP::class);
        $this->demande_Repository = $this->EM->getRepository(Demande_::class);
        $this->demande_auditEnergieRepository = $this->EM->getRepository(Demande_auditEnergie::class);
        $this->demande_auditNumeriqueRepository = $this->EM->getRepository(Demande_auditNumerique::class);
        $this->demande_travauxRepository = $this->EM->getRepository(Demande_travaux::class);
        $this->demande_travaux_devisRepository = $this->EM->getRepository(Demande_travaux_devis::class);
        $this->demande_travaux_devis_uploadRepository = $this->EM->getRepository(Demande_travaux_devis_upload::class);
        $this->ficheTechniqueRepository = $this->EM->getRepository(FicheTechnique::class);
        $this->historique_Repository = $this->EM->getRepository(Historique_::class);
        $this->instruction_Repository = $this->EM->getRepository(Instruction_::class);
        $this->logementRepository = $this->EM->getRepository(Logement::class);
        $this->partenaire_Repository = $this->EM->getRepository(Partenaire_::class);
        $this->remboursement_Repository = $this->EM->getRepository(Remboursement_::class);
        $this->structure_Repository = $this->EM->getRepository(Structure_::class);
        $this->structure_conseillerRepository = $this->EM->getRepository(Structure_conseiller::class);
        $this->titreRepository = $this->EM->getRepository(Titre::class);
    }



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $conformiteJP
     * @param $conformiteKBIS
     * @param $conformiteAI
     * @param $documentJP
     * @param $documentKBIS
     * @param $documentAI
     * @param $documentJPAlt
     * @param $documentKBISAlt
     * @param $documentAIAlt
     * @param $instruction
     * @param $typeBeneficiaire
     * @param $auditeur
     * @return int|string
     */
    public function searchStatutForDemandeAuditEnergie(
        $conformiteJP,
        $conformiteKBIS,
        $conformiteAI,
        $documentJP,
        $documentKBIS,
        $documentAI,
        $documentJPAlt,
        $documentKBISAlt,
        $documentAIAlt,
        $instruction,
        $typeBeneficiaire,
        $auditeur
    ) {
        $statut = '';

        if ('0 | particulier' == $typeBeneficiaire) {
            $documentKBIS = '1';
            $conformiteKBIS = '0';
        }

        if (!($documentJP || $documentJPAlt) || !($documentKBIS || $documentKBISAlt) || !($documentAI || $documentAIAlt)) {
            $statut = (!$auditeur) ? Demande_statut::STATUS_1 : Demande_statut::STATUS_3;
        } elseif (null == $instruction || null == $conformiteJP || null == $conformiteKBIS || null == $conformiteAI) {
            $statut = (!$auditeur) ? Demande_statut::STATUS_2 : Demande_statut::STATUS_4;
        } elseif (null != $instruction) {
            if ('1' == $conformiteJP || '1' == $conformiteKBIS || '1' == $conformiteAI) {
                $statut = (!$auditeur) ? Demande_statut::STATUS_6 : Demande_statut::STATUS_9;
            } elseif ('2' == $conformiteJP || '2' == $conformiteKBIS || '2' == $conformiteAI) {
                $statut = (!$auditeur) ? Demande_statut::STATUS_7 : Demande_statut::STATUS_10;
            } else {
                $statut = (!$auditeur) ? Demande_statut::STATUS_5 : Demande_statut::STATUS_8;
            }
        }

        return $statut;
    }

    /**
     * @param $dateCPId
     * @param $statutAuditEnergie
     * @return int
     */
    public function searchStatutForDemandeAuditNumerique($dateCPId, $statutAuditEnergie)
    {
        if (Demande_statut::STATUS_15 == $statutAuditEnergie) {
            $statut = Demande_statut::STATUS_17;
        } else {
            $statut = Demande_statut::STATUS_16;

            if ($dateCPId) {
                $statut = Demande_statut::STATUS_8;
            }
        }

        return $statut;
    }

    /**
     * @param $conformiteJP
     * @param $conformiteKBIS
     * @param $conformiteAI
     * @param $documentJP
     * @param $documentKBIS
     * @param $documentAI
     * @param $documentJPAlt
     * @param $documentKBISAlt
     * @param $documentAIAlt
     * @param $instruction
     * @param $devis
     * @param $audit
     * @param $ficheTechniqueStatut
     * @param $typeBeneficiaire
     * @param $instructionDevis
     * @param $isFicheTechniqueValidationConseiller
     * @return int|string
     */
    public function searchStatutForDemandeTravauxAndDevis(
        $conformiteJP,
        $conformiteKBIS,
        $conformiteAI,
        $documentJP,
        $documentKBIS,
        $documentAI,
        $documentJPAlt,
        $documentKBISAlt,
        $documentAIAlt,
        $instruction,
        $devis,
        $audit,
        $ficheTechniqueStatut,
        $typeBeneficiaire,
        $instructionDevis,
        $isFicheTechniqueValidationConseiller = null
    ) {
        $statut = '';

        if ('0 | particulier' == $typeBeneficiaire) {
            $documentKBIS = '1';
            $conformiteKBIS = '0';
        }

        if ((!($documentJP || $documentJPAlt)
                || !($documentKBIS || $documentKBISAlt)
                || !($documentAI || $documentAIAlt))
            && null == $devis
        ) {
            /* Sans instruction, sans devis*/
            $statut = Demande_statut::STATUS_18;
        } elseif (
            (null == $instruction || null == $conformiteJP || null == $conformiteKBIS || null == $conformiteAI) &&
            null == $devis
        ) {
            /* Sans instruction, sans devis*/
            $statut = Demande_statut::STATUS_19;
        } elseif (null != $instruction && null == $devis) {
            /* Avec instruction, sans devis */
            if ('1' == $conformiteJP || '1' == $conformiteKBIS || '1' == $conformiteAI) {
                $statut = Demande_statut::STATUS_27;
            } elseif ('2' == $conformiteJP || '2' == $conformiteKBIS || '2' == $conformiteAI) {
                $statut = Demande_statut::STATUS_28;
            } else {
                $statut = Demande_statut::STATUS_26;
            }
        } elseif (
            (null == $instruction || null == $conformiteJP || null == $conformiteKBIS || null == $conformiteAI) &&
            null != $devis &&
            null == $instructionDevis
        ) {
            /* Avec devis, sans instruction, sans instuction H&E */
            if (!($documentJP || $documentJPAlt) || !($documentKBIS || $documentKBISAlt) || !($documentAI || $documentAIAlt)) {
                $statut = Demande_statut::STATUS_43;
            } else {
                $statut = Demande_statut::STATUS_44;
            }
        } elseif (
            (null == $instruction || null == $conformiteJP || null == $conformiteKBIS || null == $conformiteAI) &&
            null != $devis &&
            null != $instructionDevis &&
            true != $ficheTechniqueStatut
        ) {
            /* Avec devis, sans instruction, avec instuction H&E, sans fiche technique */
            if (!($documentJP || $documentJPAlt) || !($documentKBIS || $documentKBISAlt) || !($documentAI || $documentAIAlt)) {
                $statut = ('0 | oui' == $instructionDevis) ? (empty($audit) ? Demande_statut::STATUS_20 : Demande_statut::STATUS_38) : Demande_statut::STATUS_43;
            } else {
                $statut = ('0 | oui' == $instructionDevis) ? (empty($audit) ? Demande_statut::STATUS_21 : Demande_statut::STATUS_39) : Demande_statut::STATUS_44;
            }
        } elseif (
            (null == $instruction || null == $conformiteJP || null == $conformiteKBIS || null == $conformiteAI) &&
            null != $devis &&
            '0 | oui' == $instructionDevis &&
            true == $ficheTechniqueStatut &&
            false == $isFicheTechniqueValidationConseiller
        ) {
            /* Avec devis, sans instruction, avec instuction H&E, avec fiche technique, sans validation conseiller */
            if (!($documentJP || $documentJPAlt) || !($documentKBIS || $documentKBISAlt) || !($documentAI || $documentAIAlt)) {
                $statut = Demande_statut::STATUS_23;
            } else {
                $statut = Demande_statut::STATUS_22;
            }
        } elseif (
            (null == $instruction || null == $conformiteJP || null == $conformiteKBIS || null == $conformiteAI) &&
            null != $devis &&
            '0 | oui' == $instructionDevis &&
            true == $ficheTechniqueStatut &&
            true == $isFicheTechniqueValidationConseiller
        ) {
            /* Avec devis, sans instruction, avec instuction H&E, avec fiche technique, avec validation conseiller */
            if (!($documentJP || $documentJPAlt) || !($documentKBIS || $documentKBISAlt) || !($documentAI || $documentAIAlt)) {
                $statut = Demande_statut::STATUS_3;
            } else {
                $statut = Demande_statut::STATUS_4;
            }
        } elseif (
            null != $instruction &&
            null != $devis &&
            null == $instructionDevis
        ) {
            /* Avec devis, avec instruction, sans instuction H&E */
            if ('1' == $conformiteJP || '1' == $conformiteKBIS || '1' == $conformiteAI) {
                $statut = Demande_statut::STATUS_46;
            } elseif ('2' == $conformiteJP || '2' == $conformiteKBIS || '2' == $conformiteAI) {
                $statut = Demande_statut::STATUS_47;
            } else {
                $statut = Demande_statut::STATUS_45;
            }
        } elseif (
            null != $instruction &&
            null != $devis &&
            null != $instructionDevis &&
            true != $ficheTechniqueStatut
        ) {
            /* Avec devis, avec instruction, avec instuction H&E, sans fiche technique  */
            if ('1' == $conformiteJP || '1' == $conformiteKBIS || '1' == $conformiteAI) {
                $statut = ('0 | oui' == $instructionDevis) ? (empty($audit) ? Demande_statut::STATUS_30 : Demande_statut::STATUS_41) : Demande_statut::STATUS_46;
            } elseif ('2' == $conformiteJP || '2' == $conformiteKBIS || '2' == $conformiteAI) {
                $statut = ('0 | oui' == $instructionDevis) ? (empty($audit) ? Demande_statut::STATUS_31 : Demande_statut::STATUS_42) : Demande_statut::STATUS_47;
            } else {
                $statut = ('0 | oui' == $instructionDevis) ? (empty($audit) ? Demande_statut::STATUS_29 : Demande_statut::STATUS_40) : Demande_statut::STATUS_45;
            }
        } elseif (
            null != $instruction &&
            null != $devis &&
            '0 | oui' == $instructionDevis &&
            true == $ficheTechniqueStatut &&
            false == $isFicheTechniqueValidationConseiller
        ) {
            /* Avec devis, avec instruction, avec instuction H&E, avec fiche technique, sans validation conseiller*/
            if ('1' == $conformiteJP || '1' == $conformiteKBIS || '1' == $conformiteAI) {
                $statut = Demande_statut::STATUS_33;
            } elseif ('2' == $conformiteJP || '2' == $conformiteKBIS || '2' == $conformiteAI) {
                $statut = Demande_statut::STATUS_34;
            } else {
                $statut = Demande_statut::STATUS_32;
            }
        } elseif (
            null != $instruction &&
            null != $devis &&
            '0 | oui' == $instructionDevis &&
            true == $ficheTechniqueStatut &&
            true == $isFicheTechniqueValidationConseiller
        ) {
            /* Avec devis, avec instruction, avec instuction H&E, avec fiche technique, avec validation conseiller */
            if ('1' == $conformiteJP || '1' == $conformiteKBIS || '1' == $conformiteAI) {
                $statut = Demande_statut::STATUS_9;
            } elseif ('2' == $conformiteJP || '2' == $conformiteKBIS || '2' == $conformiteAI) {
                $statut = Demande_statut::STATUS_10;
            } else {
                $statut = Demande_statut::STATUS_8;
            }
        }
        return $statut;
    }

    /**
     * @param $demandeStatutId
     * @return int
     */
    public function searchStatutAuditEnergieForDateCP($demandeStatutId)
    {
        $statut = $demandeStatutId;

        if (Demande_statut::STATUS_8 == $demandeStatutId) {
            $statut = Demande_statut::STATUS_11;
        }

        return $statut;
    }

    /**
     * @param $demandeStatutId
     * @return int
     */
    public function searchStatutAuditNumeriqueForDateCP($demandeStatutId)
    {
        $statut = $demandeStatutId;

        if (Demande_statut::STATUS_16 == $demandeStatutId) {
            $statut = Demande_statut::STATUS_8;
        }

        return $statut;
    }

    /**
     * @return int
     */
    public function searchStatutForDateCP()
    {
        $statut = Demande_statut::STATUS_12;

        return $statut;
    }

    /**
     * @return int
     */
    public function searchStatutForNoDateCP()
    {
        $statut = Demande_statut::STATUS_8;

        return $statut;
    }

    /**
     * @return int
     */
    public function searchStatutForProduction()
    {
        $statut = Demande_statut::STATUS_13;

        return $statut;
    }

    /**
     * @return int
     */
    public function searchStatutForTitre()
    {
        $statut = Demande_statut::STATUS_14;

        return $statut;
    }

    /**
     * @return int
     */
    public function searchStatutRefus()
    {
        $statut = Demande_statut::STATUS_15;

        return $statut;
    }

    /**
     * @return int
     */
    public function searchStatutRefusForAuditNumerique()
    {
        $statut = Demande_statut::STATUS_17;

        return $statut;
    }

    /**
     * @param $demandeId
     * @param $demandeType
     * @param $beneficiaireType
     * @param null $justificatifPropriete
     * @param null $pieceComplement
     * @param null $avisImposition
     * @param null $avisImpositionConjoint
     * @param bool $isNbPersFoyer
     * @param bool $isRevenuFoyer
     * @return array
     */
    public function updateInstruction(
        $demandeId,
        $demandeType,
        $beneficiaireType,
        $justificatifPropriete = null,
        $pieceComplement = null,
        $avisImposition = null,
        $avisImpositionConjoint = null,
        $isNbPersFoyer = false,
        $isRevenuFoyer = false
    ) {
        $instructionObject = $this->instruction_Repository->findOneBy([
            'demande_id' => $demandeId
        ]);

        $conformiteJP = null;
        $conformiteKBIS = null;
        $conformiteAI = null;
        $instructionFlag = $instructionObject ? true : null;
        $isUpdate = false;

        if ($instructionFlag == true) {
            if ($justificatifPropriete || ($pieceComplement && '0 | particulier' == $beneficiaireType)) {
                if (in_array($demandeType, [
                    Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                    Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
                ])) {
                    $instructionObject->getInstructionAuditEnergie()->setJPconformite(null);
                    $instructionObject->getInstructionAuditEnergie()->setJPreason([]);
                    $instructionObject->getInstructionAuditEnergie()->setJPreasonAutre(null);
                } elseif (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType) {
                    $instructionObject->getInstructionTravaux()->setJPconformite(null);
                    $instructionObject->getInstructionTravaux()->setJPreason([]);
                    $instructionObject->getInstructionTravaux()->setJPreasonAutre(null);
                }

                $isUpdate = true;
            }

            if ($pieceComplement) {
                if ('0 | particulier' != $beneficiaireType) {
                    if (in_array($demandeType, [
                        Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                        Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
                    ])) {
                        $instructionObject->getInstructionAuditEnergie()->setKBISconformite(null);
                        $instructionObject->getInstructionAuditEnergie()->setKBISreason([]);
                        $instructionObject->getInstructionAuditEnergie()->setKBISreasonAutre(null);
                    } elseif (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType) {
                        $instructionObject->getInstructionTravaux()->setKBISconformite(null);
                        $instructionObject->getInstructionTravaux()->setKBISreason([]);
                        $instructionObject->getInstructionTravaux()->setKBISreasonAutre(null);
                    }

                    $isUpdate = true;
                }
            }

            if (
                in_array($demandeType, [
                    Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                    Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
                ])
                && ($avisImposition || $avisImpositionConjoint || $isNbPersFoyer || $isRevenuFoyer)
            ) {
                $instructionObject->getInstructionAuditEnergie()->setAIconformite(null);
                $instructionObject->getInstructionAuditEnergie()->setAIreason([]);
                $instructionObject->getInstructionAuditEnergie()->setAIreasonAutre(null);
                $isUpdate = true;
            }

            if (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType && ($avisImposition || $avisImpositionConjoint || $isNbPersFoyer || $isRevenuFoyer)) {
                $instructionObject->getInstructionTravaux()->setAIconformite(null);
                $instructionObject->getInstructionTravaux()->setAIreason([]);
                $instructionObject->getInstructionTravaux()->setAIreasonAutre(null);
                $isUpdate = true;
            }

            if (
                in_array($demandeType, [
                    Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                    Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
                ])
            ) {
                $conformiteJPArray = explode(" | ", $instructionObject->getInstructionAuditEnergie()->getJPconformite());
                $conformiteKBISArray = explode(" | ", $instructionObject->getInstructionAuditEnergie()->getKBISconformite());
                $conformiteAIArray = explode(" | ", $instructionObject->getInstructionAuditEnergie()->getAIconformite());

                if (!empty($conformiteAIArray)) {
                    $conformiteAI = $conformiteAIArray[0];
                }
            } elseif (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType) {
                $conformiteJPArray = explode(" | ", $instructionObject->getInstructionTravaux()->getJPconformite());
                $conformiteKBISArray = explode(" | ", $instructionObject->getInstructionTravaux()->getKBISconformite());
                $conformiteAIArray = explode(" | ", $instructionObject->getInstructionTravaux()->getAIconformite());

                if (!empty($conformiteAIArray)) {
                    $conformiteAI = $conformiteAIArray[0];
                }
            }

            if (!empty($conformiteJPArray)) {
                $conformiteJP = $conformiteJPArray[0];
            }
            if (!empty($conformiteKBISArray)) {
                $conformiteKBIS = $conformiteKBISArray[0];
            }
        }

        if ($isUpdate) {
            $instructionObject->setDateModif(new \Datetime());
            $instructionObject->setAuteurModif($_SESSION['login']->getUsername());

            $this->EM->persist($instructionObject);
            $this->EM->flush();
        }

        return [
            'conformiteJP'   => $conformiteJP,
            'conformiteKBIS' => $conformiteKBIS,
            'conformiteAI'   => $conformiteAI,
            'instruction'    => $instructionFlag
        ];
    }

    /**
     * @param $demandeType
     * @param $beneficiaireNom
     * @param $beneficiairePrenom
     * @param $logementCodePostal
     * @param $logementVille
     * @return bool
     */
    public function checkDoublon(
        $demandeType,
        $beneficiaireNom,
        $beneficiairePrenom,
        $logementCodePostal,
        $logementVille
    ) {
        /* /////////////////////////////////////////////////////////////////
                             CREATE DUPLICATE KEY
        ///////////////////////////////////////////////////////////////// */
        $key = $beneficiaireNom . $beneficiairePrenom . $logementCodePostal . $logementVille;
        $key = DefaultUtils::formatString($key, $charset = 'utf-8');
        $key = preg_replace('/\s/', '', $key);

        /* /////////////////////////////////////////////////////////////////
                             CHECK IF DEMANDE IS DUPLICATE
        ///////////////////////////////////////////////////////////////// */
        $rowDoublon = $this->demande_auditEnergieRepository->searchDuplicate($demandeType, $key);
        $isDoublon = count($rowDoublon) > 0;

        return $isDoublon;
    }

    /**
     * @param $demandeId
     * @return array|string|string[]
     */
    public function findStatutDescriptionByDemande($demandeId)
    {
        $customDataDemande = $this->demande_Repository->findCustomForStatutDescriptionByDemande($demandeId);

        $explication = $customDataDemande['demandeStatutDescription'];
        $isDocumentManquant = strpos($explication, self::TAG_DOCUMENT_MANQUANT) !== FALSE;
        $isNonConforme = strpos($explication, self::TAG_NON_CONFORME) !== FALSE;
        $isRefus = strpos($explication, self::TAG_REFUS) !== FALSE;

        $dataMotifStatutDemande = [];

        if ($isDocumentManquant) {
            $documentManquant = $this->findDocumentManquant(
                $customDataDemande['demandeType'],
                $customDataDemande['beneficiaireType'],
                $customDataDemande['documentJPAlt'],
                $customDataDemande['documentKBISAlt'],
                $customDataDemande['documentAIAlt']
            );
            $dataMotifStatutDemande['documentManquantList'] = $documentManquant;
            $dataMotifStatutDemande['documentManquantTag'] = self::TAG_DOCUMENT_MANQUANT;
        }

        if ($isNonConforme) {
            $documentNonConforme = $this->findDocumentNonConforme($demandeId, $customDataDemande['demandeType']);
            $dataMotifStatutDemande['nonConformeList'] = $documentNonConforme;
            $dataMotifStatutDemande['documentNonConformeTag'] = self::TAG_NON_CONFORME;
        }

        if ($isRefus) {
            $demande = $this->demande_Repository->find($demandeId);

            $refusText = null;
            if ($demande->getMotifRefus()) {
                // Refus forcé par la Région
                $refusText = $demande->getMotifRefus();
            } else {
                if (Demande_::DEMANDE_AUDIT_ENERGIE_TYPE == $customDataDemande['demandeType']) {
                    // Audit Energie
                    $refusText = 'Une demande d\'audit énergétique et scénarios pour ce logement a déjà été créée';
                } elseif (Demande_::DEMANDE_TRAVAUX_TYPE == $customDataDemande['demandeType']) {
                    // Travaux
                    $refusText = 'Vous n\'avez pas souhaité vous engager avec l\'Anah ou bien votre revenu fiscal de référence du foyer dépasse le plafond de l\'Anah';
                }
            }

            $dataMotifStatutDemande['refusText'] = $refusText;
            $dataMotifStatutDemande['documentRefusTag'] = self::TAG_REFUS;
        }

        $statutDescription = DefaultServiceUtils::getStatutDescriptionByDemandeAndMotif(
            $demandeId,
            $customDataDemande['demandeStatutDescription'],
            $customDataDemande['beneficiaireType'],
            $dataMotifStatutDemande
        );

        return $statutDescription;
    }

    /**
     * @param $demandeType
     * @param $beneficiaireType
     * @param $documentJPAlt
     * @param $documentKBISAlt
     * @param $documentAIAlt
     * @return array
     */
    public function findDocumentManquant(
        $demandeType,
        $beneficiaireType,
        $documentJPAlt = null,
        $documentKBISAlt = null,
        $documentAIAlt = null
    ) {
        $listDocManquant = [];

        if (Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE == $demandeType || Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType) { // cas Audit Energetique ou Travaux
            if (!$documentAIAlt) {
                $listDocManquant[] = 'avis_imposition';
            }
        }

        if (!$documentJPAlt) {
            $listDocManquant[] = 'justificatif_propriete';
        }

        if ('1 | sci' == $beneficiaireType) {
            if (!$documentKBISAlt) {
                $listDocManquant[] =  'statut_sci';
            }
        }

        return $listDocManquant;
    }

    /**
     * @param $data
     */
    public function createFicheLiaison($data)
    {
        $pdf = new Fpdi();
        $fichier = $this->parameterBag->get('kernel.project_dir') . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'Template' . DIRECTORY_SEPARATOR . 'fiche_liaison.pdf';

        try {
            $pdf->setSourceFile($fichier);
        } catch (\Exception $e) {
            throw new \RuntimeException("Erreur lors de l'ouverture du PDF source : " . $e->getMessage());
        }

        $pdf->AddPage();
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, null, null, null, null, true);
        $pdf->SetLeftMargin(28);
        $pdf->SetRightMargin(0);
        $pdf->SetFont('Helvetica', $style = '', 13);
        $pdf->setTextColor(0, 0, 0);
        $this->writeFicheLiaison($data, $pdf);
        $pdf->Output();
    }

    /**
     * @param Demande_ $demande
     */
    public function checkEditDemande(Demande_ $demande)
    {
        if (!empty($demande->getDateCPId()) || $this->searchStatutRefus() == $demande->getStatutId()) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
    }

    /**
     * @param $beneficiaireId
     * @param $logementId
     * @return void
     */
    public function checkEditLogementBeneficiaire($beneficiaireId, $logementId = null)
    {
        $nombreDemande = $this->demande_Repository->findCountByBeneficiaireAndLogementForEditDenied($beneficiaireId, $logementId);

        if ($nombreDemande > 0) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
    }

    /**
     * @param $demandeId
     * @param $demandeType
     * @param null $instructionId
     */
    public function checkDemandeInstructionExamineReexamine($demandeId, $demandeType, $instructionId = null)
    {
        if (empty($demandeId) || empty($demandeType)) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }

        // SI PARAMETRES OK => ON FAIT LA REQUETE DE CHECK
        $row = $this->demande_Repository->findOneForInstructionExamineReexamine($demandeId, $demandeType, $instructionId);
        if (empty($row)) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
    }

    /**
     * $type => 'devis' or 'fiche_technique'
     *
     * @param $type
     * @param $devisId
     * @param null $logementId
     * @param null $ficheTechniqueId
     */
    public function checkInstructionTechniqueExamineReexamine(
        $type,
        $devisId,
        $logementId = null,
        $ficheTechniqueId = null
    ) {
        if (
            empty($type)
            || empty($devisId)
            || !in_array($type, ['devis', 'fiche_technique'])
        ) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
        if (('devis' == $type) && (empty($devisId) || empty($logementId))) {
            // DEMANDE TRAVAUX DEVIS
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }

        // SI LES PARAMETRES SONT OK ON FAIT LA REQUETE
        $row = $this->demande_Repository->findOneForInstructionTechniqueExamineReexamine(
            $type,
            $devisId,
            $logementId,
            $ficheTechniqueId
        );
        if (empty($row)) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
    }

    /**
     * @param $demandeId
     * @param $demandeType
     * @param $statutId
     * @param $statutRenovateurEmailTravaux
     * @return bool
     */
    public function isEmailDemandeSelectionRenovateur(
        $demandeId,
        $demandeType,
        $statutId,
        $statutRenovateurEmailTravaux
    ) {
        if (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType && in_array($statutId, $statutRenovateurEmailTravaux)) {
            $niveau = $this->demande_Repository->findDemandeTravauxDevisNiveau($demandeId);
            if (!empty($niveau)) {
                if (
                    $niveau == Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE
                    || $niveau == Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param Historique_ $historique
     * @param $beneficiaireEmail
     * @param $demandeStatutMotif
     * @return int
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendEmailRefusNonParticipationSARE(
        Historique_ $historique,
        $beneficiaireEmail,
        $demandeStatutMotif
    ) {
        // ON ENVOIE EMAIL SPECIFIQUE
        $body = $this->environment->render(
            'FrontOffice/Logement/email/statut_refuse.html.twig',
            [
                'motifLabelArray' => [$demandeStatutMotif],
                'isRefusSARE'     => 1
            ]
        );
        $subject = 'Région Normandie - Demande d\'inscription "Chèque éco-énergie" refusée.';

        $nbSent = $this->mailerService->sendGeneriqueEmail(
            $subject,
            $body,
            $this->parameterBag->get('mailer_address_from'),
            $beneficiaireEmail,
            null,
            'text/html',
            'UTF-8'
        );

        // ON ENREGISTRE EMAIL DEMANDE DANS HISTORIQUE
        if ($nbSent && $historique) {
            $this->historiqueService->saveHistoriqueEmail(
                $historique,
                $body,
                $this->parameterBag->get('mailer_address_from'),
                $beneficiaireEmail,
                $subject
            );
        }

        return $nbSent;
    }

    /**
     * @param Demande_|null $demandeAuditE
     * @param $participationSARE
     * @param $nbPersFoyer
     * @param $revenuReference
     * @return bool
     */
    public function checkSAREDemandeAuditEtTravaux(
        Demande_ $demandeAuditE = null,
        $participationSARE,
        $nbPersFoyer,
        $revenuReference
    ) {

        if (!empty($demandeAuditE)) {
            // Si ancien audit = 800€ et date jour <= date debut SARE => Travaux accepté
            /**
             * @var Titre $titre
             */
            $titre = $this->titreRepository->findOneByDemandeId($demandeAuditE->getId());
            if (!empty($titre)) {

                $dateDuJour = (new \DateTime('now'))->format('d-m-Y');
                $dateDebutSARE = $this->parameterBag->get('app_date_debut_SARE');
                $timeDateDuJour = strtotime($dateDuJour);
                $timeDateDebutSARE = strtotime($dateDebutSARE);

                if (
                    ((int)$titre->getValeurTitre() == 800 || $titre->getValeurTitre() == '800.00')
                    && ($timeDateDuJour <= $timeDateDebutSARE)
                ) {
                    return true;
                }
            }
        }


        if (empty($participationSARE)) {
            // SARE = NON

            $plafondANAH = $this->ANAHService->findPlafond($nbPersFoyer);

            if ((int)$revenuReference > (int)$plafondANAH) {
                return false;
            }

            if ((int)$revenuReference <= (int)$plafondANAH) {
                return true;
            }
        } else {
            // SARE = OUI
            return true;
        }

        return false;
    }

    /**
     * @param $beneficiaireId
     * @param $demandeType
     * @return array
     */
    public function getDataCountDemandeFilteredByLogement($beneficiaireId, $demandeType)
    {
        $arrayDemande = $this->demande_Repository->findByBeneficiaireAndType($beneficiaireId, $demandeType);

        $countDemande = [];
        foreach ($arrayDemande as $row) {
            $countDemande[$row['logement_id']] = count(explode('|', $row['concat_demande_id']))
                . '|' . $row['dateCP_id']
                . '|' . $row['statut_id']
                . (($demandeType != Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE) ? '|' . $row['rStatutId'] : '|')
                . (in_array($demandeType, [Demande_::DEMANDE_AUDIT_ENERGIE_TYPE, Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE, Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE])  && isset($row['partenaireStatutEnabled']) ? '|' . $row['partenaireStatutEnabled'] : '|')
                . (($demandeType == Demande_::DEMANDE_TRAVAUX_TYPE) ? '|' . str_replace(' | ', '', $row['dtdNiveau']) : '|');
        }

        return $countDemande;
    }

    /**
     * @param Demande_ $demande
     * @param $dateFormat
     * @return string|null
     */
    public function getEnabledDemandeDateCP(Demande_ $demande, $dateFormat = 'd/m/Y')
    {
        /**
         * @var DateCP $dateCP
         */
        if (!empty($demande->getDateCPId())) {

            /**
             * @var DateCP $dateCP
             */
            $dateCP = $this->dateCPRepository->find($demande->getDateCPId());

            if (!empty($dateCP) && !empty($dateCP->getEnabled())) {
                return $dateCP->getDateCP()->format($dateFormat);
            }
        }

        return null;
    }

    /**
     * @param array $demandeIdList
     * @param Beneficiaire $beneficiaire
     * @param Logement|null $logement
     * @return void
     */
    public function cleanInstructionRestoreStatutAndHistorise(
        array $demandeIdList,
        Beneficiaire $beneficiaire,
        Logement $logement = null
    ) {
        foreach ($demandeIdList as $demandeId) {

            /**
             * @var Demande_ $demande
             */
            $demande = $this->demande_Repository->find($demandeId);

            /* /////////////////////////////////////////////////////////////////
                          SUPPRESSION/MISE A JOUR INSTRUCTION ADMINISTRATIVE
            ///////////////////////////////////////////////////////////////// */
            $this->cleanInstructionAdministrative($demandeId);

            /* /////////////////////////////////////////////////////////////////
                         SUPPRESSION/MISE A JOUR POUR INSTRUCTION TECHNIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->cleanInstructionTechnique($demande);

            $this->updateStatutAndHistoriseAfterCleanInstruction(
                $demande,
                $beneficiaire,
                $logement
            );
        }
    }

    /**
     * @param $demandeId
     * @return array|string|string[]
     */
    public function findDataTagNonConformeByDemande($demandeId)
    {
        $customDataDemande = $this->demande_Repository->findCustomForStatutDescriptionByDemande($demandeId);

        $explication = $customDataDemande['demandeStatutDescription'];
        $isNonConforme = strpos($explication, self::TAG_NON_CONFORME) !== FALSE;

        $dataMotifStatutDemande = [];

        if ($isNonConforme) {
            $documentNonConforme = $this->findDocumentNonConforme($demandeId, $customDataDemande['demandeType']);
            $dataMotifStatutDemande['nonConformeList'] = $documentNonConforme;
            $dataMotifStatutDemande['documentNonConformeTag'] = self::TAG_NON_CONFORME;
        }

        $statutDescription = DefaultServiceUtils::getStatutDescriptionByDemandeAndMotif(
            $demandeId,
            $customDataDemande['demandeStatutDescription'],
            $customDataDemande['beneficiaireType'],
            $dataMotifStatutDemande,
            ['isMotifNonConformeOnly' => true]
        );

        return $statutDescription;
    }

    /**
     * @param Demande_ $demande
     * @param Beneficiaire|null $beneficiaire
     * @param array $ANAHCritere
     * @return void
     */
    public function setDemandeTypeMenage(Demande_ $demande, Beneficiaire $beneficiaire = null, array $ANAHCritere = []): void
    {
        if (empty($beneficiaire)) {
            $beneficiaire = $this->beneficiaireRepository->find($demande->getBeneficiaireId());
        }

        switch ($demande->getType()) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                CAS DEMANDE AUDIT ENERGETIQUE ET SCENARIOS
                                ET AUDIT ENERGETIQUE REGION
                ///////////////////////////////////////////////////////////////// */
                $nbPersFoyer = $demande->getDemandeAuditEnergie()->getNbPersFoyer();
                $nbPersFoyer = isset($nbPersFoyer) ? (int)$nbPersFoyer : $beneficiaire->getNbPersFoyer();
                $revenuFiscalReferenceFoyer = $demande->getDemandeAuditEnergie()->getRevenu3();
                $revenuFiscalReferenceFoyer = isset($revenuFiscalReferenceFoyer) ? trim($revenuFiscalReferenceFoyer) : $beneficiaire->getRevenuFiscalRef();
                break;

            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                  CAS DEMANDE AUDIT NUMERIQUE
                             ET MISE A JOUR AUDIT ENERGETIQUE ET SCENARIOS
                ///////////////////////////////////////////////////////////////// */
                if (!empty($beneficiaire)) {
                    $nbPersFoyer = $beneficiaire->getNbPersFoyer();
                    $revenuFiscalReferenceFoyer = $beneficiaire->getRevenuFiscalRef();
                }
                break;

            case Demande_::DEMANDE_TRAVAUX_TYPE:
                $nbPersFoyer = $demande->getDemandeTravaux()->getNbPersFoyer();
                $nbPersFoyer = isset($nbPersFoyer) ? (int)$nbPersFoyer : $beneficiaire->getNbPersFoyer();
                $revenuFiscalReferenceFoyer = $demande->getDemandeTravaux()->getRevenu3();
                $revenuFiscalReferenceFoyer = isset($revenuFiscalReferenceFoyer) ? trim($revenuFiscalReferenceFoyer) : $beneficiaire->getRevenuFiscalRef();
                break;
        }

        $typeMenageCode = $this->ANAHService->findTypeMenageCode(
            $nbPersFoyer,
            $revenuFiscalReferenceFoyer,
            $ANAHCritere
        );
        if (!empty($typeMenageCode)) {
            $demande->setTypeMenage($typeMenageCode);
        }
    }


    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $demandeId
     * @param $demandeType
     * @return array
     */
    private function findDocumentNonConforme($demandeId, $demandeType)
    {
        $arrayJPreasonSlug = [];
        $arrayKBISreasonSlug = [];
        $arrayAIreasonSlug = [];
        $conformiteJP = null;
        $conformiteKBIS = null;
        $conformiteAI = null;
        $reasonAutreJP = null;
        $reasonAutreKBIS = null;
        $reasonAutreAI = null;

        $instructionByDemande = $this->instruction_Repository->findOneBy(
            ['demande_id' => $demandeId]
        );

        if ($instructionByDemande) {
            $instruction = null;
            if (
                in_array($demandeType, [Demande_::DEMANDE_AUDIT_ENERGIE_TYPE, Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE])
                && $instructionByDemande->getInstructionAuditEnergie()
            ) {
                $instruction = $instructionByDemande->getInstructionAuditEnergie();
            } elseif (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType && $instructionByDemande->getInstructionTravaux()) {
                $instruction = $instructionByDemande->getInstructionTravaux();
            }

            $conformiteJP = DefaultUtils::getKey($instruction->getJPconformite());
            $reasonAutreJP = $instruction->getJPreasonAutre();

            $conformiteKBIS = DefaultUtils::getKey($instruction->getKBISconformite());
            $reasonAutreKBIS = $instruction->getKBISreasonAutre();

            $conformiteAI = DefaultUtils::getKey($instruction->getAIconformite());
            $reasonAutreAI = $instruction->getAIreasonAutre();

            if ($instruction) {
                if (!empty($instruction->getJPreason())) {
                    foreach ($instruction->getJPreason() as $item) {
                        // $item is an ID (int), need to fetch the entity
                        $reasonEntity = $this->EM->getRepository(Instruction_reason::class)->find($item);
                        if ($reasonEntity) {
                            $arrayJPreasonSlug[] = $reasonEntity->getSlug();
                        }
                    }
                }

                if (!empty($instruction->getKBISreason())) {
                    foreach ($instruction->getKBISreason() as $item) {
                        // $item is an ID (int), need to fetch the entity
                        $reasonEntity = $this->EM->getRepository(Instruction_reason::class)->find($item);
                        if ($reasonEntity) {
                            $arrayKBISreasonSlug[] = $reasonEntity->getSlug();
                        }
                    }
                }

                if (!empty($instruction->getAIreason())) {
                    foreach ($instruction->getAIreason() as $item) {
                        // $item is an ID (int), need to fetch the entity
                        $reasonEntity = $this->EM->getRepository(Instruction_reason::class)->find($item);
                        if ($reasonEntity) {
                            $arrayAIreasonSlug[] = $reasonEntity->getSlug();
                        }
                    }
                }
            }
        }

        return [
            'arrayJPreasonSlug'   => $arrayJPreasonSlug,
            'arrayKBISreasonSlug' => $arrayKBISreasonSlug,
            'arrayAIreasonSlug'   => $arrayAIreasonSlug,
            'conformiteJP'        => $conformiteJP,
            'conformiteKBIS'      => $conformiteKBIS,
            'conformiteAI'        => $conformiteAI,
            'reasonAutreJP'       => $reasonAutreJP,
            'reasonAutreKBIS'     => $reasonAutreKBIS,
            'reasonAutreAI'       => $reasonAutreAI,
        ];
    }

    /**
     * @param $data
     * @param Fpdi $pdf
     */
    private function writeFicheLiaison($data, Fpdi $pdf)
    {
        $style = '';
        $border = 0;

        // NUMERO DE DOSSIER
        $pdf->SetFont('Helvetica', $style, 13);
        $pdf->SetXY(100, 2);
        $pdf->Write(50, $data['numeroDossierD']);

        // TYPE DE DEMANDE
        $pdf->SetFont('Helvetica', $style, 13);
        if ($data['typeDemande'] == "niveau1") {
            $pdf->SetXY(84, 33);
            $pdf->MultiCell(0, 5, utf8_decode(Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_1_KEY));
        } elseif ($data['typeDemande'] == "niveau2") {
            $pdf->SetXY(84, 33);
            $pdf->MultiCell(0, 5, utf8_decode(Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_KEY));
        } elseif ($data['typeDemande'] == "niveau2renovateur") {
            $pdf->SetXY(70, 33);
            $pdf->MultiCell(0, 5, utf8_decode(Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_KEY));
        } elseif ($data['typeDemande'] == "niveauBBCrenovateur") {
            $pdf->SetXY(80, 33);
            $pdf->MultiCell(0, 5, utf8_decode(Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_KEY));
        } elseif ($data['typeDemande'] == "niveauBBCbiosource") {
            $pdf->SetXY(70, 33);
            $pdf->MultiCell(0, 5, utf8_decode(Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_KEY));
        }

        // ADRESSE DEMANDEUR
        $pdf->SetFont('Helvetica', $style, 10);
        $pdf->SetXY(28, 58);
        $pdf->MultiCell(0, 5,  utf8_decode($data['prenomBenef']) . " " . utf8_decode($data['nomBenef']));

        $pdf->SetXY(28, 64); // saut de ligne de +6 en Y
        $pdf->MultiCell(0, 5, utf8_decode($data['numRueBenef'] . " " . $data['nomRueBenef']));

        $pdf->SetXY(28, 70); // saut de ligne de +6 en Y
        $pdf->MultiCell(0, 5, utf8_decode($data['codePBenef'] . " " . $data['villeBenef']));

        // STRUCTURE H&E
        $pdf->SetFont('Helvetica', $style, 10);
        $pdf->SetXY(130, 58);
        $pdf->MultiCell(0, 5, utf8_decode($data['nomStructureI']));

        // CONSEILLER H&E
        $pdf->SetFont('Helvetica', $style, 10);
        $pdf->SetXY(130, 72);
        $pdf->MultiCell(0, 5, utf8_decode($data['nomStructureC'] . " " . $data['prenomStructureC']));

        // Audit réalisé : OUI / NON
        $pdf->SetFont('Helvetica', $style, 10);
        $pdf->SetXY(145, 35);
        if ($data['statutAudit'] == 1) {
            $pdf->Write(92, "OUI");
        } else {
            $pdf->Write(92, "NON");
        }

        // Adresse des travaux
        $pdf->SetFont('Helvetica', $style, 10);
        $pdf->SetXY(15, 110);
        $arrayComplementRue = $data['complementRueTravaux'] ? explode(" | ", $data['complementRueTravaux']) : [];
        $pdf->MultiCell(0, 5, utf8_decode($data['numRueAdresseTravaux'] . " " . (!empty($arrayComplementRue[1]) ? $arrayComplementRue[1] . " " : "") . $data['adresseTravaux']));
        $pdf->SetXY(15, 116); // saut de ligne de +6 en Y

        if ($data['complement1Travaux']  || $data['complement2Travaux']) {
            $pdf->MultiCell(0, 5, utf8_decode($data['complement1Travaux'] . " " . $data['complement2Travaux']));
            $pdf->SetXY(15, 122); // saut de ligne de +6 en Y
        }
        $pdf->MultiCell(0, 5, utf8_decode($data['cpAdresseTravaux'] . " " . $data['villeAdresseTravaux']));

        // Date d'inscription
        $pdf->SetFont('Helvetica', $style, 10);
        $pdf->SetXY(74, 113);
        $dateInscription = strtotime($data['dateInscription']);
        $pdf->Write(0, utf8_decode(date('d/m/Y', $dateInscription)));

        // Date de Commission Permanente
        $pdf->SetFont('Helvetica', $style, 10);
        $pdf->SetXY(74, 128);
        if (!is_null($data['dateCp'])) {
            $dateCp = strtotime($data['dateCp']);
            $pdf->Write(0, utf8_decode(date('d/m/Y', $dateCp)));
        }

        // Travaux souhaités
        $pdf->SetFont('Helvetica', $style, 10);
        if (is_null($data["travauxSouhaite"])) {
            $pdf->SetXY(137, 110);
            $pdf->Write(179, "");
        } else {
            $pdf->SetRightMargin(10);
            $pdf->SetXY(137, 110);
            $pdf->MultiCell(0, 5, utf8_decode($data['travauxSouhaite']));
        }

        // Rénovateur
        $pdf->SetRightMargin(50);
        $pdf->SetFont('Helvetica', $style, 10);
        $pdf->SetXY(74, 140);
        $pdf->MultiCell(0, 5, utf8_decode($data['renovateur'] . chr(10)));
        $pdf->SetXY(74, 145); // saut de ligne de +4 en Y
        $pdf->MultiCell(0, 5, utf8_decode($data['CP'] . " " . $data['ville']));

        // Tableau "Financement prévu"
        $tableauFinancementXMontant = 65;
        $tableauFinancementXLibelle = 96;
        $tableauFinancementYCourant = 153;
        $tableauFinancementYIncrement = 9;
        $tableauFinancementCellWMontant = 30;
        $tableauFinancementCellHMontant = 8;
        $tableauFinancementCellWLibelle = 104;
        $tableauFinancementCellHLibelle = 8;

        $pdf->SetFont('Arial', 'B', '10');
        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        if (is_null($data['aideRegion'])) {
            $pdf->Cell($tableauFinancementCellWMontant, 8, "0  " . chr(128), $border, '', 'R');
        } else {
            $pdf->Cell($tableauFinancementCellWMontant, 8, number_format($data['aideRegion'], 2, ',', ' ') . "  " . chr(128), $border, '', 'R');
        }

        $tableauFinancementYCourant += $tableauFinancementYIncrement;
        $pdf->SetFont('Arial', 'B', '10');
        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        if (is_null($data['aideDepartement'])) {
            $pdf->Cell($tableauFinancementCellWMontant, 8, "0  " . chr(128), $border, '', 'R');
        } else {
            $pdf->Cell($tableauFinancementCellWMontant, 8, number_format($data['aideDepartement'], 2, ',', ' ') . "  " . chr(128), $border, '', 'R');
        }
        $pdf->setXY($tableauFinancementXLibelle, $tableauFinancementYCourant);
        $pdf->SetFont('Arial', 'B', '9');
        if (is_null($data['aideDepartementOrigine'])) {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode(""), $border);
        } else {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode($data['aideDepartementOrigine']), $border);
        }

        $tableauFinancementYCourant += $tableauFinancementYIncrement;
        $pdf->SetFont('Arial', 'B', '10');
        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        if (is_null($data['aideIntercommunalite'])) {
            $pdf->Cell($tableauFinancementCellWMontant, 8, "0  " . chr(128), $border, '', 'R');
        } else {
            $pdf->Cell($tableauFinancementCellWMontant, 8, number_format($data['aideIntercommunalite'], 2, ',', ' ') . "  " . chr(128), $border, '', 'R');
        }
        $pdf->setXY($tableauFinancementXLibelle, $tableauFinancementYCourant);
        $pdf->SetFont('Arial', 'B', '9');
        if (is_null($data['aideIntercommunaliteOrigine'])) {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode(""), $border);
        } else {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode($data['aideIntercommunaliteOrigine']), $border);
        }

        $tableauFinancementYCourant += $tableauFinancementYIncrement;
        $pdf->SetFont('Arial', 'B', '10');
        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        if (is_null($data['creditImpot'])) {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, "0  " . chr(128), $border, '', 'R');
        } else {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, number_format($data['creditImpot'], 2, ',', ' ') . "  " . chr(128), $border, '', 'R');
        }
        $pdf->setXY($tableauFinancementXLibelle, $tableauFinancementYCourant);
        $pdf->SetFont('Arial', 'B', '9');
        if (is_null($data['typeMaPrimeRenov'])) {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode(""), $border);
        } else {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode($data['typeMaPrimeRenov']), $border);
        }

        //        $tableauFinancementYCourant += $tableauFinancementYIncrement;
        //        $pdf->SetFont('Arial', 'B', '10');
        //        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        //        if (is_null($data['aideHabiterMieux'])) {
        //            $pdf->Cell($tableauFinancementCellWMontant,$tableauFinancementCellHMontant, "0  ".chr(128), $border, '', 'R');
        //        } else {
        //            $pdf->Cell($tableauFinancementCellWMontant,$tableauFinancementCellHMontant, number_format($data['aideHabiterMieux'], 2, ',', ' ')."  ".chr(128), $border, '', 'R');
        //        }
        //        $pdf->setXY($tableauFinancementXLibelle, $tableauFinancementYCourant);
        //        $pdf->SetFont('Arial', 'B', '9');
        //        if (is_null($data['typeMaPrimeRenovSerenite'])) {
        //            $pdf->Cell($tableauFinancementCellWLibelle,$tableauFinancementCellHLibelle,utf8_decode(""), $border);
        //        } else {
        //            $pdf->Cell($tableauFinancementCellWLibelle,$tableauFinancementCellHLibelle,utf8_decode($data['typeMaPrimeRenovSerenite']), $border);
        //        }

        $tableauFinancementYCourant += $tableauFinancementYIncrement;
        $pdf->SetFont('Arial', 'B', '10');
        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        if (is_null($data['CEE'])) {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, "0  " . chr(128), $border, '', 'R');
        } else {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, number_format($data['CEE'], 2, ',', ' ') . "  " . chr(128), $border, '', 'R');
        }

        $tableauFinancementYCourant += $tableauFinancementYIncrement;
        $pdf->SetFont('Arial', 'B', '10');
        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        if (is_null($data['ecoPTZ'])) {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, "0  " . chr(128), $border, '', 'R');
        } else {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, number_format($data['ecoPTZ'], 2, ',', ' ') . "  " . chr(128), $border, '', 'R');
        }
        $pdf->setXY($tableauFinancementXLibelle, $tableauFinancementYCourant);
        $pdf->SetFont('Arial', 'B', '9');
        if (is_null($data['ecoPTZBanque'])) {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode("Pas de banque"), 0);
        } else {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode($data['ecoPTZBanque']), 0);
        }

        $tableauFinancementYCourant += $tableauFinancementYIncrement;
        $pdf->SetFont('Arial', 'B', '10');
        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        if (is_null($data['fondPropres'])) {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, "0  " . chr(128), $border, '', 'R');
        } else {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, number_format($data['fondPropres'], 2, ',', ' ') . "  " . chr(128), $border, '', 'R');
        }

        $tableauFinancementYCourant += $tableauFinancementYIncrement;
        $pdf->SetFont('Arial', 'B', '10');
        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        if (is_null($data['autrePret'])) {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, "0  " . chr(128), $border, '', 'R');
        } else {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, number_format($data['autrePret'], 2, ',', ' ') . "  " . chr(128), $border, '', 'R');
        }
        $pdf->setXY($tableauFinancementXLibelle, $tableauFinancementYCourant);
        $pdf->SetFont('Arial', 'B', '9');
        if (is_null($data['autrePretBanque'])) {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode("Pas de banque"), $border);
        } else {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode($data['autrePretBanque']), $border);
        }

        $tableauFinancementYCourant += $tableauFinancementYIncrement;
        $pdf->SetFont('Arial', 'B', '10');
        $pdf->setXY($tableauFinancementXMontant, $tableauFinancementYCourant);
        if (is_null($data['autreAide'])) {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, "0  " . chr(128), $border, '', 'R');
        } else {
            $pdf->Cell($tableauFinancementCellWMontant, $tableauFinancementCellHMontant, number_format($data['autreAide'], 2, ',', ' ') . "  " . chr(128), $border, '', 'R');
        }
        $pdf->setXY($tableauFinancementXLibelle, $tableauFinancementYCourant);
        $pdf->SetFont('Arial', 'B', '9');
        if (is_null($data['autreAideOrigine'])) {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode(""), $border);
        } else {
            $pdf->Cell($tableauFinancementCellWLibelle, $tableauFinancementCellHLibelle, utf8_decode($data['autreAideOrigine']), $border);
        }

        $tableauFinancementYMontantTravaux = $tableauFinancementYCourant + 17.5;

        // Montant Travaux
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetXY(52, $tableauFinancementYMontantTravaux);
        if (is_null($data['totalDevis'])) {
            $pdf->Cell(50, 10, "0  " . chr(128), $border, '', 'R', false);
        } else {
            $pdf->Cell(50, 10, utf8_decode(number_format($data['totalDevis'], 2, ',', ' ')) . "  " . chr(128), $border, '', 'R', false);
        }

        // Financement
        $totalPlanFinancement = (int)$data['aideRegion']
            + (int)$data['aideDepartement']
            + (int)$data['aideIntercommunalite']
            + (int)$data['creditImpot']
            //            + (integer)$data['aideHabiterMieux']
            + (int)$data['CEE']
            + (int)$data['ecoPTZ']
            + (int)$data['fondPropres']
            + (int)$data['autrePret']
            + (int)$data['autreAide'];

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetXY(150, $tableauFinancementYMontantTravaux);
        if (is_null($totalPlanFinancement)) {
            $pdf->Cell(50, 10, "0  " . chr(128), 0, '', 'R', false);
        } else {
            $pdf->Cell(50, 10, utf8_decode(number_format($totalPlanFinancement, 2, ',', ' ')) . "  " . chr(128), 0, '', 'R', false);
        }
    }

    /**
     * @param $demandeId
     * @return void
     */
    private function cleanInstructionAdministrative($demandeId)
    {
        /* /////////////////////////////////////////////////////////////////
                                GET INSTRUCTION
        ///////////////////////////////////////////////////////////////// */
        $instruction = $this->instruction_Repository->findOneBy([
            'demande_id' => $demandeId
        ]);
        if (!empty($instruction)) {
            $this->EM->remove($instruction);
            $this->EM->flush();
        }
    }

    /**
     * @param Demande_ $demande
     * @return void
     */
    private function cleanInstructionTechnique(Demande_ $demande)
    {
        if (Demande_::DEMANDE_TRAVAUX_TYPE == $demande->getType() && !empty($demande->getDemandeTravaux())) {

            $demandeTravaux = $demande->getDemandeTravaux();

            if (!empty($demandeTravaux->getFicheTechniqueId())) {
                /* /////////////////////////////////////////////////////////////////
                                 GET FICHE TECHNIQUE ET MISE A JOUR
                ///////////////////////////////////////////////////////////////// */
                $ficheTechnique = $this->ficheTechniqueRepository->find($demandeTravaux->getFicheTechniqueId());
                if (!empty($ficheTechnique)) {
                    $this->EM->remove($ficheTechnique);
                    $this->EM->flush();

                    $demandeTravaux->setFicheTechniqueId(null);
                    $demande->setDateModif(new \DateTime());
                    $demande->setAuteurModif($_SESSION['login']->getUsername());
                    $this->EM->persist($demande);
                    $this->EM->flush();
                }
            }

            if (!empty($demandeTravaux->getTravauxDevisId())) {
                /* /////////////////////////////////////////////////////////////////
                                 GET DEMANDE TRAVAUX DEVIS ET MISE A JOUR
                ///////////////////////////////////////////////////////////////// */
                $demandeTravauxDevis = $this->demande_travaux_devisRepository->find($demandeTravaux->getTravauxDevisId());
                if (!empty($demandeTravauxDevis)) {
                    $demandeTravauxDevis->setStatutInstruction('0');
                    $demandeTravauxDevis->setInstructionDossierConforme(null);
                    $this->EM->persist($demandeTravauxDevis);
                    $this->EM->flush();
                }
            }
        }
    }

    /**
     * Si Logement est passé en paramètre alors cela concerne une modification Logement,
     * sinon c'est une modification beneficiaire
     *
     * @param Demande_ $demande
     * @param Beneficiaire $beneficiaire
     * @param Logement|null $logement
     * @return void
     */
    private function updateStatutAndHistoriseAfterCleanInstruction(
        Demande_ $demande,
        Beneficiaire $beneficiaire,
        Logement $logement = null
    ) {
        $statut = null;
        $envoiEmailDansHistoriqueSave = false;
        $beneficiaireEmail = $beneficiaire->getEmail();
        $actionLabel = !empty($logement) ? 'Modification fiche Logement' : 'Modification fiche Bénéficiaire';

        if (empty($logement)) {
            // CAS MODIFICATION BENEFICIAIRE
            $logement = $this->logementRepository->find($demande->getLogementId());
        }

        /* /////////////////////////////////////////////////////////////////
                            GET DEMANDE AUDIT ENERGIE
        ///////////////////////////////////////////////////////////////// */
        if (in_array($demande->getType(), [
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_TRAVAUX_TYPE
        ])) {
            /**
             * @var Demande_ $auditE
             */
            $auditE = $this->demande_Repository->findOneBy(
                [
                    'logement_id' => $logement->getId(),
                    'type'        => Demande_::DEMANDE_AUDIT_ENERGIE_TYPE
                ],
                [
                    'id' => 'DESC'
                ]
            );
        }

        switch ($demande->getType()) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                DEMANDE AUDIT ENERGETIQUE ET SCENARIOS
                                DEMANDE AUDIT REGION NORMANDIE
                ///////////////////////////////////////////////////////////////// */
                $participationSARE = $this->demande_Repository->findParticipationSAREByLogementId($demande->getLogementId());

                /* /////////////////////////////////////////////////////////////////
                                CALCUL REVENU FISCAL DE REFERENCE
                ///////////////////////////////////////////////////////////////// */
                $checkSAREDemande = $this->checkSAREDemandeAuditEtTravaux(
                    null,
                    $participationSARE,
                    $demande->getDemandeAuditEnergie()->getNbPersFoyer(),
                    $demande->getDemandeAuditEnergie()->getRevenu3()
                );

                if (empty($checkSAREDemande)) {
                    $statut = $this->searchStatutRefus();
                    // On initie le Motif refus à celui de motif refus ANAH car si checkSAREDemandeAuditEtTravaux() a renovyé false
                    //  alors nous sommes ici dû au "revenu fiscal de référence du foyer" dépassant le "plafond de l'Anah"
                    $demande->setMotifRefus(self::MOTIF_REFUS_ANAH);
                } else {
                    $isDoublon = $this->checkDoublon(
                        $demande->getType(),
                        $beneficiaire->getNom(),
                        $beneficiaire->getPrenom(),
                        $logement->getCodePostal(),
                        $logement->getVille()
                    );
                    if (true == $isDoublon) {
                        $statut = $this->searchStatutRefus();
                        $demande->setMotifRefus(self::MOTIF_REFUS_DOUBLON);
                    } else {
                        // L'instruction (et les conformités) n'existent plus à ce moment là car effacées avant
                        $statut = $this->searchStatutForDemandeAuditEnergie(
                            null,
                            null,
                            null,
                            $demande->getDemandeAuditEnergie()->getJustificatifPropriete(),
                            $demande->getDemandeAuditEnergie()->getPieceComplement(),
                            $demande->getDemandeAuditEnergie()->getAvisImposition(),
                            $demande->getDemandeAuditEnergie()->getJustificatifProprieteAlt(),
                            $demande->getDemandeAuditEnergie()->getPieceComplementAlt(),
                            $demande->getDemandeAuditEnergie()->getAvisImpositionAlt(),
                            null,
                            $beneficiaire->getType(),
                            $demande->getDemandeAuditEnergie()->getAuditeurId()
                        );
                    }
                }

                if (!empty($statut)) {
                    $demande->setStatutId($statut);

                    $demande->setDateModif(new \Datetime());
                    $demande->setAuteurModif($_SESSION['login']->getUsername());
                }

                $this->EM->persist($demande);
                $this->EM->flush();

                // MISE A JOUR DEMANDE STATUT DESCRIPTION
                $demande->setStatutDescription($this->findStatutDescriptionByDemande($demande->getId()));
                $this->EM->persist($demande);
                $this->EM->flush();

                /* /////////////////////////////////////////////////////////////////
                                        FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                $historique = $this->historiqueService->save(
                    $demande->getId(),
                    $statut,
                    $demande->getType(),
                    $this->tokenStorage->getToken()->getUser()->getRoles(),
                    $envoiEmailDansHistoriqueSave,
                    $actionLabel,
                    $beneficiaireEmail,
                    $beneficiaire->getType(),
                    $demande->getDemandeAuditEnergie()->getJustificatifProprieteAlt(),
                    $demande->getDemandeAuditEnergie()->getPieceComplementAlt(),
                    $demande->getDemandeAuditEnergie()->getAvisImpositionAlt(),
                    true
                );
                break;
            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                DEMANDE AUDIT NUMERIQUE
                                DEMANDE MISE A JOUR AUDIT ENERGETIQUE
                ///////////////////////////////////////////////////////////////// */

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

                $checkSAREDemande = $this->demande_Repository->findParticipationSAREByLogementId($logement->getId());
                if (empty($checkSAREDemande)) {
                    // SI EPCI NON PARTICIPATION SARE => DEMANDE REFUSEE
                    $statut = $this->searchStatutRefus();
                    $demande->setMotifRefus(DemandeServiceFO::MOTIF_REFUS_NON_PARTICIPATION_SARE);
                } else {
                    $statut = (true == $isDoublon) ? $this->searchStatutForNoDateCP() : $this->searchStatutForDemandeAuditNumerique($auditE['dateCP_id'], $auditE['statut_id']);
                }

                if (!empty($statut)) {
                    $demande->setStatutId($statut);

                    $demande->setDateModif(new \Datetime());
                    $demande->setAuteurModif($_SESSION['login']->getUsername());
                }

                $this->EM->persist($demande);
                $this->EM->flush();

                // MISE A JOUR DEMANDE STATUT DESCRIPTION
                $demande->setStatutDescription($this->findStatutDescriptionByDemande($demande->getId()));
                $this->EM->persist($demande);
                $this->EM->flush();

                /* /////////////////////////////////////////////////////////////////
                                        FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                $historique = $this->historiqueService->save(
                    $demande->getId(),
                    $statut,
                    $demande->getType(),
                    $this->tokenStorage->getToken()->getUser()->getRoles(),
                    $envoiEmailDansHistoriqueSave,
                    $actionLabel,
                    $beneficiaireEmail,
                    $beneficiaire->getType(),
                    null,
                    null,
                    null,
                    true
                );
                break;
            case Demande_::DEMANDE_TRAVAUX_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                DEMANDE TRAVAUX
                ///////////////////////////////////////////////////////////////// */
                $demandeTravaux_justificatifPropriete = null;
                $demandeTravaux_pieceComplement = null;
                $demandeTravaux_avisImposition = null;
                $demandeTravaux_justificatifProprieteAlt = null;
                $demandeTravaux_pieceComplementAlt = null;
                $demandeTravaux_avisImpositionAlt = null;
                $demandeTravaux_travauxDevisId = null;
                $demandeTravaux_audit = null;

                $participationSARE = $this->demande_Repository->findParticipationSAREByLogementId($logement->getId());

                /* /////////////////////////////////////////////////////////////////
                                CALCUL REVENU FISCAL DE REFERENCE
                ///////////////////////////////////////////////////////////////// */
                $arraySituation = explode(' | ', $logement->getSituation());
                $demandeTravaux_nbPersFoyer = $demande->getDemandeTravaux()->getNbPersFoyer();
                $demandeTravaux_revenuReference = $demande->getDemandeTravaux()->getRevenu3();

                $checkSAREDemande = $this->checkSAREDemandeAuditEtTravaux(
                    $auditE,
                    $participationSARE,
                    $demandeTravaux_nbPersFoyer,
                    $demandeTravaux_revenuReference
                );

                if (empty($checkSAREDemande)) {
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

                            // La fiche technique a été effacée prealablement
                            // et le champ InstructionDossierConforme mis à null
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

                    $demande->setDateModif(new \Datetime());
                    $demande->setAuteurModif($_SESSION['login']->getUsername());
                }

                $this->EM->persist($demande);
                $this->EM->flush();

                // MISE A JOUR DEMANDE STATUT DESCRIPTION
                $demande->setStatutDescription($this->findStatutDescriptionByDemande($demande->getId()));
                $this->EM->persist($demande);
                $this->EM->flush();

                /* /////////////////////////////////////////////////////////////////
                                        FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                $historique = $this->historiqueService->save(
                    $demande->getId(),
                    $statut,
                    $demande->getType(),
                    $this->tokenStorage->getToken()->getUser()->getRoles(),
                    $envoiEmailDansHistoriqueSave,
                    $actionLabel,
                    $beneficiaireEmail,
                    $beneficiaire->getType(),
                    $demande->getDemandeTravaux()->getJustificatifProprieteAlt(),
                    $demande->getDemandeTravaux()->getPieceComplementAlt(),
                    $demande->getDemandeTravaux()->getAvisImpositionAlt(),
                    true
                );
                break;
        }

        /* /////////////////////////////////////////////////////////////////
                ENVOI EMAIL + HISTORISATION EMAIL :
                REFUS DU A EPCI NON PARTICIPATION SARE
        ///////////////////////////////////////////////////////////////// */

        // désactivé suite US refus demande motif
        //        if (isset($checkSAREDemande) && empty($checkSAREDemande)) {
        //            $this->sendEmailRefusNonParticipationSARE(
        //                $historique,
        //                $beneficiaireEmail,
        //                $demande->getMotifRefus()
        //            );
        //        }
    }
}

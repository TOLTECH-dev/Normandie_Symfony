<?php

namespace App\Service;

use App\Entity\Demande_travaux_devis;
use App\Entity\Demande_travaux_devis_upload;
use App\Entity\FicheTechnique;
use App\Entity\FicheTechniqueField;
use App\Entity\Remboursement_travaux_instruction;
use App\Entity\Remboursement_travaux_instruction_conformite;

class RollbackDocumentService
{
    public static string $suffixWithExtension = '_rollback.pdf';

    public static string $stringExtensionBase = ".{pdf, PDF, jpg, JPG, jpeg, JPEG, png, PNG}";
    public static string $stringExtensionXml = ".{xml, XML}";
    public static string $stringExtensionPdf = ".{pdf, PDF}";

    private string $rootUploadDir;

    public function __construct(string $appRootDossierDataSymfony)
    {
        $this->rootUploadDir = $appRootDossierDataSymfony;
    }

    /**
     * @param FicheTechnique $ficheTechnique
     * @param $listRollbackDocumentFilesPath
     * @return void
     */
    public function setListRollbackFicheTechniqueDemandeDocument(
        FicheTechnique $ficheTechnique,
                       &$listRollbackDocumentFilesPath
    ): void
    {
        $ficheTechniqueInitial = !empty($ficheTechnique->getFicheTechniqueInitial()) ? $ficheTechnique->getFicheTechniqueInitial() : null;
        $ficheTechniqueBBC = !empty($ficheTechnique->getFicheTechniqueBBC()) ? $ficheTechnique->getFicheTechniqueBBC() : null;
        $ficheTechniquePrescription = !empty($ficheTechnique->getFicheTechniquePrescription()) ? $ficheTechnique->getFicheTechniquePrescription() : null;

        $fichesTechniques = [
            $ficheTechniqueInitial,
            $ficheTechniqueBBC,
            $ficheTechniquePrescription
        ];

        foreach ($fichesTechniques as $currentFicheTechnique) {
            if (!empty($currentFicheTechnique)) {
                $listRollbackDocumentFilesPath[] = $this->rootUploadDir . $currentFicheTechnique->ficheTechniqueDocument_getRollbackWebPath();
            }
        }
    }

    /**
     * @param FicheTechnique $ficheTechnique
     * @param $listRollbackDocumentFilesPath
     * @return void
     */
    public function setListRollbackFicheTechniqueRemboursementDocument(
        FicheTechnique $ficheTechnique,
                       &$listRollbackDocumentFilesPath
    ): void
    {
        $ficheTechniqueInitial = !empty($ficheTechnique->getFicheTechniqueInitial()) ? $ficheTechnique->getFicheTechniqueInitial() : null;
        $ficheTechniqueBBC = !empty($ficheTechnique->getFicheTechniqueBBC()) ? $ficheTechnique->getFicheTechniqueBBC() : null;
        $ficheTechniquePrescription = !empty($ficheTechnique->getFicheTechniquePrescription()) ? $ficheTechnique->getFicheTechniquePrescription() : null;
        $ficheTechniqueFinChantier = !empty($ficheTechnique->getFicheTechniqueFinChantier()) ? $ficheTechnique->getFicheTechniqueFinChantier() : null;

        $fichesTechniques = [
            $ficheTechniqueInitial,
            $ficheTechniqueBBC,
            $ficheTechniquePrescription,
            $ficheTechniqueFinChantier
        ];

        foreach ($fichesTechniques as $currentFicheTechnique) {
            if (!empty($currentFicheTechnique)) {
                $listRollbackDocumentFilesPath[] = $this->rootUploadDir . $currentFicheTechnique->ficheTechniqueDocument_getRollbackWebPath();
            }
        }
    }

    /**
     * @param Remboursement_travaux_instruction $instruction
     * @param $listRollbackDocumentFilesPath
     * @return void
     */
    public function setListRollbackRectoVersoCheque(
        Remboursement_travaux_instruction $instruction,
                                          &$listRollbackDocumentFilesPath
    ): void
    {
        if (!empty($instruction)) {
            $listRollbackDocumentFilesPath[] = $this->rootUploadDir . $instruction->rectoCheque_getRollbackWebPath();
            $listRollbackDocumentFilesPath[] = $this->rootUploadDir . $instruction->versoCheque_getRollbackWebPath();
        }
    }

    /**
     * @param Remboursement_travaux_instruction $instruction
     * @param                                                                       $listRollbackDocumentFilesPath
     *
     * @return void
     */
    public function setListRollbackFicheTravaux(
        Remboursement_travaux_instruction $instruction,
                                          &$listRollbackDocumentFilesPath
    ): void
    {
        if (!empty($instruction)) {
            $listRollbackDocumentFilesPath[] = $this->rootUploadDir . $instruction->ficheTravaux_getRollbackWebPath();
        }
    }

    /**
     * @param Remboursement_travaux_instruction $instruction
     * @param $listRollbackDocumentFilesPath
     * @return void
     */
    public function setListRollbackTravauxFactures(
        Remboursement_travaux_instruction $instruction,
                                          &$listRollbackDocumentFilesPath
    ): void
    {
        if (!empty($instruction)) {
            /**
             * @var Remboursement_travaux_instruction_conformite $remboursementTravauxInstructionConformiteCurrent
             */
            foreach ($instruction->getRemboursementTravauxInstructionConformite() as $remboursementTravauxInstructionConformiteCurrent) {
                $listRollbackDocumentFilesPath[] = $this->rootUploadDir . $remboursementTravauxInstructionConformiteCurrent->document_getRollbackWebPath();
            }
        }
    }

    /**
     * @param Demande_travaux_devis $demandeTravauxDevis
     * @param $listRollbackDocumentFilesPath
     * @return void
     */
    public function setListRollbackTravauxDevisAudit(
        Demande_travaux_devis $demandeTravauxDevis,
                              &$listRollbackDocumentFilesPath
    ): void
    {
        if (!empty($demandeTravauxDevis)) {
            $listRollbackDocumentFilesPath[] = $this->rootUploadDir . $demandeTravauxDevis->audit_getRollbackWebPath();
        }
    }

    /**
     * @param Demande_travaux_devis $demandeTravauxDevis
     * @param $listRollbackDocumentFilesPath
     * @return void
     */
    public function setListRollbackTravauxDevisActeEngagement(
        Demande_travaux_devis $demandeTravauxDevis,
                              &$listRollbackDocumentFilesPath
    ): void
    {
        if (!empty($demandeTravauxDevis)) {
            $listRollbackDocumentFilesPath[] = $this->rootUploadDir . $demandeTravauxDevis->acteEngagement_getRollbackWebPath();
        }
    }

    /**
     * @param Demande_travaux_devis $demandeTravauxDevis
     * @param $listRollbackDocumentFilesPath
     * @return void
     */
    public function setListRollbackTravauxDevisUpload(
        Demande_travaux_devis $demandeTravauxDevis,
                              &$listRollbackDocumentFilesPath
    ): void
    {
        if (!empty($demandeTravauxDevis)) {
            $demandesTravauxDevisUpload = $demandeTravauxDevis->getDemandeTravauxDevisUpload();

            /**
             * @var Demande_travaux_devis_upload $demandeTravauxDevisUpload
             */
            foreach ($demandesTravauxDevisUpload as $demandeTravauxDevisUpload) {
                $listRollbackDocumentFilesPath[] = $this->rootUploadDir . $demandeTravauxDevisUpload->devisDocument_getRollbackWebPath();
            }
        }
    }

    /**
     * @param Remboursement_travaux_instruction $instruction
     * @param $success
     * @param bool $isReturnArrayOnly
     * @return array
     */
    public function rollbackUpdateRectoVersoCheque(
        Remboursement_travaux_instruction &$instruction,
        &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        $rectoChequeFilePrefix = $this->rootUploadDir . $instruction->rectoCheque_getRollbackWebPathPrefix();
        $rectoChequeFiles = glob($rectoChequeFilePrefix . self::$stringExtensionBase, GLOB_BRACE);
        $rectoChequeFilename = !empty($rectoChequeFiles) ? basename($rectoChequeFiles[0]) : null;
        $rectoChequeFilenameExtension = !empty($rectoChequeFilename) ? substr($rectoChequeFilename, strrpos($rectoChequeFilename, '.') + 1) : '';

        // Si document liaison existe sur le serveur => On refait la liaison
        if (!empty($rectoChequeFilename)) {
            $instruction
                ->setRectoChequeAlt($rectoChequeFilename)
                ->setRectoChequeUrl($rectoChequeFilenameExtension);
            $success = true;

            if (!empty($isReturnArrayOnly)) {
                $return['isSuccess'][] = $success;
                $return['filename'][] = basename($rectoChequeFilename);
            }
        }

        $versoChequeFilePrefix = $this->rootUploadDir . $instruction->versoCheque_getRollbackWebPathPrefix();
        $versoChequeFiles = glob($versoChequeFilePrefix . self::$stringExtensionBase, GLOB_BRACE);
        $versoChequeFilename = !empty($versoChequeFiles) ? basename($versoChequeFiles[0]) : null;
        $versoChequeFilenameExtension = !empty($versoChequeFilename) ? substr($versoChequeFilename, strrpos($versoChequeFilename, '.') + 1) : '';

        if (!empty($versoChequeFilename)) {
            $instruction
                ->setVersoChequeAlt($versoChequeFilename)
                ->setVersoChequeUrl($versoChequeFilenameExtension);
            $success = true;

            if (!empty($isReturnArrayOnly)) {
                $return['isSuccess'][] = $success;
                $return['filename'][] = basename($versoChequeFilename);
            }
        }

        return $return;
    }

    /**
     * @param Remboursement_travaux_instruction $instruction
     * @param $success
     * @param bool $isReturnArrayOnly
     * @return array
     */
    public function rollbackUpdateFicheTravaux(
        Remboursement_travaux_instruction &$instruction,
        &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        $ficheTravauxFilePrefix = $this->rootUploadDir . $instruction->ficheTravaux_getRollbackWebPathPrefix();
        $ficheTravauxFiles = glob($ficheTravauxFilePrefix . self::$stringExtensionBase, GLOB_BRACE);
        $ficheTravauxFilename = !empty($ficheTravauxFiles) ? basename($ficheTravauxFiles[0]) : null;
        $ficheTravauxFilenameExtension = !empty($ficheTravauxFilename) ? substr($ficheTravauxFilename, strrpos($ficheTravauxFilename, '.') + 1) : '';

        // Si document liaison existe sur le serveur => On refait la liaison
        if (!empty($ficheTravauxFilename)) {
            $instruction
                ->setFicheTravauxAlt($ficheTravauxFilename)
                ->setFicheTravauxUrl($ficheTravauxFilenameExtension);
            $success = true;

            if (!empty($isReturnArrayOnly)) {
                $return['isSuccess'][] = $success;
                $return['filename'][] = basename($ficheTravauxFilename);
            }
        }

        return $return;
    }

    /**
     * @param Remboursement_travaux_instruction $instruction
     * @param $success
     * @param bool $isReturnArrayOnly
     * @return array
     */
    public function rollbackUpdateTravauxFactures(
        Remboursement_travaux_instruction &$instruction,
        &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        /**
         * @var Remboursement_travaux_instruction_conformite $remboursementTravauxInstructionConformiteCurrent
         */
        foreach ($instruction->getRemboursementTravauxInstructionConformite() as $remboursementTravauxInstructionConformiteCurrent) {

            $factureFilePrefix = $this->rootUploadDir . $remboursementTravauxInstructionConformiteCurrent->document_getRollbackWebPathPrefix();
            $factureFiles = glob($factureFilePrefix . self::$stringExtensionBase, GLOB_BRACE);
            $factureFilename = !empty($factureFiles) ? basename($factureFiles[0]) : null;
            $factureFilenameExtension = !empty($factureFilename) ? substr($factureFilename, strrpos($factureFilename, '.') + 1) : '';

            if (!empty($factureFilename)) {
                $remboursementTravauxInstructionConformiteCurrent
                    ->setDocumentAlt($factureFilename)
                    ->setDocumentUrl($factureFilenameExtension);
                $success = true;

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = basename($factureFilename);
                }
            }
        }

        return $return;
    }

    /**
     * @param FicheTechnique $ficheTechnique
     * @param $success
     * @param bool $isReturnArrayOnly
     * @return array
     */
    public function rollbackUpdateFicheTechniqueDemandeDocument(
        FicheTechnique &$ficheTechnique,
        &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        $ficheTechniqueInitial = !empty($ficheTechnique->getFicheTechniqueInitial()) ? $ficheTechnique->getFicheTechniqueInitial() : null;
        $ficheTechniqueBBC = !empty($ficheTechnique->getFicheTechniqueBBC()) ? $ficheTechnique->getFicheTechniqueBBC() : null;
        $ficheTechniquePrescription = !empty($ficheTechnique->getFicheTechniquePrescription()) ? $ficheTechnique->getFicheTechniquePrescription() : null;

        $fichesTechniques = [
            $ficheTechniqueInitial,
            $ficheTechniqueBBC,
            $ficheTechniquePrescription
        ];

        /**
         * @var FicheTechniqueField $currentFicheTechnique
         */
        foreach ($fichesTechniques as $currentFicheTechnique) {
            if (!empty($currentFicheTechnique)) {
                $ficheTechniqueDocumenFilePrefix = $this->rootUploadDir . $currentFicheTechnique->ficheTechniqueDocument_getRollbackWebPathPrefix();
                $ficheTechniqueDocumenFiles = glob($ficheTechniqueDocumenFilePrefix . self::$stringExtensionXml, GLOB_BRACE);
                $ficheTechniqueDocumenFilename = !empty($ficheTechniqueDocumenFiles) ? basename($ficheTechniqueDocumenFiles[0]) : null;
                $ficheTechniqueDocumenFilenameExtension = !empty($ficheTechniqueDocumenFilename) ? substr($ficheTechniqueDocumenFilename, strrpos($ficheTechniqueDocumenFilename, '.') + 1) : '';

                if (!empty($ficheTechniqueDocumenFilename)) {
                    $currentFicheTechnique
                        ->setFicheTechniqueDocumentAlt($ficheTechniqueDocumenFilename)
                        ->setFicheTechniqueDocumentUrl($ficheTechniqueDocumenFilenameExtension);
                    $success = true;

                    if (!empty($isReturnArrayOnly)) {
                        $return['isSuccess'][] = $success;
                        $return['filename'][] = basename($ficheTechniqueDocumenFilename);
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @param FicheTechnique $ficheTechnique
     * @param $success
     * @param bool $isReturnArrayOnly
     * @return array
     */
    public function rollbackUpdateFicheTechniqueRemboursementDocument(
        FicheTechnique &$ficheTechnique,
        &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        $ficheTechniqueInitial = !empty($ficheTechnique->getFicheTechniqueInitial()) ? $ficheTechnique->getFicheTechniqueInitial() : null;
        $ficheTechniqueBBC = !empty($ficheTechnique->getFicheTechniqueBBC()) ? $ficheTechnique->getFicheTechniqueBBC() : null;
        $ficheTechniquePrescription = !empty($ficheTechnique->getFicheTechniquePrescription()) ? $ficheTechnique->getFicheTechniquePrescription() : null;
        $ficheTechniqueFinChantier = !empty($ficheTechnique->getFicheTechniqueFinChantier()) ? $ficheTechnique->getFicheTechniqueFinChantier() : null;

        $fichesTechniques = [
            $ficheTechniqueInitial,
            $ficheTechniqueBBC,
            $ficheTechniquePrescription,
            $ficheTechniqueFinChantier
        ];

        /**
         * @var FicheTechniqueField $currentFicheTechnique
         */
        foreach ($fichesTechniques as $currentFicheTechnique) {
            if (!empty($currentFicheTechnique)) {
                $ficheTechniqueDocumenFilePrefix = $this->rootUploadDir . $currentFicheTechnique->ficheTechniqueDocument_getRollbackWebPathPrefix();
                $ficheTechniqueDocumenFiles = glob($ficheTechniqueDocumenFilePrefix . self::$stringExtensionXml, GLOB_BRACE);
                $ficheTechniqueDocumenFilename = !empty($ficheTechniqueDocumenFiles) ? basename($ficheTechniqueDocumenFiles[0]) : null;
                $ficheTechniqueDocumenFilenameExtension = !empty($ficheTechniqueDocumenFilename) ? substr($ficheTechniqueDocumenFilename, strrpos($ficheTechniqueDocumenFilename, '.') + 1) : '';

                if (!empty($ficheTechniqueDocumenFilename)) {
                    $currentFicheTechnique
                        ->setFicheTechniqueDocumentAlt($ficheTechniqueDocumenFilename)
                        ->setFicheTechniqueDocumentUrl($ficheTechniqueDocumenFilenameExtension);
                    $success = true;

                    if (!empty($isReturnArrayOnly)) {
                        $return['isSuccess'][] = $success;
                        $return['filename'][] = basename($ficheTechniqueDocumenFilename);
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @param Demande_travaux_devis $demandeTravauxDevis
     * @param $success
     * @param bool $isReturnArrayOnly
     * @return array
     */
    public function rollbackUpdateTravauxDevisActeEngagement(
        Demande_travaux_devis &$demandeTravauxDevis,
        &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        $acteEngagementFilePrefix = $this->rootUploadDir . $demandeTravauxDevis->acteEngagement_getRollbackWebPathPrefix();
        $acteEngagementFiles = glob($acteEngagementFilePrefix . self::$stringExtensionPdf, GLOB_BRACE);
        $acteEngagementFilename = !empty($acteEngagementFiles) ? basename($acteEngagementFiles[0]) : null;
        $acteEngagementFilenameExtension = !empty($acteEngagementFilename) ? substr($acteEngagementFilename, strrpos($acteEngagementFilename, '.') + 1) : '';

        // Si document liaison existe sur le serveur => On refait la liaison
        if (!empty($acteEngagementFilename)) {
            $demandeTravauxDevis
                ->setActeEngagementAlt($acteEngagementFilename)
                ->setActeEngagementUrl($acteEngagementFilenameExtension);
            $success = true;

            if (!empty($isReturnArrayOnly)) {
                $return['isSuccess'][] = $success;
                $return['filename'][] = basename($acteEngagementFilename);
            }
        }

        return $return;
    }

    /**
     * @param Demande_travaux_devis $demandeTravauxDevis
     * @param $success
     * @param bool $isReturnArrayOnly
     * @return array
     */
    public function rollbackUpdateTravauxDevisAudit(
        Demande_travaux_devis &$demandeTravauxDevis,
        &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        $auditFilePrefix = $this->rootUploadDir . $demandeTravauxDevis->audit_getRollbackWebPathPrefix();
        $auditFiles = glob($auditFilePrefix . self::$stringExtensionBase, GLOB_BRACE);
        $auditFilename = !empty($auditFiles) ? basename($auditFiles[0]) : null;
        $auditFilenameExtension = !empty($auditFilename) ? substr($auditFilename, strrpos($auditFilename, '.') + 1) : '';

        // Si document liaison existe sur le serveur => On refait la liaison
        if (!empty($auditFilename)) {
            $demandeTravauxDevis
                ->setAuditAlt($auditFilename)
                ->setAuditUrl($auditFilenameExtension);
            $success = true;

            if (!empty($isReturnArrayOnly)) {
                $return['isSuccess'][] = $success;
                $return['filename'][] = basename($auditFilename);
            }
        }

        return $return;
    }

    /**
     * @param Demande_travaux_devis $demandeTravauxDevis
     * @param $success
     * @param bool $isReturnArrayOnly
     * @return array
     */
    public function rollbackUpdateTravauxDevisUpload(
        Demande_travaux_devis &$demandeTravauxDevis,
        &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        $demandesTravauxDevisUpload = $demandeTravauxDevis->getDemandeTravauxDevisUpload();
        /**
         * @var Demande_travaux_devis_upload $demandeTravauxDevisUpload
         */
        foreach ($demandesTravauxDevisUpload as $demandeTravauxDevisUpload) {

            $devisDocumentFilePrefix = $this->rootUploadDir . $demandeTravauxDevisUpload->devisDocument_getRollbackWebPathPrefix();
            $devisDocumentFiles = glob($devisDocumentFilePrefix . self::$stringExtensionBase, GLOB_BRACE);
            $devisDocumentFilename = !empty($devisDocumentFiles) ? basename($devisDocumentFiles[0]) : null;
            $devisDocumentFilenameExtension = !empty($devisDocumentFilename) ? substr($devisDocumentFilename, strrpos($devisDocumentFilename, '.') + 1) : '';

            // Si document liaison existe sur le serveur => On refait la liaison
            if (!empty($devisDocumentFilename)) {
                $demandeTravauxDevisUpload
                    ->setDevisDocumentAlt($devisDocumentFilename)
                    ->setDevisDocumentUrl($devisDocumentFilenameExtension);
                $success = true;

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = basename($devisDocumentFilename);
                }
            }
        }

        return $return;
    }
}


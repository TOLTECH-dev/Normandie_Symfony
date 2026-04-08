<?php

namespace App\Service;

use App\Entity\FicheTechnique;
use App\Entity\FicheTechniqueField;
use App\Entity\Remboursement_travaux_instruction;
use App\Entity\Remboursement_travaux_instruction_conformite;
use App\Entity\Demande_travaux_devis;
use App\Entity\Demande_travaux_devis_upload;

class DocumentService
{
    public string $rootUploadDir;
    private string $rmhFilePrefix;


    public function __construct(string $rootUploadDir, string $rmhFilePrefix)
    {
        $this->rootUploadDir = $rootUploadDir;
        $this->rmhFilePrefix = $rmhFilePrefix;
    }

    public function deleteAndUpdateInfiltrometrieDocument(
        int $demandeId,
        FicheTechniqueField &$ficheTechniqueFinChantier,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        if (!empty($ficheTechniqueFinChantier->getInfiltrometrieDocumentUrl())) {
            // SUPPRESSION 'Fichier PDF infiltrométrie'
            $infiltrometrieFile = $this->rootUploadDir . $ficheTechniqueFinChantier->infiltrometrieDocument_getWebPath();
            if (file_exists($infiltrometrieFile)) {
                if (!unlink($infiltrometrieFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId ." : Erreur suppression du fichier " . basename($infiltrometrieFile) . "\n";
                    $success = false;
                } else {
                    $ficheTechniqueFinChantier->setInfiltrometrieDocumentUrl(null);
                    $ficheTechniqueFinChantier->setInfiltrometrieDocumentAlt(null);
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $infiltrometrieFile;
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateVentilationDocument(
        int $demandeId,
        FicheTechniqueField &$ficheTechniqueFinChantier,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        if (!empty($ficheTechniqueFinChantier->getVentilationDocumentUrl())) {
            // SUPPRESSION 'Fichier PDF ventilation'
            $ventilationFile = $this->rootUploadDir . $ficheTechniqueFinChantier->ventilationDocument_getWebPath();
            if (file_exists($ventilationFile)) {
                if (!unlink($ventilationFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId ." : Erreur suppression du fichier " . basename($ventilationFile) . "\n";
                    $success = false;
                } else {
                    $ficheTechniqueFinChantier->setVentilationDocumentUrl(null);
                    $ficheTechniqueFinChantier->setVentilationDocumentAlt(null);
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $ventilationFile;
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateAuditApresTravauxDocument(
        int $demandeId,
        FicheTechniqueField &$ficheTechniqueFinChantier,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        if (!empty($ficheTechniqueFinChantier->getAuditApresTravauxDocumentUrl())) {
            // SUPPRESSION 'Fichier PDF Audit mis à jour après travaux'
            $auditApresTravauxFile = $this->rootUploadDir . $ficheTechniqueFinChantier->auditApresTravauxDocument_getWebPath();
            if (file_exists($auditApresTravauxFile)) {
                if (!unlink($auditApresTravauxFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId ." : Erreur suppression du fichier " . basename($auditApresTravauxFile) . "\n";
                    $success = false;
                } else {
                    $ficheTechniqueFinChantier->setAuditApresTravauxDocumentUrl(null);
                    $ficheTechniqueFinChantier->setAuditApresTravauxDocumentAlt(null);
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $auditApresTravauxFile;
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateFicheTechniqueFinDeChantierDocument(
        int $demandeId,
        FicheTechniqueField &$ficheTechniqueFinChantier,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        if (!empty($ficheTechniqueFinChantier) && !empty($ficheTechniqueFinChantier->getFicheTechniqueDocumentUrl())) {
            $ficheTechniqueDocumentXmlFile = $this->rootUploadDir . $ficheTechniqueFinChantier->ficheTechniqueDocument_getWebPath();
            if (file_exists($ficheTechniqueDocumentXmlFile)) {
                if (!unlink($ficheTechniqueDocumentXmlFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId ." : Erreur suppression du fichier " . basename($ficheTechniqueDocumentXmlFile) . "\n";
                    $success = false;
                } else {
                    $ficheTechniqueFinChantier
                        ->setFicheTechniqueDocumentAlt(null)
                        ->setFicheTechniqueDocumentUrl(null);
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $ficheTechniqueDocumentXmlFile;
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateFicheTechniqueDemandeDocument(
        int $demandeId,
        FicheTechnique &$ficheTechnique,
        string &$errorMessage,
        bool &$success,
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

        foreach ($fichesTechniques as $currentFicheTechnique) {
            if (!empty($currentFicheTechnique) && !empty($currentFicheTechnique->getFicheTechniqueDocumentUrl())) {
                $ficheTechniqueDocumentXmlFile = $this->rootUploadDir . $currentFicheTechnique->ficheTechniqueDocument_getWebPath();
                if (file_exists($ficheTechniqueDocumentXmlFile)) {
                    if (!unlink($ficheTechniqueDocumentXmlFile)) {
                        $errorMessage .= "\n" . "Demande n°" . $demandeId ." : Erreur suppression du fichier " . basename($ficheTechniqueDocumentXmlFile) . "\n";
                        $success = false;
                    } else {
                        $currentFicheTechnique
                            ->setFicheTechniqueDocumentAlt(null)
                            ->setFicheTechniqueDocumentUrl(null);
                        $success = true;
                    }

                    if (!empty($isReturnArrayOnly)) {
                        $return['isSuccess'][] = $success;
                        $return['filename'][] = $ficheTechniqueDocumentXmlFile;
                    }
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateFicheTechniqueRemboursementDocument(
        int $demandeId,
        FicheTechnique &$ficheTechnique,
        string &$errorMessage,
        bool &$success,
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

        foreach ($fichesTechniques as $currentFicheTechnique) {
            if (!empty($currentFicheTechnique) && !empty($currentFicheTechnique->getFicheTechniqueDocumentUrl())) {
                $ficheTechniqueDocumentXmlFile = $this->rootUploadDir . $currentFicheTechnique->ficheTechniqueDocument_getWebPath();
                if (file_exists($ficheTechniqueDocumentXmlFile)) {
                    if (!unlink($ficheTechniqueDocumentXmlFile)) {
                        $errorMessage .= "\n" . "Demande n°" . $demandeId ." : Erreur suppression du fichier " . basename($ficheTechniqueDocumentXmlFile) . "\n";
                        $success = false;
                    } else {
                        $currentFicheTechnique
                            ->setFicheTechniqueDocumentAlt(null)
                            ->setFicheTechniqueDocumentUrl(null);
                        $success = true;
                    }

                    if (!empty($isReturnArrayOnly)) {
                        $return['isSuccess'][] = $success;
                        $return['filename'][] = $ficheTechniqueDocumentXmlFile;
                    }
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateRIB(
        int $demandeId,
        &$instruction,
        bool $isNotAuditeurRIB = true,
        ?string &$errorMessage = null,
        bool &$success = false,
        bool $isReturnArrayOnly = false
    ): array
    {

        $return = [];

        if (!empty($instruction)) {
            $isAuditeurRIBChekOK = true;

            if (true === $isNotAuditeurRIB) {
                $destinataire = $instruction->getDestinataire();
                $isAuditeurRIBChekOK = false;

                if (!empty($destinataire)) {
                    $destinataireArray = explode(' | ', $destinataire);

                    // REMOVE RIB FILE IF RECIPIENT IS BENEFCIIAIRE OR RENOVATEUR
                    if ('1' == $destinataireArray[0] || '2' == $destinataireArray[0]) {
                        $isAuditeurRIBChekOK = true;
                    }
                }
            }

            if (true === $isAuditeurRIBChekOK) {
                if (!empty($instruction->getRibUrl())) {
                    $ribFile = $this->rootUploadDir . $instruction->rib_getWebPath();
                    if (file_exists($ribFile)) {
                        if (!unlink($ribFile)) {
                            $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($ribFile) . "\n";
                            $success = false;
                        } else {
                            $instruction->setRibAlt(null);
                            $instruction->setRibUrl(null);
                            $success = true;
                        }
                        if (!empty($isReturnArrayOnly)) {
                            $return['isSuccess'][] = $success;
                            $return['filename'][] = $ribFile;
                        }
                    }
                }

                $instruction->setIban(null);
                $instruction->setBic(null);
                $instruction->setDomiciliationBancaire(null);
            }
        }

        return $return;
    }

    public function deleteAndUpdateRectoVersoCheque(
        int $demandeId,
        &$instruction,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        if (!empty($instruction->getRectoChequeUrl())) {
            $rectoChequeFile = $this->rootUploadDir . $instruction->rectoCheque_getWebPath();
            if (file_exists($rectoChequeFile)) {
                if (!unlink($rectoChequeFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($rectoChequeFile) . "\n";
                    $success = false;
                } else {
                    $instruction
                        ->setRectoChequeAlt(null)
                        ->setRectoChequeUrl(null);
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $rectoChequeFile;
                }
            }
        }

        if (!empty($instruction->getVersoChequeUrl())) {
            $versoChequeFile = $this->rootUploadDir . $instruction->versoCheque_getWebPath();
            if (file_exists($versoChequeFile)) {
                if (!unlink($versoChequeFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($versoChequeFile) . "\n";
                    $success = false;
                } else {
                    $instruction
                        ->setVersoChequeAlt(null)
                        ->setVersoChequeUrl(null);
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $versoChequeFile;
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateFicheTravaux(
        int $demandeId,
        Remboursement_travaux_instruction &$instruction,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        if (!empty($instruction->getficheTravauxUrl())) {
            $ficheTravauxFile = $this->rootUploadDir . $instruction->ficheTravaux_getWebPath();
            if (file_exists($ficheTravauxFile)) {
                if (!unlink($ficheTravauxFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($ficheTravauxFile) . "\n";
                    $success = false;
                } else {
                    $instruction
                        ->setFicheTravauxAlt(null)
                        ->setFicheTravauxUrl(null);
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $ficheTravauxFile;
                }
            }
        }

        return $return;
    }

    /**
     * @param $demandeId
     * @param $instruction
     * @param $errorMessage
     * @param $success
     * @param $isReturnArrayOnly
     *
     * @return array
     */
    public function deleteAndUpdateFacture(
        int $demandeId,
        &$instruction,
        string &$errorMessage,
        bool &$success,
        $isReturnArrayOnly = false
    ) {
        $return = [];

        if (!empty($instruction->getFactureUrl())) {
            $factureFile = $this->rootUploadDir . $instruction->facture_getWebPath();
            if (file_exists($factureFile)) {
                if (!unlink($factureFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($factureFile) . "\n";
                    $success = false;
                } else {
                    $instruction
                        ->setFactureAlt(null)
                        ->setFactureUrl(null);
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $factureFile;
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateTravauxFactures(
        int $demandeId,
        Remboursement_travaux_instruction &$instruction,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        /**
         * @var Remboursement_travaux_instruction_conformite $remboursementTravauxInstructionConformiteCurrent
         */
        foreach ($instruction->getRemboursementTravauxInstructionConformite() as $remboursementTravauxInstructionConformiteCurrent) {
            if (!empty($remboursementTravauxInstructionConformiteCurrent->getDocumentUrl())) {
                $factureFile = $this->rootUploadDir . $remboursementTravauxInstructionConformiteCurrent->document_getWebPath();
                if (file_exists($factureFile)) {
                    if (!unlink($factureFile)) {
                        $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($factureFile) . "\n";
                        $success = false;
                    } else {
                        $remboursementTravauxInstructionConformiteCurrent
                            ->setDocumentAlt(null)
                            ->setDocumentUrl(null);
                        $success = true;
                    }

                    if (!empty($isReturnArrayOnly)) {
                        $return['isSuccess'][] = $success;
                        $return['filename'][] = $factureFile;
                    }
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateDepotAudit(
        int $demandeId,
        &$depot,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        if (!empty($depot->getAuditUrl())) {
            $auditFile = $this->rootUploadDir . $depot->audit_getWebPath();
            if (file_exists($auditFile)) {
                if (!unlink($auditFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($auditFile) . "\n";
                    $success = false;
                } else {
                    $depot
                        ->setAuditAlt('')
                        ->setAuditUrl('');
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $auditFile;
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateTravauxDevisAudit(
        int $demandeId,
        &$demandeTravauxDevis,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        if (!empty($demandeTravauxDevis->getAuditUrl())) {
            $auditFile = $this->rootUploadDir . $demandeTravauxDevis->audit_getWebPath();
            if (file_exists($auditFile)) {
                if (!unlink($auditFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($auditFile) . "\n";
                    $success = false;
                } else {
                    $demandeTravauxDevis
                        ->setAuditAlt('')
                        ->setAuditUrl('');
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $auditFile;
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateTravauxDevisActeEngagement(
        int $demandeId,
        Demande_travaux_devis &$demandeTravauxDevis,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        if (!empty($demandeTravauxDevis->getActeEngagementUrl())) {
            $acteEngagementFile = $this->rootUploadDir . $demandeTravauxDevis->acteEngagement_getWebPath();
            if (file_exists($acteEngagementFile)) {
                if (!unlink($acteEngagementFile)) {
                    $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($acteEngagementFile) . "\n";
                    $success = false;
                } else {
                    $demandeTravauxDevis
                        ->setActeEngagementAlt('')
                        ->setActeEngagementUrl('');
                    $success = true;
                }

                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $acteEngagementFile;
                }
            }
        }

        return $return;
    }

    public function deleteAndUpdateTravauxDevisUpload(
        int $demandeId,
        Demande_travaux_devis &$demandeTravauxDevis,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        $demandesTravauxDevisUpload = $demandeTravauxDevis->getDemandeTravauxDevisUpload();
        /**
         * @var Demande_travaux_devis_upload $demandeTravauxDevisUpload
         */
        foreach ($demandesTravauxDevisUpload as $demandeTravauxDevisUpload) {
            if (!empty($demandeTravauxDevisUpload->getDevisDocumentUrl())) {
                $devisUploadFile = $this->rootUploadDir . $demandeTravauxDevisUpload->devisDocument_getWebPath();
                if (file_exists($devisUploadFile)) {
                    if (!unlink($devisUploadFile)) {
                        $errorMessage .= "\n" . "Demande n°" . $demandeId . " : Erreur suppression du fichier " . basename($devisUploadFile) . "\n";
                        $success = false;
                    } else {
                        $demandeTravauxDevisUpload
                            ->setDevisDocumentAlt(null)
                            ->setDevisDocumentUrl(null);
                        $success = true;
                    }

                    if (!empty($isReturnArrayOnly)) {
                        $return['isSuccess'][] = $success;
                        $return['filename'][] = $devisUploadFile;
                    }
                }
            }
        }

        return $return;
    }

    public function deleteDemandeDocument(
        int $demandeId,
        array $filesToDelete,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        foreach ($filesToDelete as $filePath) {
            if (!empty($filePath)) {
                if (file_exists($filePath)) {
                    if(!unlink($filePath)) {
                        $errorMessage .= "\n" . "Demande n°" . $demandeId ." : Erreur suppression du fichier " . basename($filePath) . "\n";
                        $success = false;
                    } else {
                        $success = true;
                    }

                    if (!empty($isReturnArrayOnly)) {
                        $return['isSuccess'][] = $success;
                        $return['filename'][] = $filePath;
                    }
                }
            }
        }

        return $return;
    }

    public function deleteRMHDocument(
        int $dateRMHId,
        string &$errorMessage,
        bool &$success,
        bool $isReturnArrayOnly = false
    ): array
    {
        $return = [];

        $rootUploadDir = $this->rootUploadDir . 'uploads/remboursement/RMH/' . $dateRMHId;

        $zipArchive = $rootUploadDir . '/' . $dateRMHId . "_archive" . '.zip';
        if (file_exists($zipArchive)) {
            if(!unlink($zipArchive)) {
                $errorMessage .= "\n" . "RMH n°" . $dateRMHId ." : Erreur suppression du fichier " . basename($zipArchive) . "\n";
                $success = false;
            } else {
                $success = true;
            }
            if (!empty($isReturnArrayOnly)) {
                $return['isSuccess'][] = $success;
                $return['filename'][] = $zipArchive;
            }
        }

        $filesRMH = glob( $rootUploadDir . '/' . $this->rmhFilePrefix . $dateRMHId . '_fichier_rmh_*');
        if (!empty($filesRMH) && $filesRMH[0]) {
            $fileRMH = $filesRMH[0];

            if (file_exists($fileRMH)) {
                if(!unlink($fileRMH)) {
                    $errorMessage .= "\n" . "RMH n°" . $dateRMHId ." : Erreur suppression du fichier " . basename($fileRMH) . "\n";
                    $success = false;
                } else {
                    $success = true;
                }
                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $fileRMH;
                }
            }
        }

        $filesSynthese = glob( $rootUploadDir . '/' . $dateRMHId . '_fichier_synthese_*');
        if (!empty($filesSynthese) && $filesSynthese[0]) {
            $fileSynthese = $filesSynthese[0];

            if (file_exists($fileSynthese)) {
                if(!unlink($fileSynthese)) {
                    $errorMessage .= "\n" . "RMH n°" . $dateRMHId ." : Erreur suppression du fichier " . basename($fileSynthese) . "\n";
                    $success = false;
                } else {
                    $success = true;
                }
                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $fileSynthese;
                }
            }
        }

        $filesZipXemelios = glob( $rootUploadDir . '/' . $dateRMHId . '_fichier_xemelios_*');
        if (!empty($filesZipXemelios) && $filesZipXemelios[0]) {
            $fileZipXemelios = $filesZipXemelios[0];

            if (file_exists($fileZipXemelios)) {
                if(!unlink($fileZipXemelios)) {
                    $errorMessage .= "\n" . "RMH n°" . $dateRMHId ." : Erreur suppression du fichier " . basename($fileZipXemelios) . "\n";
                    $success = false;
                } else {
                    $success = true;
                }
                if (!empty($isReturnArrayOnly)) {
                    $return['isSuccess'][] = $success;
                    $return['filename'][] = $fileZipXemelios;
                }
            }
        }

        return $return;
    }

    public function formatFilenameForLogs(string $filename): array|string
    {
        return str_replace($this->rootUploadDir, '', $filename);
    }
}

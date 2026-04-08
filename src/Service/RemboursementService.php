<?php

namespace App\Service;

use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use App\Entity\Remboursement_;
use App\Entity\Remboursement_statut;
use App\Entity\User;
use App\Repository\Remboursement_Repository;
use App\Utils\DefaultServiceUtils;
use App\Utils\DefaultUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


class RemboursementService
{
    /**
     * @var EntityManagerInterface
     */
    private $EM;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var Remboursement_Repository
     */
    private $repo_remboursement;

    const TAG_DOCUMENT_MANQUANT = '##MOTIF_DOC_MANQUANT##';
    const TAG_NON_CONFORME = '##MOTIF_NON_CONFORME##';
    const TAG_REFUS = '##MOTIF_REFUS##';

    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag
    ) {
        $this->EM = $entityManager;
        $this->parameterBag = $parameterBag;
        $this->repo_remboursement = $this->EM->getRepository(Remboursement_::class);
    }



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $documentAudit
     * @param $documentAuditAlt
     * @param $instruction
     * @param $auditeur
     * @return int|string
     */
    public function searchStatutForRemboursementAuditEnergie(
        $documentAudit,
        $documentAuditAlt,
        $instruction,
        $auditeur
    ) {
        $statut = '';
        $isRibConforme = null;
        $isFactureConforme = null;
        $isChequeConforme = null;
        $isInstructionIncompleteDoc = false;
        $isInstruction = $instruction != null ? true : false;

        if ($isInstruction) {
            $isRibConforme = DefaultUtils::getKey($instruction->getIsRibConforme());
            $isFactureConforme = DefaultUtils::getKey($instruction->getIsFactureConforme());
            $isChequeConforme = DefaultUtils::getKey($instruction->getIsChequeConforme());
            $destinataire = DefaultUtils::getKey($instruction->getDestinataire());
            $ribAlt = ('0' == $destinataire) ? $auditeur->getRibAlt() : $instruction->getRibAlt();

            if (
                !($instruction->getRib() || $ribAlt)
                || !($instruction->getFacture() || $instruction->getFactureAlt())
                || !($instruction->getRectoCheque() || $instruction->getRectoChequeAlt())
                || !($instruction->getVersoCheque() || $instruction->getVersoChequeAlt())
            ) {
                $isInstructionIncompleteDoc = true;
            }
        }

        // si document audit inexistant
        $isDocumentAudit = (!($documentAudit || $documentAuditAlt)) ? false : true;
        $isInstructionNonConforme = ('0' != $isRibConforme || '0' != $isFactureConforme || '0' != $isChequeConforme) ? true : false;
        $isInstructionComplete = (!$isInstructionIncompleteDoc && !$isInstructionNonConforme) ? true : false;

        /* /////////////////////////////////////////////////////////////////
                                CALCUL STATUT
        ///////////////////////////////////////////////////////////////// */
        if (is_null($isRibConforme) || is_null($isFactureConforme) || is_null($isChequeConforme)) {
            // Remboursement en attente instruction Prestataire
            $statut = Remboursement_statut::STATUS_12;
        } else {
            if (!$isDocumentAudit) {
                if ($isInstructionComplete) {
                    // Instruction complete => En attente Téléchargement Audit
                    $statut = Remboursement_statut::STATUS_1;
                } else {
                    // En attente Téléchargement Audit: Remboursement Incomplet ou Non conforme
                    $statut = ($isInstructionIncompleteDoc) ? Remboursement_statut::STATUS_2 : Remboursement_statut::STATUS_3;
                }
            }

            if ($isDocumentAudit) {
                if (!$isInstruction) {
                    // Instruction inexistante: Remboursement en attente instruction Prestataire
                    $statut = Remboursement_statut::STATUS_12;
                } else {
                    // Instruction existante
                    if ($isInstructionComplete) {
                        // Instruction complete: Remboursement en attente validation Région
                        $statut = Remboursement_statut::STATUS_14;
                    } else {
                        // Remboursement incomplet ou Remboursement non conforme
                        $statut = ($isInstructionIncompleteDoc) ? Remboursement_statut::STATUS_16 : Remboursement_statut::STATUS_18;
                    }
                }
            }
        }

        return $statut;
    }

    /**
     * @param $documentAudit
     * @param $documentAuditAlt
     * @param $instruction
     * @param $auditeur
     * @return int|string
     */
    public function searchStatutForRemboursementAuditNumerique(
        $documentAudit,
        $documentAuditAlt,
        $instruction,
        $auditeur
    ) {
        $statut = '';
        $isRibConforme = null;
        $isFactureConforme = null;
        $isChequeConforme = null;
        $destinataire = null;
        $ribAlt = null;

        $isInstruction = $instruction != null ? true : false;
        if ($isInstruction) {
            $isRibConforme = DefaultUtils::getKey($instruction->getIsRibConforme());
            $isFactureConforme = DefaultUtils::getKey($instruction->getIsFactureConforme());
            $isChequeConforme = DefaultUtils::getKey($instruction->getIsChequeConforme());
            $destinataire = DefaultUtils::getKey($instruction->getDestinataire());

            if ('0' == $destinataire) $ribAlt = $auditeur->getRibAlt();
            else $ribAlt = $instruction->getRibAlt();
        }

        // si document audit inexistant
        $documentAuditOK = true;
        if (!($documentAudit || $documentAuditAlt)) $documentAuditOK = false;

        /* /////////////////////////////////////////////////////////////////
                                CALCUL STATUT
        ///////////////////////////////////////////////////////////////// */
        if ($isInstruction) { // Instruction existante

            if (is_null($isRibConforme) || is_null($isFactureConforme) || is_null($isChequeConforme)) {
                // Remboursement en attente instruction Prestataire
                $statut = Remboursement_statut::STATUS_12;

            } else {
                if (
                    !($instruction->getRib() || $ribAlt)
                    || !($instruction->getFacture() || $instruction->getFactureAlt())
                    || !($instruction->getRectoCheque() || $instruction->getRectoChequeAlt())
                    || !($instruction->getVersoCheque() || $instruction->getVersoChequeAlt())
                ) {
                    // Remboursement Incomplet ou Remboursement Incomplet et En attente téléchargement Audit numérique
                    $statut = ($documentAuditOK) ? Remboursement_statut::STATUS_16 : Remboursement_statut::STATUS_24;
                } else { // Instruction complete
                    if (
                        '0' != $isRibConforme
                        || '0' != $isFactureConforme
                        || '0' != $isChequeConforme
                    ) {
                        // Remboursement Non conforme ou Remboursement Non conforme et En attente Téléchargement Audit numérique
                        $statut = ($documentAuditOK) ? Remboursement_statut::STATUS_18 : Remboursement_statut::STATUS_25;
                    } else {
                        // Instruction complete et conforme: En attente Validation Région ou En attente Téléchargement Audit numérique
                        $statut = ($documentAuditOK) ? Remboursement_statut::STATUS_14 : Remboursement_statut::STATUS_23;
                    }
                }
            }

        } else {
            if ($documentAuditOK) { // Instruction inexistante
                $statut = Remboursement_statut::STATUS_12;
            }
        }

        return $statut;
    }

    /**
     * @param $demandeTravauxNiveaux
     * @param $numeroOperation
     * @param $informationValidationFinChantier
     * @param $instruction
     * @param $facture
     * @param $ficheTravaux
     * @param $ficheTravauxAlt
     * @return int
     */
    public function searchStatutForRemboursementTravaux(
        $demandeTravauxNiveaux,
        $numeroOperation,
        $informationValidationFinChantier,
        $instruction,
        $facture,
        $ficheTravaux,
        $ficheTravauxAlt
    ) {
        $isRibConforme = null;
        $isFactureConforme = null;
        $isFicheTravauxConforme = null;
        $isChequeConforme = null;
        $ribAlt = null;
        $isInstructionIncompleteDoc = false;
        $isFicheTechniqueOk = ('1' == $informationValidationFinChantier) ? true : false;
        $isFicheTechniqueToCheck = false;

        if (
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE == $demandeTravauxNiveaux
            || (
                in_array($demandeTravauxNiveaux, [
                    Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE,
                    Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE,
                    Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE,
                    Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE
                ])
                &&  $this->parameterBag->get('production_travauxNiveau_BBC1') != $numeroOperation)
        ) {
            $isFicheTechniqueToCheck = true;
        }

        $isInstruction = ($instruction != null) ? true : false;
        if ($isInstruction) {
            $isRibConforme = DefaultUtils::getKey($instruction->getIsRibConforme());
            $isChequeConforme = DefaultUtils::getKey($instruction->getIsChequeConforme());

            if (
                !($instruction->getRib() || $instruction->getRibAlt())
                || !($instruction->getRectoCheque() || $instruction->getRectoChequeAlt())
                || !($instruction->getVersoCheque() || $instruction->getVersoChequeAlt())
            ) {
                $isInstructionIncompleteDoc = true;
            }
        }

        $isInstructionNonConforme = ('0' != $isRibConforme || '0' != $isChequeConforme) ? true : false;

        if ($this->parameterBag->get('production_travauxNiveau_BBC1') == $numeroOperation) {
            // CAS TRAVAUX NIVEAUX BBC1 : FICHE DE LAISON A CHECKER

            $isInstructionIncompleteDoc = !($ficheTravaux || $ficheTravauxAlt) ? true : $isInstructionIncompleteDoc;

            if ($instruction) {
                $isFicheTravauxConforme = DefaultUtils::getKey($instruction->getIsFicheTravauxConforme());
                $isInstructionNonConforme = ('0' != $isFicheTravauxConforme) ? true : $isInstructionNonConforme;
            }

        } else {
            // SINON FACTURES A CHECKER
            $isInstructionIncompleteDoc = (!$facture) ? true : $isInstructionIncompleteDoc;

            if ($instruction) {
                $isFactureConforme = DefaultUtils::getKey($instruction->getIsFactureConforme());
                $isInstructionNonConforme = ('0' != $isFactureConforme) ? true : $isInstructionNonConforme;
            }
        }

        // Instruction complète : Ok au niveau Docs joints et OK au niveau de la conformité des Docs
        $isInstructionComplete = (!$isInstructionIncompleteDoc && !$isInstructionNonConforme) ? true : false;

        /* /////////////////////////////////////////////////////////////////
                            CALCUL STATUT
        ///////////////////////////////////////////////////////////////// */
        // We check the technical fiche in theses cases : Chèque Travaux II rénov ou III chèque n°2
        if (!$isFicheTechniqueToCheck) {

            // CAS Chèque Travaux I, II non rénov ou III chèque n°1 (BBC1)

            if ($isInstructionComplete) {
                // Remboursement en attente validation Région
                $statut = Remboursement_statut::STATUS_14;
            } else if ($isInstructionIncompleteDoc) {
                // Remboursement incomplet
                $statut = Remboursement_statut::STATUS_16;
            } else {
                // Remboursement non conforme
                $statut = Remboursement_statut::STATUS_18;
            }

        } else {

            // CAS Chèque Travaux II rénov ou III chèque n°2 (BBC2)

            if (!$isInstruction) {
                // Remboursement en attente instruction Prestataire OU
                // Remboursement en attente instruction prestataire / Remboursement en attente des informations de fin de chantier
                $statut = ($isFicheTechniqueOk) ? Remboursement_statut::STATUS_12 : Remboursement_statut::STATUS_28;
            } else {

                if ($isInstructionComplete) {
                    // Remboursement en attente validation Région OU Remboursement en attente des informations de fin de chantier
                    $statut = ($isFicheTechniqueOk) ? Remboursement_statut::STATUS_14 : Remboursement_statut::STATUS_27;
                } else if ($isInstructionIncompleteDoc) {
                    // // Remboursement incomplet OU Remboursement incomplet / Remboursement en attente des informations de fin de chantier
                    $statut = ($isFicheTechniqueOk) ? Remboursement_statut::STATUS_16 : Remboursement_statut::STATUS_29;
                } else {
                    // Remboursement non conforme OU Remboursement non conforme / Remboursement en attente des informations de fin de chantier
                    $statut = ($isFicheTechniqueOk) ? Remboursement_statut::STATUS_18 : Remboursement_statut::STATUS_30;
                }
            }
        }

        return $statut;
    }

    /**
     * @return int
     */
    public function searchStatutRefus()
    {
        $statut = Remboursement_statut::STATUS_20;

        return $statut;
    }

    /**
     * @param null $ribAlt
     * @param null $factureAlt
     * @param null $rectoChequeAlt
     * @param null $versoChequeAlt
     * @param null $ficheTravauxAlt
     * @param null $isBBC1
     * @return array
     */
    public function findDocumentManquant(
        $ribAlt = null,
        $factureAlt = null,
        $rectoChequeAlt = null,
        $versoChequeAlt = null,
        $ficheTravauxAlt = null,
        $isBBC1 = null
    ) {
        $listDocManquant = array();

        if (!$ribAlt) {
            $listDocManquant[] = 'rib';
        }

        if (!$rectoChequeAlt || !$versoChequeAlt) {
            $listDocManquant[] = 'cheque';
        }

        if (true == $isBBC1) {
            if (!$ficheTravauxAlt) {
                $listDocManquant[] = 'fiche_travaux';
            }
        } else {
            if (!$factureAlt) {
                $listDocManquant[] = 'facture';
            }
        }

        return $listDocManquant;
    }

    /**
     * @param $travauxInstructionConformite
     * @return bool
     */
    public function isTravauxInstructionFactureComplet($travauxInstructionConformite)
    {
        if (!empty($travauxInstructionConformite)) {
            foreach ($travauxInstructionConformite as $item) {
                if ($item && ($item->getDocument() || $item->getDocumentAlt())) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $remboursementId
     * @param $production_travauxNiveau_BBC1
     * @param $remboursementStatutDescription
     * @return array|mixed|string|string[]|null
     * @throws Exception
     */
    public function findStatutDescriptionByRemboursement(
        $remboursementId,
        $production_travauxNiveau_BBC1,
        $remboursementStatutDescription = null
    ) {
        $customDataRemboursement = $this->repo_remboursement->findCustomForStatutDescriptionByRemboursement(
            $remboursementId,
            $production_travauxNiveau_BBC1
        );

        $explication = !empty($remboursementStatutDescription) ? $remboursementStatutDescription : $customDataRemboursement['remboursementStatutDescription'];
        $isDocumentManquant = strpos($explication, self::TAG_DOCUMENT_MANQUANT) !== FALSE;
        $isNonConforme = strpos($explication, self::TAG_NON_CONFORME) !== FALSE;
        $isRefus = strpos($explication, self::TAG_REFUS) !== FALSE;

        $dataMotifStatutRemboursement = [];

        if ($isDocumentManquant) {
            $documentManquant = $this->findDocumentManquant(
                $customDataRemboursement['ribAlt'],
                $customDataRemboursement['factureAlt'],
                $customDataRemboursement['rectoChequeAlt'],
                $customDataRemboursement['versoChequeAlt'],
                $customDataRemboursement['ficheTravauxAlt'],
                ($customDataRemboursement['isBBC1'] == '1' ? true : false)
            );
            $dataMotifStatutRemboursement['documentManquantList'] = $documentManquant;
            $dataMotifStatutRemboursement['documentManquantTag'] = self::TAG_DOCUMENT_MANQUANT;
        }

        if ($isNonConforme) {
            $documentNonConforme = $this->findDocumentNonConforme(
                $remboursementId,
                $customDataRemboursement['demandeType'],
                ($customDataRemboursement['isBBC1'] == '1' ? true : false)
            );
            $dataMotifStatutRemboursement['nonConformeList'] = $documentNonConforme;
            $dataMotifStatutRemboursement['documentNonConformeTag'] = self::TAG_NON_CONFORME;
        }

        if ($isRefus) {
            $remboursement = $this->repo_remboursement->find($remboursementId);

            $refusText = null;
            if ($remboursement->getMotifRefus()) {
                $refusText = $remboursement->getMotifRefus();
            }

            $dataMotifStatutRemboursement['refusText'] = $refusText;
            $dataMotifStatutRemboursement['documentRefusTag'] = self::TAG_REFUS;
        }

        $statutDescription = DefaultServiceUtils::getStatutDescriptionByRemboursementAndMotif(
            $customDataRemboursement['remboursementId'],
            $explication,
            $dataMotifStatutRemboursement
        );

        return $statutDescription;
    }

    /**
     * @param $option
     * @return StreamedResponse
     * @throws Exception
     */
    public function export($option)
    {
        $data = $this->repo_remboursement->findDataExport($option);
        $response = new StreamedResponse();

        $response->setCallback(function() use ($data) {

            $fields = array(
                'numeroDossier'             => iconv("UTF-8", "Windows-1252//TRANSLIT",'N° Dossier'),
                'typeCheque'                => iconv("UTF-8", "Windows-1252//TRANSLIT",'Type de chèque'),
                'nom'                       => iconv("UTF-8", "Windows-1252//TRANSLIT",'Nom'),
                'prenom'                    => iconv("UTF-8", "Windows-1252//TRANSLIT",'Prénom'),
                'logementCodePostal'        => iconv("UTF-8", "Windows-1252//TRANSLIT",'Code postal'),
                'logementVille'             => iconv("UTF-8", "Windows-1252//TRANSLIT",'Ville'),
                'dateDemandeRemboursement'  => iconv("UTF-8", "Windows-1252//TRANSLIT",'Date de la demande de remboursement'),
                'statut'                    => iconv("UTF-8", "Windows-1252//TRANSLIT",'Statut'),
                'conseillerNom'             => iconv("UTF-8", "Windows-1252//TRANSLIT",'Nom du conseiller'),
                'raisonSociale'             => iconv("UTF-8", "Windows-1252//TRANSLIT",'Raison sociale'),
                'RMHDate'                   => iconv("UTF-8", "Windows-1252//TRANSLIT",'Date de RMH')
            );

            $handle = fopen('php://output', 'r+');
            fputcsv($handle, $fields, ';');

            foreach ($data as $row) {

                $RMHDate = (!empty($row['RMHDate'])) ? date_format(date_create($row['RMHDate']), 'd/m/Y') : '';
                $raisonSociale = !empty($row['raisonSociale']) ? iconv("UTF-8", "Windows-1252//TRANSLIT", $row['raisonSociale']) : '';

                $fieldsData = array(
                    'numeroDossier'             => $row['demandeId'],
                    'typeCheque'                => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['typeCheque']),
                    'nom'                       => strtoupper(iconv("UTF-8", "Windows-1252//TRANSLIT", $row['beneficiaireNom'])),
                    'prenom'                    => ucfirst(iconv("UTF-8", "Windows-1252//TRANSLIT", $row['beneficiairePrenom'])),
                    'logementCodePostal'        => $row['logementCodePostal'],
                    'logementVille'             => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['logementVille']),
                    'dateDemandeRemboursement'  => date_format(date_create($row['dateDemandeRemboursement']), 'd/m/Y'),
                    'statut'                    => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['remboursementStatutSlug']),
                    'conseillerNom'             => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['conseillerNom']),
                    'raisonSociale'             => $raisonSociale,
                    'RMHDate'                   => $RMHDate
                );

                fputcsv($handle, $fieldsData, ';');
            }
            fclose($handle);
        });

        $filename = "export_remboursement_" . date("YmdHis") . ".csv";
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename);

        return $response;
    }

    /**
     * @param $demandeTypeCustom
     * @param null $titreId
     * @param null $remboursementId
     * @throws Exception
     */
    public function checkExamineReexamine(
        $demandeTypeCustom,
        $titreId = null,
        $remboursementId = null
    ) {
        if (
            !in_array($demandeTypeCustom, [
                Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
                Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
                Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE,
                Demande_::DEMANDE_TRAVAUX_TYPE
            ])
            || (empty($titreId) && empty($remboursementId))
        ) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }

        // SI PARAMETRES OK => ON FAIT LA REQUETE DE CHECK
        $row = $this->repo_remboursement->findOneForExamineReexamine($demandeTypeCustom, $titreId, $remboursementId);
        if (empty($row)) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
    }

    /**
     * @param $demandeTypeCustom
     * @param null $demandeId
     * @param null $remboursementId
     * @throws Exception
     */
    public function checkDepot(
        $demandeTypeCustom,
        $demandeId = null,
        $remboursementId = null
    ) {

        // Seulement pour AUDIT ENERGETIQUE ET NUMERIQUE
        if (
            !in_array($demandeTypeCustom, [
                Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
                Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
                Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE
            ])
            || (empty($demandeId) && empty($remboursementId))
        ) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }

        // SI PARAMETRES OK => ON FAIT LA REQUETE DE CHECK
        $row = $this->repo_remboursement->findOneForDepot($demandeTypeCustom, $demandeId, $remboursementId);
        if (empty($row)) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
    }

    /**
     * @param $demandeTypeCustom
     * @param $productionTravauxNiveauBBC2
     * @param null $titreId
     * @param null $remboursementId
     * @throws Exception
     */
    public function checkFicheTechnique(
        $demandeTypeCustom,
        $productionTravauxNiveauBBC2,
        $titreId = null,
        $remboursementId = null
    ) {
        // SEULEMENT POUR TRAVAUX
        if (
            Demande_::DEMANDE_TRAVAUX_TYPE != $demandeTypeCustom
            || (empty($titreId) && empty($remboursementId))
        ) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }

        // SI PARAMETRES OK => ON FAIT LA REQUETE DE CHECK
        $row = $this->repo_remboursement->findOneForFicheTechnique(
            $productionTravauxNiveauBBC2,
            $titreId,
            $remboursementId
        );
        if (empty($row) || $row['isTechnicalAccess'] != '1') {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
    }

    /**
     * @param Remboursement_ $remboursement
     * @param $userRoles
     */
    public function setDateInstruction(Remboursement_ &$remboursement, $userRoles)
    {
        if (in_array(User::PARAM_ROLE_INSTRUCTEUR, $userRoles)
            || in_array(User::PARAM_ROLE_INSTRUCTEUR_UP, $userRoles)
        ) {
            if (empty($remboursement->getDateInstructionInstructeur())) {
                $remboursement->setDateInstructionInstructeur(new \Datetime());
            }
        }
    }

    /**
     * @return array
     */
    public function getDataForListAjax($productionTravauxNiveauBBC1, $productionTravauxNiveauBBC2)
    {
        $arrayColumnWhere = [];
        $where = '';

        if (!empty($_POST) ) {

            /* START of $_POST variables coming from datatable */
            $draw = $_POST["draw"]; //Counter used by DataTables to ensure that the Ajax returns from server-side processing requests are drawn in sequence
            $orderBy = null;
            $orderType = null;
            if (isset($_POST['order'][0]['column'])) {
                $orderByColumnIndex = $_POST['order'][0]['column']; //Index of the sorting column (0 index based)
                $orderBy = $_POST['columns'][$orderByColumnIndex]['data']; //Get name of the sorting column from its index
                $orderType = $_POST['order'][0]['dir']; //ASC or DESC
            }

            $start  = $_POST["start"]; //Paging first record indicator
            $length = $_POST['length']; //Number of records that the table can display in the current draw
            /* END of $_POST variables */


            /* START INIT of column search */
            $columnWhereTmp = [];
            for ($i=0; $i<count($_POST['columns']); $i++) {
                if ('' != ($_POST['columns'][$i]['search']['value'])) {
                    $columnWhereTmp[] = $_POST['columns'][$i]['search']['value'];
                }
            }
            /* END INIT of column search */


            /* START of search */
            if (!empty($columnWhereTmp)) {

                for ($i=0; $i<count($_POST['columns']); $i++) {
                    if ('' != ($_POST['columns'][$i]['search']['value'])) {
                        $columnSearch = $_POST['columns'][$i]['data'];
                        $columnValue = $_POST['columns'][$i]['search']['value'];

                        switch ($columnSearch) {
                            case 'demandeId':
                                $arrayColumnWhere[] = "d.id LIKE \"%" . $columnValue . "%\"";
                                break;
                            case 'numeroCheque':
                                $arrayColumnWhere[] = "t.numero_cheque LIKE \"%" . $columnValue . "%\"";
                                break;
                            case 'beneficiaire':
                                $arrayColumnWhere[] = "(b.nom LIKE \"%" . $columnValue . "%\"" .
                                    " OR b.prenom LIKE \"%" . $columnValue . "%\")";
                                break;
                            case 'logement':
                                $arrayColumnWhere[] = "(l.code_postal LIKE \"%" . $columnValue . "%\"" .
                                    " OR l.ville LIKE \"%" . $columnValue . "%\")";
                                break;
                            case 'statut':
                                $arrayColumnWhere[] = "(ds.slug LIKE \"%" . $columnValue . "%\"".
                                    " OR rs.slug LIKE \"%" . $columnValue . "%\")";
                                break;
                            case 'structureConseiller':
                                $arrayColumnWhere[] = "(si_dae.nom LIKE \"%" . $columnValue . "%\"" .
                                    " OR sc_dae.nom LIKE \"%" . $columnValue . "%\"" .
                                    " OR si_dan.nom LIKE \"%" . $columnValue . "%\"" .
                                    " OR sc_dan.nom LIKE \"%" . $columnValue . "%\"" .
                                    " OR si_dt.nom LIKE \"%" . $columnValue . "%\"" .
                                    " OR sc_dt.nom LIKE \"%" . $columnValue . "%\")";
                                break;
                            case 'partenaire':
                                $arrayColumnWhere[] = "(pi_dae.raison_sociale LIKE \"%" . $columnValue . "%\"" .
                                    " OR pi_dan.raison_sociale LIKE \"%" . $columnValue . "%\"" .
                                    " OR pi_dtd.raison_sociale LIKE \"%" . $columnValue . "%\")";
                                break;
                            case 'demandeType':
                                $searchValue = $_POST['columns'][$i]['search']['value'];
                                $columnDemandeType = 'd.type';
                                $columnDemandeNiveauSubstring = 'SUBSTRING(dtd.niveau, 1, 1)';
                                if (isset($searchValue)) {
                                    switch ($searchValue) {
                                        case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE .
                                                " AND dtd.niveau IS NULL)";
                                            break;
                                        case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE .
                                                " AND dtd.niveau IS NULL)";
                                            break;
                                        case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE .
                                                " AND dtd.niveau IS NULL)";
                                            break;
                                        case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE .
                                                " AND dtd.niveau IS NULL)";
                                            break;
                                        case Demande_::DEMANDE_TRAVAUX_TYPE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND dtd.niveau IS NULL)";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '0')" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '1')" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '2')" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '3'" .
                                                " AND t.numero_operation = " . $productionTravauxNiveauBBC1 .")" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '3'" .
                                                " AND t.numero_operation = " . $productionTravauxNiveauBBC2 .")" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '4'" .
                                                " AND t.numero_operation = " . $productionTravauxNiveauBBC1 .")" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '4'" .
                                                " AND t.numero_operation = " . $productionTravauxNiveauBBC2 .")" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '6')" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '7')" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '8'" .
                                                " AND t.numero_operation = " . $productionTravauxNiveauBBC1 .")" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '8'" .
                                                " AND t.numero_operation = " . $productionTravauxNiveauBBC2 .")" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '9'" .
                                                " AND t.numero_operation = " . $productionTravauxNiveauBBC1 .")" ;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE:
                                            $arrayColumnWhere[] = "(" . $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE .
                                                " AND " . $columnDemandeNiveauSubstring . " = '9'" .
                                                " AND t.numero_operation = " . $productionTravauxNiveauBBC2 .")" ;
                                            break;
                                        default:
                                            break;
                                    }
                                }
                                break;
                            case 'RMHDate':
                                $arrayColumnWhere[] = "DATE_FORMAT(dRMH.date_RMH, '%d/%m/%Y') LIKE \"%" . $columnValue . "%\"";
                                break;
                        }
                    }
                }

                if (!empty($arrayColumnWhere)) {
                    $where = ' AND ' . implode(' AND ', $arrayColumnWhere);
                }
            }

            return [
                'draw'           => $draw,
                'orderBy'        => $orderBy,
                'orderType'      => $orderType,
                'start'          => $start,
                'length'         => $length,
                'columnWhereTmp' => $columnWhereTmp,
                'where'          => $where
            ];
        }

        return [];
    }

    /**
     * @return array
     */
    public function getDataOptionRepository(User $user)
    {
        return [
            'production_travauxNiveau_BBC1'  => $this->parameterBag->get('production_travauxNiveau_BBC1'),
            'production_travauxNiveau_BBC2'  => $this->parameterBag->get('production_travauxNiveau_BBC2'),
            'app_date_us_nouvel_instructeur' => $this->parameterBag->get('app_date_us_nouvel_instructeur'),
            'roles'                          => $user->getRoles(),
            'username'                       => $user->getUsername()
        ];
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $remboursementId
     * @param $demandeType
     * @param null $isBBC1
     * @return array
     */
    private function findDocumentNonConforme(
        $remboursementId,
        $demandeType,
        $isBBC1 = null
    ) {
        $arrayRibReasonSlug = array();
        $arrayFactureReasonSlug = array();
        $arrayChequeReasonSlug = array();
        $arrayFicheTravauxReasonSlug = array();
        $conformiteRib = null;
        $conformiteFacture = null;
        $conformiteCheque = null;
        $conformiteFicheTravaux = null;
        $reasonAutreRib = null;
        $reasonAutreFacture = null;
        $reasonAutreCheque = null;
        $reasonAutreFicheTravaux = null;

        /* /////////////////////////////////////////////////////////////////
                                GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursement = $this->repo_remboursement->find($remboursementId);

        $instruction = null;
        if (in_array($demandeType, [Demande_::DEMANDE_AUDIT_ENERGIE_TYPE, Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE])) {
            // Audit Energie / Audit Energie Région
            $instruction = ($remboursement->getRemboursementAuditEnergie() && $remboursement->getRemboursementAuditEnergie()->getInstruction()) ? $remboursement->getRemboursementAuditEnergie()->getInstruction() : null;
        } elseif (in_array($demandeType, [Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE, Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE])) {
            // Audit Numerique / Mise à jour audit
            $instruction = ($remboursement->getRemboursementAuditNumerique() && $remboursement->getRemboursementAuditNumerique()->getInstruction()) ? $remboursement->getRemboursementAuditNumerique()->getInstruction() : null;
        } elseif (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType) {
            // Travaux
            $instruction = ($remboursement->getRemboursementTravaux() && $remboursement->getRemboursementTravaux()->getInstruction()) ? $remboursement->getRemboursementTravaux()->getInstruction() : null;
        }

        if ($instruction) {

            $conformiteRib = DefaultUtils::getKey($instruction->getIsRibConforme());
            $reasonAutreRib = $instruction->getRibReasonAutre();

            $conformiteCheque = DefaultUtils::getKey($instruction->getIsChequeConforme());
            $reasonAutreCheque = $instruction->getChequeReasonAutre();

            // AUDIT ENERGIE /  AUDIT ENERGIE REGION / AUDIT NUMERIQUE / MISE A JOUR AUDIT / TRAVAUX BBC 2
            if (
                in_array($demandeType, [
                    Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                    Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
                    Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
                    Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE
                ])
                || (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType && false == $isBBC1)
            ) {
                $conformiteFacture = DefaultUtils::getKey($instruction->getIsFactureConforme());
                $reasonAutreFacture = $instruction->getFactureReasonAutre();
            }

            // TRAVAUX BBC 1
            if (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType && true == $isBBC1) {
                $conformiteFicheTravaux = DefaultUtils::getKey($instruction->getIsFicheTravauxConforme());
                $reasonAutreFicheTravaux = $instruction->getFicheTravauxReasonAutre();
            }

            foreach ($instruction->getRibReason() as $item) {
                $arrayRibReasonSlug[] = $item->getSlug();
            }

            foreach ($instruction->getChequeReason() as $item) {
                $arrayChequeReasonSlug[] = $item->getSlug();
            }

            // AUDIT ENERGIE /  AUDIT ENERGIE REGION / AUDIT NUMERIQUE / MISE A JOUR AUDIT / TRAVAUX BBC 2
            if (
                in_array($demandeType, [
                    Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                    Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
                    Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
                    Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE
                ])
                || (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType && false == $isBBC1)
            ) {
                foreach ($instruction->getFactureReason() as $item) {
                    $arrayFactureReasonSlug[] = $item->getSlug();
                }
            }

            // TRAVAUX BBC 1
            if (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType && true == $isBBC1) {
                foreach ($instruction->getFicheTravauxReason() as $item) {
                    $arrayFicheTravauxReasonSlug[] = $item->getSlug();
                }
            }
        }

        return array(
            'arrayRibReasonSlug'            => $arrayRibReasonSlug,
            'arrayFactureReasonSlug'        => $arrayFactureReasonSlug,
            'arrayChequeReasonSlug'         => $arrayChequeReasonSlug,
            'arrayFicheTravauxReasonSlug'   => $arrayFicheTravauxReasonSlug,
            'conformiteRib'                 => $conformiteRib,
            'conformiteFacture'             => $conformiteFacture,
            'conformiteCheque'              => $conformiteCheque,
            'conformiteFicheTravaux'        => $conformiteFicheTravaux,
            'reasonAutreRib'                => $reasonAutreRib,
            'reasonAutreFacture'            => $reasonAutreFacture,
            'reasonAutreCheque'             => $reasonAutreCheque,
            'reasonAutreFicheTravaux'       => $reasonAutreFicheTravaux
        );
    }



}

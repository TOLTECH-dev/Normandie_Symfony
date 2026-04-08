<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Entity\FicheTechnique;
use App\Entity\Remboursement_;
use App\Entity\Remboursement_auditEnergie;
use App\Entity\Remboursement_travaux;
use App\Repository\DateRMHRepository;
use App\Repository\FicheTechniqueRepository;
use App\Repository\Remboursement_Repository;
use App\Repository\TitreRepository;
use App\Service\HistoriqueService;
use App\Service\DocumentService;
use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use App\Entity\Demande_travaux_devis_upload;
use App\Repository\Demande_Repository;
use App\Repository\Demande_travaux_devisRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:processDeleteDocument',
    description: 'Lancer la suppression des documents à supprimer (Date RMH > 1 AN ou refusées 1 an apres la dernière modification de la demande/remboursement)',
)]
class ProcessDeleteDocumentCommand extends Command
{
    const TYPE_SUPPRESSION_DOCUMENT_DEMANDE_REFUS = 'TYPE_SUPPRESSION_DOCUMENT_DEMANDE_REFUS';
    const TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS = 'TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS';
    const TYPE_SUPPRESSION_DOCUMENT_DATE_RHM = 'TYPE_SUPPRESSION_DOCUMENT_DATE_RHM';
    private Demande_Repository $demandeRepository;
    private Demande_travaux_devisRepository $demandeTravauxDevisRepository;
    private FicheTechniqueRepository $ficheTechniqueRepository;
    private Remboursement_Repository $remboursementRepository;
    private TitreRepository $titreRepository;
    private DateRMHRepository $dateRMHRepository;
    private EntityManagerInterface $em;
    private DocumentService $documentService;
    private HistoriqueService $historiqueService;
    private LoggerInterface $commandDeleteDocumentsLogger;
    private string $rootUploadDir;
    private string $productionTravauxNiveauBBC1;
    private string $productionTravauxNiveauBBC2;
    private $listDeleteDocumentDemandeRefus = [];
    private $listHistoriqueDemandeRefus = [];
    private $listDeleteDocumentRemboursementRefus = [];
    private $listHistoriqueRemboursementRefus = [];
    private $listDeleteDocumentRMH = [];
    private $listHistoriqueDemandeRMH = [];

    public function __construct(
        Demande_Repository              $demandeRepository,
        Demande_travaux_devisRepository $demandeTravauxDevisRepository,
        FicheTechniqueRepository        $ficheTechniqueRepository,
        Remboursement_Repository        $remboursementRepository,
        TitreRepository                 $titreRepository,
        DateRMHRepository               $dateRMHRepository,
        EntityManagerInterface          $em,
        DocumentService                 $documentService,
        HistoriqueService               $historiqueService,
        LoggerInterface                 $commandDeleteDocumentsLogger,
        string                          $rootUploadDir,
        string                          $productionTravauxNiveauBBC1,
        string                          $productionTravauxNiveauBBC2
    )
    {
        parent::__construct();
        $this->demandeRepository = $demandeRepository;
        $this->demandeTravauxDevisRepository = $demandeTravauxDevisRepository;
        $this->ficheTechniqueRepository = $ficheTechniqueRepository;
        $this->remboursementRepository = $remboursementRepository;
        $this->titreRepository = $titreRepository;
        $this->dateRMHRepository = $dateRMHRepository;
        $this->em = $em;
        $this->documentService = $documentService;
        $this->historiqueService = $historiqueService;
        $this->commandDeleteDocumentsLogger = $commandDeleteDocumentsLogger;
        $this->rootUploadDir = $rootUploadDir;
        $this->productionTravauxNiveauBBC1 = $productionTravauxNiveauBBC1;
        $this->productionTravauxNiveauBBC2 = $productionTravauxNiveauBBC2;
    }


    /* *****************************************************************
    ********************************************************************
                            PROTECTED FUNCTION
    ********************************************************************
    *******************************************************************/
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandDeleteDocumentsLogger->info('--- ProcessDeleteDocumentCommand: Start ---', []);
        try {
            $this->runCustom($output);
            $this->commandDeleteDocumentsLogger->info('--- ProcessDeleteDocumentCommand: End ---', []);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }


    /* *****************************************************************
    ********************************************************************
                            PRIVATE FUNCTION
    ********************************************************************
    *******************************************************************/
    /**
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function runCustom(): void
    {
        /* /////////////////////////////////////////////////////////////////////////////////////
                GET LIST REMBOURSEMENT REFUSE DONT DATE DE DERNIERE MISE À JOUR > 1 AN
        ///////////////////////////////////////////////////////////////////////////////////// */
        $listRemboursementRefuse = $this->remboursementRepository->findRefusForDeleteDocumentProcess();
        $this->commandDeleteDocumentsLogger->info('LIST REMBOURSEMENT REFUSE DONT DATE DE DERNIERE MISE À JOUR > 1 AN', []);

        if (empty($listRemboursementRefuse)) {
            $this->commandDeleteDocumentsLogger->notice('Aucun document à supprimer pour cette partie là', []);
        } else {
            foreach ($listRemboursementRefuse as $rowRemboursement) {

                $this->deleteDocumentForDemandeRefus(
                    $rowRemboursement['demandeId'],
                    self::TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS
                );

                $this->deleteDocumentForRemboursementRefus(
                    $rowRemboursement['remboursementId'],
                    $rowRemboursement['demandeType']
                );
            }
        }


        /* /////////////////////////////////////////////////////////////////////////////////////
                GET LIST DEMANDE REFUSE DONT DATE DE DERNIERE MISE À JOUR > 1 AN
        ///////////////////////////////////////////////////////////////////////////////////// */
        $this->commandDeleteDocumentsLogger->info('LIST DEMANDE REFUSE DONT DATE DE DERNIERE MISE À JOUR > 1 AN', []);

        $listDemandeRefuse = $this->demandeRepository->findRefusForDeleteDocumentProcess();
        if (empty($listDemandeRefuse)) {
            $this->commandDeleteDocumentsLogger->notice('Aucun document à supprimer pour cette partie là', []);
        } else {
            foreach ($listDemandeRefuse as $demandeId) {
                $this->deleteDocumentForDemandeRefus($demandeId, self::TYPE_SUPPRESSION_DOCUMENT_DEMANDE_REFUS);
            }
        }


        /* /////////////////////////////////////////////////////////////////////////////////////
                        GET LIST REMBOURSEMENTS DONT LA DATE RMH > 1 AN
        ///////////////////////////////////////////////////////////////////////////////////// */
        $this->commandDeleteDocumentsLogger->info('LIST REMBOURSEMENT DONT LA DATE RMH > 1 AN', []);

        $listRemboursementWithRMHCondition = $this->remboursementRepository->findWithRMHConditionForDeleteDocumentProcess(
            $this->productionTravauxNiveauBBC1,
            $this->productionTravauxNiveauBBC1
        );
        if (empty($listRemboursementWithRMHCondition)) {
            $this->commandDeleteDocumentsLogger->notice('Aucun document à supprimer pour cette partie là', []);
        } else {
            /**
             * @var Remboursement_ $remboursement
             */
            foreach ($listRemboursementWithRMHCondition as $remboursement) {
                $this->deleteDocumentWithRMHCondition($remboursement);
            }
        }

        $this->em->clear();
    }

    /**
     * Supprimer :
     * Justificatif de propriété
     * Pièce complémentaire / Statut de la SCI
     * Avis d'imposition
     * Avis d'imposition du conjoint
     * Autres (Travaux: Audit réalisé ou Evaluation thermique, Devis, acte d'engagement dans le cadre d'un accompagnement par un Espace Conseil France Rénov', xml de la fiche technique)
     * ET Mettre à null les champs des fichiers concernés
     */
    private function deleteDemandeDocumentAndUpdate(Demande_ $demande, ?string $typeSuppression = null): void
    {
        $success = true;
        $errorMessage = "";
        $filesToDelete = [];
        $filesToDeleteDevis = [];

        switch ($demande->getType()) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE :
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE :
                $demandeAuditE = $demande->getDemandeAuditEnergie();

                /* /////////////////////////////////////////////////////////////////////////////////////
                                            DOCUMENTS AUDIT ENERGETIQUE
                ///////////////////////////////////////////////////////////////////////////////////// */
                $justificatifProprieteFile = !empty($demandeAuditE->getJustificatifProprieteUrl()) ? $this->rootUploadDir . $demandeAuditE->justificatifPropriete_getWebPath() : null;
                $pieceComplementFile = !empty($demandeAuditE->getPieceComplementUrl()) ? $this->rootUploadDir . $demandeAuditE->pieceComplement_getWebPath() : null;
                $avisImpositionFile = !empty($demandeAuditE->getAvisImpositionUrl()) ? $this->rootUploadDir . $demandeAuditE->avisImposition_getWebPath() : null;
                $avisImpositionConjointFile = !empty($demandeAuditE->getAvisImpositionConjointUrl()) ? $this->rootUploadDir . $demandeAuditE->avisImpositionConjoint_getWebPath() : null;

                /* /////////////////////////////////////////////////////////////////////////////////////
                                            DELETE AND UPDATE DOCUMENTS
                ///////////////////////////////////////////////////////////////////////////////////// */
                $filesToDelete = [
                    $justificatifProprieteFile,
                    $pieceComplementFile,
                    $avisImpositionFile,
                    $avisImpositionConjointFile
                ];
                $resultDeleteDemandeDocument = $this->documentService->deleteDemandeDocument(
                    $demande->getId(),
                    $filesToDelete,
                    $errorMessage,
                    $success,
                    true
                );
                $this->logByResultArray($resultDeleteDemandeDocument, $demande->getId(), null);

                $demandeAuditE->setJustificatifProprieteAlt(null)
                    ->setJustificatifProprieteUrl(null)
                    ->setPieceComplementAlt(null)
                    ->setPieceComplementUrl(null)
                    ->setAvisImpositionAlt(null)
                    ->setAvisImpositionUrl(null)
                    ->setAvisImpositionConjointAlt(null)
                    ->setAvisImpositionConjointUrl(null);
                break;

            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE :
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE :
                break;

            case Demande_::DEMANDE_TRAVAUX_TYPE:
                /* /////////////////////////////////////////////////////////////////////////////////////
                                            DOCUMENTS TRAVAUX
                ///////////////////////////////////////////////////////////////////////////////////// */
                $demandeTravaux = $demande->getDemandeTravaux();
                $justificatifProprieteFile = !empty($demandeTravaux->getJustificatifProprieteUrl()) ? $this->rootUploadDir . $demandeTravaux->justificatifPropriete_getWebPath() : null;
                $pieceComplementFile = !empty($demandeTravaux->getPieceComplementUrl()) ? $this->rootUploadDir . $demandeTravaux->pieceComplement_getWebPath() : null;
                $avisImpositionFile = !empty($demandeTravaux->getAvisImpositionUrl()) ? $this->rootUploadDir . $demandeTravaux->avisImposition_getWebPath() : null;
                $avisImpositionConjointFile = !empty($demandeTravaux->getAvisImpositionConjointUrl()) ? $this->rootUploadDir . $demandeTravaux->avisImpositionConjoint_getWebPath() : null;
                $demandeTravauxDevisAuditFile = null;
                $demandeTravauxDevisActeEngagementFile = null;
                $ficheTechniqueDocumentXmlInitialFile = null;
                $ficheTechniqueDocumentXmlBBCFile = null;
                $ficheTechniqueDocumentXmlTravauxFile = null;

                if (self::TYPE_SUPPRESSION_DOCUMENT_DEMANDE_REFUS == $typeSuppression) {

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                                DOCUMENTS TRAVAUX DEVIS
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    if (!empty($demandeTravaux->getTravauxDevisId())) {
                        /**
                         * @var Demande_travaux_devis $demandeTravauxDevis
                         */
                        $demandeTravauxDevis = $this->demandeTravauxDevisRepository->find($demandeTravaux->getTravauxDevisId());

                        $demandeTravauxDevisAuditFile = !empty($demandeTravauxDevis->getAuditUrl()) ? $this->rootUploadDir . $demandeTravauxDevis->audit_getWebPath() : null;
                        $demandeTravauxDevisActeEngagementFile = !empty($demandeTravauxDevis->getActeEngagementUrl()) ? $this->rootUploadDir . $demandeTravauxDevis->acteEngagement_getWebPath() : null;

                        $demandesTravauxDevisUpload = $demandeTravauxDevis->getDemandeTravauxDevisUpload();
                        /**
                         * @var Demande_travaux_devis_upload $demandeTravauxDevisUpload
                         */
                        foreach ($demandesTravauxDevisUpload as $demandeTravauxDevisUpload) {
                            if (!empty($demandeTravauxDevisUpload->getDevisDocumentUrl())) {
                                $filesToDeleteDevis[] = $this->rootUploadDir . $demandeTravauxDevisUpload->devisDocument_getWebPath();
                            }
                        }
                    }

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                                DOCUMENTS FICHE TECHNIQUE
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    if (!empty($demandeTravaux->getFicheTechniqueId())) {
                        /**
                         * @var FicheTechnique $ficheTechnique
                         */
                        $ficheTechnique = $this->ficheTechniqueRepository->find($demandeTravaux->getFicheTechniqueId());
                        if (!empty($ficheTechnique)) {
                            $ficheTechniqueDocumentXmlInitialFile = !empty($ficheTechnique->getFicheTechniqueInitial()) && !empty($ficheTechnique->getFicheTechniqueInitial()->getFicheTechniqueDocumentUrl()) ? $this->rootUploadDir . $ficheTechnique->getFicheTechniqueInitial()->ficheTechniqueDocument_getWebPath() : null;
                            $ficheTechniqueDocumentXmlBBCFile = !empty($ficheTechnique->getFicheTechniqueBBC()) && !empty($ficheTechnique->getFicheTechniqueBBC()->getFicheTechniqueDocumentUrl()) ? $this->rootUploadDir . $ficheTechnique->getFicheTechniqueBBC()->ficheTechniqueDocument_getWebPath() : null;
                            $ficheTechniqueDocumentXmlTravauxFile = !empty($ficheTechnique->getFicheTechniquePrescription()) && !empty($ficheTechnique->getFicheTechniquePrescription()->getFicheTechniqueDocumentUrl()) ? $this->rootUploadDir . $ficheTechnique->getFicheTechniquePrescription()->ficheTechniqueDocument_getWebPath() : null;
                        }
                    }
                }

                /* /////////////////////////////////////////////////////////////////////////////////////
                                            DELETE AND UPDATE DOCUMENTS
                ///////////////////////////////////////////////////////////////////////////////////// */
                $filesToDelete = array_merge_recursive($filesToDeleteDevis, [
                    $justificatifProprieteFile,
                    $pieceComplementFile,
                    $avisImpositionFile,
                    $avisImpositionConjointFile,
                    $demandeTravauxDevisAuditFile,
                    $demandeTravauxDevisActeEngagementFile,
                    $ficheTechniqueDocumentXmlInitialFile,
                    $ficheTechniqueDocumentXmlBBCFile,
                    $ficheTechniqueDocumentXmlTravauxFile
                ]);

                $resultDeleteDemandeDocument = $this->documentService->deleteDemandeDocument(
                    $demande->getId(),
                    $filesToDelete,
                    $errorMessage,
                    $success,
                    true
                );
                $this->logByResultArray($resultDeleteDemandeDocument, $demande->getId(), null);

                $demandeTravaux->setJustificatifProprieteAlt(null)
                    ->setJustificatifProprieteUrl(null)
                    ->setPieceComplementAlt(null)
                    ->setPieceComplementUrl(null)
                    ->setAvisImpositionAlt(null)
                    ->setAvisImpositionUrl(null)
                    ->setAvisImpositionConjointAlt(null)
                    ->setAvisImpositionConjointUrl(null);

                if (self::TYPE_SUPPRESSION_DOCUMENT_DEMANDE_REFUS == $typeSuppression) {

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                                DOCUMENTS TRAVAUX DEVIS
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    if (!empty($demandeTravauxDevis)) {
                        $demandeTravauxDevis->setAuditAlt(null);
                        $demandeTravauxDevis->setAuditUrl(null);
                        $demandeTravauxDevis->setActeEngagementAlt(null);
                        $demandeTravauxDevis->setActeEngagementUrl(null);

                        /**
                         * @var Demande_travaux_devis_upload $demandeTravauxDevisUpload
                         */
                        foreach ($demandesTravauxDevisUpload as $demandeTravauxDevisUpload) {
                            $demandeTravauxDevisUpload->setDevisDocumentAlt(null);
                            $demandeTravauxDevisUpload->setDevisDocumentUrl(null);
                        }
                    }

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                                DOCUMENTS FICHE TECHNIQUE
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    if (!empty($ficheTechnique)) {
                        if (!empty($ficheTechnique->getFicheTechniqueInitial())) {
                            $ficheTechnique->getFicheTechniqueInitial()->setFicheTechniqueDocumentAlt(null);
                            $ficheTechnique->getFicheTechniqueInitial()->setFicheTechniqueDocumentUrl(null);
                        }
                        if (!empty($ficheTechnique->getFicheTechniqueBBC())) {
                            $ficheTechnique->getFicheTechniqueBBC()->setFicheTechniqueDocumentAlt(null);
                            $ficheTechnique->getFicheTechniqueBBC()->setFicheTechniqueDocumentUrl(null);
                        }
                        if (!empty($ficheTechnique->getFicheTechniquePrescription())) {
                            $ficheTechnique->getFicheTechniquePrescription()->setFicheTechniqueDocumentAlt(null);
                            $ficheTechnique->getFicheTechniquePrescription()->setFicheTechniqueDocumentUrl(null);
                        }
                    }
                }

                break;
        }

        if (!empty($filesToDelete)) {
            if (!empty($demandeTravauxDevis)) {
                $this->em->persist($demandeTravauxDevis);
            }
            if (!empty($ficheTechnique)) {
                $this->em->persist($ficheTechnique);
            }

            $demande->setRgpd(true);

            $this->em->persist($demande);
            $this->em->flush();
        }
    }

    private function deleteRemboursementDocumentAndUpdate(
        Remboursement_ $remboursement,
        int            $demandeType,
        string         $typeSuppression
    ): array
    {

        $success = true;
        $errorMessage = "";

        switch ($demandeType) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                /**
                 * @var Remboursement_auditEnergie $remboursementByType
                 */
                $remboursementByType = (in_array($demandeType, [
                    Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                    Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
                ])) ? $remboursement->getRemboursementAuditEnergie() : $remboursement->getRemboursementAuditNumerique();

                if (!empty($remboursementByType)) {

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                     DOCUMENTS INSTRUCTION (SUPPRESSION ET MISE A JOUR)
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    $instruction = $remboursementByType->getInstruction();
                    if (!empty($instruction)) {

                        if (in_array($typeSuppression, [
                            self::TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS,
                            self::TYPE_SUPPRESSION_DOCUMENT_DATE_RHM
                        ])) {
                            $resultDeleteAndUpdateRIB = $this->documentService->deleteAndUpdateRIB(
                                $remboursement->getDemandeId(),
                                $instruction,
                                true,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateRIB, $remboursement->getDemandeId(), $remboursement->getId());
                        }

                        if (self::TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS == $typeSuppression) {
                            $resultDeleteAndUpdateRectoVersoCheque = $this->documentService->deleteAndUpdateRectoVersoCheque(
                                $remboursement->getDemandeId(),
                                $instruction,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateRectoVersoCheque, $remboursement->getDemandeId(), $remboursement->getId());

                            $resultDeleteAndUpdateFacture = $this->documentService->deleteAndUpdateFacture(
                                $remboursement->getDemandeId(),
                                $instruction,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateFacture, $remboursement->getDemandeId(), $remboursement->getId());
                        }
                    }

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                     DOCUMENTS DEPOT AUDIT (SUPPRESSION ET MISE A JOUR)
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    $depot = $remboursementByType->getDepot();
                    if (!empty($depot)) {
                        if (self::TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS == $typeSuppression) {
                            $resultDeleteAndUpdateDepotAudit = $this->documentService->deleteAndUpdateDepotAudit(
                                $remboursement->getDemandeId(),
                                $depot,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateDepotAudit, $remboursement->getDemandeId(), $remboursement->getId());
                        }
                    }
                }
                break;

            case Demande_::DEMANDE_TRAVAUX_TYPE:
                /**
                 * @var Remboursement_travaux $remboursementByType
                 */
                $remboursementByType = $remboursement->getRemboursementTravaux();
                if (!empty($remboursementByType)) {

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                        DOCUMENTS INSTRUCTION (SUPPRESSION ET MISE A JOUR)
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    $instruction = $remboursementByType->getInstruction();
                    if (!empty($instruction)) {

                        if (in_array($typeSuppression, [
                            self::TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS,
                            self::TYPE_SUPPRESSION_DOCUMENT_DATE_RHM
                        ])) {
                            $resultDeleteAndUpdateRIB = $this->documentService->deleteAndUpdateRIB(
                                $remboursement->getDemandeId(),
                                $instruction,
                                ($typeSuppression == self::TYPE_SUPPRESSION_DOCUMENT_DATE_RHM),
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateRIB, $remboursement->getDemandeId(), $remboursement->getId());
                        }

                        if (self::TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS == $typeSuppression) {
                            $resultDeleteAndUpdateRectoVersoCheque = $this->documentService->deleteAndUpdateRectoVersoCheque(
                                $remboursement->getDemandeId(),
                                $instruction,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateRectoVersoCheque, $remboursement->getDemandeId(), $remboursement->getId());

                            $resultDeleteAndUpdateTravauxFactures = $this->documentService->deleteAndUpdateTravauxFactures(
                                $remboursement->getDemandeId(),
                                $instruction,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateTravauxFactures, $remboursement->getDemandeId(), $remboursement->getId());
                        }
                    }

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                   DOCUMENTS FICHE TECHNIQUE (SUPPRESSION ET MISE A JOUR)
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    if (
                        !empty($remboursement->getRemboursementTravaux())
                        && !empty($remboursement->getRemboursementTravaux()->getFicheTechnique())
                        && !empty($remboursement->getRemboursementTravaux()->getFicheTechnique()->getFicheTechniqueFinChantier())
                    ) {
                        $ficheTechniqueFinChantier = $remboursement->getRemboursementTravaux()->getFicheTechnique()->getFicheTechniqueFinChantier();

                        if (in_array($typeSuppression, [
                            self::TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS,
                            self::TYPE_SUPPRESSION_DOCUMENT_DATE_RHM
                        ])) {
                            $resultDeleteAndUpdateInfiltrometrieDocument = $this->documentService->deleteAndUpdateInfiltrometrieDocument(
                                $remboursement->getDemandeId(),
                                $ficheTechniqueFinChantier,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateInfiltrometrieDocument, $remboursement->getDemandeId(), $remboursement->getId());

                            $resultDeleteAndUpdateVentilationDocument = $this->documentService->deleteAndUpdateVentilationDocument(
                                $remboursement->getDemandeId(),
                                $ficheTechniqueFinChantier,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateVentilationDocument, $remboursement->getDemandeId(), $remboursement->getId());

                            $resultDeleteAndUpdateAuditApresTravauxDocument = $this->documentService->deleteAndUpdateAuditApresTravauxDocument(
                                $remboursement->getDemandeId(),
                                $ficheTechniqueFinChantier,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateAuditApresTravauxDocument, $remboursement->getDemandeId(), $remboursement->getId());
                        }

                        if (self::TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS == $typeSuppression) {
                            $resultDeleteAndUpdateFicheTechniqueFinDeChantierDocument = $this->documentService->deleteAndUpdateFicheTechniqueFinDeChantierDocument(
                                $remboursement->getDemandeId(),
                                $ficheTechniqueFinChantier,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateFicheTechniqueFinDeChantierDocument, $remboursement->getDemandeId(), $remboursement->getId());
                        }
                    }
                }
                break;
        }

        /* /////////////////////////////////////////////////////////////////////////////////////
                       MISE A JOUR DE DE LA DATE ET AUTEUR REMBOURSEMENT
        ///////////////////////////////////////////////////////////////////////////////////// */
        $remboursement->setRgpd(true);

        $this->em->persist($remboursement);
        $this->em->flush();

        return [
            'success' => $success,
            'errorMessage' => $errorMessage
        ];
    }

    private function getRemboursementProcess(Remboursement_ $remboursement): array
    {
        /**
         * @var Demande_ $demande
         */
        $demande = $this->demandeRepository->find($remboursement->getDemandeId());

        $listRemboursementProcess = array($remboursement);

        /* ///////////////////////////////////////////////////////////////////////////
                                            IF TRAVAUX
        /////////////////////////////////////////////////////////////////////////// */
        if (Demande_::DEMANDE_TRAVAUX_TYPE == $demande->getType()) {
            $titreId = $remboursement->getTitreId();
            $titre = $this->titreRepository->find($titreId);

            if ($this->productionTravauxNiveauBBC1 == $titre->getNumeroOperation()) {
                /* ///////////////////////////////////////////////////////////////////////////
                    IF IS TRAVAUX BBC1 => NO PROCESSING
                /////////////////////////////////////////////////////////////////////////// */
                $listRemboursementProcess = [];
            } elseif ($this->productionTravauxNiveauBBC2 == $titre->getNumeroOperation()) {
                /* ///////////////////////////////////////////////////////////////////////////
                    IF IS TRAVAUX BBC2 => FIND IF EXISTS BBC1 WITH STATUT REMB = REMBOURSE
                /////////////////////////////////////////////////////////////////////////// */

                $remboursementBBC1 = $this->remboursementRepository->findBBC1RembourseByDemandeAndBBC1Parameter(
                    $remboursement->getDemandeId(),
                    $this->productionTravauxNiveauBBC1
                );
                if ($remboursementBBC1) {
                    $listRemboursementProcess[] = $this->remboursementRepository->find($remboursementBBC1->getId());
                }
            }

        }

        return $listRemboursementProcess;
    }

    private function deleteDocumentForDemandeRefus(int $demandeId, string $typeSuppression): void
    {
        /**
         * @var Demande_ $demande
         */
        $demande = $this->demandeRepository->find($demandeId);

        if (!in_array($demande->getId(), $this->listDeleteDocumentDemandeRefus)) {
            $this->deleteDemandeDocumentAndUpdate(
                $demande,
                self::TYPE_SUPPRESSION_DOCUMENT_DEMANDE_REFUS
            );

            $this->listDeleteDocumentDemandeRefus[] = $demande->getId();

            $demande->setRgpd(true);

            $this->em->persist($demande);
            $this->em->flush();
            $this->em->clear();
        }

        // Si on est dans le nettoyage typeSuppression = "Remboursement Refus", on ne remet pas un historique car deja fait dans partie Remboursement
        if (self::TYPE_SUPPRESSION_DOCUMENT_DEMANDE_REFUS == $typeSuppression) {
            if (!in_array($demande->getId(), $this->listHistoriqueDemandeRefus)) {
                /* /////////////////////////////////////////////////////////////////
                                        FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                $userRoles = array('ROLE_AUTOMATE');
                $this->historiqueService->save(
                    $demande->getId(),
                    $demande->getStatutId(),
                    $demande->getType(),
                    $userRoles,
                    false,
                    'Nettoyage demande refusée'
                );
                $this->listHistoriqueDemandeRefus[] = $demande->getId();
            }
        }
    }

    private function deleteDocumentForRemboursementRefus(int $remboursementId, int $demandeType): void
    {
        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $this->remboursementRepository->find($remboursementId);

        if (!in_array($remboursement->getId(), $this->listDeleteDocumentRemboursementRefus)) {
            $this->deleteRemboursementDocumentAndUpdate(
                $remboursement,
                $demandeType,
                self::TYPE_SUPPRESSION_DOCUMENT_REMBOURSEMENT_REFUS
            );
            $this->listDeleteDocumentRemboursementRefus[] = $remboursement->getId();
        }

        if (!in_array($remboursement->getDemandeId(), $this->listHistoriqueRemboursementRefus)) {
            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $userRoles = array('ROLE_AUTOMATE');
            $this->historiqueService->save(
                $remboursement->getDemandeId(),
                $remboursement->getStatutId(),
                $demandeType,
                $userRoles,
                false,
                'Nettoyage remboursement refusé',
                null,
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId()
            );
            $this->listHistoriqueRemboursementRefus[] = $remboursement->getDemandeId();
        }
    }

    private function deleteDocumentWithRMHCondition(Remboursement_ $remboursement): void
    {
        $listRemboursementProcess = $this->getRemboursementProcess($remboursement);
        $success = true;
        $errorMessage = "";

        if (!empty($listRemboursementProcess)) {
            foreach ($listRemboursementProcess as $remboursementProcess) {

                /**
                 * @var Demande_ $demande
                 */
                $demande = $this->demandeRepository->find($remboursementProcess->getDemandeId());

                $this->deleteDemandeDocumentAndUpdate($demande, self::TYPE_SUPPRESSION_DOCUMENT_DATE_RHM);

                $this->deleteRemboursementDocumentAndUpdate(
                    $remboursementProcess,
                    $demande->getType(),
                    self::TYPE_SUPPRESSION_DOCUMENT_DATE_RHM
                );

                if (!in_array($remboursementProcess->getDateRMHId(), $this->listDeleteDocumentRMH)) {
                    $resultDeleteRMHDocument = $this->documentService->deleteRMHDocument(
                        $remboursementProcess->getDateRMHId(),
                        $errorMessage,
                        $success,
                        true
                    );
                    $this->logByResultArray($resultDeleteRMHDocument, $remboursementProcess->getDemandeId(), $remboursementProcess->getId());

                    $this->listDeleteDocumentRMH[] = $remboursementProcess->getDateRMHId();

                    $dateRMH = $this->dateRMHRepository->find($remboursementProcess->getDateRMHId());
                    $dateRMH->setDateModif(new \DateTime());
                    $dateRMH->setAuteurModif('Automate');
                    $dateRMH->setRgpd(true);

                    $this->em->persist($dateRMH);
                    $this->em->flush();
                }

                if (!in_array($remboursementProcess->getDemandeId(), $this->listHistoriqueDemandeRMH)) {
                    /* /////////////////////////////////////////////////////////////////
                                            FILL UP HISTORIQUE
                    ///////////////////////////////////////////////////////////////// */
                    $userRoles = array('ROLE_AUTOMATE');
                    $this->historiqueService->save(
                        $remboursementProcess->getDemandeId(),
                        $remboursementProcess->getStatutId(),
                        $demande->getType(),
                        $userRoles,
                        false,
                        'Nettoyage RGPD',
                        null,
                        null,
                        null,
                        null,
                        null,
                        false,
                        $remboursementProcess->getId()
                    );
                    $this->listHistoriqueDemandeRMH[] = $remboursementProcess->getDemandeId();
                }
            }
        }
    }

    private function logByResultArray(array $result, int $demandeId, ?int $remboursementId): void
    {
        if (!empty($result)) {
            foreach ($result['isSuccess'] as $keySuccess => $valSuccess) {
                $errorMessage = (false === $valSuccess) ? 'Erreur Suppression doc ' . $this->documentService->formatFilenameForLogs($result['filename'][$keySuccess]) : '';
                $detailMessage = (true === $valSuccess) ? 'Suppression doc ' . $this->documentService->formatFilenameForLogs($result['filename'][$keySuccess]) : '';
                $contexLogger = [
                    'NumeroDossier' => $demandeId . ' / RemboursementId: ' . $remboursementId,
                    'IsSuccess' => $valSuccess,
                    'ErrorMessage' => $errorMessage,
                    'DetailMessage' => $detailMessage
                ];

                if (false === $valSuccess) {
                    $this->commandDeleteDocumentsLogger->error('', $contexLogger);
                } else {
                    $this->commandDeleteDocumentsLogger->info('', $contexLogger);
                }
            }
        }
    }
}

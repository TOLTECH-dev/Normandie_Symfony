<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Entity\FicheTechnique;
use App\Entity\Remboursement_;
use App\Entity\Remboursement_travaux;
use App\Repository\FicheTechniqueRepository;
use App\Repository\Remboursement_Repository;
use App\Service\HistoriqueService;
use App\Service\DocumentService;
use App\Entity\Demande_;
use App\Entity\Demande_travaux;
use App\Entity\Demande_travaux_devis;
use App\Repository\Demande_travaux_devisRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:processDeleteDocumentTravauxRemboursementTermine',
    description: 'Lancer la suppression des documents Travaux pour les remboursements terminés dont la date de dernière modification remboursement date d\'au moins 1 AN',
)]
class DeleteDocumentTravauxRemboursementTermineCommand extends Command
{
    private EntityManagerInterface $em;
    private Demande_travaux_devisRepository $demandeTravauxDevisRepository;
    private FicheTechniqueRepository $ficheTechniqueRepository;
    private Remboursement_Repository $remboursementRepository;
    private DocumentService $documentService;
    private HistoriqueService $historiqueService;
    private LoggerInterface $commandDeleteDocumentsLogger;
    private array $listDeleteDocumentRemboursementTermine = [];
    private array $listHistoriqueRemboursementTermine = [];
    private string $productionTravauxNiveauBBC1;
    private string $productionTravauxNiveauBBC2;

    public function __construct(
        EntityManagerInterface          $em,
        Demande_travaux_devisRepository $demandeTravauxDevisRepository,
        FicheTechniqueRepository        $ficheTechniqueRepository,
        Remboursement_Repository        $remboursementRepository,
        HistoriqueService               $historiqueService,
        DocumentService                 $documentService,
        LoggerInterface                 $commandDeleteDocumentsLogger,
        string                          $productionTravauxNiveauBBC1,
        string                          $productionTravauxNiveauBBC2
    )
    {
        parent::__construct();
        $this->em = $em;
        $this->demandeTravauxDevisRepository = $demandeTravauxDevisRepository;
        $this->ficheTechniqueRepository = $ficheTechniqueRepository;
        $this->remboursementRepository = $remboursementRepository;
        $this->historiqueService = $historiqueService;
        $this->documentService = $documentService;
        $this->commandDeleteDocumentsLogger = $commandDeleteDocumentsLogger;
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
        $this->commandDeleteDocumentsLogger->info('--- DeleteDocumentTravauxRemboursementTermineCommand: Start ---', []);

        try {
            $this->runCustom();
            $this->commandDeleteDocumentsLogger->info('--- DeleteDocumentTravauxRemboursementTermineCommand: End ---', []);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }


    /* *****************************************************************
    ********************************************************************
                            PRIVATE FUNCTION
    ********************************************************************
    *******************************************************************/
    /**
     * @throws Exception
     */
    private function runCustom(): void
    {
        /* /////////////////////////////////////////////////////////////////////////////////////
                GET LIST REMBOURSEMENT
        ///////////////////////////////////////////////////////////////////////////////////// */
        $listRemboursementTermine = $this->remboursementRepository->findForDeleteTravauxDocumentRmbTermineProcess(
            $this->productionTravauxNiveauBBC1,
            $this->productionTravauxNiveauBBC2
        );
        if (empty($listRemboursementTermine)) {
            $this->commandDeleteDocumentsLogger->notice('Aucun document à supprimer', []);
        } else {
            foreach ($listRemboursementTermine as $rowRemboursement) {

                if (!empty($rowRemboursement['remboursementId2']) && $rowRemboursement['remboursementId2'] != $rowRemboursement['remboursementId']) {
                    $this->deleteDocumentForRemboursementTermine(
                        $rowRemboursement['remboursementId'],
                        $rowRemboursement['demandeTravaux'],
                        $rowRemboursement['demandeType']
                    );
                    $this->deleteDocumentForRemboursementTermine(
                        $rowRemboursement['remboursementId2'],
                        $rowRemboursement['demandeTravaux'],
                        $rowRemboursement['demandeType']
                    );
                } else {
                    $this->deleteDocumentForRemboursementTermine(
                        $rowRemboursement['remboursementId'],
                        $rowRemboursement['demandeTravaux'],
                        $rowRemboursement['demandeType']
                    );
                }

            }
        }

        $this->em->clear();
    }

    private function deleteRemboursementDocumentAndUpdate(
        Remboursement_  $remboursement,
        Demande_travaux $demandeTravaux,
        int             $demandeType
    ): void
    {
        $success = true;
        $errorMessage = "";

        switch ($demandeType) {
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

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                        RECTO - VERSO CHEQUE
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateRectoVersoCheque = $this->documentService->deleteAndUpdateRectoVersoCheque(
                            $remboursement->getDemandeId(),
                            $instruction,
                            $errorMessage,
                            $success,
                            true
                        );
                        $this->logByResultArray($resultDeleteAndUpdateRectoVersoCheque, $remboursement->getDemandeId(), $remboursement->getId());

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                              FICHE TRAVAUX / FICHE DE LIAISON (EN GENERAL POUR BBC1)
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateFicheTravaux = $this->documentService->deleteAndUpdateFicheTravaux(
                            $remboursement->getDemandeId(),
                            $instruction,
                            $errorMessage,
                            $success,
                            true
                        );
                        $this->logByResultArray($resultDeleteAndUpdateFicheTravaux, $remboursement->getDemandeId(), $remboursement->getId());


                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                        FACTURES
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateTravauxFactures = $this->documentService->deleteAndUpdateTravauxFactures(
                            $remboursement->getDemandeId(),
                            $instruction,
                            $errorMessage,
                            $success,
                            true
                        );
                        $this->logByResultArray($resultDeleteAndUpdateTravauxFactures, $remboursement->getDemandeId(), $remboursement->getId());
                    }

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                   DOCUMENTS FICHE TECHNIQUE (SUPPRESSION ET MISE A JOUR)
                    ///////////////////////////////////////////////////////////////////////////////////// */

                    if (!empty($demandeTravaux->getFicheTechniqueId())) {
                        /**
                         * @var FicheTechnique $ficheTechnique
                         */
                        $ficheTechnique = $this->ficheTechniqueRepository->find($demandeTravaux->getFicheTechniqueId());
                        if (!empty($ficheTechnique)) {
                            // Suppression Document XML
                            $resultDeleteAndUpdateFicheTechniqueDemandeDocument = $this->documentService->deleteAndUpdateFicheTechniqueDemandeDocument(
                                $remboursement->getDemandeId(),
                                $ficheTechnique,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateFicheTechniqueDemandeDocument, $remboursement->getDemandeId(), $remboursement->getId());
                        }
                    }

                    if (
                        !empty($remboursement->getRemboursementTravaux())
                        && !empty($remboursement->getRemboursementTravaux()->getFicheTechnique())
                    ) {
                        $ficheTechnique = $remboursement->getRemboursementTravaux()->getFicheTechnique();
                        if (!empty($ficheTechnique)) {
                            // Suppression Document XML
                            $resultDeleteAndUpdateFicheTechniqueRemboursementDocument = $this->documentService->deleteAndUpdateFicheTechniqueRemboursementDocument(
                                $remboursement->getDemandeId(),
                                $ficheTechnique,
                                $errorMessage,
                                $success,
                                true
                            );
                            $this->logByResultArray($resultDeleteAndUpdateFicheTechniqueRemboursementDocument, $remboursement->getDemandeId(), $remboursement->getId());
                        }
                    }

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                                DOCUMENTS TRAVAUX DEVIS
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    if (!empty($demandeTravaux->getTravauxDevisId())) {
                        /**
                         * @var Demande_travaux_devis $demandeTravauxDevis
                         */
                        $demandeTravauxDevis = $this->demandeTravauxDevisRepository->find($demandeTravaux->getTravauxDevisId());

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                    DOCUMENTS TRAVAUX DEVIS > ACTE ENGAGEMENT
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateTravauxDevisActeEngagement = $this->documentService->deleteAndUpdateTravauxDevisActeEngagement(
                            $remboursement->getDemandeId(),
                            $demandeTravauxDevis,
                            $errorMessage,
                            $success,
                            true
                        );
                        $this->logByResultArray($resultDeleteAndUpdateTravauxDevisActeEngagement, $remboursement->getDemandeId(), $remboursement->getId());

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                    DOCUMENTS TRAVAUX DEVIS > AUDIT
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateTravauxDevisAudit = $this->documentService->deleteAndUpdateTravauxDevisAudit(
                            $remboursement->getDemandeId(),
                            $demandeTravauxDevis,
                            $errorMessage,
                            $success,
                            true
                        );
                        $this->logByResultArray($resultDeleteAndUpdateTravauxDevisAudit, $remboursement->getDemandeId(), $remboursement->getId());

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                 DOCUMENTS TRAVAUX DEVIS > DEVIS UPLOAD
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateTravauxDevisUpload = $this->documentService->deleteAndUpdateTravauxDevisUpload(
                            $remboursement->getDemandeId(),
                            $demandeTravauxDevis,
                            $errorMessage,
                            $success,
                            true
                        );
                        $this->logByResultArray($resultDeleteAndUpdateTravauxDevisUpload, $remboursement->getDemandeId(), $remboursement->getId());

                    }
                }
                break;
        }

        $remboursement->setIsTravauxRmbTermineDocDeleted(true);

        $this->em->persist($remboursement);
        $this->em->flush();
    }

    private function deleteDocumentForRemboursementTermine(
        int             $remboursementId,
        Demande_travaux $demandeTravaux,
        int             $demandeType
    ): void
    {
        $remboursement = $this->remboursementRepository->find($remboursementId);

        if (!in_array($remboursement->getId(), $this->listDeleteDocumentRemboursementTermine)) {
            $this->deleteRemboursementDocumentAndUpdate(
                $remboursement,
                $demandeTravaux,
                $demandeType
            );
            $this->listDeleteDocumentRemboursementTermine[] = $remboursement->getId();
        }

        if (!in_array($remboursement->getDemandeId(), $this->listHistoriqueRemboursementTermine)) {
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
                'Nettoyage documents remboursement terminé',
                null,
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId()
            );
            $this->listHistoriqueRemboursementTermine[] = $remboursement->getDemandeId();
        }
    }

    private function logByResultArray(array $result, int $demandeId, int $remboursementId): void
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

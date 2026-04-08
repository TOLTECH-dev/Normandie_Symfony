<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
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
use App\Service\RollbackDocumentService;
use App\Entity\Demande_;
use App\Entity\Demande_travaux;
use App\Entity\Demande_travaux_devis;
use App\Repository\Demande_travaux_devisRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:rollbackDeletedDocumentTravauxRemboursementTermine',
    description: 'Rollback (mise à jour BDD) liste des documents pour les demandes Travaux chèque 1 dont remboursement pas encore terminé (ou inexistant) pour le chèque 2.',
)]
class RollbackDeletedDocumentTravauxRemboursementTermineCommand extends Command
{
    private Demande_travaux_devisRepository $demandeTravauxDevisRepository;
    private FicheTechniqueRepository $ficheTechniqueRepository;
    private Remboursement_Repository $remboursementRepository;
    private EntityManagerInterface $em;
    private RollbackDocumentService $rollbackDocumentService;
    private HistoriqueService $historiqueService;
    private LoggerInterface $commandDeleteDocumentsLogger;
    private string $productionTravauxNiveauBBC1;
    private string $productionTravauxNiveauBBC2;
    private array $listRollbackDocumentRemboursementTermine = [];
    private array $listHistoriqueRemboursementTermine = [];

    public function __construct(
        Demande_travaux_devisRepository $demandeTravauxDevisRepository,
        FicheTechniqueRepository        $ficheTechniqueRepository,
        Remboursement_Repository        $remboursementRepository,
        EntityManagerInterface          $em,
        RollbackDocumentService         $rollbackDocumentService,
        HistoriqueService               $historiqueService,
        LoggerInterface                 $commandDeleteDocumentsLogger,
        string                          $productionTravauxNiveauBBC1,
        string                          $productionTravauxNiveauBBC2
    )
    {
        parent::__construct();
        $this->demandeTravauxDevisRepository = $demandeTravauxDevisRepository;
        $this->ficheTechniqueRepository = $ficheTechniqueRepository;
        $this->remboursementRepository = $remboursementRepository;
        $this->em = $em;
        $this->rollbackDocumentService = $rollbackDocumentService;
        $this->historiqueService = $historiqueService;
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
        $this->commandDeleteDocumentsLogger->info('--- RollbackDeletedDocumentTravauxRemboursementTermineCommand: Start ---', []);

        try {
            $this->runCustom();
            $this->commandDeleteDocumentsLogger->info('--- RollbackDeletedDocumentTravauxRemboursementTermineCommand: End ---', []);

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }


    /* *****************************************************************
    ********************************************************************
                            PRIVATE FUNCTION
    ********************************************************************
    *******************************************************************/

    private function runCustom(): void
    {
        /* /////////////////////////////////////////////////////////////////////////////////////
                            GET LIST REMBOURSEMENT A ROLLBACK
        ///////////////////////////////////////////////////////////////////////////////////// */
        $listRemboursementRollback = $this->remboursementRepository->findForRollbackDeleteTravauxDocumentRmbTermineProcess(
            $this->productionTravauxNiveauBBC1,
            $this->productionTravauxNiveauBBC2
        );

        if (empty($listRemboursementRollback)) {
            $this->commandDeleteDocumentsLogger->notice('Aucun document à mettre à jour', []);
        } else {
            foreach ($listRemboursementRollback as $rowRemboursement) {
                $this->rollbackDocumentForRemboursementTermine(
                    $rowRemboursement['remboursementId'],
                    $rowRemboursement['demandeTravaux'],
                    $rowRemboursement['demandeType']
                );
            }
        }

        $this->em->clear();
    }

    private function rollbackRemboursementDocumentAndUpdate(
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
                                     DOCUMENTS INSTRUCTION (MISE A JOUR)
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    $instruction = $remboursementByType->getInstruction();
                    if (!empty($instruction)) {

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                        RECTO - VERSO CHEQUE
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateRectoVersoCheque = $this->rollbackDocumentService->rollbackUpdateRectoVersoCheque(
                            $instruction,
                            $success,
                            true
                        );
                        $this->logByResultArray(
                            'Remboursement travaux instruction: Chèque recto/verso',
                            $resultDeleteAndUpdateRectoVersoCheque,
                            $remboursement->getDemandeId(),
                            $remboursement->getId()
                        );


                        /* /////////////////////////////////////////////////////////////////////////////////////
                                              FICHE TRAVAUX / FICHE DE LIAISON (EN GENERAL POUR BBC1)
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateFicheTravaux = $this->rollbackDocumentService->rollbackUpdateFicheTravaux(
                            $instruction,
                            $success,
                            true
                        );
                        $this->logByResultArray(
                            'Remboursement travaux instruction: Fiche travaux/liaison',
                            $resultDeleteAndUpdateFicheTravaux,
                            $remboursement->getDemandeId(),
                            $remboursement->getId()
                        );

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                        FACTURES
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateTravauxFactures = $this->rollbackDocumentService->rollbackUpdateTravauxFactures(
                            $instruction,
                            $success,
                            true
                        );
                        $this->logByResultArray(
                            'Remboursement travaux instruction: Factures',
                            $resultDeleteAndUpdateTravauxFactures,
                            $remboursement->getDemandeId(),
                            $remboursement->getId()
                        );
                    }

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                   DOCUMENTS FICHE TECHNIQUE (MISE A JOUR)
                    ///////////////////////////////////////////////////////////////////////////////////// */

                    if (!empty($demandeTravaux->getFicheTechniqueId())) {
                        /**
                         * @var FicheTechnique $ficheTechnique
                         */
                        $ficheTechnique = $this->ficheTechniqueRepository->find($demandeTravaux->getFicheTechniqueId());
                        if (!empty($ficheTechnique)) {
                            $resultDeleteAndUpdateFicheTechniqueDemandeDocument = $this->rollbackDocumentService->rollbackUpdateFicheTechniqueDemandeDocument(
                                $ficheTechnique,
                                $success,
                                true
                            );
                            $this->logByResultArray(
                                'Fiche technique: demande document XML',
                                $resultDeleteAndUpdateFicheTechniqueDemandeDocument,
                                $remboursement->getDemandeId(),
                                $remboursement->getId()
                            );
                        }
                    }

                    if (
                        !empty($remboursement->getRemboursementTravaux())
                        && !empty($remboursement->getRemboursementTravaux()->getFicheTechnique())
                    ) {
                        $ficheTechnique = $remboursement->getRemboursementTravaux()->getFicheTechnique();
                        if (!empty($ficheTechnique)) {
                            $resultDeleteAndUpdateFicheTechniqueRemboursementDocument = $this->rollbackDocumentService->rollbackUpdateFicheTechniqueRemboursementDocument(
                                $ficheTechnique,
                                $success,
                                true
                            );
                            $this->logByResultArray(
                                'Fiche technique: remboursement document XML',
                                $resultDeleteAndUpdateFicheTechniqueRemboursementDocument,
                                $remboursement->getDemandeId(),
                                $remboursement->getId()
                            );
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
                        $resultDeleteAndUpdateTravauxDevisActeEngagement = $this->rollbackDocumentService->rollbackUpdateTravauxDevisActeEngagement(
                            $demandeTravauxDevis,
                            $success,
                            true
                        );
                        $this->logByResultArray(
                            'Demande travaux devis: acte angagement',
                            $resultDeleteAndUpdateTravauxDevisActeEngagement,
                            $remboursement->getDemandeId(),
                            $remboursement->getId()
                        );

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                    DOCUMENTS TRAVAUX DEVIS > AUDIT
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateTravauxDevisAudit = $this->rollbackDocumentService->rollbackUpdateTravauxDevisAudit(
                            $demandeTravauxDevis,
                            $success,
                            true
                        );
                        $this->logByResultArray(
                            'Demande travaux devis: audit',
                            $resultDeleteAndUpdateTravauxDevisAudit,
                            $remboursement->getDemandeId(),
                            $remboursement->getId()
                        );

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                 DOCUMENTS TRAVAUX DEVIS > DEVIS UPLOAD
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateTravauxDevisUpload = $this->rollbackDocumentService->rollbackUpdateTravauxDevisUpload(
                            $demandeTravauxDevis,
                            $success,
                            true
                        );
                        $this->logByResultArray(
                            'Demande travaux devis: devis',
                            $resultDeleteAndUpdateTravauxDevisUpload,
                            $remboursement->getDemandeId(),
                            $remboursement->getId()
                        );
                    }
                }
                break;
        }

        $remboursement->setIsTravauxRmbTermineDocDeleted(true);

        $this->em->persist($remboursement);
        $this->em->flush();
    }

    private function rollbackDocumentForRemboursementTermine(
        int             $remboursementId,
        Demande_travaux $demandeTravaux,
        int             $demandeType
    ): void
    {
        $remboursement = $this->remboursementRepository->find($remboursementId);

        if (!in_array($remboursement->getId(), $this->listRollbackDocumentRemboursementTermine)) {
            $this->rollbackRemboursementDocumentAndUpdate(
                $remboursement,
                $demandeTravaux,
                $demandeType
            );
            $this->listRollbackDocumentRemboursementTermine[] = $remboursement->getId();
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
                'Récupération documents remboursement terminé',
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

    private function logByResultArray(
        string $mainMessage,
        array  $result,
        int    $demandeId,
        int    $remboursementId
    ): void
    {
        if (!empty($result)) {
            foreach ($result['isSuccess'] as $keySuccess => $valSuccess) {
                $errorMessage = (false === $valSuccess) ? 'Erreur Mise à jour' : '';
                $detailMessage = (true === $valSuccess) ? 'Mise à jour doc ' . $result['filename'][$keySuccess] : '';
                $contexLogger = [
                    'NumeroDossier' => $demandeId . ' / RemboursementId: ' . $remboursementId,
                    'IsSuccess' => $valSuccess,
                    'ErrorMessage' => $errorMessage,
                    'DetailMessage' => $detailMessage
                ];

                if (false === $valSuccess) {
                    $this->commandDeleteDocumentsLogger->error($mainMessage, $contexLogger);
                } else {
                    $this->commandDeleteDocumentsLogger->info($mainMessage, $contexLogger);
                }
            }
        }
    }
}

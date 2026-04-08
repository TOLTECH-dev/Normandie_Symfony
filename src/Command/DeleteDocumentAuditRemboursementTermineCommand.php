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
use App\Entity\Remboursement_;
use App\Entity\Remboursement_auditEnergie;
use App\Repository\Remboursement_Repository;
use App\Service\HistoriqueService;
use App\Service\DocumentService;
use App\Entity\Demande_;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:processDeleteDocumentAuditRemboursementTermine',
    description: 'Lancer la suppression des documents Audit pour les remboursements terminés dont la date de dernière modification remboursement date d\'au moins 1 AN',
)]
class DeleteDocumentAuditRemboursementTermineCommand extends Command
{
    private Remboursement_Repository $remboursementRepository;
    private EntityManagerInterface $EM;
    private DocumentService $documentService;
    private HistoriqueService $historiqueService;
    private LoggerInterface $commandDeleteDocumentsLogger;
    private array $listDeleteDocumentRemboursementTermine = [];
    private array $listHistoriqueRemboursementTermine = [];

    public function __construct(
        Remboursement_Repository $remboursementRepository,
        EntityManagerInterface   $EM,
        DocumentService          $documentService,
        HistoriqueService        $historiqueService,
        LoggerInterface          $commandDeleteDocumentsLogger
    )
    {
        parent::__construct();
        $this->remboursementRepository = $remboursementRepository;
        $this->EM = $EM;
        $this->documentService = $documentService;
        $this->historiqueService = $historiqueService;
        $this->commandDeleteDocumentsLogger = $commandDeleteDocumentsLogger;
    }



    /* *****************************************************************
    ********************************************************************
                            PROTECTED FUNCTION
    ********************************************************************
    *******************************************************************/

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandDeleteDocumentsLogger->info('--- DeleteDocumentAuditRemboursementTermineCommand: Start ---', []);
        try {
            $this->runCustom($output);
            $this->commandDeleteDocumentsLogger->info('--- DeleteDocumentAuditRemboursementTermineCommand: End ---', []);
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
    private function runCustom(OutputInterface $output): void
    {
        /* /////////////////////////////////////////////////////////////////////////////////////
                GET LIST REMBOURSEMENT
        ///////////////////////////////////////////////////////////////////////////////////// */
        $listRemboursementTermine = $this->remboursementRepository->findForDeleteAuditDocumentRmbTermineProcess();

        if (empty($listRemboursementTermine)) {
            $this->commandDeleteDocumentsLogger->notice('Aucun document à supprimer', []);
        } else {
            foreach ($listRemboursementTermine as $rowRemboursement) {
                $this->deleteDocumentForRemboursementTermine(
                    $rowRemboursement['remboursementId'],
                    $rowRemboursement['demandeType']
                );
            }
        }

        $this->EM->clear();
    }

    private function deleteRemboursementDocumentAndUpdate(Remboursement_ $remboursement, int $demandeType): void
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
                    Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
                ])) ? $remboursement->getRemboursementAuditEnergie() : $remboursement->getRemboursementAuditNumerique();

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
                                                        FACTURE
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $resultDeleteAndUpdateFacture = $this->documentService->deleteAndUpdateFacture(
                            $remboursement->getDemandeId(),
                            $instruction,
                            $errorMessage,
                            $success,
                            true
                        );
                        $this->logByResultArray($resultDeleteAndUpdateFacture, $remboursement->getDemandeId(), $remboursement->getId());
                    }

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                     DOCUMENTS DEPOT AUDIT (SUPPRESSION ET MISE A JOUR)
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    $depot = $remboursementByType->getDepot();
                    if (!empty($depot)) {
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
                break;
        }

        $remboursement->setIsAuditRmbTermineDocDeleted(true);

        $this->EM->persist($remboursement);
        $this->EM->flush();
    }

    private function deleteDocumentForRemboursementTermine(int $remboursementId, int $demandeType): void
    {
        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $this->remboursementRepository->find($remboursementId);

        if (!in_array($remboursement->getId(), $this->listDeleteDocumentRemboursementTermine)) {
            $this->deleteRemboursementDocumentAndUpdate(
                $remboursement,
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
                    'IsSuccess'     => $valSuccess,
                    'ErrorMessage'  => $errorMessage,
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

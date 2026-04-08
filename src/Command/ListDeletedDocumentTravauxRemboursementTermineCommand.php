<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Demande_;
use App\Entity\Demande_travaux;
use App\Entity\Demande_travaux_devis;
use App\Entity\FicheTechnique;
use App\Entity\Remboursement_;
use App\Entity\Remboursement_travaux;
use App\Repository\Demande_travaux_devisRepository;
use App\Repository\FicheTechniqueRepository;
use App\Repository\Remboursement_Repository;
use App\Service\RollbackDocumentService;
use App\Utils\DefaultUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListDeletedDocumentTravauxRemboursementTermineCommand extends Command
{
    private Demande_travaux_devisRepository $demandeTravauxDevisRepository;
    private FicheTechniqueRepository $ficheTechniqueRepository;
    private Remboursement_Repository $remboursementRepository;
    private RollbackDocumentService $rollbackDocumentService;
    private string $appRootDossierDataSymfony;
    private string $productionTravauxNiveauBBC1;
    private string $productionTravauxNiveauBBC2;

    private array $listRollbackDocumentRemboursement;
    private array $listRollbackDocumentRemboursementFilesPath;

    public static string $exportCsvDirectory = 'rollback_documents_remboursement_termine';



    public function __construct(
        Demande_travaux_devisRepository $demandeTravauxDevisRepository,
        FicheTechniqueRepository $ficheTechniqueRepository,
        Remboursement_Repository $remboursementRepository,
        RollbackDocumentService $rollbackDocumentService,
        string $appRootDossierDataSymfony,
        string $productionTravauxNiveauBBC1,
        string $productionTravauxNiveauBBC2
    ) {
        parent::__construct();
        $this->demandeTravauxDevisRepository = $demandeTravauxDevisRepository;
        $this->ficheTechniqueRepository = $ficheTechniqueRepository;
        $this->remboursementRepository = $remboursementRepository;
        $this->rollbackDocumentService = $rollbackDocumentService;
        $this->appRootDossierDataSymfony = $appRootDossierDataSymfony;
        $this->productionTravauxNiveauBBC1 = $productionTravauxNiveauBBC1;
        $this->productionTravauxNiveauBBC2 = $productionTravauxNiveauBBC2;
    }



    /* *****************************************************************
    ********************************************************************
                            PROTECTED FUNCTION
    ********************************************************************
    *******************************************************************/

    protected function configure(): void
    {
        $this
            ->setName('normandie:listDeletedDocumentTravauxRemboursementTermine')
            ->setDescription('Génération fichier contant la liste des documents à restaurer pour les demandes Travaux chèque 1 dont remboursement pas encore terminé (ou inexistant) pour le chèque 2.')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->listRollbackDocumentRemboursement = [];
        $this->listRollbackDocumentRemboursementFilesPath = [];

        $output->writeln('--- RollbackGenerateListDeleteDocumentTravauxCommand: Start ---');
        $this->runCustom($output);
        $output->writeln('--- RollbackGenerateListDeleteDocumentTravauxCommand: End ---');

        return Command::SUCCESS;
    }


    /* *****************************************************************
    ********************************************************************
                            PRIVATE FUNCTION
    ********************************************************************
    *******************************************************************/

    /**
     * @param OutputInterface $output
     * @return void
     */
    private function runCustom(OutputInterface $output): void
    {
        /* /////////////////////////////////////////////////////////////////////////////////////
                            GET LIST REMBOURSEMENT A ROLLBACK
        ///////////////////////////////////////////////////////////////////////////////////// */
        $listRemboursementRollback = $this->remboursementRepository->findForRollbackDeleteTravauxDocumentRmbTermineProcess(
            $this->productionTravauxNiveauBBC1,
            $this->productionTravauxNiveauBBC2
        );

        if (empty($listRemboursementRollback)) {
            $output->writeln('Aucune demande à traiter');
        } else {

            foreach ($listRemboursementRollback as $rowRemboursement) {
                $remboursement = $this->remboursementRepository->find($rowRemboursement['remboursementId']);

                if (!in_array($remboursement->getId(), $this->listRollbackDocumentRemboursement)) {
                    $this->setListRollbackDocumentRemboursementFilesPath(
                        $remboursement,
                        $rowRemboursement['demandeTravaux'],
                        (string)$rowRemboursement['demandeType']
                    );
                    $this->listRollbackDocumentRemboursement[] = $remboursement->getId();
                }
            }

            if (!empty($this->listRollbackDocumentRemboursementFilesPath) && !empty($this->appRootDossierDataSymfony)) {
                $pathExportCsvDirectory = $this->appRootDossierDataSymfony . self::$exportCsvDirectory;
                if (!is_dir($pathExportCsvDirectory)) {
                    try {
                        DefaultUtils::createDirectory($pathExportCsvDirectory, 0775, true);
                    } catch (\Exception $e) {
                        $output->writeln('Erreur lors de la création du dossier ' . self::$exportCsvDirectory . ' : ' . $e->getMessage());
                    }
                }

                $delimiter = ';';
                $fileCsvRessource = fopen($pathExportCsvDirectory . '/document_list.' . DefaultUtils::FILE_CODE_CSV, 'w+');
                fprintf($fileCsvRessource, chr(0xEF) . chr(0xBB) . chr(0xBF));
                foreach ($this->listRollbackDocumentRemboursementFilesPath as $path) {
                    fputcsv($fileCsvRessource, [$path], $delimiter);
                }
                fclose($fileCsvRessource);
                $output->writeln('Ecriture terminée');
            }
        }
    }

    private function setListRollbackDocumentRemboursementFilesPath(
        Remboursement_ $remboursement,
        Demande_travaux $demandeTravaux,
        string $demandeType
    ): void {
        switch ($demandeType) {
            case Demande_::DEMANDE_TRAVAUX_TYPE:
                /**
                 * @var Remboursement_travaux $remboursementByType
                 */
                $remboursementByType = $remboursement->getRemboursementTravaux();
                if (!empty($remboursementByType)) {

                    /* /////////////////////////////////////////////////////////////////////////////////////
                                            DOCUMENTS INSTRUCTION
                    ///////////////////////////////////////////////////////////////////////////////////// */
                    $instruction = $remboursementByType->getInstruction();
                    if (!empty($instruction)) {

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                        RECTO - VERSO CHEQUE
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $this->rollbackDocumentService->setListRollbackRectoVersoCheque(
                            $instruction,
                            $this->listRollbackDocumentRemboursementFilesPath
                        );

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                              FICHE TRAVAUX / FICHE DE LIAISON (EN GENERAL POUR BBC1)
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $this->rollbackDocumentService->setListRollbackFicheTravaux(
                            $instruction,
                            $this->listRollbackDocumentRemboursementFilesPath
                        );

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                        FACTURES
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $this->rollbackDocumentService->setListRollbackTravauxFactures(
                            $instruction,
                            $this->listRollbackDocumentRemboursementFilesPath
                        );
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
                            // Document XML
                            $this->rollbackDocumentService->setListRollbackFicheTechniqueDemandeDocument(
                                $ficheTechnique,
                                $this->listRollbackDocumentRemboursementFilesPath
                            );
                        }
                    }

                    if (
                        !empty($remboursement->getRemboursementTravaux())
                        && !empty($remboursement->getRemboursementTravaux()->getFicheTechnique())
                    ) {
                        $ficheTechnique = $remboursement->getRemboursementTravaux()->getFicheTechnique();
                        if (!empty($ficheTechnique)) {
                            // Document XML
                            $this->rollbackDocumentService->setListRollbackFicheTechniqueRemboursementDocument(
                                $ficheTechnique,
                                $this->listRollbackDocumentRemboursementFilesPath
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
                        $this->rollbackDocumentService->setListRollbackTravauxDevisActeEngagement(
                            $demandeTravauxDevis,
                            $this->listRollbackDocumentRemboursementFilesPath
                        );

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                    DOCUMENTS TRAVAUX DEVIS > AUDIT
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $this->rollbackDocumentService->setListRollbackTravauxDevisAudit(
                            $demandeTravauxDevis,
                            $this->listRollbackDocumentRemboursementFilesPath
                        );

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                 DOCUMENTS TRAVAUX DEVIS > DEVIS UPLOAD
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        $this->rollbackDocumentService->setListRollbackTravauxDevisUpload(
                            $demandeTravauxDevis,
                            $this->listRollbackDocumentRemboursementFilesPath
                        );
                    }
                }
                break;
        }
    }
}

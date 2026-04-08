<?php

namespace App\Service;

use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux_devis;
use App\Entity\Titre;
use App\Repository\TitreRepository;
use App\Utils\DefaultServiceUtils;
use setasign\Fpdi\Fpdi;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class TitreService
{
    final public const MONTANT_DEUX_CENT_AVEC_VIRGULE   = '200,00';
    final public const MONTANT_QUATRE_CENT_AVEC_VIRGULE = '400,00';
    final public const MONTANT_CINQ_CENT_AVEC_VIRGULE   = '500,00';
    final public const MONTANT_HUIT_CENT_AVEC_VIRGULE   = '800,00';
    final public const MONTANT_SIX_CENT_AVEC_VIRGULE    = '600,00';

    final public const MONTANT_HUIT_MILLE           = 8000;
    final public const MONTANT_NEUF_MILLE_DEUX_CENT = 9200;

    final public const FILE_CODE_TXT = 'txt';

    public function __construct(
        private readonly TitreRepository $titreRepository,
        private readonly ValidatorTitreService $validator,
        private readonly MailerService $mailerService,
        private readonly Environment $environment,
        private readonly ParameterBagInterface  $parameterBag,
        private readonly string $appDateUsNouvelInstructeur,
        private readonly string $appAs400FolderIn,
        private readonly string $appDatePassageMontantTravauxNiveau3Bbc,
        private readonly string $appRootDossierDataSymfony,
        private readonly string $projectRoot
    ) {}




    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $path
     * @param $listOPE
     * @param $listDemande
     * @param $listProduction
     * @return array
     * @throws \Exception
     */
    public function validateFile($path, $listOPE, $listDemande, $listProduction): array
    {
        // Retrieve files
        $listFile = array();
        if (is_dir($path)) {
            if ($handle = opendir($path)) {
                while (($file = readdir($handle)) !== false) {
                    $pathFile = $path . '/' . $file;
                    if ($file != "." && $file != ".." && "dir" != filetype($pathFile)) {
                        $pathInfo = pathinfo($pathFile);
                        foreach ($listOPE as $numOPE) {
                            if (preg_match(Titre::PATTERN_FILE_AS400 . $numOPE . "#", $pathInfo['filename'])) {
                                $listFile[] = $pathInfo;
                            }
                        }
                    }
                }
                closedir($handle);
            }
        }

        // Validate data by file
        return $this->validator->validate($listFile, $listDemande, $listProduction);
    }

    /**
     * @param $pathInfo
     * @return array
     * @throws \Exception
     */
    public function persistFile($pathInfo): array
    {
        if (0 == strcasecmp($pathInfo['extension'], self::FILE_CODE_TXT)) {
            $return = $this->persistFileTXT($pathInfo);
        } else {
            throw new \Exception('Erreur interne.');
        }

        // Create report content
        $reportContent = "Nom du fichier du retour de Production : " . $return['filename'] . "\r\n";
        $return['reportContent'] = $reportContent;
        unset($return['filename']);

        return $return;
    }

    /**
     * @param $data
     */
    public function createAttestationNonReception($data): void
    {
        $pdf = new Fpdi();
        $file = $this->projectRoot . '/resources/Template/attestation_non_reception.pdf';
        if (file_exists($file)) {
            $pdf->setSourceFile($file);
        }
        $pdf->AddPage();
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, null, null, null, null, true);
        $pdf->SetLeftMargin(0);
        $pdf->SetRightMargin(0);
        $pdf->SetFont('Helvetica', $style = '', 10);
        $pdf->setTextColor(0, 0, 0);

        $this->writeAttestationNonReception($data, $pdf, $this->appDateUsNouvelInstructeur);
        $pdf->Output();
    }

    /**
     * @param $arrayOPE
     * @throws \Exception
     */
    public function getFilesRetourProductionAS400($arrayOPE): void
    {
        $remoteDir = $this->appAs400FolderIn;
        $localDir = $this->appRootDossierDataSymfony . Titre::$filenameRetourProduction;

        if (!is_dir($localDir)) {
            DefaultServiceUtils::createDirectory($localDir, 0775, true);
        }

        if ($arrayOPE) {
            DefaultServiceUtils::getFilesByPattern($remoteDir, $localDir, $arrayOPE);
        }
    }

    /**
     * @param Demande_|null $demandeAuditE
     * @return int
     */
    public function getMontantTravauxNiveau3BBC(?Demande_ $demandeAuditE = null): int
    {
        if (!empty($demandeAuditE) && $demandeAuditE->getStatutId() != Demande_statut::STATUS_15) {
            /**
             * @var Titre $titre
             */
            $titre = $this->titreRepository->findOneByDemandeId($demandeAuditE->getId());
            if (!empty($titre)) {
                if ((int)$titre->getValeurTitre() == 500 || $titre->getValeurTitre() == '500.00') {
                    return self::MONTANT_HUIT_MILLE;
                }
            }
        }

        $dateDuJour = (new \DateTime('now'))->format('d-m-Y');
        $datePassage = $this->appDatePassageMontantTravauxNiveau3Bbc;
        $timeDateDuJour = strtotime($dateDuJour);
        $timeDatePassage = strtotime($datePassage);

        if ($timeDateDuJour > $timeDatePassage) {
            return self::MONTANT_HUIT_MILLE;
        }

        return self::MONTANT_NEUF_MILLE_DEUX_CENT;
    }

    /**
     * @return array
     */
    public function getDataForListAjax(int $productionTravauxNiveauBBC1, int $productionTravauxNiveauBBC2, array $postData): array
    {
        $arrayColumnWhere = [];

        if (!empty($postData)) {
            /* START of POST variables coming from datatable */
            $draw = $postData["draw"] ?? 0; //Counter used by DataTables to ensure that the Ajax returns from server-side processing requests are drawn in sequence
            $orderBy = null;
            $orderType = null;
            if (!empty($postData['order'][0]['column'])) {
                $orderByColumnIndex = $postData['order'][0]['column']; //Index of the sorting column (0 index based)
                $orderBy = $postData['columns'][$orderByColumnIndex]['data']; //Get name of the sorting column from its index
                $orderType = $postData['order'][0]['dir']; //ASC or DESC
            }

            $start  = $postData["start"] ?? 0; //Paging first record indicator
            $length = $postData['length'] ?? 0; //Number of records that the table can display in the current draw
            /* END of POST variables */


            /* START INIT of column search */
            $columnWhereTmp = [];
            for ($i = 0; $i < count($postData['columns']); $i++) {
                if ('' != ($postData['columns'][$i]['search']['value'] ?? '')) {
                    $columnWhereTmp[] = $postData['columns'][$i]['search']['value'];
                }
            }
            /* END INIT of column search */


            /* START of search */
            if (!empty($columnWhereTmp)) {

                for ($i = 0; $i < count($postData['columns']); $i++) {
                    if ('' != ($postData['columns'][$i]['search']['value'] ?? '')) {
                        $columnSearch = $postData['columns'][$i]['data'];
                        $arrayWhere = [];

                        switch ($columnSearch) {
                            case 'demandeType':
                                $arrayDemandeType = [];
                                $searchValue = $postData['columns'][$i]['search']['value'];
                                $columnDemandeType = 'd.type';
                                $columnDemandeNiveauSubstring = 'SUBSTRING(dtd.niveau, 1, 1)';
                                if (isset($searchValue)) {
                                    switch ($searchValue) {
                                        case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE;
                                            $arrayDemandeType[] = "dtd.niveau = NULL";
                                            break;
                                        case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE;
                                            $arrayDemandeType[] = "dtd.niveau = NULL";
                                            break;
                                        case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE;
                                            $arrayDemandeType[] = "dtd.niveau = NULL";
                                            break;
                                        case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE;
                                            $arrayDemandeType[] = "dtd.niveau = NULL";
                                            break;
                                        case Demande_::DEMANDE_TRAVAUX_TYPE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = "dtd.niveau = NULL";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 0";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 1";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 2";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 3";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 3";
                                            $arrayDemandeType[] = "t.numeroOperation = " . $productionTravauxNiveauBBC1;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 3";
                                            $arrayDemandeType[] = "t.numeroOperation = " . $productionTravauxNiveauBBC2;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 4";
                                            $arrayDemandeType[] = "t.numeroOperation = " . $productionTravauxNiveauBBC1;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 4";
                                            $arrayDemandeType[] = "t.numeroOperation = " . $productionTravauxNiveauBBC2;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 6";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 7";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 8";
                                            $arrayDemandeType[] = "t.numeroOperation = " . $productionTravauxNiveauBBC1;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 8";
                                            $arrayDemandeType[] = "t.numeroOperation = " . $productionTravauxNiveauBBC2;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 9";
                                            $arrayDemandeType[] = "t.numeroOperation = " . $productionTravauxNiveauBBC1;
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE:
                                            $arrayDemandeType[] = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $arrayDemandeType[] = $columnDemandeNiveauSubstring . " = 9";
                                            $arrayDemandeType[] = "t.numeroOperation = " . $productionTravauxNiveauBBC2;
                                            break;
                                        default:
                                            break;
                                    }
                                }
                                if (!empty($arrayDemandeType)) {
                                    $arrayWhere[] = $arrayDemandeType;
                                }
                                break;
                            case 'beneficiaire':
                                $arrayBeneficiaire = [];
                                $arrayBeneficiaire[] = "b.nom LIKE %" . $postData['columns'][$i]['search']['value'] . "%";
                                $arrayBeneficiaire[] = "b.prenom LIKE %" . $postData['columns'][$i]['search']['value'] . "%";
                                $arrayWhere = $arrayBeneficiaire;
                                break;
                            case 'demandeId':
                                $arrayWhere[] = "t.demandeId LIKE %" . $postData['columns'][$i]['search']['value'] . "%";
                                break;
                            case 'productionId':
                                $arrayWhere[] = "t.productionId LIKE %" . $postData['columns'][$i]['search']['value'] . "%";
                                break;
                            case 'numeroCheque':
                                $arrayWhere[] = "t.numeroCheque LIKE %" . $postData['columns'][$i]['search']['value'] . "%";
                                break;
                            case 'valeurTitre':
                                $arrayWhere[] = "t.valeurTitre LIKE %" . DefaultServiceUtils::formatSearchForMontant($postData['columns'][$i]['search']['value']) . "%";
                                break;
                            case 'dateEmission':
                                $dateEmission = str_replace('\\', '', $postData['columns'][$i]['search']['value']);
                                $arrayWhere[] = "DATE_FORMAT(t.dateEmission, '%d/%m/%Y') LIKE %" . $dateEmission . "%";
                                break;
                            case 'dateValidite':
                                $dateValidite = str_replace('\\', '', $postData['columns'][$i]['search']['value']);
                                $arrayWhere[] = "DATE_FORMAT(t.dateValidite, '%d/%m/%Y') LIKE %" . $dateValidite . "%";
                                break;
                        }
                        $arrayColumnWhere[$columnSearch] = $arrayWhere;
                    }
                }
            }

            return [
                'draw'             => $draw,
                'orderBy'          => $orderBy,
                'orderType'        => $orderType,
                'start'            => $start,
                'length'           => $length,
                'columnWhereTmp'   => $columnWhereTmp,
                'arrayColumnWhere' => $arrayColumnWhere
            ];
        }

        return [];
    }

    /**
     * @throws \Exception
     */
    public function generateReport(Titre $object, array $reportData, array $emailData, array $option = []): void
    {
        $reportKey = $reportData['reportKey'];
        if (0 === $reportKey) {
            $targetDir = '/error';
            $targetFile = 'error';
        } elseif (1 === $reportKey) {
            $targetDir = '/success';
            $targetFile = 'success';
        } else {
            throw new \Exception('Erreur interne.');
        }
        if (array_key_exists('fluxDir', $option)) $fluxDir = $option['fluxDir'];
        else $fluxDir = $object::$filenameRetourProduction;

        $fluxPath = $this->appRootDossierDataSymfony . $fluxDir;

        DefaultServiceUtils::createDirectory($fluxPath . $targetDir, 0775, true);

        $reportName = $reportData['filename'];
        $reportPath =
            $fluxPath
            . $targetDir
            . "/"
            . $reportName
            . "_"
            . $targetFile
            . "."
            . self::FILE_CODE_TXT;
        if (file_exists($reportPath)) unlink($reportPath);

        $errorHandle = fopen($reportPath, "a+");
        fwrite($errorHandle, $reportData['content']);
        fclose($errorHandle);

        $this->mailerService->sendGeneriqueEmail(
            $emailData['subject'],
            $this->environment->render($emailData['templatePath']),
            $this->parameterBag->get('mailer_address_from'),
            $emailData['emailTo'],
            null,
            'text/html',
            'UTF-8',
            $emailData['listEmailBcc'],
            null,
            $emailData['listEmailCc'],
            null,
            $reportPath
        );
    }


  /* *****************************************************************
  ********************************************************************
                  P R I V A T E   F U N C T I O N
  ********************************************************************
  *******************************************************************/

    /**
     * @param $pathInfo
     * @return array
     */
    private function persistFileTXT(array $pathInfo): array
    {
        $listTitre = array();

        $pathFile = $pathInfo['dirname'] . '/' . $pathInfo['basename'];
        foreach (file($pathFile) as $row) {
            $numeroCheque = (int)substr($row, 38, 9);
            $listTitre[$numeroCheque] = array(
                'numeroOperation'           => (int)substr($row, 0, 5),
                'demandeId'                 => (int)substr($row, 5, 12),
                'productionId'              => (int)substr($row, 17, 12),
                'numeroChequier'            => (int)substr($row, 29, 9),
                'numeroCheque'              => $numeroCheque,
                'typeCheque'                => (int)substr($row, 47, 3),
                'valeurTitre'               => (substr($row, 50, 7)) / 100,
                'dateFormatEmissionTitre'   => \DateTime::createFromFormat('d.m.Y', substr($row, 57, 10)),
                'dateFormatValiditeTitre'   => \DateTime::createFromFormat('d.m.Y', substr($row, 67, 10)),
                'codeEtatTitre'             => (int)substr($row, 77, 3)
            );
        }

        // Move import file to historique folder
        $historiqueFile = $pathInfo['dirname'] . '/historique/' . $pathInfo['basename'];
        rename($pathFile, $historiqueFile);

        $return = array(
            'listTitre' => $listTitre,
            'filename'  => $pathInfo['basename']
        );

        return $return;
    }

    /**
     * @param array $data
     * @param Fpdi $pdf
     * @param string|null $dateUsNouvelInstructeur
     */
    private function writeAttestationNonReception(array $data, Fpdi $pdf, ?string $dateUsNouvelInstructeur = null): void
    {
        $positionXBeginLeftColumn = 14;
        $positionXBeginRightColumn = 117;
        $hauteurReference = 5;
        $largeurMaxCellColGauche = 102;

        $civiliteIndex = explode(' | ', $data['beneficiaireCivilite'])[0];
        $civilite = '';
        $soussigne = 'soussigné';
        if ($civiliteIndex == 0) {
            $civilite = 'Mme';
            $soussigne .= 'e';
        } elseif ($civiliteIndex == 1) {
            $civilite = 'M.';
        }
        /* /////////////////////////////////////////////////////////////////
                                JE SOUSSIGNE
        ///////////////////////////////////////////////////////////////// */
        $pdf->SetXY($positionXBeginLeftColumn, 63);
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Je ' . $soussigne . ','));

        /* /////////////////////////////////////////////////////////////////
                                BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $pdf->Ln();
        $pdf->Ln();
        $pdf->SetX($positionXBeginLeftColumn);
        $beneficiaire = iconv('UTF-8', 'windows-1252', $civilite)
            . ' ' . iconv('UTF-8', 'windows-1252', $data['beneficiairePrenom'])
            . ' ' . strtoupper(iconv('UTF-8', 'windows-1252', $data['beneficiaireNom']));
        $pdf->MultiCell(0, $hauteurReference, $beneficiaire);

        /* /////////////////////////////////////////////////////////////////
                            ADRESSE BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $pdf->Ln();
        $pdf->SetX($positionXBeginLeftColumn);
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Demeurant à '));

        $complementNumeroRueValue = ($data['beneficiaireComplementNumeroRue']) ? explode(' | ', $data['beneficiaireComplementNumeroRue'])[1] : '';
        $complementNumeroRue = ($complementNumeroRueValue) ? ' ' . strtoupper($complementNumeroRueValue) : '';
        $beneficiaireAdresse = iconv('UTF-8', 'windows-1252', $data['beneficiaireNumeroRue'])
            . $complementNumeroRue
            .  ' ' . iconv('UTF-8', 'windows-1252', $data['beneficiaireNomRue']);
        $pdf->SetX($positionXBeginLeftColumn + 23);
        $pdf->MultiCell(0, $hauteurReference, $beneficiaireAdresse);

        $pdf->SetX($positionXBeginLeftColumn + 23);
        $pdf->MultiCell(0, $hauteurReference, iconv('UTF-8', 'windows-1252', $data['beneficiaireCodePostal'] . ' ' . $data['beneficiaireVille']));

        /* /////////////////////////////////////////////////////////////////
                            INSCRIPTION EXTRANET
        ///////////////////////////////////////////////////////////////// */
        $pdf->Ln();
        $pdf->SetX($positionXBeginLeftColumn);
        $pdf->MultiCell(0, $hauteurReference, iconv('UTF-8', 'windows-1252', 'Inscrit sur l\'extranet sous le numéro ' . $data['demandeId']));

        /* /////////////////////////////////////////////////////////////////
                ATTESTATION NON RECEPTION CHEQUE ECO-ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $pdf->Ln();
        $pdf->Ln();
        $pdf->SetX($positionXBeginLeftColumn);
        $pdf->MultiCell($largeurMaxCellColGauche, $hauteurReference, iconv('UTF-8', 'windows-1252', 'atteste sur l\'honneur ne pas avoir reçu le chèque éco-énergie Normandie pour un :'), 0, 'L');

        $demandeTypeLabelArray = [
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE                                           => Demande_::$demandeType[Demande_::DEMANDE_AUDIT_ENERGIE_TYPE],
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE                                    => Demande_::$demandeType[Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE],
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE                                         => Demande_::$demandeType[Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE],
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE                               => Demande_::$demandeType[Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE],
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE                           => 'Travaux Niveau 1',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE                           => 'Travaux Niveau 2',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE                => 'Travaux Niveau 2 - Rénovateur BBC',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE         => 'Travaux Niveau 3 - Rénovation BBC (1/2)',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE         => 'Travaux Niveau 3 - Rénovation BBC (2/2)',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE          => 'Travaux niveau 3 - Biosourcé (1/2)',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE          => 'Travaux niveau 3 - Biosourcé (2/2)',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE             => 'Travaux - Sortie de passoire',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE              => 'Travaux - Première étape BBC avec RGE',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE  => 'Travaux - Première étape BBC avec Rénovateur (1/2)',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE  => 'Travaux - Première étape BBC avec Rénovateur (2/2)',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE => 'Travaux - Rénovation globale BBC (1/2)',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE => 'Travaux - Rénovation globale BBC (2/2)',
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_AUTRE_CODE                       => 'Autre'
        ];

        /* /////////////////////////////////////////////////////////////////
                    TYPE DEMANDE ET MONTANT CHEQUE ECO-ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $pdf->SetX($positionXBeginLeftColumn);
        $pdf->setFont('', 'BI');
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', $demandeTypeLabelArray[$data['labelDemandeType']] . ','));

        $pdf->Ln();
        $montantLine = iconv('UTF-8', 'windows-1252', 'd\'un montant de ')
            . number_format($data['valeurTitre'], 0, '', ' ')
            . ' euros';
        $pdf->SetX($positionXBeginLeftColumn);
        $pdf->setFont('', 'BI');
        $pdf->MultiCell($largeurMaxCellColGauche, $hauteurReference, $montantLine, 0, 'L');

        /* /////////////////////////////////////////////////////////////////
                            NUMERO DE TITRE
        ///////////////////////////////////////////////////////////////// */
        $pdf->SetX($positionXBeginLeftColumn);
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', '(titre n') . chr(176) . ' ' . iconv('UTF-8', 'windows-1252', $data['numeroCheque']) . ')');
        $pdf->setFont('', '');

        $pdf->Ln();
        $pdf->Ln();
        $pdf->Ln();
        $pdf->Ln();

        /* /////////////////////////////////////////////////////////////////
                            BLOC ATTESTE AUDIT REALISE
        ///////////////////////////////////////////////////////////////// */
        if (in_array($data['labelDemandeType'], [
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE
        ])) {
            $pdf->SetX($positionXBeginLeftColumn);
            $pdf->MultiCell($largeurMaxCellColGauche, $hauteurReference, iconv('UTF-8', 'windows-1252', 'atteste que l\'audit a été réalisé et demande que le versement du montant de l\'aide soit fait au bénéfice de l\'entreprise suivante :'), 0, 'L');

            $pdf->Ln();
            $pdf->SetX($positionXBeginLeftColumn);
            $auditeurRS = (in_array($data['labelDemandeType'], [Demande_::DEMANDE_AUDIT_ENERGIE_TYPE, Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE])) ? $data['auditeurNomAuditE'] : $data['auditeurNomAuditN'];
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', $auditeurRS));

            $this->writeACompleterEntrepriseBlock($pdf, $hauteurReference, $positionXBeginRightColumn);
        }

        $this->writeSignatureBlock($pdf, $hauteurReference, $positionXBeginLeftColumn);

        if (Demande_::DEMANDE_TRAVAUX_TYPE == $data['typeAide']) {
            $isAdresseUp = ($data['dateEmission'] < $dateUsNouvelInstructeur);
            $this->writeAdressePaiementBlock($pdf, $hauteurReference, $positionXBeginRightColumn, $isAdresseUp);

            $this->writeAccompagneBlock($pdf, $hauteurReference, $positionXBeginRightColumn, $data);
        }
    }

    /**
     * @param Fpdi $pdf
     * @param int $hauteurReference
     * @param int $positionXBeginRightColumn
     */
    private function writeACompleterEntrepriseBlock(Fpdi $pdf, int $hauteurReference, int $positionXBeginRightColumn): void
    {
        $pdf->SetXY($positionXBeginRightColumn, 63);
        $pdf->setFont('', 'B');
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Cadre à compléter par l\'entreprise'));
        $pdf->setFont('', '');

        $pdf->SetX($positionXBeginRightColumn);
        $lineY = (63 + (3 * $hauteurReference));
        $pdf->Line($positionXBeginRightColumn, $lineY, ($positionXBeginRightColumn + 65), $lineY);

        $pdf->SetXY($positionXBeginRightColumn, $lineY + ($hauteurReference));
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Bon pour acceptation du mandat'));

        $pdf->Ln();
        $pdf->Ln();
        $pdf->SetX($positionXBeginRightColumn);
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Cachet, date et signature :'));
    }

    /**
     * @param Fpdi $pdf
     * @param int $hauteurReference
     * @param int $positionXBeginLeftColumn
     */
    private function writeSignatureBlock(Fpdi $pdf, int $hauteurReference, int $positionXBeginLeftColumn): void
    {
        $pdf->SetXY($positionXBeginLeftColumn, 185);
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Date'));

        $pdf->SetXY(($positionXBeginLeftColumn + 50), 185);
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Signature'));

        $pdf->Ln();
        $pdf->Ln();
        $pdf->Ln();
        $pdf->Ln();
        $pdf->Ln();
        $pdf->SetX($positionXBeginLeftColumn);
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Faire suivre de la mention : '));

        $pdf->setFont('', 'I');
        $pdf->SetX($positionXBeginLeftColumn + 44);
        $luEtApprouve = chr(171) . iconv('UTF-8', 'windows-1252', ' Lu et approuvé ') . chr(187);
        $pdf->write($hauteurReference, $luEtApprouve);
        $pdf->setFont('', '');
    }

    /**
     * @param Fpdi $pdf
     * @param int $hauteurReference
     * @param int $positionXBeginRightColumn
     * @param bool $isAdresseUp
     */
    private function writeAdressePaiementBlock(Fpdi $pdf, int $hauteurReference, int $positionXBeginRightColumn, bool $isAdresseUp = true): void
    {
        $pdf->SetXY($positionXBeginRightColumn, 165);
        $pdf->setFont('', 'U');
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'A adresser pour paiement'));
        $pdf->setFont('', '');

        $pdf->Ln();
        $pdf->Ln();
        $pdf->SetX($positionXBeginRightColumn);

        if ($isAdresseUp) {
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'CT LIRE COLL'));
            $pdf->Ln();
            $pdf->SetX($positionXBeginRightColumn);
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Chèque éco-énergie Normandie'));
            $pdf->Ln();
            $pdf->SetX($positionXBeginRightColumn);
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'CS 80078'));
            $pdf->Ln();
            $pdf->SetX($positionXBeginRightColumn);
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', '51203 EPERNAY Cedex'));
        } else {
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'DOCAPOSTE'));
            $pdf->Ln();
            $pdf->SetX($positionXBeginRightColumn);
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Chèque Éco-Énergie Normandie'));
            $pdf->Ln();
            $pdf->SetX($positionXBeginRightColumn);
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', '2 Avenue Sébastopol'));
            $pdf->Ln();
            $pdf->SetX($positionXBeginRightColumn);
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'BP 65052'));
            $pdf->Ln();
            $pdf->SetX($positionXBeginRightColumn);
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', '57072 Metz Cedex 3'));
        }
    }

    /**
     * @param Fpdi $pdf
     * @param int $hauteurReference
     * @param int $positionXBeginRightColumn
     * @param array $data
     */
    private function writeAccompagneBlock(Fpdi $pdf, int $hauteurReference, int $positionXBeginRightColumn, array $data): void
    {
        $pdf->SetXY($positionXBeginRightColumn, 205);
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', 'Accompagnée :'));

        $pdf->Ln();
        $pdf->SetX($positionXBeginRightColumn + 2);

        if ('travaux_niveau_3_1_BBC' == $data['labelDemandeType'] or 'travaux_niveau_3_1_biosource' == $data['labelDemandeType']) {
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', '- De la fiche de liaison (remise par votre conseiller)'));
            $pdf->Ln();
        } else {
            $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', '- De vos factures acquittées'));
            $pdf->Ln();
        }
        $pdf->SetX($positionXBeginRightColumn + 2);
        $pdf->write($hauteurReference, iconv('UTF-8', 'windows-1252', '- D\'un RIB'));
    }
}

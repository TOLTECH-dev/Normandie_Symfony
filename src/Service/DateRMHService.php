<?php

namespace App\Service;

use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use App\Repository\DateRMHRepository;
use App\Utils\DefaultUtils;
use setasign\Fpdi\Fpdi;
use Doctrine\DBAL\Exception;

class DateRMHService
{
    public function __construct(
        private DateRMHRepository $dateRMHRepository,
        private string $productionTravauxNiveauBBC1,
        private string $productionTravauxNiveauBBC2,
        private string $rmhFilePrefix,
        private string $rmhFileNumeroDepartement,
        private string $rmhFileCodeCollectivite,
        private string $rmhFileNumeroTrainVirement,
        private string $rmhFileCodeNature,
        private string $rmhFileCodeMonnaie,
        private string $rmhFileLibelleOperation,
        private string $appRootDossierDataSymfony,
        private FpdiTableService $fpdiTable
    ) {}

    /**
     * @param mixed $data
     * @return Fpdi
     */
    public function createRecapitulatifPreRMH(mixed $data): Fpdi
    {
        $pdf = new Fpdi('L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->AddPage();

        $this->writeRecapitulatifPreRMHHeader($pdf);
        $this->writeRecapitulatifPreRMHBasicTable($pdf, $data);

        return $pdf;
    }

    /**
     * @param mixed $dateRMHId
     * @throws Exception
     */
    public function createFileRMH(mixed $dateRMHId): void
    {
        $file = $this->rmhFilePrefix . $dateRMHId . '_fichier_rmh_' . date("YmdHis") . '.TXT';
        $filePath = $this->getRootDir($dateRMHId) . '/' . $file;
        $result = [];

        $ASCII_space = mb_convert_encoding(chr(32), 'HTML-ENTITIES', 'UTF-8');

        $data = $this->dateRMHRepository->findDataFileRMH($dateRMHId);

        if ($data) {
            foreach ($data as $row) {
                if ($row['destinataire']) {

                    $row['destinataire'] = mb_convert_encoding($row['destinataire'], 'HTML-ENTITIES', 'UTF-8');
                    $row['matricule'] = mb_convert_encoding($row['matricule'], 'HTML-ENTITIES', 'UTF-8');
                    $row['VNTotal'] = mb_convert_encoding($row['VNTotal'], 'HTML-ENTITIES', 'UTF-8');
                    $row['domiciliationBancaire'] = html_entity_decode(mb_convert_encoding($this->deleteAccent($row['domiciliationBancaire']), 'HTML-ENTITIES', 'UTF-8'));
                    $row['BIC'] = html_entity_decode(mb_convert_encoding($this->deleteAccent($row['BIC']), 'HTML-ENTITIES', 'UTF-8'));
                    $row['IBAN'] = html_entity_decode(mb_convert_encoding($this->deleteAccent($row['IBAN']), 'HTML-ENTITIES', 'UTF-8'));
                    $row['nom'] = html_entity_decode(mb_convert_encoding($this->deleteAccent($row['nom']), 'HTML-ENTITIES', 'UTF-8'));

                    $dataToWrite =
                        $this->rmhFileNumeroDepartement .
                        $this->rmhFileCodeCollectivite .
                        $this->rmhFileNumeroTrainVirement .
                        $this->rmhFileCodeNature;

                    $dataToWrite .= $this->getFormattedMatricule($row['destinataire'], $row['matricule']);
                    $dataToWrite .= $this->rmhFileCodeMonnaie;
                    $VNTotal = ((int)$row['VNTotal']) * 100;
                    $dataToWrite .= DefaultUtils::strPadCustom($VNTotal, 16, "0", STR_PAD_LEFT);

                    // Filler
                    $dataToWrite .= DefaultUtils::strPadCustom("", 19, $ASCII_space, STR_PAD_RIGHT);

                    $dataToWrite .= DefaultUtils::strPadCustom($row['domiciliationBancaire'], 24, $ASCII_space, STR_PAD_RIGHT);
                    $dataToWrite .= DefaultUtils::strPadCustom($row['BIC'], 11, $ASCII_space, STR_PAD_RIGHT);
                    $dataToWrite .= DefaultUtils::strPadCustom($row['IBAN'], 34, $ASCII_space, STR_PAD_RIGHT);
                    $dataToWrite .= DefaultUtils::strPadCustom($row['nom'], 70, $ASCII_space, STR_PAD_RIGHT);

                    // Filler
                    $dataToWrite .= DefaultUtils::strPadCustom("", 424, $ASCII_space, STR_PAD_RIGHT);

                    $dataToWrite .= DefaultUtils::strPadCustom($this->rmhFileLibelleOperation, 140, $ASCII_space, STR_PAD_RIGHT);

                    // Filler
                    $dataToWrite .= DefaultUtils::strPadCustom("", 226, $ASCII_space, STR_PAD_RIGHT);

                    $result[] = $dataToWrite;
                }
            }
        }

        // Create file even if empty
        $ASCII_CR = iconv("UTF-8", "ASCII//TRANSLIT", chr(13));
        $ASCII_LF = iconv("UTF-8", "ASCII//TRANSLIT", chr(10));
        $content = empty($result) ? 'EMPTY' : implode($ASCII_CR . $ASCII_LF, $result);
        file_put_contents($filePath, $content);
    }

    /**
     * @param mixed $dateRMHId
     * @throws Exception
     */
    public function createFileSynthese(mixed $dateRMHId): void
    {
        $file = $dateRMHId . '_fichier_synthese_' . date("YmdHis") . '.pdf';
        $filePath = $this->getRootDir($dateRMHId) . '/' . $file;

        $pdf = new Fpdi();
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 12);

        $pdf->SetMargins(15, 20, 15);

        /* /////////////////////////////////////////////////////////////////
                                    HEADER
        ///////////////////////////////////////////////////////////////// */
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($pdf->GetPageWidth(), 10, iconv('UTF-8', 'windows-1252', 'Chèques éco-énergie NORMANDIE'), 0, 2, 'C');
        $pdf->Ln(2);
        $pdf->Cell($pdf->GetPageWidth(), 10, iconv('UTF-8', 'windows-1252', 'Synthèse des demandes de paiement'), 0, 2, 'C');
        $pdf->Ln(1);
        $pdf->Cell($pdf->GetPageWidth(), 10, iconv('UTF-8', 'windows-1252', 'Fichier n') . chr(176) . iconv('UTF-8', 'windows-1252', $dateRMHId . ' du ' . date("d/m/Y")), 0, 2, 'C');
        $pdf->Ln(15);

        /* /////////////////////////////////////////////////////////////////
                                BLOC "TOTAL DU RELEVE"
        ///////////////////////////////////////////////////////////////// */
        $dataFileSynthese_totalReleveParticulier = $this->dateRMHRepository->findDataFileSyntheseForTotalReleve(
            $dateRMHId,
            $this->productionTravauxNiveauBBC1,
            $this->productionTravauxNiveauBBC2,
            0
        );

        $dataFileSynthese_totalReleveProfessionnel = $this->dateRMHRepository->findDataFileSyntheseForTotalReleve(
            $dateRMHId,
            $this->productionTravauxNiveauBBC1,
            $this->productionTravauxNiveauBBC2,
            1
        );

        $dataFileSynthese_totalReleve = [
            'particulier'   => $dataFileSynthese_totalReleveParticulier,
            'professionnel' => $dataFileSynthese_totalReleveProfessionnel
        ];
        $this->writeFileSyntheseForTotalReleve($pdf, $dataFileSynthese_totalReleve);

        /* /////////////////////////////////////////////////////////////////
            BLOC "DETAIL DES DEMANDES DE PAIEMENT PAR COMMISSION PERMANENTE"
        ///////////////////////////////////////////////////////////////// */
        $dataFileSynthese_dateCP = $this->dateRMHRepository->findDataFileSyntheseForDateCP($dateRMHId);
        $this->writeFileSyntheseForDateCP($dateRMHId, $pdf, $dataFileSynthese_dateCP);

        $pdf->Output($filePath, 'F');
    }

    /**
     * @param mixed $dateRMHId
     * @throws \Exception
     */
    public function createFileXemelios(mixed $dateRMHId): void
    {
        $path = $this->getRootDir($dateRMHId) . '/';
        $file = $dateRMHId . '_fichier_xemelios_' . date("YmdHis") . '.xml';
        $filePath = $path . $file;

        /* /////////////////////////////////////////////////////////////////
                                    INIT XEMELIOS
        ///////////////////////////////////////////////////////////////// */

        $XMLskeleton =
            <<<XML
            <n:EtatVersement xmlns:n="http://www.minefi.gouv.fr/cp/helios/pes/versement/1.0" xmlns:cm="http://www.minefi.gouv.fr/cp/helios/pes/commun" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.minefi.gouv.fr/cp/helios/pes/versement/1.0 EtatVersement.xsd">
                <IdVer V="1.4" />
                <Annee V="" />
                <Budget>
                    <Libelle V="CHEQUE ECO ENERGIE" />
                    <Lcode V="32300"  />
                </Budget>
                <Sequence>
                    <NumOrdre V="" />
                    <NbTotal V="" />
                </Sequence>
                <Prestation>
                    <Libelle V="CHEQUE ECO ENERGIE" />
                    <Code V="" />
                </Prestation>
                <Date V="" />
                <Mois V="" />
                <Emetteur>
                    <Nom V="REGION NORMANDIE" />
                    <Siret V="20005340300016" />
                    <Adresse>
                        <Adr1 V="ABBAYE AUX DAMES" />
                        <Adr2 V="PLACE REINE MATHILDE" />
                        <Adr3 V="CS 50523" />
                        <CP V="14035" />
                        <Ville V="CAEN Cedex 1" />
                    </Adresse>
                </Emetteur>
                <DonneesVersement>
                </DonneesVersement>
                <Nomenclatures>
                    <NomenclaturePrestation>
                        <Description V="Nomenclature cheque eco energie" />
                        <Correspondance>
                            <Libelle V="Audit energetique et scenarios" />
                            <Code V="Audit energetique et scenarios" />
                        </Correspondance>
                        <Correspondance>
                            <Libelle V="Travaux I, II et BBC" />
                            <Code V="Travaux I, II et BBC" />
                        </Correspondance>
                    </NomenclaturePrestation>
                </Nomenclatures>
            </n:EtatVersement>
XML;
        $rootNode = new \SimpleXMLElement($XMLskeleton);

        // Add solid data
        $rootNode->Sequence->NumOrdre['V'] = $dateRMHId;
        $rootNode->Sequence->NbTotal['V'] = $dateRMHId;
        $rootNode->Prestation->Code['V'] = $dateRMHId;
        $rootNode->Annee['V'] = date("Y");
        $rootNode->Mois['V'] = date("m");
        $rootNode->Date['V'] = date("Y-m-d");

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FOR XEMELIOS
        ///////////////////////////////////////////////////////////////// */

        // Requête pour tputes les balises hors <DESTINATAIRE>
        $fileRMH = $this->rmhFilePrefix . $dateRMHId . '_fichier_rmh_' . date("YmdHis") . '.TXT';
        $rQuery1 = $this->dateRMHRepository->findDataXemelios($dateRMHId, $fileRMH, $this->productionTravauxNiveauBBC1);

        // Requête pour la balise <DESTINATAIRE>
        $rQuery2 = $this->dateRMHRepository->findDataXemeliosForPartenaire();
        $arrayPartenaire = [];
        foreach ($rQuery2 as $value) {
            $arrayPartenaire[$value['IdAuditeur']] = $value;
        }

        /* /////////////////////////////////////////////////////////////////
                                CREATE XEMELIOS FILE
        ///////////////////////////////////////////////////////////////// */

        // Get balise référence
        $array_baliseBeneficiaire = [
            'IdVerst',
            'Date',
            'Prestation',
            'IdDossier',
            'Beneficiaire' => [
                'InfoTiers' => [
                    'IdTiers',
                    'RefTiers',
                    'Nom',
                    'Prenom'
                ],
                'Adresse' => [
                    'Adr1',
                    'Adr2',
                    'CP',
                    'Ville'
                ],
                'CpteBancaire' => [
                    'BIC',
                    'IBAN',
                    'LibBanc',
                    'TitCpte'
                ]
            ]
        ];
        $array_baliseDestinataire = [
            'Destinataire' => [
                'InfoTiers' => [
                    'IdTiers',
                    'RefTiers',
                    'CatTiers',
                    'Nom'
                ],
                'Adresse' => [
                    'Adr1',
                    'Adr2',
                    'CP',
                    'Ville'
                ],
                'CpteBancaire' => [
                    'BIC',
                    'IBAN',
                    'LibBanc',
                    'TitCpte'
                ]
            ]
        ];
        $array_baliseDecision = [
            'Decision' => [
                'Date',
                'DateEffet',
                'Nature' => 'DELIB',
                'IdDecision'
            ]
        ];
        $array_balisePJ = [
            'Support' => '01',
            'IdUnique',
            'NomPJ'
        ];
        $array_baliseComplementaire = [
            'Mt',
            'Service'      => 'ENV',
            'CodeNature'   => '907',
            'CodeBudget'   => '20422',
            'CodeFonction' => 75
        ];

        // Create <VERSEMENT> for each demande
        $listDossier = [];
        foreach ($rQuery1 as $row) {
            if (in_array(DefaultUtils::getKey($row['TYPECHEQUE']), ['0', '1', '2'])) {

                // Init balise <VERSEMENT>
                $listDossier[$row['IdDossier']] = $row['UID'];
                $node = $rootNode->DonneesVersement->addChild('Versement');

                // Balise BENEFICIAIRE
                $rowClone = $row;
                if (in_array(DefaultUtils::getKey($row['TYPECHEQUE']), ['0', '2'])) {
                    $rowClone['BIC'] = '';
                    $rowClone['IBAN'] = '';
                    $rowClone['LibBanc'] = '';
                    $rowClone['TitCpte'] = '';
                }
                $this->writeRecursiveXML($node, $array_baliseBeneficiaire, $rowClone);

                // Balise DESTINATAIRE
                if (
                    in_array(DefaultUtils::getKey($row['TYPECHEQUE']), ['0'])
                    && isset($arrayPartenaire[$row['IdAuditeur']])
                ) {
                    // Si le type de chèque est 0, on retourne l'Auditeur sinon on retourne le bénéficiaire / rénovateur
                    $this->writeRecursiveXML($node, $array_baliseDestinataire, $arrayPartenaire[$row['IdAuditeur']]);
                } else {
                    // Cas Bénéficiaire / Rénovateur: SIRET à vide dans la balise destinataire
                    $row['RefTiers'] = '';
                    $this->writeRecursiveXML($node, $array_baliseDestinataire, $row);
                }

                // Balise DECISION
                $row['Date'] = $row['DateEffet'];
                $this->writeRecursiveXML($node, $array_baliseDecision, $row);

                // Balise PJ
                $arrayPJ = [];
                if ($row['PJRef0']) {
                    $arrayPJRef[] = [
                        'Support'  => '01',
                        'IdUnique' => $row['IdDossier'] . '_RIB.' . $row['PJRef0'],
                        'NomPJ'    => $row['IdDossier'] . '_RIB.' . $row['PJRef0']
                    ];
                    $arrayPJ[] = $arrayPJRef;
                    unset($arrayPJRef);
                }

                if ($row['PJRef1']) {
                    if ('1' != $row['isBBC1'] && Demande_::DEMANDE_TRAVAUX_TYPE == $row['demandeType']) {
                        $z = 0;
                        $explode_PJRef1 = explode('##', $row['PJRef1']);
                        foreach ($explode_PJRef1 as $item) {
                            $arrayPJRef[] = [
                                'Support'  => '01',
                                'IdUnique' => $row['IdDossier'] . '_FACTURE_' . $z . '.' . $item,
                                'NomPJ'    => $row['IdDossier'] . '_FACTURE_' . $z . '.' . $item
                            ];
                            $arrayPJ[] = $arrayPJRef;
                            unset($arrayPJRef);
                            $z++;
                        }
                    } else {
                        $arrayPJRef[] = [
                            'Support'  => '01',
                            'IdUnique' => $row['IdDossier'] . '_FACTURE.' . $row['PJRef1'],
                            'NomPJ'    => $row['IdDossier'] . '_FACTURE.' . $row['PJRef1']
                        ];
                        $arrayPJ[] = $arrayPJRef;
                        unset($arrayPJRef);
                    }
                }

                if ($row['PJRef2']) {
                    $arrayPJRef[] = [
                        'Support'  => '01',
                        'IdUnique' => $row['IdDossier'] . '_RECTO_CHEQUE.' . $row['PJRef2'],
                        'NomPJ'    => $row['IdDossier'] . '_RECTO_CHEQUE.' . $row['PJRef2']
                    ];
                    $arrayPJ[] = $arrayPJRef;
                    unset($arrayPJRef);
                }

                if ($row['PJRef3']) {
                    $arrayPJRef[] = [
                        'Support'  => '01',
                        'IdUnique' => $row['IdDossier'] . '_VERSO_CHEQUE.' . $row['PJRef3'],
                        'NomPJ'    => $row['IdDossier'] . '_VERSO_CHEQUE.' . $row['PJRef3']
                    ];
                    $arrayPJ[] = $arrayPJRef;
                    unset($arrayPJRef);
                }

                foreach ($arrayPJ as $item) {
                    foreach ($item as $value) {
                        $pj = $node->Decision->addChild('PJRef');
                        $this->writeRecursiveXML($pj, $array_balisePJ, $value);
                    }
                }

                // Balise FIN VERSEMENT
                $this->writeRecursiveXML($node, $array_baliseComplementaire, $row);
            }
        }

        // Save XML file
        file_put_contents($filePath, $rootNode->asXML());

        /* /////////////////////////////////////////////////////////////////
                            CREATE XEMELIOS PJ FOLDER
        ///////////////////////////////////////////////////////////////// */

        // Copy all documents to PJ folder
        $PJDir = $this->appRootDossierDataSymfony . 'uploads/remboursement/RMH/' . $dateRMHId . '/PJ/';
        if (!file_exists($PJDir) || !is_dir($PJDir)) {
            DefaultUtils::createDirectory($PJDir, 0755);
        }

        foreach ($rQuery1 as $data) {
            switch ($data['demandeType']) {
                case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
                case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                    $dirInstruction = $this->appRootDossierDataSymfony . 'uploads/remboursement/auditEnergie_instruction/';
                    $dirAuditeur = $this->appRootDossierDataSymfony . 'uploads/partenaire/auditeur/';

                    // RIB
                    if (in_array(DefaultUtils::getKey($data['TYPECHEQUE']), ['0'])) {
                        if (file_exists($dirAuditeur . $data['IdAuditeurRib'] . '_rib.' . $data['PJRef0'])) {
                            copy(
                                $dirAuditeur . $data['IdAuditeurRib'] . '_rib.' . $data['PJRef0'],
                                $PJDir . $data['IdDossier'] . '_RIB.' . $data['PJRef0']
                            );
                        }
                    } else {
                        if (file_exists($dirInstruction . $data['IdEntity'] . '_rib.' . $data['PJRef0'])) {
                            copy(
                                $dirInstruction . $data['IdEntity'] . '_rib.' . $data['PJRef0'],
                                $PJDir . $data['IdDossier'] . '_RIB.' . $data['PJRef0']
                            );
                        }
                    }

                    // FACTURE
                    if (file_exists($dirInstruction . $data['IdEntity'] . '_facture.' . $data['PJRef1'])) {
                        copy(
                            $dirInstruction . $data['IdEntity'] . '_facture.' . $data['PJRef1'],
                            $PJDir . $data['IdDossier'] . '_FACTURE.' . $data['PJRef1']
                        );
                    }

                    // RECTO CHEQUE
                    if (file_exists($dirInstruction . $data['IdEntity'] . '_recto_cheque.' . $data['PJRef2'])) {
                        copy(
                            $dirInstruction . $data['IdEntity'] . '_recto_cheque.' . $data['PJRef2'],
                            $PJDir . $data['IdDossier'] . '_RECTO_CHEQUE.' . $data['PJRef2']
                        );
                    }

                    // VERSO CHEQUE
                    if (file_exists($dirInstruction . $data['IdEntity'] . '_verso_cheque.' . $data['PJRef3'])) {
                        copy(
                            $dirInstruction . $data['IdEntity'] . '_verso_cheque.' . $data['PJRef3'],
                            $PJDir . $data['IdDossier'] . '_VERSO_CHEQUE.' . $data['PJRef3']
                        );
                    }
                    break;

                case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
                case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                    $dirInstruction = $this->appRootDossierDataSymfony . 'uploads/remboursement/auditNumerique_instruction/';
                    $dirAuditeur = $this->appRootDossierDataSymfony . 'uploads/partenaire/auditeur/';

                    // RIB
                    if (in_array(DefaultUtils::getKey($data['TYPECHEQUE']), ['0'])) {
                        if (file_exists($dirAuditeur . $data['IdAuditeurRib'] . '_rib.' . $data['PJRef0'])) {
                            copy(
                                $dirAuditeur . $data['IdAuditeurRib'] . '_rib.' . $data['PJRef0'],
                                $PJDir . $data['IdDossier'] . '_RIB.' . $data['PJRef0']
                            );
                        }
                    } else {
                        if (file_exists($dirInstruction . $data['IdEntity'] . '_rib.' . $data['PJRef0'])) {
                            copy(
                                $dirInstruction . $data['IdEntity'] . '_rib.' . $data['PJRef0'],
                                $PJDir . $data['IdDossier'] . '_RIB.' . $data['PJRef0']
                            );
                        }
                    }

                    // FACTURE
                    if (file_exists($dirInstruction . $data['IdEntity'] . '_facture.' . $data['PJRef1'])) {
                        copy(
                            $dirInstruction . $data['IdEntity'] . '_facture.' . $data['PJRef1'],
                            $PJDir . $data['IdDossier'] . '_FACTURE.' . $data['PJRef1']
                        );
                    }

                    // RECTO CHEQUE
                    if (file_exists($dirInstruction . $data['IdEntity'] . '_recto_cheque.' . $data['PJRef2'])) {
                        copy(
                            $dirInstruction . $data['IdEntity'] . '_recto_cheque.' . $data['PJRef2'],
                            $PJDir . $data['IdDossier'] . '_RECTO_CHEQUE.' . $data['PJRef2']
                        );
                    }

                    // VERSO CHEQUE
                    if (file_exists($dirInstruction . $data['IdEntity'] . '_verso_cheque.' . $data['PJRef3'])) {
                        copy(
                            $dirInstruction . $data['IdEntity'] . '_verso_cheque.' . $data['PJRef3'],
                            $PJDir . $data['IdDossier'] . '_VERSO_CHEQUE.' . $data['PJRef3']
                        );
                    }
                    break;

                case Demande_::DEMANDE_TRAVAUX_TYPE:
                    $dirInstruction = $this->appRootDossierDataSymfony . 'uploads/remboursement/travaux_instruction/';

                    // RIB
                    if (file_exists($dirInstruction . $data['IdEntity'] . '_rib.' . $data['PJRef0'])) {
                        copy(
                            $dirInstruction . $data['IdEntity'] . '_rib.' . $data['PJRef0'],
                            $PJDir . $data['IdDossier'] . '_RIB.' . $data['PJRef0']
                        );
                    }

                    // FACTURE
                    if ('1' == $data['isBBC1']) {
                        if (file_exists($dirInstruction . $data['IdEntity'] . '_fiche_travaux.' . $data['PJRef1'])) {
                            copy(
                                $dirInstruction . $data['IdEntity'] . '_fiche_travaux.' . $data['PJRef1'],
                                $PJDir . $data['IdDossier'] . '_FACTURE.' . $data['PJRef1']
                            );
                        }
                    } else {
                        $dirFacture = $dirInstruction . 'conformite/';
                        $explode_PJRef1 = explode('##', $data['PJRef1']);
                        $array_extension = [];
                        foreach ($explode_PJRef1 as $value) {
                            $array_extension[] = $value;
                        }

                        $explode_id = explode('##', $data['IdEntityTravauxConformite']);
                        $z = 0;
                        foreach ($explode_id as $key => $value) {
                            if (file_exists($dirFacture . $value . '_document.' . $array_extension[$key])) {
                                copy(
                                    $dirFacture . $value . '_document.' . $array_extension[$key],
                                    $PJDir . $data['IdDossier'] . '_FACTURE_' . $z . '.' . $array_extension[$key]
                                );
                            }
                            $z++;
                        }
                    }

                    // RECTO CHEQUE
                    if (file_exists($dirInstruction . $data['IdEntity'] . '_recto_cheque.' . $data['PJRef2'])) {
                        copy(
                            $dirInstruction . $data['IdEntity'] . '_recto_cheque.' . $data['PJRef2'],
                            $PJDir . $data['IdDossier'] . '_RECTO_CHEQUE.' . $data['PJRef2']
                        );
                    }

                    // VERSO CHEQUE
                    if (file_exists($dirInstruction . $data['IdEntity'] . '_verso_cheque.' . $data['PJRef3'])) {
                        copy(
                            $dirInstruction . $data['IdEntity'] . '_verso_cheque.' . $data['PJRef3'],
                            $PJDir . $data['IdDossier'] . '_VERSO_CHEQUE.' . $data['PJRef3']
                        );
                    }
                    break;
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                CREATE XEMELIOS ZIP
        ///////////////////////////////////////////////////////////////// */

        // Generate ZIP
        $zip = new \ZipArchive();
        $zipPath = $path . basename($file, ".xml") . '.zip';

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Echec lors de la création de l'archive <$zipPath>");
        }

        // Add PJ folder to ZIP
        $options = [
            'add_path'        => "PJ/",
            'remove_all_path' => TRUE
        ];
        $zip->addGlob($PJDir . "*", GLOB_BRACE, $options);

        // Add XEMELIOS file to ZIP
        $zip->addFile($filePath, $file);
        $zip->close();

        // Delete XEMELIOS file
        unlink($filePath);

        // Delete PJ folder
        $this->deleteFiles($PJDir);
    }

    /**
     * @param mixed $dateRMHId
     * @return string
     */
    public function createZipRMH(mixed $dateRMHId): string
    {
        $path = $this->getRootDir($dateRMHId) . '/';
        $zipName = $path . $dateRMHId . "_archive" . '.zip';
        if (!file_exists($zipName)) {
            $zip = new \ZipArchive();

            if ($zip->open($zipName, \ZipArchive::CREATE) !== TRUE) {
                exit("Echec lors de l'ouverture de l'archive <$zipName>\n");
            }

            $filesRMH = glob( $path . $this->rmhFilePrefix . $dateRMHId . '_fichier_rmh_*');
            if (!empty($filesRMH) && $filesRMH[0]) {
                $fileRMH = basename($filesRMH[0]);
            }

            $fileSynthese = glob( $path . $dateRMHId . '_fichier_synthese_*');
            if (!empty($fileSynthese) && $fileSynthese[0]) {
                $fileSynthese = basename($fileSynthese[0]);
            }

            $zipXemelios = glob( $path . $dateRMHId . '_fichier_xemelios_*');
            if (!empty($zipXemelios) && $zipXemelios[0]) {
                $zipXemelios = basename($zipXemelios[0]);
            }

            $zip->addFile($path . $fileRMH, $fileRMH);
            $zip->addFile($path . $fileSynthese, $fileSynthese);
            $zip->addFile($path . $zipXemelios, $zipXemelios);
            $zip->close();
        }

        return $zipName;
    }

    /**
     * @param mixed $target
     */
    public function deleteFiles(mixed $target): void
    {
        if (is_dir($target)) {
            //GLOB_MARK adds a slash to directories returned
            $files = glob($target . '*', GLOB_MARK);

            foreach ($files as $file) {
                $this->deleteFiles($file);
            }

            rmdir($target);
        } elseif (is_file($target)) {
            unlink($target);
        }
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param Fpdi $pdf
     */
    private function writeRecapitulatifPreRMHHeader(Fpdi $pdf): void
    {
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(242, 251, 244);
        $pdf->SetFillColor(0, 176, 240);
        $pdf->setXY(56, 22);
        $pdf->Cell(185, 8, iconv('UTF-8', 'windows-1252', 'CONTRÔLE AVANT GÉNÉRATION DU RMH'), 0, '', 'C', true);

        $pdf->Ln();
    }

    /**
     * @param Fpdi $pdf
     * @param mixed $data
     */
    private function writeRecapitulatifPreRMHBasicTable(Fpdi $pdf, mixed $data): void
    {
        $pdf->setY(40);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $widthLabelColumn = 116;
        $widthMontantColumn = 18;

        $cptLines = 0;
        foreach ($data as $row) {
            $pdf->setX(82);
            $cptCol = 0;

            foreach ($row as $col) {
                if ($cptCol % 2 == 0) {
                    // LABEL WITH YEAR COLUMN
                    $width = $widthLabelColumn;
                    $center = 'L';
                    $value = iconv('UTF-8', 'windows-1252', 'Montant total correspondant au montant des dossiers passés en CP en ' . $col);
                } else {
                    // MONTANT COLUMN
                    $width = $widthMontantColumn;
                    $center = 'R';
                    $value = number_format($col, 0, '', ' ') . ' ' . chr(128);
                }

                $pdf->SetFillColor(147, 205, 221);
                $pdf->Cell($width, 5, $value, 1, '', $center, true);

                $cptCol++;
            }
            $pdf->Ln();

            $cptLines++;

            // Empty cell
            if ($cptLines < count($data)) {
                $pdf->setX(82);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Cell($widthLabelColumn, 4, '', 1, '', '', true);

                $pdf->SetFillColor(255, 255, 255);
                $pdf->Cell($widthMontantColumn, 4, '', 1, '', '', true);

                $pdf->Ln();
            }
        }
    }

    /**
     * @param mixed $destinataire
     * @param mixed $matricule
     * @return string
     */
    private function getFormattedMatricule(mixed $destinataire, mixed $matricule): string
    {
        $destinataireKey = DefaultUtils::getKey($destinataire);
        $prefixeMatricule = "";
        $matriculeLength = 15;

        if ($destinataireKey == '0') {
            // auditeur
            $prefixeMatricule = '9';
            $matriculeLength = 14;
        } elseif ($destinataireKey == '2') {
            // renovateur
            $prefixeMatricule = '8';
            $matriculeLength = 14;
        }
        $formattedMatricule = $prefixeMatricule . DefaultUtils::strPadCustom($matricule, $matriculeLength, "0", STR_PAD_LEFT);

        return $formattedMatricule;
    }

    /**
     * @param mixed $str
     * @param string $encoding
     * @return array|string|null
     */
    private function deleteAccent(mixed $str, string $encoding = 'utf-8'): array|string|null
    {
        $str = htmlentities($str, ENT_NOQUOTES, $encoding);

        $str = preg_replace('#&([A-za-z])(?:acute|grave|cedil|circ|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
        $str = preg_replace('#&[^;]+;#', '', $str);

        return $str;
    }

    /**
     * @param mixed $dateRMHId
     * @return string
     */
    private function getRootDir(mixed $dateRMHId): string
    {
        $RMHDir = $this->appRootDossierDataSymfony . 'uploads/remboursement/RMH/' . $dateRMHId;

        $oldUmask = umask(0);
        try {
            DefaultUtils::createDirectory($RMHDir, 0777, true);
        } catch (\Exception $e) {
            print($e->getMessage());
        }
        umask($oldUmask);

        return $RMHDir;
    }

    /**
     * @param Fpdi $pdf
     * @param mixed $data
     */
    private function writeFileSyntheseForTotalReleve(Fpdi $pdf, mixed $data): void
    {
        /* /////////////////////////////////////////////////////////////////
                                INIT BLOC "TOTAL RELEVE"
        ///////////////////////////////////////////////////////////////// */
        $totalReleveParticulier = [
            'nbCheque'     => [],
            'montantTitre' => []
        ];
        if (!empty($data['particulier'])) {
            foreach ($data['particulier'] as $rowParticulier) {
                $totalReleveParticulier['nbCheque'][$rowParticulier['niveauDemande']] = $rowParticulier['nbCheque'];
                $totalReleveParticulier['montantTitre'][$rowParticulier['niveauDemande']] = $rowParticulier['montantTitre'];
            }
        }

        $totalReleveProfessionnel = [
            'nbCheque'     => [],
            'montantTitre' => []
        ];
        if (!empty($data['professionnel'])) {
            foreach ($data['professionnel'] as $rowProfessionnel) {
                $totalReleveProfessionnel['nbCheque'][$rowProfessionnel['niveauDemande']] = $rowProfessionnel['nbCheque'];
                $totalReleveProfessionnel['montantTitre'][$rowProfessionnel['niveauDemande']] = $rowProfessionnel['montantTitre'];
            }
        }

        $pdf->SetFont('Arial', 'BU', 12);
        $pdf->Cell($pdf->GetPageWidth(), 10, iconv('UTF-8', 'windows-1252', 'TOTAL DU RELEVE'), 0, 2, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 11);

        // Couleurs, épaisseur du trait et police grasse
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0);
        // $pdf->SetDrawColor(128,0,0);
        $pdf->SetLineWidth(.3);
        $pdf->SetFont('', 'B');

        $widthTotalColumnThematique = 100;
        $widthTotalColumnOther = 40;

        $header = [
            '',
            'Nombre de Chèques',
            'Montant total'
        ];
        foreach ($header as $key => $headerData) {
            $widthCell = ($key == 0) ? $widthTotalColumnThematique : $widthTotalColumnOther;
            $pdf->Cell($widthCell, 7, iconv('UTF-8', 'windows-1252', $headerData), 1, 0, 'C', true);
        }
        $pdf->Ln();

        $headerRembourseParticulier = [
            'Remboursés aux particuliers',
            '',
            ''
        ];
        foreach ($headerRembourseParticulier as $key => $itemData) {
            $widthCell = ($key == 0) ? $widthTotalColumnThematique : $widthTotalColumnOther;
            $pdf->Cell($widthCell, 7, iconv('UTF-8', 'windows-1252', $itemData), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Restauration des couleurs et de la police
        $pdf->SetFillColor(180, 180, 180);
        $pdf->SetTextColor(0);
        $pdf->SetFont('');
        // Données
        $fill = false;
        $border = 'LRT';

        $travauxNiveauParticulierThematique = [
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE                           => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE                           => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE                => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE         => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE         => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE          => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE          => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE             => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE              => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE  => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE  => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_LABEL,
            Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE => Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_LABEL
        ];

        foreach ($travauxNiveauParticulierThematique as $codeTravauxNiveau => $labelTravauxNiveau) {
            $rembourseParticulierThematique[] = [
                $labelTravauxNiveau,
                !empty($totalReleveParticulier['nbCheque'][$codeTravauxNiveau]) ? $totalReleveParticulier['nbCheque'][$codeTravauxNiveau] : ' - ',
                !empty($totalReleveParticulier['montantTitre'][$codeTravauxNiveau]) ? number_format((int)$totalReleveParticulier['montantTitre'][$codeTravauxNiveau], 0, '', ' ') : ' - '
            ];
        }

        foreach ($rembourseParticulierThematique as $row) {
            foreach ($row as $key => $value) {
                $widthCell = ($key == 0) ? $widthTotalColumnThematique : $widthTotalColumnOther;
                $pdf->Cell($widthCell, 6, iconv('UTF-8', 'windows-1252', $value), $border, 0, 'C', $fill);
            }
            $border = 'LR';
            $pdf->Ln();
            $fill = !$fill;
        }

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('', 'B');

        $headerRembourseProfessionnel = [
            'Remboursés aux professionnels',
            '',
            ''
        ];
        foreach ($headerRembourseProfessionnel as $key => $itemData) {
            $widthCell = ($key == 0) ? $widthTotalColumnThematique : $widthTotalColumnOther;
            $pdf->Cell($widthCell, 7, iconv('UTF-8', 'windows-1252', $itemData), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Restauration des couleurs et de la police
        $pdf->SetFillColor(180, 180, 180);
        $pdf->SetTextColor(0);
        $pdf->SetFont('');
        // Données
        $fill = false;
        $border = 'LRT';

        $auditProfessionnelThematique = [
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE             => Demande_::DEMANDE_AUDIT_ENERGIE_LABEL,
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE      => Demande_::DEMANDE_AUDIT_ENERGIE_REGION_LABEL,
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE           => Demande_::DEMANDE_AUDIT_NUMERIQUE_LABEL,
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE => Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_LABEL
        ];

        foreach ($auditProfessionnelThematique as $codeAudit => $labelAudit) {
            $rembourseProfessionnelThematique[] = [
                $labelAudit,
                !empty($totalReleveProfessionnel['nbCheque'][$codeAudit]) ? $totalReleveProfessionnel['nbCheque'][$codeAudit] : ' - ',
                !empty($totalReleveProfessionnel['montantTitre'][$codeAudit]) ? number_format((int)$totalReleveProfessionnel['montantTitre'][$codeAudit], 0, '', ' ') : ' - '
            ];
        }

        foreach ($rembourseProfessionnelThematique as $row) {
            foreach ($row as $key => $value) {
                $widthCell = ($key == 0) ? $widthTotalColumnThematique : $widthTotalColumnOther;
                $pdf->Cell($widthCell, 6, iconv('UTF-8', 'windows-1252', $value), $border, 0, 'C', $fill);
            }
            $border = 'LR';
            $pdf->Ln();
            $fill = !$fill;
        }

        // TOTAL ROW
        $nbChequeTotal = 0;
        $montantTitreTotal = 0;

        $totalReleve = [
            'particulier'   => array_keys($travauxNiveauParticulierThematique),
            'professionnel' => array_keys($auditProfessionnelThematique)
        ];

        foreach ($totalReleve['particulier'] as $niveauParticulier) {
            if (!empty($totalReleveParticulier['nbCheque'][$niveauParticulier])) {
                $nbChequeTotal += $totalReleveParticulier['nbCheque'][$niveauParticulier];
            }
            if (!empty($totalReleveParticulier['montantTitre'][$niveauParticulier])) {
                $montantTitreTotal += (int)$totalReleveParticulier['montantTitre'][$niveauParticulier];
            }
        }

        foreach ($totalReleve['professionnel'] as $niveauProfessionnel) {
            if (!empty($totalReleveProfessionnel['nbCheque'][$niveauProfessionnel])) {
                $nbChequeTotal += $totalReleveProfessionnel['nbCheque'][$niveauProfessionnel];
            }
            if (!empty($totalReleveProfessionnel['montantTitre'][$niveauProfessionnel])) {
                $montantTitreTotal += (int)$totalReleveProfessionnel['montantTitre'][$niveauProfessionnel];
            }
        }

        $footerTotal = [
            'TOTAL',
            $nbChequeTotal,
            number_format($montantTitreTotal, 0, '', ' ')
        ];
        foreach ($footerTotal as $key => $itemData) {
            $widthCell = ($key == 0) ? $widthTotalColumnThematique : $widthTotalColumnOther;
            $pdf->Cell($widthCell, 7, iconv('UTF-8', 'windows-1252', $itemData), 1, 0, 'C', false);
        }
        $pdf->Ln();
        foreach ($header as $key => $headerData) {
            $widthCell = ($key == 0) ? $widthTotalColumnThematique : $widthTotalColumnOther;
            // Trait de terminaison
            $pdf->Cell($widthCell, 0, '', 'T');
        }
    }

    /**
     * @param mixed $dateRMHId
     * @param Fpdi $pdf
     * @param mixed $data
     * @throws Exception
     */
    private function writeFileSyntheseForDateCP(mixed $dateRMHId, Fpdi $pdf, mixed $data): void
    {
        $cells_datecp = [];
        $totaux = [];

        foreach ($data as $row) {
            if ($row['dateCP'] != null) {
                $date = "='" . $row['dateCP'] . "'";
            } else {
                $date = 'IS NULL';
                $row['dateCP'] = 'Commission Permanente NC';
            }

            $dataFileSyntheseForDateCPDetail = $this->dateRMHRepository->findDataFileSyntheseForDateCPDetail(
                $dateRMHId,
                $this->productionTravauxNiveauBBC1,
                $this->productionTravauxNiveauBBC2,
                $date
            );

            $cells_datecp[$row['dateCP']] = $dataFileSyntheseForDateCPDetail;
            $totaux[$row['dateCP']] = $row['totalTitre'];
        }

        if (!empty($cells_datecp)) {
            $pdf->SetMargins(5, 5, 10);
            $pdf->AddPage('L', [420, 330]);
            $pdf->SetFont('Arial', 'BU', 12);
            $pdf->Cell($pdf->GetPageWidth(), 10, iconv('UTF-8', 'windows-1252', 'DETAIL DES DEMANDES DE PAIEMENT PAR COMMISSION PERMANENTE'), 0, 2, 'C');
            $pdf->Ln(2);
            $pdf->SetFont('Arial', '', 11);
        }

        // Encodage (iconv) de ces titres entetes se fait deja dans la methode $pdf->FancyTable() donc à ne pas refaire ici
        $columnCorrespondance = [
            'niveauDemande'     => 'Type d\'aide',
            'numPartenaire'     => 'N° Partenaire',
            'montantTitre'      => 'Montant Aide',
            'numeroCheque'      => 'N° Chèque',
            'raisonSociale'     => 'Raison sociale / Nom Particulier',
            'IBAN'              => 'IBAN',
            'BIC'               => 'BIC',
            'numeroDossier'     => 'N° Dossier',
            'nomParticulier'    => 'Nom Particulier',
            'prenomParticulier' => 'Prénom Particulier',
        ];

        foreach ($cells_datecp as $key => $cells) {
            // $key => 2016-09-13 (dateCP)

            $column = array_keys($cells[0]);
            array_walk(
                $column,
                function ($item, $key) use (&$column, $columnCorrespondance) {
                    $column[$key] = $columnCorrespondance[$item];
                }
            );

            array_walk(
                $cells,
                function ($item, $key) use (&$cells) {
                    $cells[$key]['montantTitre'] = number_format((int)$item['montantTitre'], 0, '', ' ');
                }
            );

            $cells_datecp[$key]['header'] = $column;
            $cells_datecp[$key]['data'] = $this->fpdiTable->loadData($cells);
            $cells_datecp[$key]['width'] = $this->fpdiTable->getWidthCol($cells_datecp[$key]['header'], $cells_datecp[$key]['data']);
        }

        foreach ($cells_datecp as $key => $cells) {
            $dateCPVal = str_replace('-', '/', $key);
            $middle_width = array_sum($cells_datecp[$key]['width']) / 2;
            $this->fpdiTable->fancyTable($pdf, $cells_datecp[$key]['header'], $cells_datecp[$key]['data'], $cells_datecp[$key]['width'], $dateCPVal);
            $end_width = $pdf->GetX() - $middle_width;
            $pdf->Ln(5);
            $pdf->SetX($middle_width);
            $pdf->Cell($end_width, 7, 'Sous total CP du ' . $dateCPVal . $this->getASCIISpace(28) . number_format((int)$totaux[$key], 0, '', ' '), 1, 0, 'L', '');
            $pdf->Ln(20);
        }
    }

    /**
     * @param int $length
     * @return string
     */
    private function getASCIISpace(int $length): string
    {
        $str = '';
        for ($cpt = 0; $cpt < $length; $cpt++) {
            $str .= chr(32);
        }

        return $str;
    }

    /**
     * @param \SimpleXMLElement $node
     * @param array $arrayRef
     * @param mixed $data
     * @return void
     */
    private function writeRecursiveXML(\SimpleXMLElement $node, array $arrayRef, mixed $data = null): void
    {
        $result = null;
        if ($data != null) {
            foreach ($arrayRef as $key => $value) {
                if (!is_int($key)) { // La clé est une balise
                    if (is_array($value)) { // La valeur de la clé est un tableau de balise
                        $result = $node->addChild($key);
                        $this->writeRecursiveXML($node->$key, $value, $data);
                    } else { // La valeur de la clé est un attribut
                        $node->addChild($key);
                        $node->$key->addAttribute('V', $value);
                    }
                } else {
                    $node->addChild($value);
                    if ($data[$value]) $node->$value->addAttribute('V', $data[$value]);
                }
            }
        }
    }
}

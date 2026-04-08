<?php

namespace App\Service;

use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Production_;
use App\Repository\Demande_Repository;
use App\Repository\Demande_statutRepository;
use App\Service\MailerService;
use App\Utils\DefaultUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class ProductionService
 */
class ProductionService
{

    /**
     * @var Demande_Repository
     */
    private Demande_Repository $demandeRepository;

    /**
     * @var Demande_statutRepository
     */
    private Demande_statutRepository $demandeStatutRepository;

    /**
     * @var TitreService
     */
    private TitreService $titreService;

    /**
     * Code Thematique / Type de cheque
     *
     * @var array
     */
    private static array $TYPE_CHEQUE = [
        Production_::TYPE_AUDIT_ENERGETIQUE_ET_SCENARIO_KEY => '01',
        Production_::TYPE_AUDIT_NUMERIQUE_KEY               => '01',
        Production_::TYPE_AUDIT_ENERGIE_REGION_KEY          => '02',
        Production_::TYPE_MISE_A_JOUR_AUDIT_ENERGIE_KEY     => '02',
        'niveau1'                                           => '01',
        'niveau2'                                           => '02',
        'niveau2renovateur'                                 => '03',
        'production_travauxNiveau_BBC1'                     => '01',
        'production_travauxNiveau_BBC2'                     => '01',
        'production_travauxNiveau_BBC1_v2'                  => '01',
        'production_travauxNiveau_BBC2_v2'                  => '01',
        'production_travauxNiveau_BBC1biosource'            => '02',
        'production_travauxNiveau_BBC2biosource'            => '02',
        'sortiePassoire'                                    => '05',
        'sortiePassoire_avecBonification'                   => '05',
        'premiereEtapeBBCRGE'                               => '06',
        'premiereEtapeBBCRGE_avecBonification'              => '06',
        'premiereEtapeBBCRenovateur_BBC1'                   => '03', // sur OPE 588
        'premiereEtapeBBCRenovateur_BBC2'                   => '03', // sur OPE 589
        'premiereEtapeBBCRenovateur_BBC2_avecBonification'  => '03', // sur OPE 589 avec bonification
        'renovationGobaleBBC_BBC1'                          => '04', // sur OPE 588
        'renovationGobaleBBC_BBC2'                          => '04', // sur OPE 589
        'renovationGobaleBBC_BBC2_avecBonification'         => '04' // sur OPE 589 avec bonification
    ];

    /**
     * Les montants sont en centimes (800 euros => 80000)
     *
     * @var array
     */
    private static array $MONTANT_CHEQUE = [
        Production_::TYPE_AUDIT_ENERGETIQUE_ET_SCENARIO_KEY => '50000',
        Production_::TYPE_AUDIT_NUMERIQUE_KEY               => '40000',
        Production_::TYPE_AUDIT_ENERGIE_REGION_KEY          => '60000',
        Production_::TYPE_MISE_A_JOUR_AUDIT_ENERGIE_KEY     => '20000',
        'niveau1'                                           => '250000',
        'niveau2'                                           => '400000',
        'niveau2renovateur'                                 => '500000',
        'production_travauxNiveau_BBC1'                     => '300000',
        'production_travauxNiveau_BBC2'                     => '620000',
        'production_travauxNiveau_BBC1_v2'                  => '300000',
        'production_travauxNiveau_BBC2_v2'                  => '500000',
        'production_travauxNiveau_BBC1biosource'            => '300000',
        'production_travauxNiveau_BBC2biosource'            => '650000',
        'sortiePassoire'                                    => '300000',
        'sortiePassoire_avecBonification'                   => '500000',
        'premiereEtapeBBCRGE'                               => '250000',
        'premiereEtapeBBCRGE_avecBonification'              => '450000',
        'premiereEtapeBBCRenovateur_BBC1'                   => '300000', // sur OPE 588
        'premiereEtapeBBCRenovateur_BBC2'                   => '350000', // sur OPE 589
        'premiereEtapeBBCRenovateur_BBC2_avecBonification'  => '550000', // sur OPE 589 avec bonification
        'renovationGobaleBBC_BBC1'                          => '400000', // sur OPE 588
        'renovationGobaleBBC_BBC2'                          => '600000', // sur OPE 589
        'renovationGobaleBBC_BBC2_avecBonification'         => '800000' // sur OPE 589 avec bonification
    ];


    /**
     * Production parameters (injected)
     */
    private string $productionTravauxNiveauBBC1;
    private string $productionTravauxNiveauBBC2;
    private string $productionTopReeditionValue;
    private string $productionDematValue;
    private string $appAs400FolderOut;
    private bool $isProduction;
    private string $mailerAddressFrom;
    private HistoriqueService $historiqueService;
    private DemandeServiceFO $demandeServiceFO;
    private EntityManagerInterface $entityManager;
    private MailerService $mailerService;
    private Environment $environment;
    private TokenStorageInterface $tokenStorage;
    private string $appRootDossierDataSymfony;

    /**
     * @param EntityManagerInterface $entityManager
     * @param Demande_Repository $demandeRepository
     * @param Demande_statutRepository $demandeStatutRepository
     * @param TitreService $titreService
     * @param HistoriqueService $historiqueService
     * @param DemandeServiceFO $demandeServiceFO
     * @param MailerService $mailerService
     * @param TokenStorageInterface $tokenStorage
     * @param Environment $environment
     * @param string $productionTravauxNiveauBBC1
     * @param string $productionTravauxNiveauBBC2
     * @param string $productionTopReeditionValue
     * @param string $productionDematValue
     * @param string $appAs400FolderOut
     * @param string $appRootDossierDataSymfony
     * @param bool $isProduction
     * @param string $mailerAddressFrom
     */
    public function __construct(
        EntityManagerInterface   $entityManager,
        Demande_Repository       $demandeRepository,
        Demande_statutRepository $demandeStatutRepository,
        TitreService             $titreService,
        HistoriqueService        $historiqueService,
        DemandeServiceFO         $demandeServiceFO,
        MailerService            $mailerService,
        TokenStorageInterface    $tokenStorage,
        Environment              $environment,
        string                   $productionTravauxNiveauBBC1,
        string                   $productionTravauxNiveauBBC2,
        string                   $productionTopReeditionValue,
        string                   $productionDematValue,
        string                   $appAs400FolderOut,
        string                   $appRootDossierDataSymfony,
        bool                     $isProduction,
        string                   $mailerAddressFrom,
    ) {

        $this->demandeRepository = $demandeRepository;
        $this->demandeStatutRepository = $demandeStatutRepository;
        $this->titreService = $titreService;
        $this->productionTravauxNiveauBBC1 = $productionTravauxNiveauBBC1;
        $this->productionTravauxNiveauBBC2 = $productionTravauxNiveauBBC2;
        $this->productionTopReeditionValue = $productionTopReeditionValue;
        $this->productionDematValue = $productionDematValue;
        $this->appAs400FolderOut = $appAs400FolderOut;
        $this->appRootDossierDataSymfony = $appRootDossierDataSymfony;
        $this->isProduction = $isProduction;
        $this->mailerAddressFrom = $mailerAddressFrom;
        $this->historiqueService = $historiqueService;
        $this->demandeServiceFO = $demandeServiceFO;
        $this->entityManager = $entityManager;
        $this->mailerService = $mailerService;
        $this->environment = $environment;
        $this->tokenStorage = $tokenStorage;
    }
    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $listData
     * @param $production
     * @return array
     * @throws \Exception
     */
    public function writeData($listData, $production): array
    {
        $arrayReturn = array(
            'success'       => true,
            'reportContent' => ''
        );

        if (0 != count($listData)) {

            $date_historique = new \DateTime();
            $date_historique_format = date_format($date_historique, 'YmdHis');


            $dirFluxProductionArray = $this->getRootDir();
            $fluxProductionDir = $dirFluxProductionArray['flux_production_dir'];
            $fluxProductionHistoriqueDir = $dirFluxProductionArray['flux_production_historique_dir'];

            $statutForProduction = $this->demandeServiceFO->searchStatutForProduction();
            /**
             * @var Demande_statut $demandeStatutForProduction
             */
            $demandeStatutForProduction = $this->demandeStatutRepository->findOneByStatut($statutForProduction);
            // $key => OPE
            foreach ($listData as $key => $value) {
                if (!empty($value)) {
                    $result = array();
                    $nomFichierProd = 'INT' . $key . '.TXT';
                    $fichierProd = $fluxProductionDir . '/' . $nomFichierProd;
                    $fichierHist = $fluxProductionHistoriqueDir . '/INT' . $key . $date_historique_format . '.TXT';

                    foreach ($value as $item) {
                        // The condition is for not duplicate the entry demande-production for travaux niveau bbc
                        if ($key != $this->productionTravauxNiveauBBC2) {
                            $demande = $this->demandeRepository->find($item['demandeId']);
                            $demande->setStatutId($statutForProduction);
                            // MISE A JOUR DEMANDE STATUT DESCRIPTION
                            $demande->setStatutDescription($demandeStatutForProduction->getDescription());
                            $production->addDemande($demande);
                            $this->entityManager->persist($production);
                            $this->entityManager->flush();
                        }

                        /* /////////////////////////////////////////////////////////////////
                                                FILL UP HISTORIQUE
                        ///////////////////////////////////////////////////////////////// */
                        $this->historiqueService->save(
                            $item['demandeId'],
                            $statutForProduction,
                            $item['demandeType'],
                            $this->tokenStorage->getToken()->getUser()->getRoles(),
                            false,
                            'Production'
                        );

                        $numeroOperation = DefaultUtils::formatString($key);
                        $numero_operation_ = DefaultUtils::strPadCustom($numeroOperation, 5, "0", STR_PAD_LEFT);

                        $numero_production = DefaultUtils::formatString((string)$production->getId());
                        $numero_production_convert = DefaultUtils::strPadCustom($numero_production, 12, "0", STR_PAD_LEFT);

                        $numero_dossier_externe = DefaultUtils::formatString((string)$item['demandeId']);
                        $numero_dossier_externe_convert = DefaultUtils::strPadCustom($numero_dossier_externe, 12, "0", STR_PAD_LEFT);

                        $numero_beneficiaire = DefaultUtils::formatString((string)$item['beneficiaireId']);
                        $numero_beneficiaire_convert = DefaultUtils::strPadCustom($numero_beneficiaire, 12, "0", STR_PAD_LEFT);

                        $numero_demande = '01';
                        $frequence_production = '01';

                        $beneficiaire_nom_ = DefaultUtils::formatString((string)$item['beneficiaireNom']);
                        $beneficiaire_nom_convert = DefaultUtils::strPadCustom($beneficiaire_nom_, 30, " ", STR_PAD_RIGHT);

                        $beneficiaire_prenom_ = DefaultUtils::formatString((string)$item['beneficiairePrenom']);
                        $beneficiaire_prenom_convert = DefaultUtils::strPadCustom($beneficiaire_prenom_, 30, " ", STR_PAD_RIGHT);

                        $beneficiaire_filler_0_convert = DefaultUtils::strPadCustom("", 19, " ", STR_PAD_RIGHT);

                        $beneficiaire_complement_numero_rue = $item['beneficiaireComplementNumeroRue'];
                        $array_beneficiaire_complement_rue = $beneficiaire_complement_numero_rue ? explode(" | ", $beneficiaire_complement_numero_rue) : array();
                        $beneficiaire_numero_rue = $item['beneficiaireNumeroRue'];
                        $beneficiaire_numero_rue .= isset($array_beneficiaire_complement_rue[1]) ? " " . trim($array_beneficiaire_complement_rue[1]) : "";
                        $beneficiaire_numero_rue .= $item['beneficiaireNomRue'] ? " " . trim($item['beneficiaireNomRue']) : "";
                        $beneficiaire_numero_rue .= $item['beneficiaireComplement1'] ? " " . trim($item['beneficiaireComplement1']) : "";
                        $beneficiaire_numero_rue .= $item['beneficiaireComplement2'] ? " " . trim($item['beneficiaireComplement2']) : "";

                        $beneficiaire_numero_rue_ = DefaultUtils::formatString((string)$beneficiaire_numero_rue);
                        $beneficiaire_numero_rue_convert = DefaultUtils::strPadCustom($beneficiaire_numero_rue_, 114, " ", STR_PAD_RIGHT);

                        $beneficiaire_adresse4_convert = DefaultUtils::strPadCustom("", 38, " ", STR_PAD_RIGHT);

                        $beneficiaire_code_postal_ = DefaultUtils::formatString((string)$item['beneficiaireCodePostal']);
                        $beneficiaire_code_postal_convert = DefaultUtils::strPadCustom($beneficiaire_code_postal_, 5, " ", STR_PAD_RIGHT);

                        $beneficiaire_filler1_convert = DefaultUtils::strPadCustom("", 5, " ", STR_PAD_RIGHT);

                        $beneficiaire_ville_ = DefaultUtils::formatString((string)$item['beneficiaireVille']);
                        $beneficiaire_ville_convert = DefaultUtils::strPadCustom($beneficiaire_ville_, 32, " ", STR_PAD_RIGHT);

                        $beneficiaire_filler2_convert = DefaultUtils::strPadCustom("", 40, " ", STR_PAD_RIGHT);

                        $beneficiaire_filler3_convert = DefaultUtils::strPadCustom("", 167, " ", STR_PAD_RIGHT);

                        $logement_numero_rue = $item['logementNumeroRue'];
                        $array_logement_complement_rue = $item['logementComplementRue'] ? explode(" | ", $item['logementComplementRue']) : array();
                        $logement_numero_rue .= isset($array_logement_complement_rue[1]) ? " " . trim($array_logement_complement_rue[1]) : "";
                        $logement_numero_rue .= $item['logementAdresse'] ? " " . trim($item['logementAdresse']) : "";
                        $logement_numero_rue .= $item['logementComplement1'] ? " " . trim($item['logementComplement1']) : "";
                        $logement_numero_rue .= $item['logementComplement2'] ? " " . trim($item['logementComplement2']) : "";
                        $logement_numero_rue_ = DefaultUtils::formatString((string)$logement_numero_rue);
                        $logement_numero_rue_convert = DefaultUtils::strPadCustom($logement_numero_rue_, 114, " ", STR_PAD_RIGHT);

                        $logement_adresse_4_convert = DefaultUtils::strPadCustom("", 38, " ", STR_PAD_RIGHT);

                        $logement_code_postal_ = DefaultUtils::formatString((string)$item['logementCodePostal']);
                        $logement_code_postal_convert = DefaultUtils::strPadCustom($logement_code_postal_, 5, " ", STR_PAD_RIGHT);

                        $logement_ville_ = DefaultUtils::formatString((string)$item['logementVille']);
                        $logement_ville_convert = DefaultUtils::strPadCustom($logement_ville_, 32, " ", STR_PAD_RIGHT);

                        $beneficiaire_filler4_convert = DefaultUtils::strPadCustom("", 114, " ", STR_PAD_RIGHT);

                        $demandeTypeCheque = '';
                        $demandeMontantCheque = '';

                        /* /////////////////////////////////////////////////////////////////////////////////////
                                                    CAS DEMANDES TRAVAUX
                        ///////////////////////////////////////////////////////////////////////////////////// */
                        if ($item['demandeType'] == Demande_::DEMANDE_TRAVAUX_TYPE && !empty($item['typeTravauxNiveau'])) {
                            $typeTravauxNiveauArray = explode('|', $item['typeTravauxNiveau']);
                            $typeTravauxNiveauValue = trim($typeTravauxNiveauArray[1]);

                            $keyArrayChequeSuffix = '';
                            if (!empty($item['demandeTravauxDevisIsBonificationAide'])) {
                                $keyArrayChequeSuffix .= '_avecBonification';
                            }

                            switch ($typeTravauxNiveauValue) {
                                case 'niveauBBCrenovateur':
                                    /* /////////////////////////////////////////////////////////////////////////////////////
                                        RECUPERATION MONTANT Travaux Niveau3 BBC (Aide region)
                                    ///////////////////////////////////////////////////////////////////////////////////// */

                                    $demandeAuditE = $this->demandeRepository->findOneBy(
                                        [
                                            'logement_id' => $item['travauxDevisLogementId'],
                                            'type'        => Demande_::DEMANDE_AUDIT_ENERGIE_TYPE
                                        ],
                                        [
                                            'id' => 'DESC'
                                        ]
                                    );
                                    $montantTravauxNiveau3BBC = $this->titreService->getMontantTravauxNiveau3BBC($demandeAuditE);

                                    if ($this->productionTravauxNiveauBBC1 == $key) {
                                        $keyBBC1 = 'production_travauxNiveau_BBC1';
                                        if (TitreService::MONTANT_HUIT_MILLE == $montantTravauxNiveau3BBC) {
                                            $keyBBC1 .= '_v2';
                                        }
                                        $demandeTypeCheque = self::$TYPE_CHEQUE[$keyBBC1];
                                        $demandeMontantCheque = self::$MONTANT_CHEQUE[$keyBBC1];
                                    } elseif ($this->productionTravauxNiveauBBC2 == $key) {
                                        $keyBBC2 = 'production_travauxNiveau_BBC2';
                                        if (TitreService::MONTANT_HUIT_MILLE == $montantTravauxNiveau3BBC) {
                                            $keyBBC2 .= '_v2';
                                        }
                                        $demandeTypeCheque = self::$TYPE_CHEQUE[$keyBBC2];
                                        $demandeMontantCheque = self::$MONTANT_CHEQUE[$keyBBC2];
                                    }
                                    break;
                                case 'niveauBBCbiosource':
                                    if ($this->productionTravauxNiveauBBC1 == $key) {
                                        $demandeTypeCheque = self::$TYPE_CHEQUE['production_travauxNiveau_BBC1biosource'];
                                        $demandeMontantCheque = self::$MONTANT_CHEQUE['production_travauxNiveau_BBC1biosource'];
                                    } elseif ($this->productionTravauxNiveauBBC2 == $key) {
                                        $demandeTypeCheque = self::$TYPE_CHEQUE['production_travauxNiveau_BBC2biosource'];
                                        $demandeMontantCheque = self::$MONTANT_CHEQUE['production_travauxNiveau_BBC2biosource'];
                                    }
                                    break;
                                case 'sortiePassoire':
                                case 'premiereEtapeBBCRGE':
                                    $keyArrayCheque = $typeTravauxNiveauValue . $keyArrayChequeSuffix;
                                    $demandeTypeCheque = self::$TYPE_CHEQUE[$keyArrayCheque];
                                    $demandeMontantCheque = self::$MONTANT_CHEQUE[$keyArrayCheque];
                                    break;
                                case 'premiereEtapeBBCRenovateur':
                                case 'renovationGobaleBBC':
                                    if ($this->productionTravauxNiveauBBC1 == $key) {
                                        // Sans possibilité de bonification
                                        $keyArrayCheque = $typeTravauxNiveauValue . '_BBC1';
                                        $demandeTypeCheque = self::$TYPE_CHEQUE[$keyArrayCheque];
                                        $demandeMontantCheque = self::$MONTANT_CHEQUE[$keyArrayCheque];
                                    } elseif ($this->productionTravauxNiveauBBC2 == $key) {
                                        // Avec possibilité de bonification
                                        $keyArrayCheque = $typeTravauxNiveauValue . '_BBC2' . $keyArrayChequeSuffix;
                                        $demandeTypeCheque = self::$TYPE_CHEQUE[$keyArrayCheque];
                                        $demandeMontantCheque = self::$MONTANT_CHEQUE[$keyArrayCheque];
                                    }
                                    break;
                                default:
                                    $demandeTypeCheque = self::$TYPE_CHEQUE[$typeTravauxNiveauValue];
                                    $demandeMontantCheque = self::$MONTANT_CHEQUE[$typeTravauxNiveauValue];
                                    break;
                            }
                        } else {
                            /* /////////////////////////////////////////////////////////////////////////////////////
                                                CAS AUTRES QUE DEMANDES TRAVAUX : AUDIT ENERGETIQUE REGION NORMANDIE,
                                                MISE A JOUR AUDIT ENERGETIQUE ETC
                            ///////////////////////////////////////////////////////////////////////////////////// */
                            switch ($item['demandeType']) {
                                case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
                                    $demandeTypeCheque = self::$TYPE_CHEQUE[Production_::TYPE_AUDIT_ENERGETIQUE_ET_SCENARIO_KEY];
                                    $demandeMontantCheque = self::$MONTANT_CHEQUE[Production_::TYPE_AUDIT_ENERGETIQUE_ET_SCENARIO_KEY];
                                    break;
                                case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
                                    $demandeTypeCheque = self::$TYPE_CHEQUE[Production_::TYPE_AUDIT_NUMERIQUE_KEY];
                                    $demandeMontantCheque = self::$MONTANT_CHEQUE[Production_::TYPE_AUDIT_NUMERIQUE_KEY];
                                    break;
                                case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                                    $demandeTypeCheque = self::$TYPE_CHEQUE[Production_::TYPE_AUDIT_ENERGIE_REGION_KEY];
                                    $demandeMontantCheque = self::$MONTANT_CHEQUE[Production_::TYPE_AUDIT_ENERGIE_REGION_KEY];
                                    break;
                                case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                                    $demandeTypeCheque = self::$TYPE_CHEQUE[Production_::TYPE_MISE_A_JOUR_AUDIT_ENERGIE_KEY];
                                    $demandeMontantCheque = self::$MONTANT_CHEQUE[Production_::TYPE_MISE_A_JOUR_AUDIT_ENERGIE_KEY];
                                    break;
                            }
                        }

                        $demandeTypeChequeConvert = DefaultUtils::strPadCustom((string)$demandeTypeCheque, 2, " ", STR_PAD_RIGHT);
                        $demandeMontantChequeConvert = DefaultUtils::strPadCustom((string)$demandeMontantCheque, 10, "0", STR_PAD_LEFT);

                        $beneficiaire_filler5_convert = DefaultUtils::strPadCustom("", 60, " ", STR_PAD_RIGHT);

                        $dataToWrite = $numero_operation_ . "" .
                            $numero_production_convert . "" .
                            $numero_dossier_externe_convert . "" .
                            $numero_beneficiaire_convert . "" .
                            $numero_demande . "" .
                            $frequence_production . "" .
                            $this->productionTopReeditionValue . "" .
                            $beneficiaire_nom_convert . "" .
                            $beneficiaire_prenom_convert . "" .
                            $beneficiaire_filler_0_convert . "" .
                            $beneficiaire_numero_rue_convert . "" .
                            $beneficiaire_adresse4_convert . "" .
                            $beneficiaire_code_postal_convert . "" .
                            $beneficiaire_filler1_convert . "" .
                            $beneficiaire_ville_convert . "" .
                            $beneficiaire_filler2_convert . "" .
                            $this->productionDematValue . "" .
                            $beneficiaire_filler3_convert .
                            $logement_numero_rue_convert . "" .
                            $logement_adresse_4_convert . "" .
                            $logement_code_postal_convert . "" .
                            $logement_ville_convert . "" .
                            $beneficiaire_filler4_convert . "" .
                            $demandeTypeChequeConvert . "" .
                            $beneficiaire_filler5_convert . "" .
                            $demandeMontantChequeConvert;

                        $result[] = $dataToWrite;
                    }

                    $fichierProdSuccess = file_put_contents($fichierProd, implode(PHP_EOL, $result));
                    if ($fichierProdSuccess === false) {
                        $arrayReturn['success'] = false;
                        $arrayReturn['reportContent'] .= '<p>' . basename($fichierProd) . ' : Erreur création fichier</p>';
                    } else {
                        $arrayReturn['reportContent'] .= '<p>' . basename($fichierProd) . ' : Succès</p>';

                        if (!is_writable($this->appAs400FolderOut)) {
                            $arrayReturn['reportContent'] .= '<p>' . basename($this->appAs400FolderOut) . ' : Erreur copie fichier vers la destination (droits)</p>';
                        }

                        $isFileProductionCreateSuccess = (file_put_contents($this->appAs400FolderOut . $nomFichierProd, implode(PHP_EOL, $result)) !== false);
                        if ($isFileProductionCreateSuccess) {
                            // Historique
                            file_put_contents($fichierHist, implode(PHP_EOL, $result));

                            // Suppression du fichier de prod en local si envoi sur l'emplacement "out"
                            if ($this->isProduction && file_exists($fichierProd)) {
                                unlink($fichierProd);
                            }
                        }
                    }

                    unset($result);
                }
            }
        }

        return $arrayReturn;
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @return array
     */
    private function getRootDir(): array
    {
        $fluxDir = $this->appRootDossierDataSymfony . 'flux';
        $fluxProductionDir = $fluxDir . '/production';
        $fluxProductionHistoriqueDir = $fluxDir . '/production/historique';
        $fluxRetourProductionDir = $fluxDir . '/retour_production';
        $fluxRetourProductionHistoriqueDir = $fluxDir . '/retour_production/historique';

        $oldUmask = umask(0);

        try {
            DefaultUtils::createDirectory($fluxDir, 0777);
        } catch (\Exception $e) {
            print($e->getMessage());
        }

        try {
            DefaultUtils::createDirectory($fluxProductionDir, 0777);
        } catch (\Exception $e) {
            print($e->getMessage());
        }

        try {
            DefaultUtils::createDirectory($fluxProductionHistoriqueDir, 0777);
        } catch (\Exception $e) {
            print($e->getMessage());
        }

        try {
            DefaultUtils::createDirectory($fluxRetourProductionDir, 0777);
        } catch (\Exception $e) {
            print($e->getMessage());
        }

        try {
            DefaultUtils::createDirectory($fluxRetourProductionHistoriqueDir, 0777);
        } catch (\Exception $e) {
            print($e->getMessage());
        }

        umask($oldUmask);

        return array(
            'flux_production_dir'                   => $fluxProductionDir,
            'flux_production_historique_dir'        => $fluxProductionHistoriqueDir,
            'flux_retour_production_dir'            => $fluxRetourProductionDir,
            'flux_retour_production_historique_dir' => $fluxRetourProductionHistoriqueDir
        );
    }

    /**
     * @param $subject
     * @param $templatePath
     * @param array $templatePathContext
     * @param $emailTo
     * @param $listEmailBcc
     * @param $listEmailCc
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendEmailReport(
        $subject,
        $templatePath,
        array $templatePathContext = array(),
        $emailTo = null,
        $listEmailBcc = null,
        $listEmailCc = null
    ): void {
        $this->mailerService->sendGeneriqueEmail(
            $subject,
            $this->environment->render($templatePath, $templatePathContext),
            $this->mailerAddressFrom,
            $emailTo,
            null,
            'text/html',
            'UTF-8',
            $listEmailBcc,
            null,
            $listEmailCc,
            null
        );
    }
}

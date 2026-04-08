<?php

namespace App\Service;

use App\Entity\Remboursement_;
use App\Entity\Remboursement_travaux_instruction_conformite;
use App\Repository\FicheTechniqueRepository;
use App\Repository\Remboursement_Repository;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use App\Entity\Logement;
use App\Repository\BeneficiaireRepository;
use App\Repository\Demande_travaux_devisRepository;
use App\Repository\LogementRepository;
use App\Entity\User;

class CarnetInformationLogementService
{
    private APIService $APIService;
    private HistoriqueService $historiqueService;
    private BeneficiaireRepository $beneficiaireRepository;
    protected FicheTechniqueRepository $ficheTechniqueRepository;
    private LogementRepository $logementRepository;
    private Remboursement_Repository $remboursementRepository;
    private Demande_travaux_devisRepository $demande_travaux_devisRepository;
    private string $appApiCleaUserId;
    private bool $appApiCleaIsDataProduction;
    private string $appRootFolderDataSymfony;

    // 2592000 => 30 jours
    const EXPIRATION_SECONDS_BEFORE_VALIDATE_CLEA_CREATE = 2592000;

    public function __construct(
        APIService                      $APIService,
        HistoriqueService               $historiqueService,
        BeneficiaireRepository          $beneficiaireRepository,
        FicheTechniqueRepository        $ficheTechniqueRepository,
        LogementRepository              $logementRepository,
        Remboursement_Repository        $remboursementRepository,
        Demande_travaux_devisRepository $demande_travaux_devisRepository,
        string                          $appApiCleaUserId,
        bool                            $appApiCleaIsDataProduction,
        string                          $appRootFolderDataSymfony
    )
    {
        $this->APIService = $APIService;
        $this->historiqueService = $historiqueService;
        $this->beneficiaireRepository = $beneficiaireRepository;
        $this->ficheTechniqueRepository = $ficheTechniqueRepository;
        $this->logementRepository = $logementRepository;
        $this->remboursementRepository = $remboursementRepository;
        $this->demande_travaux_devisRepository = $demande_travaux_devisRepository;
        $this->appApiCleaUserId = $appApiCleaUserId;
        $this->appApiCleaIsDataProduction = $appApiCleaIsDataProduction;
        $this->appRootFolderDataSymfony = $appRootFolderDataSymfony;
    }

    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    public function getCLEACarnetCreateBody(?Demande_ $demande, int $remboursementId): ?array
    {
        if (empty($demande))
            return null;

        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $this->remboursementRepository->find($remboursementId);
        $ficheTechniqueField = $this->getFicheTechniqueField($demande, $remboursement);

        $arrayConstruction = [];
        $dpeEtiquetteEnergetique = null;

        if (!empty($ficheTechniqueField)) {
            $dpeEtiquetteEnergetique = in_array($ficheTechniqueField->getCEPEtiquetteEnergetique(), APIService::$API_CLEA_DPE_ETIQUETTE_ENERGETIQUE) ? $ficheTechniqueField->getCEPEtiquetteEnergetique() : null;
            $arrayConstruction['superficieHabitable'] = $ficheTechniqueField->getSurfaceHabitable();
            $arrayConstruction['natureChauffage'] = $this->getCLEANatureChauffageECSBody($ficheTechniqueField->getChauffageEnergie());
            $arrayConstruction['natureEauChaude'] = $this->getCLEANatureChauffageECSBody($ficheTechniqueField->getECSEnergie());
        }

        if (true === $this->appApiCleaIsDataProduction) {
            /**
             * @var Logement $logement
             */
            $logement = $this->logementRepository->find($demande->getLogementId());

            // ON UTILISE LES DONNÉES DE PRODUCTION
            $logementNom = $logement->getNom();
            $adresseNumero = $logement->getNumeroRue();
            $adresseNomVoie = $logement->getAdresse();
            $adresseCodePostal = $logement->getCodePostal();
            $adresseNomCommune = $logement->getVille();
            $adresseNomComplementaire = $logement->getComplement1();
        } else {
            $intTest = rand(1, 100000);
            $logementNom = 'logement ' . $intTest;
            $adresseNumero = 'numero ' . $intTest;
            $adresseNomVoie = 'nom voie ' . $intTest;
            $adresseCodePostal = '7500' . rand(1, 9);
            $adresseNomCommune = 'nom commune ' . $intTest;
            $adresseNomComplementaire = 'nom complementaire ' . $intTest;
        }

        return [
            "user" => $this->appApiCleaUserId,
            "nom" => $logementNom,
            "typeLogement" => "MAISON",
            "adresse" => [
                "numero" => $adresseNumero,
                "nomVoie" => $adresseNomVoie,
                "codePostal" => $adresseCodePostal,
                "nomCommune" => $adresseNomCommune,
                "nomComplementaire" => $adresseNomComplementaire
            ],
            "nomMaitreOeuvre" => "",
            "parcelleCadastrale" => "",
            "appartenanceTerrainLotissement" => "",
            "pointLivraisonEnedis" => "",
            "certifie" => null,
            "certificationNF" => "",
            "numeroCertificationNF" => "",
            "pointComptage" => "",
            "numeroFiscal" => "",
            "copropriete" => [
            ],
            "construction" => $arrayConstruction,
            "dpe" => [
                "etiquetteEnergetique" => $dpeEtiquetteEnergetique,
                "etiquetteEnvironnementale" => ""
            ],
            "nuisance" => [
            ],
            "zone" => [
            ]
        ];
    }

    public function getCLEACarnetAddOwnerBody(?int $beneficiaireId): ?array
    {
        if (empty($beneficiaireId))
            return null;

        /**
         * @var Beneficiaire $beneficiaire
         */
        $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);

        if (true === $this->appApiCleaIsDataProduction) {
            // ON UTILISE LES DONNÉES DE PRODUCTION
            $nom = $beneficiaire->getNom();
            $prenom = $beneficiaire->getPrenom();
            $adresseEmail = $beneficiaire->getEmail();
            $beneficiaireCiviliteKey = explode(' | ', $beneficiaire->getCivilite())[0];
            $genre = APIService::$API_CLEA_GENRE_BY_CIVILITE[$beneficiaireCiviliteKey];
        } else {
            $intTest = rand(1, 100000);
            $nom = 'nom ' . $intTest;
            $prenom = 'prenom ' . $intTest;
            $adresseEmail = 'adresseEmail' . $intTest . '@test.fr';
            $genre = rand(0, 1);
        }

        return [
            "nom" => $nom,
            "prenom" => $prenom,
            "adresseEmail" => $adresseEmail,
            "genre" => $genre
        ];
    }

    public function getCLEAEquipementAddBody(?Demande_ $demande, int $remboursementId): ?array
    {
        if (empty($demande))
            return null;

        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $this->remboursementRepository->find($remboursementId);
        $ficheTechniqueField = $this->getFicheTechniqueField($demande, $remboursement);
        $arrayEquipements = [];

        if (!empty($ficheTechniqueField)) {
            if (!empty($ficheTechniqueField->getClimatisationTypeVentilation())
                && !empty(APIService::$API_CLEA_VENTILATION[$ficheTechniqueField->getClimatisationTypeVentilation()])
            ) {
                $arrayEquipements = APIService::$API_CLEA_VENTILATION[$ficheTechniqueField->getClimatisationTypeVentilation()];
            }
        }

        return [
            "equipements" => $arrayEquipements
        ];
    }

    public function getCLEANatureChauffageECSBody(array $natureChauffage = []): ?string
    {
        if (!empty($natureChauffage)) {
            foreach ($natureChauffage as $value) {
                if (!empty(APIService::$API_CLEA_CONSTRUCTION_NATURE_CHAUFFAGE[$value])) {
                    return APIService::$API_CLEA_CONSTRUCTION_NATURE_CHAUFFAGE[$value];
                }
            }
        }
        return null;
    }

    public function getCLEADocumentUploadBody(?Demande_ $demande, int $remboursementId): ?array
    {
        if (empty($demande))
            return null;

        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $this->remboursementRepository->find($remboursementId);
        $ficheTechniqueField = $this->getFicheTechniqueField($demande, $remboursement);
        $arrayFilesData = [];
        $cptFiles = 0;

        if (!empty($demande->getDemandeTravaux()->getTravauxDevisId())) {
            /**
             * @var Demande_travaux_devis $demandeTravauxDevis
             */
            $demandeTravauxDevis = $this->demande_travaux_devisRepository->find($demande->getDemandeTravaux()->getTravauxDevisId());
            if (!empty($demandeTravauxDevis->getAuditUrl())) {
                // ETAPE
                $arrayFilesData[$cptFiles]['api_clea_document_avant_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_AUDIT_AVANT_TRAVAUX_ETAPE_CODE;

                $arrayFilesData[$cptFiles]['fileAlt'] = $demandeTravauxDevis->getAuditAlt();
                $arrayFilesData[$cptFiles]['filePath'] = $this->appRootFolderDataSymfony . $demandeTravauxDevis->audit_getWebPath();
                $arrayFilesData[$cptFiles]['nomDocumentSlug'] = APIService::API_CLEA_DOCUMENT_SLUG_ATTESTATIONS_PERFORMANCE;

                // ETAPE
                if (!empty($ficheTechniqueField)) {
                    if (!empty($ficheTechniqueField->getInfiltrometrieDocumentUrl())) {
                        $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_INFILTROMETRIE_ETAPE_CODE;
                    } elseif (!empty($ficheTechniqueField->getVentilationDocumentUrl())) {
                        $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_VENTILATION_ETAPE_CODE;
                    } elseif (!empty($ficheTechniqueField->getAuditApresTravauxDocumentUrl())) {
                        $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_AUDIT_MIS_A_JOUR_APRES_TRAVAUX_ETAPE_CODE;
                    }
                } elseif (!empty($remboursement) && !empty($remboursement->getRemboursementTravaux()->getInstruction()->getRemboursementTravauxInstructionConformite())) {
                    $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_CODE;
                }

                $cptFiles++;
            }
        }

        if (!empty($ficheTechniqueField)) {
            if (!empty($ficheTechniqueField->getInfiltrometrieDocumentUrl())) {
                // ETAPE
                $arrayFilesData[$cptFiles]['api_clea_document_avant_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_INFILTROMETRIE_ETAPE_CODE;

                $arrayFilesData[$cptFiles]['fileAlt'] = $ficheTechniqueField->getInfiltrometrieDocumentAlt();
                $arrayFilesData[$cptFiles]['nomDocumentSlug'] = APIService::API_CLEA_DOCUMENT_SLUG_TRAVAUX;
                $arrayFilesData[$cptFiles]['filePath'] = $this->appRootFolderDataSymfony . $ficheTechniqueField->infiltrometrieDocument_getWebPath();

                // ETAPE
                if (!empty($ficheTechniqueField)) {
                    if (!empty($ficheTechniqueField->getVentilationDocumentUrl())) {
                        $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_VENTILATION_ETAPE_CODE;
                    } elseif (!empty($ficheTechniqueField->getAuditApresTravauxDocumentUrl())) {
                        $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_AUDIT_MIS_A_JOUR_APRES_TRAVAUX_ETAPE_CODE;
                    }
                } elseif (!empty($remboursement) && !empty($remboursement->getRemboursementTravaux()->getInstruction()->getRemboursementTravauxInstructionConformite())) {
                    $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_CODE;
                }

                $cptFiles++;
            }

            if (!empty($ficheTechniqueField->getVentilationDocumentUrl())) {
                // ETAPE
                $arrayFilesData[$cptFiles]['api_clea_document_avant_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_VENTILATION_ETAPE_CODE;

                $arrayFilesData[$cptFiles]['fileAlt'] = $ficheTechniqueField->getVentilationDocumentAlt();
                $arrayFilesData[$cptFiles]['nomDocumentSlug'] = APIService::API_CLEA_DOCUMENT_SLUG_TRAVAUX;
                $arrayFilesData[$cptFiles]['filePath'] = $this->appRootFolderDataSymfony . $ficheTechniqueField->ventilationDocument_getWebPath();

                // ETAPE
                if (!empty($ficheTechniqueField)) {
                    if (!empty($ficheTechniqueField->getAuditApresTravauxDocumentUrl())) {
                        $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_AUDIT_MIS_A_JOUR_APRES_TRAVAUX_ETAPE_CODE;
                    }
                } elseif (!empty($remboursement) && !empty($remboursement->getRemboursementTravaux()->getInstruction()->getRemboursementTravauxInstructionConformite())) {
                    $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_CODE;
                }

                $cptFiles++;
            }

            if (!empty($ficheTechniqueField->getAuditApresTravauxDocumentUrl())) {
                // ETAPE
                $arrayFilesData[$cptFiles]['api_clea_document_avant_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_AUDIT_MIS_A_JOUR_APRES_TRAVAUX_ETAPE_CODE;

                $arrayFilesData[$cptFiles]['fileAlt'] = $ficheTechniqueField->getAuditApresTravauxDocumentAlt();
                $arrayFilesData[$cptFiles]['nomDocumentSlug'] = APIService::API_CLEA_DOCUMENT_SLUG_ATTESTATIONS_PERFORMANCE;
                $arrayFilesData[$cptFiles]['filePath'] = $this->appRootFolderDataSymfony . $ficheTechniqueField->auditApresTravauxDocument_getWebPath();

                // ETAPE
                if (!empty($remboursement) && !empty($remboursement->getRemboursementTravaux()->getInstruction()->getRemboursementTravauxInstructionConformite())) {
                    $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_CODE;
                }

                $cptFiles++;
            }
        }

        if (!empty($remboursement)) {
            if (!empty($remboursement->getRemboursementTravaux()->getInstruction()->getRemboursementTravauxInstructionConformite())) {

                /**
                 * @var Remboursement_travaux_instruction_conformite $remboursementTravauxInstructionConformite
                 */
                foreach ($remboursement->getRemboursementTravaux()->getInstruction()->getRemboursementTravauxInstructionConformite() as $remboursementTravauxInstructionConformite) {
                    $arrayFilesData[$cptFiles]['fileAlt'] = $remboursementTravauxInstructionConformite->getDocumentAlt();
                    $arrayFilesData[$cptFiles]['nomDocumentSlug'] = APIService::API_CLEA_DOCUMENT_SLUG_TRAVAUX;
                    $arrayFilesData[$cptFiles]['filePath'] = $this->appRootFolderDataSymfony . $remboursementTravauxInstructionConformite->document_getWebPath();

                    // ETAPE
                    $arrayFilesData[$cptFiles]['api_clea_document_avant_upload_etape_code'] = APIService::API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_CODE;

                    // ETAPE
                    $arrayFilesData[$cptFiles]['api_clea_document_apres_upload_etape_code'] = APIService::API_CLEA_EQUIPEMENT_ADD_ETAPE_CODE;

                    $cptFiles++;
                }
            }
        }

        return $arrayFilesData;
    }

    public function doEtapeCLEAAndGetData(
        Demande_ &$demande,
        int      $remboursementId,
        int      $remboursementStatutId,
        string   $accessTtoken,
        int      $etape,
        ?int     $carnetId = null
    ): array
    {
        $CLEAStatus = null;
        $CLEAMessage = null;
        $CLEADetail = null;

        switch ($etape) {
            case APIService::API_CLEA_CARNET_CREATE_ETAPE_CODE:
                // ETAPE
                $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_CARNET_CREATE_ETAPE_CODE);

                $dataBodyArrayCarnetCreate = $this->getCLEACarnetCreateBody($demande, $remboursementId);
                $returnArrayCarnetCreate = $this->APIService->getCLEACarnetCreate(
                    $accessTtoken,
                    $dataBodyArrayCarnetCreate
                );
                $CLEAMessage = !empty($returnArrayCarnetCreate['message']) ? $returnArrayCarnetCreate['message'] : '';
                $CLEADetail = '';
                if (!empty($returnArrayCarnetCreate['errors'])) {
                    $CLEADetail = json_encode($returnArrayCarnetCreate['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                /* /////////////////////////////////////////////////////////////////
                                        FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                $this->historiqueSave(
                    $demande,
                    $remboursementId,
                    $remboursementStatutId,
                    'Carnet d\'information CLÉA / Création d\'un carnet : ' . $CLEAMessage
                );

                $CLEAStatus = $returnArrayCarnetCreate['status'];

                if (APIService::API_CLEA_CODE_STATUS_SUCCESS == $CLEAStatus) {
                    // ETAPE
                    $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_CARNET_ADD_OWNER_ETAPE_CODE);

                    if (!empty($returnArrayCarnetCreate['id'])) {
                        $demande->setCarnetInformationCLEAId($returnArrayCarnetCreate['id']);
                        $carnetId = $returnArrayCarnetCreate['id'];
                    }
                }
                break;

            case APIService::API_CLEA_CARNET_ADD_OWNER_ETAPE_CODE:
                // ETAPE
                $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_CARNET_ADD_OWNER_ETAPE_CODE);
                $dataBodyArrayCarnetAddOwner = $this->getCLEACarnetAddOwnerBody($demande->getBeneficiaireId());

                $returnArrayCarnetAddOwner = $this->APIService->getCLEACarnetAddOwner(
                    $accessTtoken,
                    $dataBodyArrayCarnetAddOwner,
                    $carnetId
                );

                $CLEAMessage = !empty($returnArrayCarnetAddOwner['message']) ? $returnArrayCarnetAddOwner['message'] : '';
                $CLEADetail = !empty($returnArrayCarnetAddOwner['detail']) ? $returnArrayCarnetAddOwner['detail'] : '';

                /* /////////////////////////////////////////////////////////////////
                                        FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                $this->historiqueSave(
                    $demande,
                    $remboursementId,
                    $remboursementStatutId,
                    'Carnet d\'information CLÉA / Ajout d\'un propriétaire à un Logement : ' . $CLEAMessage
                );

                $CLEAStatus = $returnArrayCarnetAddOwner['status'];
                if (APIService::API_CLEA_CODE_STATUS_SUCCESS == $CLEAStatus) {
                    // ETAPE
                    $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_DOCUMENT_UPLOAD_CODE);
                }
                break;

            case APIService::API_CLEA_DOCUMENT_UPLOAD_CODE:
            case APIService::API_CLEA_DOCUMENT_UPLOAD_AUDIT_AVANT_TRAVAUX_ETAPE_CODE:
            case APIService::API_CLEA_DOCUMENT_UPLOAD_INFILTROMETRIE_ETAPE_CODE:
            case APIService::API_CLEA_DOCUMENT_UPLOAD_VENTILATION_ETAPE_CODE:
            case APIService::API_CLEA_DOCUMENT_UPLOAD_AUDIT_MIS_A_JOUR_APRES_TRAVAUX_ETAPE_CODE:
            case APIService::API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_CODE:
                // ETAPE
                $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_DOCUMENT_UPLOAD_CODE);
                $dataBodyArrayCarnetDocumentUpload = $this->getCLEADocumentUploadBody($demande, $remboursementId);
                /* /////////////////////////////////////////////////////////////////
                                   ON PARCOURS TOUS LES FICHIERS
                                      ET ON ENVOIE UN PAR UN
                ///////////////////////////////////////////////////////////////// */
                $isAllUploadOk = true;
                foreach ($dataBodyArrayCarnetDocumentUpload as $rowDataCarnetDocumentUpload) {

                    // ETAPE
                    $demande->setCarnetInformationCLEAEtapeCode($rowDataCarnetDocumentUpload['api_clea_document_avant_upload_etape_code']);
                    $returnArrayDocumentUpload = $this->APIService->getCLEADocumentUpload(
                        $accessTtoken,
                        $rowDataCarnetDocumentUpload,
                        $carnetId
                    );

                    $CLEAMessage = !empty($returnArrayDocumentUpload['message']) ? $returnArrayDocumentUpload['message'] : '';
                    $CLEADetail = !empty($returnArrayDocumentUpload['detail']) ? $returnArrayDocumentUpload['detail'] : '';

                    /* /////////////////////////////////////////////////////////////////
                                            FILL UP HISTORIQUE BY FILE
                    ///////////////////////////////////////////////////////////////// */
                    $this->historiqueSave(
                        $demande,
                        $remboursementId,
                        $remboursementStatutId,
                        'Carnet d\'information CLÉA / Ajout d\'un document (' . $rowDataCarnetDocumentUpload['fileAlt'] . ') à un carnet : ' . $CLEAMessage
                    );

                    $CLEAStatus = $returnArrayDocumentUpload['status'];
                    if (APIService::API_CLEA_CODE_STATUS_SUCCESS == $CLEAStatus) {
                        // ETAPE
                        $demande->setCarnetInformationCLEAEtapeCode($rowDataCarnetDocumentUpload['api_clea_document_apres_upload_etape_code']);
                    } else {
                        $isAllUploadOk = false;
                        break;
                    }
                }

                if (true === $isAllUploadOk) {
                    // ETAPE
                    $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_EQUIPEMENT_ADD_ETAPE_CODE);
                    $CLEAStatus = APIService::API_CLEA_CODE_STATUS_SUCCESS;
                }
                break;

            case APIService::API_CLEA_EQUIPEMENT_ADD_ETAPE_CODE:
                // ETAPE
                $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_EQUIPEMENT_ADD_ETAPE_CODE);
                $dataBodyArrayEquipementAdd = $this->getCLEAEquipementAddBody($demande, $remboursementId);

                if (!empty($dataBodyArrayEquipementAdd)) {
                    // IL EXISTE DES EQUIPEMENTS A Y AJOUTER

                    // ETAPE
                    $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_EQUIPEMENT_ADD_ETAPE_CODE);

                    $returnArrayEquipementAdd = $this->APIService->getCLEAEquipementAdd(
                        $accessTtoken,
                        $dataBodyArrayEquipementAdd,
                        $carnetId
                    );
                    $CLEAMessage = !empty($returnArrayEquipementAdd['message']) ? $returnArrayEquipementAdd['message'] : '';
                    $CLEADetail = !empty($returnArrayEquipementAdd['detail']) ? $returnArrayEquipementAdd['detail'] : '';

                    /* /////////////////////////////////////////////////////////////////
                                            FILL UP HISTORIQUE
                    ///////////////////////////////////////////////////////////////// */
                    $this->historiqueSave(
                        $demande,
                        $remboursementId,
                        $remboursementStatutId,
                        'Carnet d\'information CLÉA / Ajout d\'une liste d\'équipements au logement : ' . $CLEAMessage
                    );

                    $CLEAStatus = $returnArrayEquipementAdd['status'];
                    if (APIService::API_CLEA_CODE_STATUS_SUCCESS == $CLEAStatus) {
                        // ETAPE
                        $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_CARNET_TRANSFERT_ETAPE_CODE);
                    }
                }
                break;

            case APIService::API_CLEA_CARNET_TRANSFERT_ETAPE_CODE:
                // ETAPE
                $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_CARNET_TRANSFERT_ETAPE_CODE);

                $returnArrayCarnetTransfert = $this->APIService->getCLEACarnetTransfert(
                    $accessTtoken,
                    $carnetId
                );
                $CLEAMessage = !empty($returnArrayCarnetTransfert['message']) ? $returnArrayCarnetTransfert['message'] : '';
                $CLEADetail = !empty($returnArrayCarnetTransfert['detail']) ? $returnArrayCarnetTransfert['detail'] : '';

                /* /////////////////////////////////////////////////////////////////
                                        FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                $this->historiqueSave(
                    $demande,
                    $remboursementId,
                    $remboursementStatutId,
                    'Carnet d\'information CLÉA / Transfert d\'un carnet aux propriétaires: ' . $CLEAMessage
                );

                $CLEAStatus = $returnArrayCarnetTransfert['status'];
                if (APIService::API_CLEA_CODE_STATUS_SUCCESS == $CLEAStatus) {
                    // ETAPE
                    $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_FINISHED_SUCCESS_ETAPE_CODE);
                }
                break;
        }

        return [
            'newEtape' => $demande->getCarnetInformationCLEAEtapeCode(),
            'carnetId' => $carnetId,
            'CLEAStatus' => $CLEAStatus,
            'CLEAMessage' => $CLEAMessage,
            'CLEADetail' => $CLEADetail
        ];
    }

    public function getFicheTechniqueField(Demande_ $demande, Remboursement_ $remboursement)
    {
        /* ///////////////////////////////////////////////////////////////////////
                 GET FICHE TECHNIQUE FIELD - FIN DE CHANTIER (REMBOURSEMENT)
        /////////////////////////////////////////////////////////////////////// */
        $ficheTechniqueField = !empty($remboursement->getRemboursementTravaux())
        && !empty($remboursement->getRemboursementTravaux()->getFicheTechnique())
        && !empty($remboursement->getRemboursementTravaux()->getFicheTechnique()->getFicheTechniqueFinChantier())
            ? $remboursement->getRemboursementTravaux()->getFicheTechnique()->getFicheTechniqueFinChantier() : null;

        if (empty($ficheTechniqueField)) {
            // On recupère celle de la demande
            if (Demande_::DEMANDE_TRAVAUX_TYPE == $demande->getType()) {
                $demandeTravaux = $demande->getDemandeTravaux();
                if (!empty($demandeTravaux->getFicheTechniqueId())) {
                    /* //////////////////////////////////////////////////////////////////////////////////////
                         GET FICHE TECHNIQUE FIELD - PRESCRIPTION/TRAVAUX CORRESPONDANT AUX DEVIS (DEMANDE)
                    ////////////////////////////////////////////////////////////////////////////////////// */
                    $ficheTechnique = $this->ficheTechniqueRepository->find($demandeTravaux->getFicheTechniqueId());
                    $ficheTechniqueField = $ficheTechnique->getFicheTechniquePrescription();
                }
            }
        }
        return $ficheTechniqueField;
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    private function historiqueSave(
        Demande_ $demande,
        int      $remboursementId,
        int      $remboursementStatutId,
        string   $actionLabel
    ): void
    {
        /* /////////////////////////////////////////////////////////////////
                                FILL UP HISTORIQUE
        ///////////////////////////////////////////////////////////////// */
        $this->historiqueService->save(
            $demande->getId(),
            $remboursementStatutId,
            $demande->getType(),
            [User::PARAM_ROLE_AUTOMATE],
            false,
            $actionLabel,
            null,
            null,
            null,
            null,
            null,
            false,
            $remboursementId
        );
    }
}

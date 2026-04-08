<?php

namespace App\Service;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class APIService
{
    const API_CLEA_GET_TOKEN = 'oauth/token';
    const API_CLEA_PARAMETER_CARNET_ID_CODE = '{logement}';
    const API_CLEA_CARNET_CREATE = 'partner-api/carnet/';
    const API_CLEA_CARNET_ADD_OWNER = 'partner-api/carnet/{logement}/add-owner';
    const API_CLEA_DOCUMENT_UPLOAD = 'partner-api/document/{logement}/upload';
    const API_CLEA_EQUIPEMENT_ADD = 'partner-api/equipement/{logement}';
    const API_CLEA_CARNET_TRANSFERT = 'partner-api/carnet/{logement}/transfert';

    const API_CLEA_GET_AUTH_TOKEN_ETAPE_LABEL = 'Récupération du Token d\'authentification';
    const API_CLEA_CARNET_CREATE_ETAPE_LABEL = 'Création d\'un carnet';
    const API_CLEA_CARNET_ADD_OWNER_ETAPE_LABEL = 'Ajout d\'un propritaire à un Logement par son ID';
    const API_CLEA_DOCUMENT_UPLOAD_LABEL = 'Ajout de documents à un carnet';
    const API_CLEA_DOCUMENT_UPLOAD_AUDIT_AVANT_TRAVAUX_ETAPE_LABEL = 'Ajout d\'un document à un carnet: Fichier PDF Audit avant travaux';
    const API_CLEA_DOCUMENT_UPLOAD_INFILTROMETRIE_ETAPE_LABEL = 'Ajout d\'un document à un carnet: Fichier PDF infiltrométrie';
    const API_CLEA_DOCUMENT_UPLOAD_VENTILATION_ETAPE_LABEL = 'Ajout d\'un document à un carnet: Fichier PDF ventilation';
    const API_CLEA_DOCUMENT_UPLOAD_AUDIT_MIS_A_JOUR_APRES_TRAVAUX_ETAPE_LABEL = 'Ajout d\'un document à un carnet: Fichier PDF Audit mis à jour après travaux';
    const API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_LABEL = 'Ajout d\'un document à un carnet: Factures';
    const API_CLEA_EQUIPEMENT_ADD_ETAPE_LABEL = 'Ajout d\'une liste d\'équipements par leurs ID au logement';
    const API_CLEA_CARNET_TRANSFERT_ETAPE_LABEL = 'Transfert d\'un carnet aux propriétaires par son ID';
    const API_CLEA_FINISHED_SUCCESS_ETAPE_LABEL = 'L\'enregistrement du carnet et de toutes ses étapes liées ont effectuées avec succès.';

    const API_CLEA_GET_AUTH_TOKEN_ETAPE_CODE = 10;
    const API_CLEA_CARNET_CREATE_ETAPE_CODE = 20;
    const API_CLEA_CARNET_ADD_OWNER_ETAPE_CODE = 30;
    const API_CLEA_DOCUMENT_UPLOAD_CODE = 40;
    const API_CLEA_DOCUMENT_UPLOAD_AUDIT_AVANT_TRAVAUX_ETAPE_CODE = 50;
    const API_CLEA_DOCUMENT_UPLOAD_INFILTROMETRIE_ETAPE_CODE = 60;
    const API_CLEA_DOCUMENT_UPLOAD_VENTILATION_ETAPE_CODE = 70;
    const API_CLEA_DOCUMENT_UPLOAD_AUDIT_MIS_A_JOUR_APRES_TRAVAUX_ETAPE_CODE = 80;
    const API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_CODE = 90;
    const API_CLEA_EQUIPEMENT_ADD_ETAPE_CODE = 100;
    const API_CLEA_CARNET_TRANSFERT_ETAPE_CODE = 110;
    const API_CLEA_FINISHED_SUCCESS_ETAPE_CODE = 999;

    const API_CLEA_CODE_STATUS_SUCCESS = 'success';
    const API_CLEA_CODE_STATUS_ERROR = 'error';

    const API_CLEA_GENRE_MADAME = 1;
    const API_CLEA_GENRE_MONSIEUR = 0;

    const API_CLEA_NATURE_CHAUFFAGE_ELECTRICITE = 'ELECTRICITE';
    const API_CLEA_NATURE_CHAUFFAGE_BOIS = 'BOIS';
    const API_CLEA_NATURE_CHAUFFAGE_GAZ = 'GAZ';
    const API_CLEA_NATURE_CHAUFFAGE_FIOUL = 'FIOUL';
    const API_CLEA_NATURE_CHAUFFAGE_AUTRE = 'AUTRE';

    const API_CLEA_DOCUMENT_SLUG_TRAVAUX = 'cil-travaux-realises';
    const API_CLEA_DOCUMENT_SLUG_ATTESTATIONS_PERFORMANCE = 'cil-attestations-performance-energetique-dpe-audit';

    const API_CLEA_VENTILATION_VENTILATEUR_VMC_CODE = 31;
    const API_CLEA_VENTILATION_ECHANGEUR_VMC_DOUBLE_FLUX_CODE = 32;
    const API_CLEA_VENTILATION_NATURELLE_CODE = 57;
    const API_CLEA_VENTILATION_VMC_DOUBLE_FLUX_CODE = 61;
    const API_CLEA_VENTILATION_VMC_SIMPLE_FLUX_CODE = 181;

    const NORMANDIE_CIVILITE_MADAME = 0;
    const NORMANDIE_CIVILITE_MONSIEUR = 1;

    const NORMANDIE_CHAUFFAGE_ECS_FIOUL = '11 | fioul';
    const NORMANDIE_CHAUFFAGE_ECS_GAZ_NATUREL = '12 | gaz naturel';
    const NORMANDIE_CHAUFFAGE_ECS_GAZ_PROPANE = '13 | gaz propane';
    const NORMANDIE_CHAUFFAGE_ECS_ELECTRICITE = '14 | electricite';
    const NORMANDIE_CHAUFFAGE_ECS_POMPE_CHALEUR = '15 | pompe chaleur';
    const NORMANDIE_CHAUFFAGE_ECS_SOLAIRE_THERMIQUE = '16 | solaire thermique';
    const NORMANDIE_CHAUFFAGE_ECS_SOLAIRE_PHOTOVOLTAIQUE = '17 | solaire photvoltaique';
    const NORMANDIE_CHAUFFAGE_ECS_CHARBON = '18 | charbon';
    const NORMANDIE_CHAUFFAGE_ECS_BOIS = '19 | bois';

    const NORMANDIE_VENTILATION_NATURELLE = '20 | naturelle';
    const NORMANDIE_VENTILATION_DOUBLE_FLUX = '21 | double flux';
    const NORMANDIE_VENTILATION_SIMPLE_FLUX = '22 | simple flux';
    const NORMANDIE_VENTILATION_SIMPLE_FLUX_AUTOREGLABLE = '23 | simple flux autoreglable';
    const NORMANDIE_VENTILATION_SIMPLE_FLUX_B = '24 | simple flux B';
    const NORMANDIE_VENTILATION_SIMPLE_FLUX_A = '25 | simple flux A';
    const NORMANDIE_VENTILATION_VMR = '26 | vmr';
    const NORMANDIE_VENTILATION_VMI = '27 | vmi';

    public static $API_CLEA_DPE_ETIQUETTE_ENERGETIQUE = [
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G'
    ];

    public static $API_CLEA_GENRE_BY_CIVILITE = [
        self::NORMANDIE_CIVILITE_MADAME => self::API_CLEA_GENRE_MADAME,
        self::NORMANDIE_CIVILITE_MONSIEUR => self::API_CLEA_GENRE_MONSIEUR
    ];

    public static $API_CLEA_CONSTRUCTION_NATURE_CHAUFFAGE = [
        self::NORMANDIE_CHAUFFAGE_ECS_ELECTRICITE => self::API_CLEA_NATURE_CHAUFFAGE_ELECTRICITE,
        self::NORMANDIE_CHAUFFAGE_ECS_POMPE_CHALEUR => self::API_CLEA_NATURE_CHAUFFAGE_ELECTRICITE,
        self::NORMANDIE_CHAUFFAGE_ECS_SOLAIRE_PHOTOVOLTAIQUE => self::API_CLEA_NATURE_CHAUFFAGE_ELECTRICITE,
        self::NORMANDIE_CHAUFFAGE_ECS_BOIS => self::API_CLEA_NATURE_CHAUFFAGE_BOIS,
        self::NORMANDIE_CHAUFFAGE_ECS_GAZ_NATUREL => self::API_CLEA_NATURE_CHAUFFAGE_GAZ,
        self::NORMANDIE_CHAUFFAGE_ECS_GAZ_PROPANE => self::API_CLEA_NATURE_CHAUFFAGE_GAZ,
        self::NORMANDIE_CHAUFFAGE_ECS_FIOUL => self::API_CLEA_NATURE_CHAUFFAGE_FIOUL,
        self::NORMANDIE_CHAUFFAGE_ECS_SOLAIRE_THERMIQUE => self::API_CLEA_NATURE_CHAUFFAGE_AUTRE,
        self::NORMANDIE_CHAUFFAGE_ECS_CHARBON => self::API_CLEA_NATURE_CHAUFFAGE_AUTRE,
    ];

    // Equipement ventilation : correspondances Normandie / CLEA
    public static $API_CLEA_VENTILATION = [
        self::NORMANDIE_VENTILATION_NATURELLE => [
            self::API_CLEA_VENTILATION_NATURELLE_CODE
        ],
        self::NORMANDIE_VENTILATION_DOUBLE_FLUX => [
            self::API_CLEA_VENTILATION_ECHANGEUR_VMC_DOUBLE_FLUX_CODE,
            self::API_CLEA_VENTILATION_VMC_DOUBLE_FLUX_CODE
        ],
        self::NORMANDIE_VENTILATION_SIMPLE_FLUX => [
            self::API_CLEA_VENTILATION_VENTILATEUR_VMC_CODE,
            self::API_CLEA_VENTILATION_VMC_SIMPLE_FLUX_CODE
        ],
        self::NORMANDIE_VENTILATION_SIMPLE_FLUX_AUTOREGLABLE => [
            self::API_CLEA_VENTILATION_VENTILATEUR_VMC_CODE,
            self::API_CLEA_VENTILATION_VMC_SIMPLE_FLUX_CODE
        ],
        self::NORMANDIE_VENTILATION_SIMPLE_FLUX_B => [
            self::API_CLEA_VENTILATION_VENTILATEUR_VMC_CODE,
            self::API_CLEA_VENTILATION_VMC_SIMPLE_FLUX_CODE
        ],
        self::NORMANDIE_VENTILATION_SIMPLE_FLUX_A => [
            self::API_CLEA_VENTILATION_VENTILATEUR_VMC_CODE,
            self::API_CLEA_VENTILATION_VMC_SIMPLE_FLUX_CODE
        ],
        self::NORMANDIE_VENTILATION_VMR => [
            self::API_CLEA_VENTILATION_VENTILATEUR_VMC_CODE,
            self::API_CLEA_VENTILATION_VMC_SIMPLE_FLUX_CODE
        ],
        self::NORMANDIE_VENTILATION_VMI => [
            self::API_CLEA_VENTILATION_ECHANGEUR_VMC_DOUBLE_FLUX_CODE,
            self::API_CLEA_VENTILATION_VMC_DOUBLE_FLUX_CODE
        ]
    ];

    public static $API_CLEA_ETAPE = [
        self::API_CLEA_GET_AUTH_TOKEN_ETAPE_CODE => self::API_CLEA_GET_AUTH_TOKEN_ETAPE_LABEL,
        self::API_CLEA_CARNET_CREATE_ETAPE_CODE => self::API_CLEA_CARNET_CREATE_ETAPE_LABEL,
        self::API_CLEA_CARNET_ADD_OWNER_ETAPE_CODE => self::API_CLEA_CARNET_ADD_OWNER_ETAPE_LABEL,
        self::API_CLEA_DOCUMENT_UPLOAD_CODE => self::API_CLEA_DOCUMENT_UPLOAD_LABEL,
        self::API_CLEA_DOCUMENT_UPLOAD_AUDIT_AVANT_TRAVAUX_ETAPE_CODE => self::API_CLEA_DOCUMENT_UPLOAD_AUDIT_AVANT_TRAVAUX_ETAPE_LABEL,
        self::API_CLEA_DOCUMENT_UPLOAD_INFILTROMETRIE_ETAPE_CODE => self::API_CLEA_DOCUMENT_UPLOAD_INFILTROMETRIE_ETAPE_LABEL,
        self::API_CLEA_DOCUMENT_UPLOAD_VENTILATION_ETAPE_CODE => self::API_CLEA_DOCUMENT_UPLOAD_VENTILATION_ETAPE_LABEL,
        self::API_CLEA_DOCUMENT_UPLOAD_AUDIT_MIS_A_JOUR_APRES_TRAVAUX_ETAPE_CODE => self::API_CLEA_DOCUMENT_UPLOAD_AUDIT_MIS_A_JOUR_APRES_TRAVAUX_ETAPE_LABEL,
        self::API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_CODE => self::API_CLEA_DOCUMENT_UPLOAD_FACTURES_ETAPE_LABEL,
        self::API_CLEA_EQUIPEMENT_ADD_ETAPE_CODE => self::API_CLEA_EQUIPEMENT_ADD_ETAPE_LABEL,
        self::API_CLEA_CARNET_TRANSFERT_ETAPE_CODE => self::API_CLEA_CARNET_TRANSFERT_ETAPE_LABEL,
        self::API_CLEA_FINISHED_SUCCESS_ETAPE_CODE => self::API_CLEA_FINISHED_SUCCESS_ETAPE_LABEL
    ];
    private HttpClientInterface $httpClient;
    private ?string $baseUrl;
    private ?string $clientId;
    private ?string $clientSecret;

    public function __construct(
        HttpClientInterface $httpClient,
        string              $appApiCleaBaseUrl,
        string              $appApiCleaClientId,
        string              $appApiCleaClientSecret
    )
    {
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim($appApiCleaBaseUrl, '/') . '/';
        $this->clientId = $appApiCleaClientId;
        $this->clientSecret = $appApiCleaClientSecret;
    }

    /* ==========================================================
       AUTH
    ========================================================== */

    public function callApiCLEAGetAuthToken(): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . self::API_CLEA_GET_TOKEN, [
                'json' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            return $response->toArray(false);
        } catch (\Throwable $e) {
            return [
                'status' => self::API_CLEA_CODE_STATUS_ERROR,
                'message' => $e->getMessage(),
            ];
        }
    }

    /* ==========================================================
       CARNET
    ========================================================== */

    public function getCLEACarnetCreate(string $token, array $data): array
    {
        return $this->postJson(
            self::API_CLEA_CARNET_CREATE,
            $token,
            $data
        );
    }

    public function getCLEACarnetAddOwner(string $token, array $data, string $carnetId): array
    {
        $endpoint = str_replace(self::API_CLEA_PARAMETER_CARNET_ID_CODE, $carnetId, self::API_CLEA_CARNET_ADD_OWNER);

        return $this->postJson($endpoint, $token, $data);
    }

    public function getCLEACarnetTransfert(string $token, string $carnetId): array
    {
        $endpoint = str_replace(self::API_CLEA_PARAMETER_CARNET_ID_CODE, $carnetId, self::API_CLEA_CARNET_TRANSFERT);

        return $this->postJson($endpoint, $token, []);
    }

    public function getCLEAEquipementAdd(string $token, array $data, string $carnetId): array
    {
        $endpoint = str_replace(self::API_CLEA_PARAMETER_CARNET_ID_CODE, $carnetId, self::API_CLEA_EQUIPEMENT_ADD);

        return $this->postJson($endpoint, $token, $data);
    }

    /* ==========================================================
       DOCUMENT UPLOAD
    ========================================================== */

    public function getCLEADocumentUpload(string $token, array $fileData, string $carnetId): array
    {
        $endpoint = str_replace(self::API_CLEA_PARAMETER_CARNET_ID_CODE, $carnetId, self::API_CLEA_DOCUMENT_UPLOAD);

        try {
            $formData = new FormDataPart([
                'nom_document_obligatoire_slug' => $fileData['nomDocumentSlug'],
                'documents[]' => DataPart::fromPath($fileData['filePath']),
            ]);

            $response = $this->httpClient->request('POST', $this->baseUrl . $endpoint, [
                'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ] + $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToIterable(),
            ]);

            $content = $response->getContent(false);

            return json_decode($content, true) ?: [];
        } catch (\Throwable $e) {
            return [
                'status' => self::API_CLEA_CODE_STATUS_ERROR,
                'message' => $e->getMessage(),
            ];
        }
    }

    /* ==========================================================
       INTERNAL HELPER
    ========================================================== */

    private function postJson(string $endpoint, string $token, array $payload): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . $endpoint, [
                'json' => $payload,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            return $response->toArray(false);
        } catch (\Throwable $e) {
            return [
                'status' => self::API_CLEA_CODE_STATUS_ERROR,
                'message' => $e->getMessage(),
            ];
        }
    }
}

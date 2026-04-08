<?php

namespace App\Entity;

use App\Repository\Demande_travaux_devis_uploadRepository;
use App\Service\RollbackDocumentService;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "demande_travaux_devis_upload")]
#[ORM\Entity(repositoryClass: Demande_travaux_devis_uploadRepository::class)]
class Demande_travaux_devis_upload
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "type", type: "string", length: 35, nullable: true)]
    #[Assert\NotBlank]
    private ?string $type = null;

    #[ORM\Column(name: "biosource", type: "string", length: 20, nullable: true)]
    private ?string $biosource = null;

    #[ORM\Column(name: "montant", type: "float", nullable: true)]
    #[Assert\NotBlank]
    private ?float $montant = null;

    #[ORM\Column(name: "entreprise_RGE", type: "string", length: 20, nullable: true)]
    private ?string $entrepriseRGE = null;

    #[ORM\Column(name: "bonification", type: "smallint", nullable: true)]
    private ?int $bonification = null;

    #[ORM\Column(name: "devis_document_url", type: "string", length: 255, nullable: true)]
    private ?string $devisDocument_url = null;

    #[ORM\Column(name: "devis_document_alt", type: "string", length: 255, nullable: true)]
    private ?string $devisDocument_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $devisDocument = null;

    private ?string $tempFilename = null;

    /*
     * CONSTANTES
     */
    const BIOSOURCE_OUI = '0 | oui';
    const BIOSOURCE_NON = '1 | non';

    const BONIFICATION_NON_ID                 = 1;
    const BONIFICATION_CRITERE_BIOSOURCE_ID   = 2;
    const BONIFICATION_CRITERE_ENR_ID         = 3;
    const BONIFICATION_CRITERE_VENTILATION_ID = 4;

    const BONIFICATION_NON_LABEL                 = 'NON';
    const BONIFICATION_CRITERE_BIOSOURCE_LABEL   = 'Critère Biosourcé';
    const BONIFICATION_CRITERE_ENR_LABEL         = 'Critère ENR';
    const BONIFICATION_CRITERE_VENTILATION_LABEL = 'Critère Ventilation';

    /**
     * @var array
     */
    public static $ARRAY_BIOSOURCE = [
        'OUI' => self::BIOSOURCE_OUI,
        'NON' => self::BIOSOURCE_NON
    ];

    /**
     * @var array
     */
    public static $arrayBonification = [
        self::BONIFICATION_NON_ID                 => self::BONIFICATION_NON_LABEL,
        self::BONIFICATION_CRITERE_BIOSOURCE_ID   => self::BONIFICATION_CRITERE_BIOSOURCE_LABEL,
        self::BONIFICATION_CRITERE_ENR_ID         => self::BONIFICATION_CRITERE_ENR_LABEL,
        self::BONIFICATION_CRITERE_VENTILATION_ID => self::BONIFICATION_CRITERE_VENTILATION_LABEL
    ];

    const TYPE_TRAVAUX_ISOLATION_COMBLES_KEY                    = '0 | isolationCombles';
    const TYPE_TRAVAUX_ISOLATION_COMBLES_VALUE                  = 'Isolation des combles et ou rampants';
    const TYPE_TRAVAUX_MENUISERIE_KEY                           = '1 | menuiserie';
    const TYPE_TRAVAUX_MENUISERIE_VALUE                         = 'Changement des menuiseries';
    const TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_KEY             = '2 | isolationThermique';
    const TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_VALUE           = 'Isolation thermique des murs';
    const TYPE_TRAVAUX_ISOLATION_PLANCHER_KEY                   = '3 | isolationPlancher';
    const TYPE_TRAVAUX_ISOLATION_PLANCHER_VALUE                 = 'Isolation du plancher';
    const TYPE_TRAVAUX_VENTILATION_KEY                          = '4 | ventilation';
    const TYPE_TRAVAUX_VENTILATION_VALUE                        = 'Ventilation électricité';
    const TYPE_TRAVAUX_CHAUFFAGE_KEY                            = '5 | chauffage';
    const TYPE_TRAVAUX_CHAUFFAGE_VALUE                          = 'Chauffage, eau chaude sanitaire';
    const TYPE_TRAVAUX_ETUDE_KEY                                = '6 | etude';
    const TYPE_TRAVAUX_ETUDE_VALUE                              = 'Etude et infiltrométrie';
    const TYPE_TRAVAUX_AUTRES_KEY                               = '7 | autres';
    const TYPE_TRAVAUX_AUTRES_VALUE                             = 'Autres';
    const TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_INTERIEUR_KEY   = '8 | isolationThermiqueInterieur';
    const TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_INTERIEUR_VALUE = 'Isolation thermique des murs par l\'intérieur';
    const TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_EXTERIEUR_KEY   = '9 | isolationThermiqueExterieur';
    const TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_EXTERIEUR_VALUE = 'Isolation thermique des murs par l\'extérieur';

    public static array $arrayDemandeTypeTravaux = [
        self::TYPE_TRAVAUX_ISOLATION_COMBLES_KEY                  => self::TYPE_TRAVAUX_ISOLATION_COMBLES_VALUE,
        self::TYPE_TRAVAUX_MENUISERIE_KEY                         => self::TYPE_TRAVAUX_MENUISERIE_VALUE,
        self::TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_KEY           => self::TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_VALUE,
        self::TYPE_TRAVAUX_ISOLATION_PLANCHER_KEY                 => self::TYPE_TRAVAUX_ISOLATION_PLANCHER_VALUE,
        self::TYPE_TRAVAUX_VENTILATION_KEY                        => self::TYPE_TRAVAUX_VENTILATION_VALUE,
        self::TYPE_TRAVAUX_CHAUFFAGE_KEY                          => self::TYPE_TRAVAUX_CHAUFFAGE_VALUE,
        self::TYPE_TRAVAUX_ETUDE_KEY                              => self::TYPE_TRAVAUX_ETUDE_VALUE,
        self::TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_INTERIEUR_KEY => self::TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_INTERIEUR_VALUE,
        self::TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_EXTERIEUR_KEY => self::TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_EXTERIEUR_VALUE,
        self::TYPE_TRAVAUX_AUTRES_KEY                             => self::TYPE_TRAVAUX_AUTRES_VALUE,
    ];



    /**
     * Get id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set type
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set biosource
     */
    public function setBiosource(?string $biosource): self
    {
        $this->biosource = $biosource;
        return $this;
    }

    /**
     * Get biosource
     */
    public function getBiosource(): ?string
    {
        return $this->biosource;
    }

    /**
     * Set montant
     */
    public function setMontant(?float $montant): self
    {
        $this->montant = $montant;
        return $this;
    }

    /**
     * Get montant
     */
    public function getMontant(): ?float
    {
        return $this->montant;
    }

    /**
     * Set entrepriseRGE
     */
    public function setEntrepriseRGE(?string $entrepriseRGE): self
    {
        $this->entrepriseRGE = $entrepriseRGE;
        return $this;
    }

    /**
     * Get entrepriseRGE
     */
    public function getEntrepriseRGE(): ?string
    {
        return $this->entrepriseRGE;
    }

    /**
     * Set bonification
     */
    public function setBonification(?int $bonification): self
    {
        $this->bonification = $bonification;
        return $this;
    }

    /**
     * Get bonification
     */
    public function getBonification(): ?int
    {
        return $this->bonification;
    }

    /**
     * Get bonification label
     */
    public function getBonificationLabel(): ?string
    {
        return self::$arrayBonification[$this->bonification] ?? null;
    }

    /**
     * Set devisDocumentUrl
     */
    public function setDevisDocumentUrl(?string $devisDocumentUrl): self
    {
        $this->devisDocument_url = $devisDocumentUrl;
        return $this;
    }

    /**
     * Get devisDocumentUrl
     */
    public function getDevisDocumentUrl(): ?string
    {
        return $this->devisDocument_url;
    }

    /**
     * Set devisDocumentAlt
     */
    public function setDevisDocumentAlt(?string $devisDocumentAlt): self
    {
        $this->devisDocument_alt = $devisDocumentAlt;
        return $this;
    }

    /**
     * Get devisDocumentAlt
     */
    public function getDevisDocumentAlt(): ?string
    {
        return $this->devisDocument_alt;
    }

    /**
     * File upload functions for devis documents
     */
    public function getDevisDocument(): ?UploadedFile
    {
        return $this->devisDocument;
    }

    public function setDevisDocument(?UploadedFile $devisDocument): self
    {
        $this->devisDocument = $devisDocument;

        if (null !== $this->devisDocument_url) {
            $this->tempFilename = $this->devisDocument_url;
            $this->devisDocument_url = null;
            $this->devisDocument_alt = null;
        }

        if ($devisDocument !== null) {
            $this->devisDocument_url = $devisDocument->guessExtension();
            $this->devisDocument_alt = $devisDocument->getClientOriginalName();
        }

        return $this;
    }

    public function getTempFilename(): ?string
    {
        return $this->tempFilename;
    }

    public function setTempFilename(?string $tempFilename): void
    {
        $this->tempFilename = $tempFilename;
    }

    /**
     * Devis document upload paths
     */
    public function devisDocument_getUploadDir(): string
    {
        return 'uploads/demande_travaux_devis';
    }

    public function devisDocument_getWebPath(): string
    {
        return $this->devisDocument_getUploadDir() . '/' . $this->getId() . '_devis_document' . '.' . $this->getDevisDocumentUrl();
    }

    public function devisDocument_getRollbackWebPath(): string
    {
        return $this->devisDocument_getUploadDir() . '/' . $this->getId() . '_devis_document' . RollbackDocumentService::$suffixWithExtension;
    }

    public function devisDocument_getRollbackWebPathPrefix(): string
    {
        return $this->devisDocument_getUploadDir() . '/' . $this->getId() . '_devis_document';
    }
}

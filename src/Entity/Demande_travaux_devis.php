<?php

namespace App\Entity;

use App\Repository\Demande_travaux_devisRepository;
use App\Service\RollbackDocumentService;
use App\Validator\Constraints\Renovateur;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

#[ORM\Table(name: "demande_travaux_devis")]
#[ORM\Entity(repositoryClass: Demande_travaux_devisRepository::class)]
#[ORM\Index(name: "beneficiaire_idx", columns: ["beneficiaire_id"])]
#[ORM\Index(name: "logement_idx", columns: ["logement_id"])]
#[ORM\Index(name: "auditeur_idx", columns: ["auditeur_id"])]
#[ORM\Index(name: "renovateur_idx", columns: ["renovateur_id"])]
class Demande_travaux_devis
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "date_creation", type: "datetime")]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column(name: "auteur_creation", type: "string", length: 255)]
    private ?string $auteurCreation = null;

    #[ORM\Column(name: "date_modif", type: "datetime")]
    private ?\DateTime $dateModif = null;

    #[ORM\Column(name: "auteur_modif", type: "string", length: 255)]
    private ?string $auteurModif = null;

    #[ORM\Column(name: "statut_instruction", type: "string", length: 10)]
    public ?string $statutInstruction = null;

    #[ORM\Column(name: "beneficiaire_id", type: "integer")]
    private int $beneficiaire_id;

    #[ORM\Column(name: "logement_id", type: "integer")]
    private int $logement_id;

    #[ORM\ManyToMany(targetEntity: Demande_travaux_devis_upload::class, cascade: ["persist", "remove"])]
    #[Assert\Valid]
    protected $demande_travaux_devis_upload;

    #[ORM\Column(name: "auditeur_id", type: "integer", nullable: true)]
    private ?int $auditeur_id = null;

    #[ORM\Column(name: "total_devis", type: "string", length: 255, nullable: true)]
    private ?string $totalDevis = null;

    #[ORM\Column(name: "is_bonification_aide", type: "boolean", nullable: true)]
    private ?bool $isBonificationAide = null;

    #[ORM\Column(name: "niveau", type: "string", length: 30, nullable: true)]
    private ?string $niveau = null;

    #[ORM\Column(name: "renovateur_id", type: "integer", nullable: true)]
    private ?int $renovateur_id = null;

    #[ORM\Column(name: "aide_anah", type: "float", nullable: true)]
    private ?float $aideAnah = null;

    #[ORM\Column(name: "aide_habiter_mieux", type: "float", nullable: true)]
    private ?float $aideHabiterMieux = null;

    #[ORM\Column(name: "type_ma_prime_renov_serenite_nom", type: "string", length: 255, nullable: true)]
    private ?string $typeMaPrimeRenovSereniteNom = null;

    #[ORM\Column(name: "credit_impot", type: "float", nullable: true)]
    private ?float $creditImpot = null;

    #[ORM\Column(name: "type_ma_prime_renov_nom", type: "string", length: 255, nullable: true)]
    private ?string $typeMaPrimeRenovNom = null;

    #[ORM\Column(name: "aide_region", type: "float", nullable: true)]
    private ?float $aideRegion = null;

    #[ORM\Column(name: "CEE", type: "float", nullable: true)]
    private ?float $CEE = null;

    #[ORM\Column(name: "EcoPTZ", type: "float", nullable: true)]
    private ?float $EcoPTZ = null;

    #[ORM\Column(name: "EcoPTZ_banque", type: "string", length: 255, nullable: true)]
    private ?string $EcoPTZBanque = null;

    #[ORM\Column(name: "fonds_propres", type: "float", nullable: true)]
    private ?float $fondsPropres = null;

    #[ORM\Column(name: "aide_departement", type: "float", nullable: true)]
    private ?float $aideDepartement = null;

    #[ORM\Column(name: "aide_departement_origine", type: "string", length: 255, nullable: true)]
    private ?string $aideDepartementOrigine = null;

    #[ORM\Column(name: "aide_intercommunalite", type: "float", nullable: true)]
    private ?float $aideIntercommunalite = null;

    #[ORM\Column(name: "aide_intercommunalite_origine", type: "string", length: 255, nullable: true)]
    private ?string $aideIntercommunaliteOrigine = null;

    #[ORM\Column(name: "autre_aide", type: "float", nullable: true)]
    private ?float $autreAide = null;

    #[ORM\Column(name: "autre_aide_origine", type: "string", length: 255, nullable: true)]
    private ?string $autreAideOrigine = null;

    #[ORM\Column(name: "autre_pret", type: "float", nullable: true)]
    private ?float $autrePret = null;

    #[ORM\Column(name: "autre_pret_banque", type: "string", length: 255, nullable: true)]
    private ?string $autrePretBanque = null;

    #[ORM\Column(name: "total_plan", type: "string", length: 255, nullable: true)]
    private ?string $totalPlan = null;

    #[ORM\Column(name: "audit_url", type: "string", length: 255, nullable: true)]
    private ?string $audit_url = null;

    #[ORM\Column(name: "audit_alt", type: "string", length: 255, nullable: true)]
    private ?string $audit_alt = null;

    #[Assert\File(maxSize: '10240k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $audit = null;

    #[ORM\Column(name: "acte_engagement_url", type: "string", length: 255, nullable: true)]
    private ?string $acteEngagementUrl = null;

    #[ORM\Column(name: "acte_engagement_alt", type: "string", length: 255, nullable: true)]
    private ?string $acteEngagementAlt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf')]
    private ?UploadedFile $acteEngagement = null;

    private ?string $tempFilename = null;

    #[ORM\Column(name: "instruction_dossier_conforme", type: "string", length: 20, nullable: true)]
    private ?string $instructionDossierConforme = null;

    #[ORM\Column(name: "is_banque_access", type: "boolean")]
    private bool $isBanqueAccess;


    /*
     * CONSTANTES
     */
    const DEMANDE_TYPE_NIVEAU_1_KEY                      = 'Chèque Niveau I';
    const DEMANDE_TYPE_NIVEAU_2_KEY                      = 'Chèque Niveau II';
    const DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_KEY           = 'Chèque Niveau II option rénovateur';
    const DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_KEY         = 'Chèque Niveau BBC';
    const DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_KEY          = 'Chèque Niveau BBC Biosourcé';
    const DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_KEY        = 'Sortie de passoire';
    const DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_KEY         = 'Première étape BBC avec RGE';
    const DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_KEY  = 'Première étape BBC avec Rénovateur';
    const DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_KEY = 'Rénovation globale BBC';

    const DEMANDE_TYPE_NIVEAU_1_VALUE                      = '0 | niveau1';
    const DEMANDE_TYPE_NIVEAU_2_VALUE                      = '1 | niveau2';
    const DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE           = '2 | niveau2renovateur';
    const DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE         = '3 | niveauBBCrenovateur';
    const DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE          = '4 | niveauBBCbiosource';
    const DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE        = '6 | sortiePassoire';
    const DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE         = '7 | premiereEtapeBBCRGE';
    const DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE  = '8 | premiereEtapeBBCRenovateur';
    const DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE = '9 | renovationGobaleBBC';

    const DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_WITHOUT_SEPARATOR_VALUE = '6sortiePassoire';

    // CODES DETAIL DEMANDE TRAVAUX NIVEAU
    const DEMANDE_TRAVAUX_NIVEAU_A_DEFINIR_CODE                   = 3;
    const DEMANDE_TRAVAUX_NIVEAU_AUTRE_CODE                       = 99;
    const DEMANDE_TRAVAUX_NIVEAU_1_CODE                           = 30;
    const DEMANDE_TRAVAUX_NIVEAU_2_CODE                           = 31;
    const DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE                = 32;
    const DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_CODE              = 33;
    const DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE         = 331;
    const DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE         = 332;
    const DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_CODE               = 34;
    const DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE          = 341;
    const DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE          = 342;
    const DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE             = 36;
    const DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE              = 37;
    const DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_CODE       = 38;
    const DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE  = 381;
    const DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE  = 382;
    const DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_CODE      = 39;
    const DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE = 391;
    const DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE = 392;

    // LABELS DETAIL DEMANDE TRAVAUX NIVEAU
    const DEMANDE_TRAVAUX_NIVEAU_A_DEFINIR_LABEL                   = 'Travaux à définir';
    const DEMANDE_TRAVAUX_NIVEAU_AUTRE_LABEL                       = 'Autre';
    const DEMANDE_TRAVAUX_NIVEAU_1_LABEL                           = 'Travaux Niveau 1';
    const DEMANDE_TRAVAUX_NIVEAU_2_LABEL                           = 'Travaux Niveau 2';
    const DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_LABEL                = 'Travaux Niveau 2 - Rénovateur BBC';
    const DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_LABEL         = 'Chèque 1 travaux BBC';
    const DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_LABEL         = 'Chèque 2 travaux BBC';
    const DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_LABEL          = 'Chèque 1 travaux BBC Biosourcé';
    const DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_LABEL          = 'Chèque 2 travaux BBC Biosourcé';
    const DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_LABEL             = 'Travaux Sortie de passoire';
    const DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_LABEL              = 'Travaux Première étape BBC avec RGE';
    const DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_LABEL  = 'Chèque 1 travaux Première étape BBC avec Rénovateur';
    const DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_LABEL  = 'Chèque 2 travaux Première étape BBC avec Rénovateur';
    const DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_LABEL = 'Chèque 1 travaux Rénovation globale BBC';
    const DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_LABEL = 'Chèque 2 travaux Rénovation globale BBC';

    const BONIFICATION_SUPPLEMENT_AIDE_REGION_MONTANT        = 2000;
    const DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_MONTANT        = 3000;
    const DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_MONTANT         = 2500;
    const DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_MONTANT  = 6500;
    const DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_MONTANT = 10000;

    const RENOVATION_GLOBALE_BBC_MIN_DEVIS_TOTAL                        = 40000;
    const RENOVATION_GLOBALE_BBC_MIN_DEVIS_TOTAL_ENTRE_2_ET_4_FOIS_ANAH = 70000;

    const REVENU_REFERENCE_INFERIEUR_ANAH_KEY                 = '0';
    const REVENU_REFERENCE_COMPRIS_ENTRE_1_ET_2_FOIS_ANAH_KEY = '1';
    const REVENU_REFERENCE_COMPRIS_ENTRE_2_ET_4_FOIS_ANAH_KEY = '2';

    public static $arrayDemandeTypeNiveau = [
        self::DEMANDE_TYPE_NIVEAU_1_KEY                      => self::DEMANDE_TYPE_NIVEAU_1_VALUE,
        self::DEMANDE_TYPE_NIVEAU_2_KEY                      => self::DEMANDE_TYPE_NIVEAU_2_VALUE,
        self::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_KEY           => self::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE,
        self::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_KEY         => self::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE,
        self::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_KEY          => self::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE,
        self::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_KEY        => self::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE,
        self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_KEY         => self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE,
        self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_KEY  => self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE,
        self::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_KEY => self::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE
    ];

    public static $arrayDemandeTypeNiveauForForm = [
        self::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_KEY        => self::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE,
        self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_KEY         => self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE,
        self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_KEY  => self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE,
        self::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_KEY => self::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE
    ];

    public static $arrayDemandeTypeNiveauForFormMontant = [
        self::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_KEY        => self::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_MONTANT,
        self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_KEY         => self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_MONTANT,
        self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_KEY  => self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_MONTANT,
        self::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_KEY => self::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_MONTANT
    ];

    public static $arrayDemandeTypeNiveauForFormToHide = [
        self::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_KEY => self::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE,
        self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_KEY  => self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE
    ];

    public static $arrayTravauxSimpleCheque = [
        self::DEMANDE_TYPE_NIVEAU_1_VALUE,
        self::DEMANDE_TYPE_NIVEAU_2_VALUE,
        self::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE,
        self::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE,
        self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE
    ];

    public static $arrayTravauxDoubleCheque = [
        self::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE,
        self::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE,
        self::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE,
        self::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE
    ];



    /**
     * Demande_travaux_devis constructor.
     */
    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();

        $this->demande_travaux_devis_upload = new \Doctrine\Common\Collections\ArrayCollection();
    }



    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addGetterConstraint('renovateurEmpty', new Renovateur());
    }

    /**
     * Get id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set dateCreation
     */
    public function setDateCreation(\DateTime $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    /**
     * Get dateCreation
     */
    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    /**
     * Set auteurCreation
     */
    public function setAuteurCreation(string $auteurCreation): self
    {
        $this->auteurCreation = $auteurCreation;
        return $this;
    }

    /**
     * Get auteurCreation
     */
    public function getAuteurCreation(): ?string
    {
        return $this->auteurCreation;
    }

    /**
     * Set dateModif
     */
    public function setDateModif(\DateTime $dateModif): self
    {
        $this->dateModif = $dateModif;
        return $this;
    }

    /**
     * Get dateModif
     */
    public function getDateModif(): ?\DateTime
    {
        return $this->dateModif;
    }

    /**
     * Set auteurModif
     */
    public function setAuteurModif(string $auteurModif): self
    {
        $this->auteurModif = $auteurModif;
        return $this;
    }

    /**
     * Get auteurModif
     */
    public function getAuteurModif(): ?string
    {
        return $this->auteurModif;
    }

    /**
     * Set statutInstruction
     */
    public function setStatutInstruction(string $statutInstruction): self
    {
        $this->statutInstruction = $statutInstruction;
        return $this;
    }

    /**
     * Get statutInstruction
     */
    public function getStatutInstruction(): ?string
    {
        return $this->statutInstruction;
    }

    /**
     * Set beneficiaireId
     */
    public function setBeneficiaireId(int $beneficiaireId): self
    {
        $this->beneficiaire_id = $beneficiaireId;
        return $this;
    }

    /**
     * Get beneficiaireId
     */
    public function getBeneficiaireId(): int
    {
        return $this->beneficiaire_id;
    }

    /**
     * Set logementId
     */
    public function setLogementId(int $logementId): self
    {
        $this->logement_id = $logementId;
        return $this;
    }

    /**
     * Get logementId
     */
    public function getLogementId(): int
    {
        return $this->logement_id;
    }

    /**
     * Set auditeurId
     */
    public function setAuditeurId(?int $auditeurId): self
    {
        $this->auditeur_id = $auditeurId;
        return $this;
    }

    /**
     * Get auditeurId
     */
    public function getAuditeurId(): ?int
    {
        return $this->auditeur_id;
    }

    /**
     * Set totalDevis
     */
    public function setTotalDevis(?string $totalDevis): self
    {
        $this->totalDevis = $totalDevis;
        return $this;
    }

    /**
     * Get totalDevis
     */
    public function getTotalDevis(): ?string
    {
        return $this->totalDevis;
    }

    /**
     * Set isBonificationAide
     */
    public function setIsBonificationAide(?bool $isBonificationAide): self
    {
        $this->isBonificationAide = $isBonificationAide;
        return $this;
    }

    /**
     * Get isBonificationAide
     */
    public function getIsBonificationAide(): ?bool
    {
        return $this->isBonificationAide;
    }

    /**
     * Set niveau
     */
    public function setNiveau(?string $niveau): self
    {
        $this->niveau = $niveau;
        return $this;
    }

    /**
     * Get niveau
     */
    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    /**
     * Set renovateurId
     */
    public function setRenovateurId(?int $renovateurId): self
    {
        $this->renovateur_id = $renovateurId;
        return $this;
    }

    /**
     * Get renovateurId
     */
    public function getRenovateurId(): ?int
    {
        return $this->renovateur_id;
    }

    /**
     * Set aideAnah
     */
    public function setAideAnah(?float $aideAnah): self
    {
        $this->aideAnah = $aideAnah;
        return $this;
    }

    /**
     * Get aideAnah
     */
    public function getAideAnah(): ?float
    {
        return $this->aideAnah;
    }

    /**
     * Set aideHabiterMieux
     */
    public function setAideHabiterMieux(?float $aideHabiterMieux): self
    {
        $this->aideHabiterMieux = $aideHabiterMieux;
        return $this;
    }

    /**
     * Get aideHabiterMieux
     */
    public function getAideHabiterMieux(): ?float
    {
        return $this->aideHabiterMieux;
    }

    /**
     * Set typeMaPrimeRenovSereniteNom
     */
    public function setTypeMaPrimeRenovSereniteNom(?string $typeMaPrimeRenovSereniteNom): self
    {
        $this->typeMaPrimeRenovSereniteNom = $typeMaPrimeRenovSereniteNom;
        return $this;
    }

    /**
     * Get typeMaPrimeRenovSereniteNom
     */
    public function getTypeMaPrimeRenovSereniteNom(): ?string
    {
        return $this->typeMaPrimeRenovSereniteNom;
    }

    /**
     * Set creditImpot
     */
    public function setCreditImpot(?float $creditImpot): self
    {
        $this->creditImpot = $creditImpot;
        return $this;
    }

    /**
     * Get creditImpot
     */
    public function getCreditImpot(): ?float
    {
        return $this->creditImpot;
    }

    /**
     * Set typeMaPrimeRenovNom
     */
    public function setTypeMaPrimeRenovNom(?string $typeMaPrimeRenovNom): self
    {
        $this->typeMaPrimeRenovNom = $typeMaPrimeRenovNom;
        return $this;
    }

    /**
     * Get typeMaPrimeRenovNom
     */
    public function getTypeMaPrimeRenovNom(): ?string
    {
        return $this->typeMaPrimeRenovNom;
    }

    /**
     * Set aideRegion
     */
    public function setAideRegion(?float $aideRegion): self
    {
        $this->aideRegion = $aideRegion;
        return $this;
    }

    /**
     * Get aideRegion
     */
    public function getAideRegion(): ?float
    {
        return $this->aideRegion;
    }

    /**
     * Set aideDepartement
     */
    public function setAideDepartement(?float $aideDepartement): self
    {
        $this->aideDepartement = $aideDepartement;
        return $this;
    }

    /**
     * Get aideDepartement
     */
    public function getAideDepartement(): ?float
    {
        return $this->aideDepartement;
    }

    /**
     * Set aideDepartementOrigine
     */
    public function setAideDepartementOrigine(?string $aideDepartementOrigine): self
    {
        $this->aideDepartementOrigine = $aideDepartementOrigine;
        return $this;
    }

    /**
     * Get aideDepartementOrigine
     */
    public function getAideDepartementOrigine(): ?string
    {
        return $this->aideDepartementOrigine;
    }

    /**
     * Set aideIntercommunalite
     */
    public function setAideIntercommunalite(?float $aideIntercommunalite): self
    {
        $this->aideIntercommunalite = $aideIntercommunalite;
        return $this;
    }

    /**
     * Get aideIntercommunalite
     */
    public function getAideIntercommunalite(): ?float
    {
        return $this->aideIntercommunalite;
    }

    /**
     * Set aideIntercommunaliteOrigine
     */
    public function setAideIntercommunaliteOrigine(?string $aideIntercommunaliteOrigine): self
    {
        $this->aideIntercommunaliteOrigine = $aideIntercommunaliteOrigine;
        return $this;
    }

    /**
     * Get aideIntercommunaliteOrigine
     */
    public function getAideIntercommunaliteOrigine(): ?string
    {
        return $this->aideIntercommunaliteOrigine;
    }

    /**
     * Set cEE
     */
    public function setCEE(?float $CEE): self
    {
        $this->CEE = $CEE;
        return $this;
    }

    /**
     * Get cEE
     */
    public function getCEE(): ?float
    {
        return $this->CEE;
    }

    /**
     * Set ecoPTZ
     */
    public function setEcoPTZ(?float $ecoPTZ): self
    {
        $this->EcoPTZ = $ecoPTZ;
        return $this;
    }

    /**
     * Get ecoPTZ
     */
    public function getEcoPTZ(): ?float
    {
        return $this->EcoPTZ;
    }

    /**
     * Set ecoPTZBanque
     */
    public function setEcoPTZBanque(?string $ecoPTZBanque): self
    {
        $this->EcoPTZBanque = $ecoPTZBanque;
        return $this;
    }

    /**
     * Get ecoPTZBanque
     */
    public function getEcoPTZBanque(): ?string
    {
        return $this->EcoPTZBanque;
    }

    /**
     * Set fondsPropres
     */
    public function setFondsPropres(?float $fondsPropres): self
    {
        $this->fondsPropres = $fondsPropres;
        return $this;
    }

    /**
     * Get fondsPropres
     */
    public function getFondsPropres(): ?float
    {
        return $this->fondsPropres;
    }

    /**
     * Set autreAide
     */
    public function setAutreAide(?float $autreAide): self
    {
        $this->autreAide = $autreAide;
        return $this;
    }

    /**
     * Get autreAide
     */
    public function getAutreAide(): ?float
    {
        return $this->autreAide;
    }

    /**
     * Set autreAideOrigine
     */
    public function setAutreAideOrigine(?string $autreAideOrigine): self
    {
        $this->autreAideOrigine = $autreAideOrigine;
        return $this;
    }

    /**
     * Get autreAideOrigine
     */
    public function getAutreAideOrigine(): ?string
    {
        return $this->autreAideOrigine;
    }

    /**
     * Set autrePret
     */
    public function setAutrePret(?float $autrePret): self
    {
        $this->autrePret = $autrePret;
        return $this;
    }

    /**
     * Get autrePret
     */
    public function getAutrePret(): ?float
    {
        return $this->autrePret;
    }

    /**
     * Set autrePretBanque
     */
    public function setAutrePretBanque(?string $autrePretBanque): self
    {
        $this->autrePretBanque = $autrePretBanque;
        return $this;
    }

    /**
     * Get autrePretBanque
     */
    public function getAutrePretBanque(): ?string
    {
        return $this->autrePretBanque;
    }

    /**
     * Set totalPlan
     */
    public function setTotalPlan(?string $totalPlan): self
    {
        $this->totalPlan = $totalPlan;
        return $this;
    }

    /**
     * Get totalPlan
     */
    public function getTotalPlan(): ?string
    {
        return $this->totalPlan;
    }

    /**
     * Set instructionDossierConforme
     */
    public function setInstructionDossierConforme(?string $instructionDossierConforme): self
    {
        $this->instructionDossierConforme = $instructionDossierConforme;
        return $this;
    }

    /**
     * Get instructionDossierConforme
     */
    public function getInstructionDossierConforme(): ?string
    {
        return $this->instructionDossierConforme;
    }

    /**
     * Add demandeTravauxDevisUpload
     */
    public function addDemandeTravauxDevisUpload(Demande_travaux_devis_upload $demandeTravauxDevisUpload): self
    {
        $this->demande_travaux_devis_upload[] = $demandeTravauxDevisUpload;
        return $this;
    }

    /**
     * Remove demandeTravauxDevisUpload
     */
    public function removeDemandeTravauxDevisUpload(Demande_travaux_devis_upload $demandeTravauxDevisUpload): void
    {
        $this->demande_travaux_devis_upload->removeElement($demandeTravauxDevisUpload);
    }

    /**
     * Get demandeTravauxDevisUpload
     */
    public function getDemandeTravauxDevisUpload()
    {
        return $this->demande_travaux_devis_upload;
    }

    /**
     * Set auditUrl
     */
    public function setAuditUrl(?string $auditUrl): self
    {
        $this->audit_url = $auditUrl;
        return $this;
    }

    /**
     * Get auditUrl
     */
    public function getAuditUrl(): ?string
    {
        return $this->audit_url;
    }

    /**
     * Set auditAlt
     */
    public function setAuditAlt(?string $auditAlt): self
    {
        $this->audit_alt = $auditAlt;
        return $this;
    }

    /**
     * Get auditAlt
     */
    public function getAuditAlt(): ?string
    {
        return $this->audit_alt;
    }

    /**
     * Set acteEngagementUrl
     */
    public function setActeEngagementUrl(?string $acteEngagementUrl): self
    {
        $this->acteEngagementUrl = $acteEngagementUrl;
        return $this;
    }

    /**
     * Get acteEngagementUrl
     */
    public function getActeEngagementUrl(): ?string
    {
        return $this->acteEngagementUrl;
    }

    /**
     * Set acteEngagementAlt
     */
    public function setActeEngagementAlt(?string $acteEngagementAlt): self
    {
        $this->acteEngagementAlt = $acteEngagementAlt;
        return $this;
    }

    /**
     * Get acteEngagementAlt
     */
    public function getActeEngagementAlt(): ?string
    {
        return $this->acteEngagementAlt;
    }

    /**
     * Get getAudit
     */
    public function getAudit(): ?UploadedFile
    {
        return $this->audit;
    }

    /**
     * Set audit
     */
    public function setAudit(?UploadedFile $audit): self
    {
        $this->audit = $audit;

        if (null !== $this->audit_url) {
            $this->tempFilename = $this->audit_url;
            $this->audit_url = null;
            $this->audit_alt = null;
        }

        if ($audit !== null) {
            $this->audit_url = $audit->guessExtension();
            $this->audit_alt = $audit->getClientOriginalName();
        }

        return $this;
    }

    /**
     * Get getActeEngagement
     */
    public function getActeEngagement(): ?UploadedFile
    {
        return $this->acteEngagement;
    }

    /**
     * Set acteEngagement
     */
    public function setActeEngagement(?UploadedFile $acteEngagement): self
    {
        $this->acteEngagement = $acteEngagement;

        if (null !== $this->acteEngagementUrl) {
            $this->tempFilename = $this->acteEngagementUrl;
            $this->acteEngagementUrl = null;
            $this->acteEngagementAlt = null;
        }

        if ($acteEngagement !== null) {
            $this->acteEngagementUrl = $acteEngagement->guessExtension();
            $this->acteEngagementAlt = $acteEngagement->getClientOriginalName();
        }

        return $this;
    }

    /**
     * Set isBanqueAccess
     */
    public function setIsBanqueAccess(bool $isBanqueAccess): self
    {
        $this->isBanqueAccess = $isBanqueAccess;
        return $this;
    }

    /**
     * Get isBanqueAccess
     */
    public function getIsBanqueAccess(): bool
    {
        return $this->isBanqueAccess;
    }


    /**
     * Custom validation logic for renovateur field
     */
    public function isRenovateurEmpty(): bool
    {
        if (
            (
                ($this->niveau == '2 | niveau2renovateur')
                || ($this->niveau == '3 | niveauBBCrenovateur')
                || ($this->niveau == '4 | niveauBBCbiosource')
            )
            && !$this->renovateur_id
        ) {
            return true;
        }

        return false;
    }

    /**
     * Audit file upload paths
     */
    public function audit_getUploadDir(): string
    {
        return 'uploads/demande_travaux_devis';
    }

    public function audit_getWebPath(): string
    {
        return $this->audit_getUploadDir() . '/' . $this->getId() . '_audit' . '.' . $this->getAuditUrl();
    }

    public function audit_getRollbackWebPath(): string
    {
        return $this->audit_getUploadDir() . '/' . $this->getId() . '_audit' . RollbackDocumentService::$suffixWithExtension;
    }

    public function audit_getRollbackWebPathPrefix(): string
    {
        return $this->audit_getUploadDir() . '/' . $this->getId() . '_audit';
    }

    /**
     * Acte engagement file upload paths
     */
    public function acteEngagement_getUploadDir(): string
    {
        return 'uploads/demande_travaux_devis';
    }

    public function acteEngagement_getWebPath(): string
    {
        return $this->acteEngagement_getUploadDir() . '/' . $this->getId() . '_acte_engagement' . '.' . $this->getActeEngagementUrl();
    }

    public function acteEngagement_getRollbackWebPath(): string
    {
        return $this->acteEngagement_getUploadDir() . '/' . $this->getId() . '_acte_engagement' . RollbackDocumentService::$suffixWithExtension;
    }

    public function acteEngagement_getRollbackWebPathPrefix(): string
    {
        return $this->acteEngagement_getUploadDir() . '/' . $this->getId() . '_acte_engagement';
    }

    public function getTempFilename(): ?string
    {
        return $this->tempFilename;
    }

    public function setTempFilename(?string $tempFilename): void
    {
        $this->tempFilename = $tempFilename;
    }
}


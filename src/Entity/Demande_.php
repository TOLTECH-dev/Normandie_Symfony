<?php

namespace App\Entity;

use App\Repository\Demande_Repository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "demande_")]
#[ORM\Entity(repositoryClass: Demande_Repository::class)]
#[ORM\Index(name: "statut_idx", columns: ["statut_id"])]
#[ORM\Index(name: "beneficiaire_idx", columns: ["beneficiaire_id"])]
#[ORM\Index(name: "logement_idx", columns: ["logement_id"])]
#[ORM\Index(name: "dateCP_idx", columns: ["dateCP_id"])]
class Demande_
{
    // Info Bulle HTML Constants
    final public const INFO_BULLE_HTML_JUSTIFICATIF_DE_PROPRIETE = 'Taxe foncière (N-1 ou N-2) ou à défaut attestation notariale ou compromis de vente';
    final public const INFO_BULLE_HTML_PIECE_COMPLEMENTAIRE_PARTICULIER = 'Certificat d\'adressage, attestation sur l\'honneur...';
    final public const INFO_BULLE_HTML_PIECE_COMPLEMENTAIRE_SCI = 'Je positionne les statuts de la SCI (personnes physiques et non personnes morales)';
    final public const INFO_BULLE_HTML_AVIS_IMPOSITION_DEMANDEUR_PARTICULIER = 'Avis d\'imposition du demandeur ou du ménage (si déclaration commune)';
    final public const INFO_BULLE_HTML_AVIS_IMPOSITION_DEMANDEUR_SCI = 'Prise en compte du revenu fiscal de référence de chacun des membres';
    final public const INFO_BULLE_HTML_AVIS_IMPOSITION_CONJOINT = 'Avis d\'imposition du conjoint si déclaration séparée';
    final public const INFO_BULLE_HTML_REVENU_FISCAL_CONJOINT = 'A ne renseigner qu\'en cas de déclaration séparée';

    // Label Field Constants
    final public const LABEL_FIELD_AVIS_IMPOSITION_DEMANDEUR_PARTICULIER = 'Avis d\'imposition du demandeur';
    final public const LABEL_FIELD_AVIS_IMPOSITION_DEMANDEUR_SCI = 'Avis d\'imposition des membres de la SCI';
    final public const LABEL_FIELD_NOMBRE_PERSONNE_FOYER_PARTICULIER = 'Nombre de personnes constituant le foyer';
    final public const LABEL_FIELD_NOMBRE_PERSONNE_FOYER_SCI = 'Nombre de personnes constituant la SCI (foyer fiscal)';
    final public const LABEL_FIELD_REVENU_FISCAL_DEMANDEUR_PARTICULIER = 'Revenu fiscal de référence du demandeur (n-1 ou n-2)';
    final public const LABEL_FIELD_REVENU_FISCAL_DEMANDEUR_SCI = 'Revenu fiscal de référence des personnes constituant la SCI (n-1 ou n-2)';
    final public const LABEL_FIELD_REVENU_FISCAL_FOYER_PARTICULIER = 'Revenu fiscal de référence du foyer (n-1 ou n-2)';
    final public const LABEL_FIELD_REVENU_FISCAL_FOYER_SCI = 'Revenu fiscal de référence global (n-1 ou n-2)';

    // Demande Type Constants
    final public const DEMANDE_AUDIT_ENERGIE_TYPE = 1;
    final public const DEMANDE_AUDIT_NUMERIQUE_TYPE = 2;
    final public const DEMANDE_TRAVAUX_TYPE = 3;
    final public const DEMANDE_AUDIT_ENERGIE_REGION_TYPE = 4;
    final public const DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE = 5;

    // Demande Label Constants
    final public const DEMANDE_AUDIT_ENERGIE_LABEL = 'Audit énergétique et scénarios';
    final public const DEMANDE_AUDIT_NUMERIQUE_LABEL = 'Audit Numérique';
    final public const DEMANDE_TRAVAUX_LABEL = 'Travaux';
    final public const DEMANDE_AUDIT_ENERGIE_REGION_LABEL = 'Audit énergétique Région Normandie';
    final public const DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_LABEL = 'Mise à jour Audit énergétique et scénarios';

    /**
     * @var array<int, string>
     */
    public static array $demandeType = [
        self::DEMANDE_AUDIT_ENERGIE_TYPE => self::DEMANDE_AUDIT_ENERGIE_LABEL,
        self::DEMANDE_AUDIT_NUMERIQUE_TYPE => self::DEMANDE_AUDIT_NUMERIQUE_LABEL,
        self::DEMANDE_TRAVAUX_TYPE => self::DEMANDE_TRAVAUX_LABEL,
        self::DEMANDE_AUDIT_ENERGIE_REGION_TYPE => self::DEMANDE_AUDIT_ENERGIE_REGION_LABEL,
        self::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE => self::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_LABEL
    ];

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

    #[ORM\OneToOne(targetEntity: Demande_auditEnergie::class, cascade: ["persist", "remove"])]
    #[Assert\Valid]
    public ?Demande_auditEnergie $demande_auditEnergie = null;

    #[ORM\OneToOne(targetEntity: Demande_auditNumerique::class, cascade: ["persist", "remove"])]
    #[Assert\Valid]
    public ?Demande_auditNumerique $demande_auditNumerique = null;

    #[ORM\OneToOne(targetEntity: Demande_travaux::class, cascade: ["persist", "remove"])]
    #[Assert\Valid]
    public ?Demande_travaux $demande_travaux = null;

    #[ORM\Column(name: "statut_id", type: "integer")]
    private ?int $statut_id = null;

    #[ORM\Column(name: "type", type: "smallint")]
    private ?int $type = null;

    #[ORM\Column(name: "beneficiaire_id", type: "integer")]
    private ?int $beneficiaire_id = null;

    #[ORM\Column(name: "logement_id", type: "integer")]
    private ?int $logement_id = null;

    #[ORM\Column(name: "dateCP_id", type: "integer", nullable: true)]
    private ?int $dateCP_id = null;

    #[ORM\Column(name: "motif_refus", type: "text", nullable: true)]
    #[Assert\Length(max: 245)]
    private ?string $motifRefus = null;

    #[ORM\Column(name: "statut_description", type: "text", nullable: true)]
    private ?string $statutDescription = null;

    #[ORM\Column(name: "carnet_information_token", type: "string", length: 255, nullable: true)]
    private ?string $carnetInformationToken = null;

    #[ORM\Column(name: "carnet_information_requested_at", type: "datetime", nullable: true)]
    private ?\DateTime $carnetInformationRequestedAt = null;

    #[ORM\Column(name: "carnet_information_validated_at", type: "datetime", nullable: true)]
    private ?\DateTime $carnetInformationValidatedAt = null;

    #[ORM\Column(name: "carnet_information_clea_id", type: "integer", nullable: true)]
    private ?int $carnetInformationCLEAId = null;

    #[ORM\Column(name: "carnet_information_clea_etape_code", type: "smallint", nullable: true)]
    private ?int $carnetInformationCLEAEtapeCode = null;

    #[ORM\Column(name: "type_menage", type: "smallint", nullable: true)]
    private ?int $typeMenage = null;

    #[ORM\Column(name: "rgpd", type: "boolean", options: ["default" => false])]
    private bool $rgpd = false;

    /**
     * Demande_ constructor.
     */
    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();
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
    public function setDateCreation(?\DateTime $dateCreation): self
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
    public function setAuteurCreation(?string $auteurCreation): self
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
    public function setDateModif(?\DateTime $dateModif): self
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
    public function setAuteurModif(?string $auteurModif): self
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
     * Set statutId
     */
    public function setStatutId(?int $statutId): self
    {
        $this->statut_id = $statutId;
        return $this;
    }

    /**
     * Get statutId
     */
    public function getStatutId(): ?int
    {
        return $this->statut_id;
    }

    /**
     * Set type
     */
    public function setType(?int $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     */
    public function getType(): ?int
    {
        return $this->type;
    }

    /**
     * Set beneficiaireId
     */
    public function setBeneficiaireId(?int $beneficiaireId): self
    {
        $this->beneficiaire_id = $beneficiaireId;
        return $this;
    }

    /**
     * Get beneficiaireId
     */
    public function getBeneficiaireId(): ?int
    {
        return $this->beneficiaire_id;
    }

    /**
     * Set logementId
     */
    public function setLogementId(?int $logementId): self
    {
        $this->logement_id = $logementId;
        return $this;
    }

    /**
     * Get logementId
     */
    public function getLogementId(): ?int
    {
        return $this->logement_id;
    }

    /**
     * Set demandeAuditEnergie
     */
    public function setDemandeAuditEnergie(?Demande_auditEnergie $demandeAuditEnergie): self
    {
        $this->demande_auditEnergie = $demandeAuditEnergie;
        return $this;
    }

    /**
     * Get demandeAuditEnergie
     */
    public function getDemandeAuditEnergie(): ?Demande_auditEnergie
    {
        return $this->demande_auditEnergie;
    }

    /**
     * Set demandeAuditNumerique
     */
    public function setDemandeAuditNumerique(?Demande_auditNumerique $demandeAuditNumerique): self
    {
        $this->demande_auditNumerique = $demandeAuditNumerique;
        return $this;
    }

    /**
     * Get demandeAuditNumerique
     */
    public function getDemandeAuditNumerique(): ?Demande_auditNumerique
    {
        return $this->demande_auditNumerique;
    }

    /**
     * Set demandeTravaux
     */
    public function setDemandeTravaux(?Demande_travaux $demandeTravaux): self
    {
        $this->demande_travaux = $demandeTravaux;
        return $this;
    }

    /**
     * Get demandeTravaux
     */
    public function getDemandeTravaux(): ?Demande_travaux
    {
        return $this->demande_travaux;
    }

    /**
     * Set dateCPId
     */
    public function setDateCPId(?int $dateCPId): self
    {
        $this->dateCP_id = $dateCPId;
        return $this;
    }

    /**
     * Get dateCPId
     */
    public function getDateCPId(): ?int
    {
        return $this->dateCP_id;
    }

    /**
     * Set motifRefus
     */
    public function setMotifRefus(?string $motifRefus): self
    {
        $this->motifRefus = $motifRefus;
        return $this;
    }

    /**
     * Get motifRefus
     */
    public function getMotifRefus(): ?string
    {
        return $this->motifRefus;
    }

    /**
     * Get typeLabel
     */
    public function getTypeLabel(): ?string
    {
        return self::$demandeType[$this->getType()] ?? null;
    }

    /**
     * Set statutDescription
     */
    public function setStatutDescription(?string $statutDescription): self
    {
        $this->statutDescription = $statutDescription;
        return $this;
    }

    /**
     * Get statutDescription
     */
    public function getStatutDescription(): ?string
    {
        return $this->statutDescription;
    }

    /**
     * Set carnetInformationToken
     */
    public function setCarnetInformationToken(?string $carnetInformationToken): self
    {
        $this->carnetInformationToken = $carnetInformationToken;
        return $this;
    }

    /**
     * Get carnetInformationToken
     */
    public function getCarnetInformationToken(): ?string
    {
        return $this->carnetInformationToken;
    }

    /**
     * Set carnetInformationRequestedAt
     */
    public function setCarnetInformationRequestedAt(?\DateTime $carnetInformationRequestedAt): self
    {
        $this->carnetInformationRequestedAt = $carnetInformationRequestedAt;
        return $this;
    }

    /**
     * Get carnetInformationRequestedAt
     */
    public function getCarnetInformationRequestedAt(): ?\DateTime
    {
        return $this->carnetInformationRequestedAt;
    }

    /**
     * Set carnetInformationValidatedAt
     */
    public function setCarnetInformationValidatedAt(?\DateTime $carnetInformationValidatedAt): self
    {
        $this->carnetInformationValidatedAt = $carnetInformationValidatedAt;
        return $this;
    }

    /**
     * Get carnetInformationValidatedAt
     */
    public function getCarnetInformationValidatedAt(): ?\DateTime
    {
        return $this->carnetInformationValidatedAt;
    }

    /**
     * Set carnetInformationCLEAId
     */
    public function setCarnetInformationCLEAId(?int $carnetInformationCLEAId): self
    {
        $this->carnetInformationCLEAId = $carnetInformationCLEAId;
        return $this;
    }

    /**
     * Get carnetInformationCLEAId
     */
    public function getCarnetInformationCLEAId(): ?int
    {
        return $this->carnetInformationCLEAId;
    }

    /**
     * Set carnetInformationCLEAEtapeCode
     */
    public function setCarnetInformationCLEAEtapeCode(?int $carnetInformationCLEAEtapeCode): self
    {
        $this->carnetInformationCLEAEtapeCode = $carnetInformationCLEAEtapeCode;
        return $this;
    }

    /**
     * Get carnetInformationCLEAEtapeCode
     */
    public function getCarnetInformationCLEAEtapeCode(): ?int
    {
        return $this->carnetInformationCLEAEtapeCode;
    }

    /**
     * Set typeMenage
     */
    public function setTypeMenage(?int $typeMenage): self
    {
        $this->typeMenage = $typeMenage;
        return $this;
    }

    /**
     * Get typeMenage
     */
    public function getTypeMenage(): ?int
    {
        return $this->typeMenage;
    }

    /**
     * Set rgpd
     */
    public function setRgpd(bool $rgpd): self
    {
        $this->rgpd = $rgpd;
        return $this;
    }

    /**
     * Get rgpd
     */
    public function getRgpd(): bool
    {
        return $this->rgpd;
    }
}


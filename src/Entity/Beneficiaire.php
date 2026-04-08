<?php

namespace App\Entity;

use App\Repository\BeneficiaireRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "beneficiaire")]
#[ORM\Entity(repositoryClass: BeneficiaireRepository::class)]
#[ORM\Index(name: "structure_idx", columns: ["structure_id"])]
#[ORM\Index(name: "auditeur_idx", columns: ["auditeur_id"])]
#[ORM\Index(name: "financeur_idx", columns: ["financeur_id"])]
#[ORM\Index(name: "renovateur_idx", columns: ["renovateur_id"])]
#[ORM\Index(name: "user_idx", columns: ["user_id"])]
#[ORM\Index(name: "structure_rattachement_idx", columns: ["structure_rattachement_id"])]
#[ORM\Index(name: "conseiller_rattachement_idx", columns: ["conseiller_rattachement_id"])]
#[ORM\Index(name: "ville_idx", columns: ["ville_id"])]
class Beneficiaire
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private int $id;

    #[ORM\Column(name: "date_creation", type: "datetime")]
    private \DateTime $dateCreation;

    #[ORM\Column(name: "auteur_creation", type: "string", length: 255)]
    private string $auteurCreation;

    #[ORM\Column(name: "date_modif", type: "datetime")]
    private \DateTime $dateModif;

    #[ORM\Column(name: "auteur_modif", type: "string", length: 255)]
    private string $auteurModif;

    #[ORM\Column(name: "type", type: "string", length: 20)]
    private string $type;

    #[ORM\Column(name: "nom_SCI", type: "string", length: 255, nullable: true)]
    private ?string $nomSCI = null;

    #[ORM\Column(name: "civilite", type: "string", length: 20)]
    private string $civilite;

    #[ORM\Column(name: "nom", type: "string", length: 255)]
    #[Assert\NotBlank]
    private string $nom;

    #[ORM\Column(name: "prenom", type: "string", length: 255)]
    #[Assert\NotBlank]
    private string $prenom;

    #[ORM\Column(name: "code_postal", type: "string", length: 20)]
    #[Assert\NotBlank]
    private string $codePostal;

    #[ORM\Column(name: "ville", type: "string", length: 255)]
    #[Assert\NotBlank]
    private string $ville;

    #[ORM\Column(name: "ville_id", type: "integer")]
    private int $villeId;

    #[ORM\Column(name: "INSEE", type: "string", length: 20)]
    #[Assert\NotBlank]
    private string $INSEE;

    #[ORM\Column(name: "numero_rue", type: "integer")]
    #[Assert\NotBlank]
    private int $numeroRue;

    #[ORM\Column(name: "complement_numero_rue", type: "string", length: 20, nullable: true)]
    private ?string $complementNumeroRue = null;

    #[ORM\Column(name: "nom_rue", type: "string", length: 255)]
    #[Assert\NotBlank]
    private string $nomRue;

    #[ORM\Column(name: "nom_rue_not_found", type: "boolean", nullable: true)]
    private ?bool $nomRueNotFound = null;

    #[ORM\Column(name: "complement_1", type: "string", length: 255, nullable: true)]
    private ?string $complement1 = null;

    #[ORM\Column(name: "complement_2", type: "string", length: 255, nullable: true)]
    private ?string $complement2 = null;

    #[ORM\Column(name: "email", type: "string", length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: "tel_1", type: "string", length: 30, nullable: true)]
    private ?string $tel1 = null;

    #[ORM\Column(name: "tel_2", type: "string", length: 30, nullable: true)]
    private ?string $tel2 = null;

    #[ORM\Column(name: "situation_famille", type: "string", length: 20, nullable: true)]
    private ?string $situationFamille = null;

    #[ORM\Column(name: "nom_conjoint", type: "string", length: 255, nullable: true)]
    private ?string $nomConjoint = null;

    #[ORM\Column(name: "prenom_conjoint", type: "string", length: 255, nullable: true)]
    private ?string $prenomConjoint = null;

    #[ORM\Column(name: "nb_pers_foyer", type: "integer")]
    #[Assert\NotBlank]
    private int $nbPersFoyer;

    #[ORM\Column(name: "revenu_fiscal_ref", type: "float")]
    #[Assert\NotBlank]
    private float $revenuFiscalRef;

    #[ORM\Column(name: "known_by_media", type: "boolean", nullable: true)]
    private ?bool $knownByMedia = null;

    #[ORM\Column(name: "structure_id", type: "integer", nullable: true)]
    private ?int $structure_id = null;

    #[ORM\Column(name: "auditeur_id", type: "integer", nullable: true)]
    private ?int $auditeur_id = null;

    #[ORM\Column(name: "renovateur_id", type: "integer", nullable: true)]
    private ?int $renovateur_id = null;

    #[ORM\Column(name: "financeur_id", type: "integer", nullable: true)]
    private ?int $financeur_id = null;

    #[ORM\Column(name: "known_by_other", type: "boolean", nullable: true)]
    private ?bool $knownByOther = null;

    #[ORM\Column(name: "user_id", type: "integer")]
    private int $user_id;

    #[ORM\Column(name: "structure_rattachement_id", type: "integer", nullable: true)]
    private ?int $structure_rattachement_id = null;

    #[ORM\Column(name: "conseiller_rattachement_id", type: "integer", nullable: true)]
    private ?int $conseiller_rattachement_id = null;

    #[ORM\Column(name: "duplicate_key", type: "string", length: 255, nullable: true)]
    private ?string $duplicateKey = null;

    const SITUATION_FAMILIALE_CELIBATAIRE_KEY    = '0 | celibataire';
    const SITUATION_FAMILIALE_CELIBATAIRE_VALUE  = 'Célibataire';
    const SITUATION_FAMILIALE_MARIE_KEY          = '1 | marie';
    const SITUATION_FAMILIALE_MARIE_VALUE        = 'Marié(e)';
    const SITUATION_FAMILIALE_UNION_LIBRE_KEY    = '2 | union_libre';
    const SITUATION_FAMILIALE_UNION_LIBRE_VALUE  = 'Union libre';
    const SITUATION_FAMILIALE_VEUF_DIVORCE_KEY   = '3 | veuf_divorce';
    const SITUATION_FAMILIALE_VEUF_DIVORCE_VALUE = 'Veuf(ve)/Divorcé';
    const SITUATION_FAMILIALE_PACSE_KEY          = '4 | pacse';
    const SITUATION_FAMILIALE_PACSE_VALUE        = 'Pacsé(e)';

    public static $ARRAY_SITUATION_FAMILIALE = [
        self::SITUATION_FAMILIALE_CELIBATAIRE_KEY  => self::SITUATION_FAMILIALE_CELIBATAIRE_VALUE,
        self::SITUATION_FAMILIALE_MARIE_KEY        => self::SITUATION_FAMILIALE_MARIE_VALUE,
        self::SITUATION_FAMILIALE_UNION_LIBRE_KEY  => self::SITUATION_FAMILIALE_UNION_LIBRE_VALUE,
        self::SITUATION_FAMILIALE_VEUF_DIVORCE_KEY => self::SITUATION_FAMILIALE_VEUF_DIVORCE_VALUE,
        self::SITUATION_FAMILIALE_PACSE_KEY        => self::SITUATION_FAMILIALE_PACSE_VALUE
    ];



    /**
     * Beneficiaire constructor.
     */
    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();
    }



    /**
     * Get id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Beneficiaire
     */
    public function setDateCreation(\DateTime $dateCreation): self
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation
     *
     * @return \DateTime
     */
    public function getDateCreation(): \DateTime
    {
        return $this->dateCreation;
    }

    /**
     * Set auteurCreation
     *
     * @param string $auteurCreation
     *
     * @return Beneficiaire
     */
    public function setAuteurCreation(string $auteurCreation): self
    {
        $this->auteurCreation = $auteurCreation;

        return $this;
    }

    /**
     * Get auteurCreation
     *
     * @return string
     */
    public function getAuteurCreation(): string
    {
        return $this->auteurCreation;
    }

    /**
     * Set dateModif
     *
     * @param \DateTime $dateModif
     *
     * @return Beneficiaire
     */
    public function setDateModif(\DateTime $dateModif): self
    {
        $this->dateModif = $dateModif;

        return $this;
    }

    /**
     * Get dateModif
     *
     * @return \DateTime
     */
    public function getDateModif(): \DateTime
    {
        return $this->dateModif;
    }

    /**
     * Set auteurModif
     *
     * @param string $auteurModif
     *
     * @return Beneficiaire
     */
    public function setAuteurModif(string $auteurModif): self
    {
        $this->auteurModif = $auteurModif;

        return $this;
    }

    /**
     * Get auteurModif
     *
     * @return string
     */
    public function getAuteurModif(): string
    {
        return $this->auteurModif;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return Beneficiaire
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set nomSCI
     *
     * @param string $nomSCI
     *
     * @return Beneficiaire
     */
    public function setNomSCI(string $nomSCI): self
    {
        $this->nomSCI = $nomSCI;

        return $this;
    }

    /**
     * Get nomSCI
     *
     * @return string
     */
    public function getNomSCI(): string
    {
        return $this->nomSCI;
    }

    /**
     * Set civilite
     *
     * @param string $civilite
     *
     * @return Beneficiaire
     */
    public function setCivilite(string $civilite): self
    {
        $this->civilite = $civilite;

        return $this;
    }

    /**
     * Get civilite
     *
     * @return string
     */
    public function getCivilite(): string
    {
        return $this->civilite;
    }

    /**
     * Set nom
     *
     * @param string $nom
     *
     * @return Beneficiaire
     */
    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom
     *
     * @return string
     */
    public function getNom(): ?string
    {
        return $this->nom;
    }

    /**
     * Set prenom
     *
     * @param string $prenom
     *
     * @return Beneficiaire
     */
    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    /**
     * Get prenom
     *
     * @return string
     */
    public function getPrenom(): string
    {
        return $this->prenom;
    }

    /**
     * Set codePostal
     *
     * @param string $codePostal
     *
     * @return Beneficiaire
     */
    public function setCodePostal(string $codePostal): self
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    /**
     * Get codePostal
     *
     * @return string
     */
    public function getCodePostal(): string
    {
        return $this->codePostal;
    }

    /**
     * Set ville
     *
     * @param string $ville
     *
     * @return Beneficiaire
     */
    public function setVille(string $ville): self
    {
        $this->ville = $ville;

        return $this;
    }

    /**
     * Get ville
     *
     * @return string
     */
    public function getVille(): string
    {
        return $this->ville;
    }

    /**
     * Set iNSEE
     *
     * @param string $iNSEE
     *
     * @return Beneficiaire
     */
    public function setINSEE(string $iNSEE): self
    {
        $this->INSEE = $iNSEE;

        return $this;
    }

    /**
     * Get iNSEE
     *
     * @return string
     */
    public function getINSEE(): string
    {
        return $this->INSEE;
    }

    /**
     * Set numeroRue
     *
     * @param integer $numeroRue
     *
     * @return Beneficiaire
     */
    public function setNumeroRue(int $numeroRue): self
    {
        $this->numeroRue = $numeroRue;

        return $this;
    }

    /**
     * Get numeroRue
     *
     * @return integer
     */
    public function getNumeroRue(): int
    {
        return $this->numeroRue;
    }

    /**
     * Set complementNumeroRue
     *
     * @param string $complementNumeroRue
     *
     * @return Beneficiaire
     */
    public function setComplementNumeroRue(?string $complementNumeroRue): self
    {
        $this->complementNumeroRue = $complementNumeroRue;

        return $this;
    }

    /**
     * Get complementNumeroRue
     *
     * @return string
     */
    public function getComplementNumeroRue(): ?string
    {
        return $this->complementNumeroRue;
    }

    /**
     * Set nomRue
     *
     * @param string $nomRue
     *
     * @return Beneficiaire
     */
    public function setNomRue(string $nomRue): self
    {
        $this->nomRue = $nomRue;

        return $this;
    }

    /**
     * Get nomRue
     *
     * @return string
     */
    public function getNomRue(): string
    {
        return $this->nomRue;
    }

    /**
     * Set nomRueNotFound
     *
     * @param boolean $nomRueNotFound
     *
     * @return Beneficiaire
     */
    public function setNomRueNotFound(?bool $nomRueNotFound): self
    {
        $this->nomRueNotFound = $nomRueNotFound;

        return $this;
    }

    /**
     * Get nomRueNotFound
     *
     * @return boolean
     */
    public function getNomRueNotFound(): ?bool
    {
        return $this->nomRueNotFound;
    }

    /**
     * Set complement1
     *
     * @param string $complement1
     *
     * @return Beneficiaire
     */
    public function setComplement1(?string $complement1): self
    {
        $this->complement1 = $complement1;

        return $this;
    }

    /**
     * Get complement1
     *
     * @return string
     */
    public function getComplement1(): ?string
    {
        return $this->complement1;
    }

    /**
     * Set complement2
     *
     * @param string $complement2
     *
     * @return Beneficiaire
     */
    public function setComplement2(?string $complement2): self
    {
        $this->complement2 = $complement2;

        return $this;
    }

    /**
     * Get complement2
     *
     * @return string
     */
    public function getComplement2(): ?string
    {
        return $this->complement2;
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return Beneficiaire
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set tel1
     *
     * @param string $tel1
     *
     * @return Beneficiaire
     */
    public function setTel1(?string $tel1): self
    {
        $this->tel1 = $tel1;

        return $this;
    }

    /**
     * Get tel1
     *
     * @return string
     */
    public function getTel1(): ?string
    {
        return $this->tel1;
    }

    /**
     * Set tel2
     *
     * @param string $tel2
     *
     * @return Beneficiaire
     */
    public function setTel2(?string $tel2): self
    {
        $this->tel2 = $tel2;

        return $this;
    }

    /**
     * Get tel2
     *
     * @return string
     */
    public function getTel2(): ?string
    {
        return $this->tel2;
    }

    /**
     * Set situationFamille
     *
     * @param string $situationFamille
     *
     * @return Beneficiaire
     */
    public function setSituationFamille(?string $situationFamille): self
    {
        $this->situationFamille = $situationFamille;

        return $this;
    }

    /**
     * Get situationFamille
     *
     * @return string
     */
    public function getSituationFamille(): ?string
    {
        return $this->situationFamille;
    }

    /**
     * @return string|null
     */
    public function getSituationFamilleLabel(): ?string
    {
        return self::$ARRAY_SITUATION_FAMILIALE[$this->situationFamille];
    }

    /**
     * Set nomConjoint
     *
     * @param string $nomConjoint
     *
     * @return Beneficiaire
     */
    public function setNomConjoint(?string $nomConjoint): self
    {
        $this->nomConjoint = $nomConjoint;

        return $this;
    }

    /**
     * Get nomConjoint
     *
     * @return string
     */
    public function getNomConjoint(): ?string
    {
        return $this->nomConjoint;
    }

    /**
     * Set prenomConjoint
     *
     * @param string $prenomConjoint
     *
     * @return Beneficiaire
     */
    public function setPrenomConjoint(?string $prenomConjoint): self
    {
        $this->prenomConjoint = $prenomConjoint;

        return $this;
    }

    /**
     * Get prenomConjoint
     *
     * @return string
     */
    public function getPrenomConjoint(): ?string
    {
        return $this->prenomConjoint;
    }

    /**
     * Set nbPersFoyer
     *
     * @param integer $nbPersFoyer
     *
     * @return Beneficiaire
     */
    public function setNbPersFoyer(int $nbPersFoyer): self
    {
        $this->nbPersFoyer = $nbPersFoyer;

        return $this;
    }

    /**
     * Get nbPersFoyer
     *
     * @return integer
     */
    public function getNbPersFoyer(): int
    {
        return $this->nbPersFoyer;
    }

    /**
     * Set revenuFiscalRef
     *
     * @param float $revenuFiscalRef
     *
     * @return Beneficiaire
     */
    public function setRevenuFiscalRef(float $revenuFiscalRef): self
    {
        $this->revenuFiscalRef = $revenuFiscalRef;

        return $this;
    }

    /**
     * Get revenuFiscalRef
     *
     * @return float
     */
    public function getRevenuFiscalRef(): float
    {
        return $this->revenuFiscalRef;
    }

    /**
     * Set knownByMedia
     *
     * @param boolean $knownByMedia
     *
     * @return Beneficiaire
     */
    public function setKnownByMedia(?bool $knownByMedia): self
    {
        $this->knownByMedia = $knownByMedia;

        return $this;
    }

    /**
     * Get knownByMedia
     *
     * @return boolean
     */
    public function getKnownByMedia(): ?bool
    {
        return $this->knownByMedia;
    }

    /**
     * Set structureId
     *
     * @param integer $structureId
     *
     * @return Beneficiaire
     */
    public function setStructureId(int $structureId): self
    {
        $this->structure_id = $structureId;

        return $this;
    }

    /**
     * Get structureId
     *
     * @return int|null
     */
    public function getStructureId(): ?int
    {
        return $this->structure_id;
    }

    /**
     * Set auditeurId
     *
     * @param integer $auditeurId
     *
     * @return Beneficiaire
     */
    public function setAuditeurId(int $auditeurId): self
    {
        $this->auditeur_id = $auditeurId;

        return $this;
    }

    /**
     * Get auditeurId
     *
     * @return int|null
     */
    public function getAuditeurId(): ?int
    {
        return $this->auditeur_id;
    }

    /**
     * Set renovateurId
     *
     * @param integer $renovateurId
     *
     * @return Beneficiaire
     */
    public function setRenovateurId(int $renovateurId): self
    {
        $this->renovateur_id = $renovateurId;

        return $this;
    }

    /**
     * Get renovateurId
     *
     * @return int|null
     */
    public function getRenovateurId(): ?int
    {
        return $this->renovateur_id;
    }

    /**
     * Set financeurId
     *
     * @param integer $financeurId
     *
     * @return Beneficiaire
     */
    public function setFinanceurId(int $financeurId): self
    {
        $this->financeur_id = $financeurId;

        return $this;
    }

    /**
     * Get financeurId
     *
     * @return int|null
     */
    public function getFinanceurId(): ?int
    {
        return $this->financeur_id;
    }

    /**
     * Set knownByOther
     *
     * @param boolean $knownByOther
     *
     * @return Beneficiaire
     */
    public function setKnownByOther(?bool $knownByOther): self
    {
        $this->knownByOther = $knownByOther;

        return $this;
    }

    /**
     * Get knownByOther
     *
     * @return boolean
     */
    public function getKnownByOther(): ?bool
    {
        return $this->knownByOther;
    }

    /**
     * Set userId
     *
     * @param integer $userId
     *
     * @return Beneficiaire
     */
    public function setUserId(int $userId): self
    {
        $this->user_id = $userId;

        return $this;
    }

    /**
     * Get userId
     *
     * @return integer
     */
    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * Set structureRattachementId
     *
     * @param int|null $structureRattachementId
     *
     * @return Beneficiaire
     */
    public function setStructureRattachementId(?int $structureRattachementId): self
    {
        $this->structure_rattachement_id = $structureRattachementId;

        return $this;
    }

    /**
     * Get structureRattachementId
     *
     * @return integer
     */
    public function getStructureRattachementId(): ?int
    {
        return $this->structure_rattachement_id;
    }

    /**
     * Set conseillerRattachementId
     *
     * @param integer $conseillerRattachementId
     *
     * @return Beneficiaire
     */
    public function setConseillerRattachementId(?int $conseillerRattachementId): self
    {
        $this->conseiller_rattachement_id = $conseillerRattachementId;

        return $this;
    }

    /**
     * Get conseillerRattachementId
     *
     * @return int|null
     */
    public function getConseillerRattachementId(): ?int
    {
        return $this->conseiller_rattachement_id;
    }

    /**
     * Set duplicateKey
     *
     * @param string $duplicateKey
     *
     * @return Beneficiaire
     */
    public function setDuplicateKey(?string $duplicateKey): self
    {
        $this->duplicateKey = $duplicateKey;

        return $this;
    }

    /**
     * Get duplicateKey
     *
     * @return string
     */
    public function getDuplicateKey(): ?string
    {
        return $this->duplicateKey;
    }

    /**
     * Set villeId
     *
     * @param integer $villeId
     *
     * @return Beneficiaire
     */
    public function setVilleId(int $villeId): self
    {
        $this->villeId = $villeId;

        return $this;
    }

    /**
     * Get villeId
     *
     * @return integer
     */
    public function getVilleId(): int
    {
        return $this->villeId;
    }
}

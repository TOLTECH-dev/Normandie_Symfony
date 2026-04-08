<?php

namespace App\Entity;

use App\Repository\LogementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "logement")]
#[ORM\Index(name: "beneficiaire_idx", columns: ["beneficiaire_id"])]
#[ORM\Index(name: "INSEE_idx", columns: ["INSEE"])]
#[ORM\Index(name: "ville_idx", columns: ["ville_id"])]
#[ORM\Entity(repositoryClass: LogementRepository::class)]
class Logement
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

    #[ORM\Column(name: "statut", type: "string", length: 20)]
    private ?string $statut = null;

    #[ORM\Column(name: "motif", type: "array", nullable: true)]
    private ?array $motif = null;

    #[ORM\Column(name: "nom", type: "string", length: 255)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(name: "is_different", type: "boolean", nullable: true)]
    private ?bool $isDifferent = null;

    #[ORM\Column(name: "code_postal", type: "string", length: 20)]
    #[Assert\NotBlank]
    private ?string $codePostal = null;

    #[ORM\Column(name: "ville", type: "string", length: 255)]
    #[Assert\NotBlank]
    private ?string $ville = null;

    #[ORM\Column(name: "ville_id", type: "integer")]
    private ?int $villeId = null;

    #[ORM\Column(name: "INSEE", type: "string", length: 5)]
    #[Assert\NotBlank]
    private ?string $INSEE = null;

    #[ORM\Column(name: "numero_rue", type: "integer")]
    #[Assert\NotBlank]
    private ?int $numeroRue = null;

    #[ORM\Column(name: "complement_rue", type: "string", length: 20, nullable: true)]
    private ?string $complementRue = null;

    #[ORM\Column(name: "adresse", type: "string", length: 255)]
    #[Assert\NotBlank]
    private ?string $adresse = null;

    #[ORM\Column(name: "nom_rue_not_found", type: "boolean", nullable: true)]
    private ?bool $nomRueNotFound = null;

    #[ORM\Column(name: "complement_1", type: "string", length: 255, nullable: true)]
    private ?string $complement1 = null;

    #[ORM\Column(name: "complement_2", type: "string", length: 255, nullable: true)]
    private ?string $complement2 = null;

    #[ORM\Column(name: "situation", type: "string", length: 30)]
    private ?string $situation = null;

    #[ORM\Column(name: "type_logement", type: "string", length: 30)]
    private ?string $typeLogement = null;

    #[ORM\Column(name: "type_habitation", type: "string", length: 20, nullable: true)]
    private ?string $typeHabitation = null;

    #[ORM\Column(name: "annee_construction", type: "string", length: 20)]
    private ?string $anneeConstruction = null;

    #[ORM\Column(name: "description_projet", type: "text")]
    #[Assert\NotBlank]
    #[Assert\Length(max: 245)]
    private ?string $descriptionProjet = null;

    #[ORM\Column(name: "beneficiaire_id", type: "integer")]
    private int $beneficiaire_id;

    #[ORM\Column(name: "duplicate_key", type: "string", length: 255, nullable: true)]
    private ?string $duplicateKey = null;



    /**
     * Logement constructor.
     */
    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModif = new \DateTime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
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
     * Set statut
     */
    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    /**
     * Get statut
     */
    public function getStatut(): ?string
    {
        return $this->statut;
    }

    /**
     * Set motif
     */
    public function setMotif(?array $motif): self
    {
        $this->motif = $motif;
        return $this;
    }

    /**
     * Get motif
     */
    public function getMotif(): ?array
    {
        return $this->motif;
    }

    /**
     * Set nom
     */
    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    /**
     * Get nom
     */
    public function getNom(): ?string
    {
        return $this->nom;
    }

    /**
     * Set isDifferent
     */
    public function setIsDifferent(?bool $isDifferent): self
    {
        $this->isDifferent = $isDifferent;
        return $this;
    }

    /**
     * Get isDifferent
     */
    public function getIsDifferent(): ?bool
    {
        return $this->isDifferent;
    }

    /**
     * Set codePostal
     */
    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    /**
     * Get codePostal
     */
    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    /**
     * Set ville
     */
    public function setVille(?string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    /**
     * Get ville
     */
    public function getVille(): ?string
    {
        return $this->ville;
    }

    /**
     * Set villeId
     */
    public function setVilleId(?int $villeId): self
    {
        $this->villeId = $villeId;
        return $this;
    }

    /**
     * Get villeId
     */
    public function getVilleId(): ?int
    {
        return $this->villeId;
    }

    /**
     * Set INSEE
     */
    public function setINSEE(?string $INSEE): self
    {
        $this->INSEE = $INSEE;
        return $this;
    }

    /**
     * Get INSEE
     */
    public function getINSEE(): ?string
    {
        return $this->INSEE;
    }

    /**
     * Set numeroRue
     */
    public function setNumeroRue(?int $numeroRue): self
    {
        $this->numeroRue = $numeroRue;
        return $this;
    }

    /**
     * Get numeroRue
     */
    public function getNumeroRue(): ?int
    {
        return $this->numeroRue;
    }

    /**
     * Set complementRue
     */
    public function setComplementRue(?string $complementRue): self
    {
        $this->complementRue = $complementRue;
        return $this;
    }

    /**
     * Get complementRue
     */
    public function getComplementRue(): ?string
    {
        return $this->complementRue;
    }

    /**
     * Set adresse
     */
    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    /**
     * Get adresse
     */
    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    /**
     * Set nomRueNotFound
     */
    public function setNomRueNotFound(?bool $nomRueNotFound): self
    {
        $this->nomRueNotFound = $nomRueNotFound;
        return $this;
    }

    /**
     * Get nomRueNotFound
     */
    public function getNomRueNotFound(): ?bool
    {
        return $this->nomRueNotFound;
    }

    /**
     * Set complement1
     */
    public function setComplement1(?string $complement1): self
    {
        $this->complement1 = $complement1;
        return $this;
    }

    /**
     * Get complement1
     */
    public function getComplement1(): ?string
    {
        return $this->complement1;
    }

    /**
     * Set complement2
     */
    public function setComplement2(?string $complement2): self
    {
        $this->complement2 = $complement2;
        return $this;
    }

    /**
     * Get complement2
     */
    public function getComplement2(): ?string
    {
        return $this->complement2;
    }

    /**
     * Set situation
     */
    public function setSituation(?string $situation): self
    {
        $this->situation = $situation;
        return $this;
    }

    /**
     * Get situation
     */
    public function getSituation(): ?string
    {
        return $this->situation;
    }

    /**
     * Set typeLogement
     */
    public function setTypeLogement(?string $typeLogement): self
    {
        $this->typeLogement = $typeLogement;
        return $this;
    }

    /**
     * Get typeLogement
     */
    public function getTypeLogement(): ?string
    {
        return $this->typeLogement;
    }

    /**
     * Set typeHabitation
     */
    public function setTypeHabitation(?string $typeHabitation): self
    {
        $this->typeHabitation = $typeHabitation;
        return $this;
    }

    /**
     * Get typeHabitation
     */
    public function getTypeHabitation(): ?string
    {
        return $this->typeHabitation;
    }

    /**
     * Set anneeConstruction
     */
    public function setAnneeConstruction(?string $anneeConstruction): self
    {
        $this->anneeConstruction = $anneeConstruction;
        return $this;
    }

    /**
     * Get anneeConstruction
     */
    public function getAnneeConstruction(): ?string
    {
        return $this->anneeConstruction;
    }

    /**
     * Set descriptionProjet
     */
    public function setDescriptionProjet(?string $descriptionProjet): self
    {
        $this->descriptionProjet = $descriptionProjet;
        return $this;
    }

    /**
     * Get descriptionProjet
     */
    public function getDescriptionProjet(): ?string
    {
        return $this->descriptionProjet;
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
     * Set duplicateKey
     */
    public function setDuplicateKey(?string $duplicateKey): self
    {
        $this->duplicateKey = $duplicateKey;
        return $this;
    }

    /**
     * Get duplicateKey
     */
    public function getDuplicateKey(): ?string
    {
        return $this->duplicateKey;
    }
}

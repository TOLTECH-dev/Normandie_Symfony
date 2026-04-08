<?php

namespace App\Entity;

use App\Repository\Structure_Repository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "structure_")]
#[ORM\Entity(repositoryClass: Structure_Repository::class)]
class Structure_
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private $id;

    #[ORM\Column(name: "date_creation", type: "datetime")]
    private $dateCreation;

    #[ORM\Column(name: "auteur_creation", type: "string", length: 255)]
    private $auteurCreation;

    #[ORM\Column(name: "date_modif", type: "datetime")]
    private $dateModif;

    #[ORM\Column(name: "auteur_modif", type: "string", length: 255)]
    private $auteurModif;

    #[ORM\OneToOne(targetEntity: Structure_identification::class, cascade: ["persist"])]
    #[ORM\JoinColumn(nullable: false)]
    public Structure_identification $structure_identification;

    #[ORM\OneToOne(targetEntity: Structure_adresse::class, cascade: ["persist"])]
    #[ORM\JoinColumn(nullable: false)]
    public Structure_adresse $structure_adresse;

    #[ORM\OneToOne(targetEntity: Structure_statut::class, cascade: ["persist"])]
    #[ORM\JoinColumn(nullable: false)]
    public ?Structure_statut $structure_statut = null;

    #[ORM\ManyToMany(targetEntity: Structure_contact::class, cascade: ["persist"])]
    protected Collection $structure_contact;

    #[ORM\ManyToMany(targetEntity: Structure_conseiller::class, cascade: ["persist"])]
    protected Collection $structure_conseiller;

    #[ORM\ManyToMany(targetEntity: Structure_permanence::class, cascade: ["persist"])]
    #[Assert\Valid]
    protected Collection $structure_permanence;

    #[ORM\OneToMany(targetEntity: Orientation_structureInferieur::class, mappedBy: "structure", cascade: ["persist"], orphanRemoval: true)]
    private Collection $orientation_structureInferieur;

    #[ORM\OneToMany(targetEntity: Orientation_structureSuperieur::class, mappedBy: "structure", cascade: ["persist"], orphanRemoval: true)]
    private Collection $orientation_structureSuperieur;



    /**
     * Structure_ constructor.
     */
    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();

        $this->structure_contact = new ArrayCollection();
        $this->structure_conseiller = new ArrayCollection();
        $this->structure_permanence = new ArrayCollection();

        $this->orientation_structureInferieur = new ArrayCollection();
    }



    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->id;
    }



    /**
     * Get id
     *
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Structure_
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
    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    /**
     * Set auteurCreation
     *
     * @param string $auteurCreation
     *
     * @return Structure_
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
    public function getAuteurCreation(): ?string
    {
        return $this->auteurCreation;
    }

    /**
     * Set dateModif
     *
     * @param \DateTime $dateModif
     *
     * @return Structure_
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
    public function getDateModif(): ?\DateTime
    {
        return $this->dateModif;
    }

    /**
     * Set auteurModif
     *
     * @param string $auteurModif
     *
     * @return Structure_
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
    public function getAuteurModif(): ?string
    {
        return $this->auteurModif;
    }

    /**
     * Set structureIdentification
     *
     * @param Structure_identification $structureIdentification
     *
     * @return Structure_
     */
    public function setStructureIdentification(Structure_identification $structureIdentification): self
    {
        $this->structure_identification = $structureIdentification;

        return $this;
    }

    /**
     * Get structureIdentification
     *
     * @return Structure_identification
     */
    public function getStructureIdentification(): ?Structure_identification
    {
        return $this->structure_identification;
    }

    /**
     * Set structureAdresse
     *
     * @param Structure_adresse $structureAdresse
     *
     * @return Structure_
     */
    public function setStructureAdresse(Structure_adresse $structureAdresse): self
    {
        $this->structure_adresse = $structureAdresse;

        return $this;
    }

    /**
     * Get structureAdresse
     *
     * @return Structure_adresse
     */
    public function getStructureAdresse(): ?Structure_adresse
    {
        return $this->structure_adresse;
    }

    /**
     * Set structureStatut
     *
     * @param Structure_statut $structureStatut
     *
     * @return Structure_
     */
    public function setStructureStatut(?Structure_statut $structureStatut): self
    {
        $this->structure_statut = $structureStatut;

        return $this;
    }

    /**
     * Get structureStatut
     *
     * @return Structure_statut
     */
    public function getStructure_statut(): ?Structure_statut
    {
        return $this->structure_statut;
    }

    /**
     * Add structureContact
     *
     * @param Structure_contact $structureContact
     *
     * @return Structure_
     */
    public function addStructureContact(Structure_contact $structureContact): self
    {
        $this->structure_contact[] = $structureContact;

        return $this;
    }

    /**
     * Remove structureContact
     *
     * @param Structure_contact $structureContact
     */
    public function removeStructureContact(Structure_contact $structureContact): void
    {
        $this->structure_contact->removeElement($structureContact);
    }

    /**
     * Get structureContact
     *
     * @return Collection
     */
    public function getStructureContact(): Collection
    {
        return $this->structure_contact;
    }

    /**
     * Add structureConseiller
     *
     * @param Structure_conseiller $structureConseiller
     *
     * @return Structure_
     */
    public function addStructureConseiller(Structure_conseiller $structureConseiller): self
    {
        $this->structure_conseiller[] = $structureConseiller;

        return $this;
    }

    /**
     * Remove structureConseiller
     *
     * @param Structure_conseiller $structureConseiller
     */
    public function removeStructureConseiller(Structure_conseiller $structureConseiller): void
    {
        $this->structure_conseiller->removeElement($structureConseiller);
    }

    /**
     * Get structureConseiller
     *
     * @return Collection
     */
    public function getStructureConseiller(): Collection
    {
        return $this->structure_conseiller;
    }

    /**
     * Add structurePermanence
     *
     * @param Structure_permanence $structurePermanence
     *
     * @return Structure_
     */
    public function addStructurePermanence(Structure_permanence $structurePermanence): self
    {
        $this->structure_permanence[] = $structurePermanence;

        return $this;
    }

    /**
     * Remove structurePermanence
     *
     * @param Structure_permanence $structurePermanence
     */
    public function removeStructurePermanence(Structure_permanence $structurePermanence): void
    {
        $this->structure_permanence->removeElement($structurePermanence);
    }

    /**
     * Get structurePermanence
     *
     * @return Collection
     */
    public function getStructurePermanence(): Collection
    {
        return $this->structure_permanence;
    }

    /**
     * Add orientationStructureInferieur
     *
     * @param Orientation_structureInferieur $orientationStructureInferieur
     *
     * @return Structure_
     */
    public function addOrientationStructureInferieur(Orientation_structureInferieur $orientationStructureInferieur): self
    {
        $this->orientation_structureInferieur[] = $orientationStructureInferieur;
        $orientationStructureInferieur->setStructure($this);

        return $this;
    }

    /**
     * Remove orientationStructureInferieur
     *
     * @param Orientation_structureInferieur $orientationStructureInferieur
     */
    public function removeOrientationStructureInferieur(Orientation_structureInferieur $orientationStructureInferieur): void
    {
        $this->orientation_structureInferieur->removeElement($orientationStructureInferieur);
    }

    /**
     * Get orientationStructureInferieur
     *
     * @return Collection
     */
    public function getOrientationStructureInferieur(): Collection
    {
        return $this->orientation_structureInferieur;
    }

    /**
     * Add orientationStructureSuperieur
     *
     * @param Orientation_structureSuperieur $orientationStructureSuperieur
     *
     * @return Structure_
     */
    public function addOrientationStructureSuperieur(Orientation_structureSuperieur $orientationStructureSuperieur): self
    {
        $this->orientation_structureSuperieur[] = $orientationStructureSuperieur;

        return $this;
    }

    /**
     * Remove orientationStructureSuperieur
     *
     * @param Orientation_structureSuperieur $orientationStructureSuperieur
     */
    public function removeOrientationStructureSuperieur(Orientation_structureSuperieur $orientationStructureSuperieur): void
    {
        $this->orientation_structureSuperieur->removeElement($orientationStructureSuperieur);
    }

    /**
     * Get orientationStructureSuperieur
     *
     * @return Collection
     */
    public function getOrientationStructureSuperieur(): Collection
    {
        return $this->orientation_structureSuperieur;
    }
}

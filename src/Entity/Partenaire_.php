<?php

namespace App\Entity;

use App\Repository\Partenaire_Repository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Partenaire_Repository::class)]
#[ORM\Table(name: 'partenaire_')]
class Partenaire_
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTime $dateCreation;

    #[ORM\Column(name: 'auteur_creation', type: 'string', length: 255)]
    private string $auteurCreation;

    #[ORM\Column(name: 'date_modif', type: 'datetime')]
    private \DateTime $dateModif;

    #[ORM\Column(name: 'auteur_modif', type: 'string', length: 255)]
    private string $auteurModif;

    #[ORM\Column(name: 'type', type: 'string', length: 255)]
    private string $type;

    #[ORM\OneToOne(targetEntity: Partenaire_identification::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    public ?Partenaire_identification $partenaire_identification = null;

    #[ORM\OneToOne(targetEntity: Partenaire_adresse::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    public ?Partenaire_adresse $partenaire_adresse = null;

    #[ORM\OneToOne(targetEntity: Partenaire_statut::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    public ?Partenaire_statut $partenaire_statut = null;

    #[ORM\OneToOne(targetEntity: Partenaire_optionAuditeur::class, cascade: ['persist'])]
    #[Assert\Valid]
    public ?Partenaire_optionAuditeur $partenaire_optionAuditeur = null;

    #[ORM\OneToOne(targetEntity: Partenaire_optionRenovateur::class, cascade: ['persist'])]
    #[Assert\Valid]
    public ?Partenaire_optionRenovateur $partenaire_optionRenovateur = null;

    #[ORM\ManyToMany(targetEntity: Partenaire_contact::class, cascade: ['persist'])]
    protected Collection $partenaire_contact;

    #[ORM\ManyToMany(targetEntity: Partenaire_agence::class, cascade: ['persist'])]
    protected Collection $partenaire_agence;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModif = new \DateTime();
        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
        $this->partenaire_contact = new ArrayCollection();
        $this->partenaire_agence = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string)$this->id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function setDateCreation(\DateTime $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }
    public function getDateCreation(): \DateTime
    {
        return $this->dateCreation;
    }
    public function setAuteurCreation(string $auteurCreation): self
    {
        $this->auteurCreation = $auteurCreation;
        return $this;
    }
    public function getAuteurCreation(): string
    {
        return $this->auteurCreation;
    }
    public function setDateModif(\DateTime $dateModif): self
    {
        $this->dateModif = $dateModif;
        return $this;
    }
    public function getDateModif(): \DateTime
    {
        return $this->dateModif;
    }
    public function setAuteurModif(string $auteurModif): self
    {
        $this->auteurModif = $auteurModif;
        return $this;
    }
    public function getAuteurModif(): string
    {
        return $this->auteurModif;
    }
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
    public function getType(): string
    {
        return $this->type;
    }
    public function setPartenaireIdentification(?Partenaire_identification $partenaire_identification): self
    {
        $this->partenaire_identification = $partenaire_identification;
        return $this;
    }
    public function getPartenaireIdentification(): ?Partenaire_identification
    {
        return $this->partenaire_identification;
    }
    public function setPartenaireAdresse(?Partenaire_adresse $partenaire_adresse): self
    {
        $this->partenaire_adresse = $partenaire_adresse;
        return $this;
    }
    public function getPartenaireAdresse(): ?Partenaire_adresse
    {
        return $this->partenaire_adresse;
    }
    public function setPartenaireStatut(?Partenaire_statut $partenaire_statut): self
    {
        $this->partenaire_statut = $partenaire_statut;
        return $this;
    }
    public function getPartenaireStatut(): ?Partenaire_statut
    {
        return $this->partenaire_statut;
    }
    public function setPartenaireOptionAuditeur(?Partenaire_optionAuditeur $partenaire_optionAuditeur): self
    {
        $this->partenaire_optionAuditeur = $partenaire_optionAuditeur;
        return $this;
    }
    public function getPartenaireOptionAuditeur(): ?Partenaire_optionAuditeur
    {
        return $this->partenaire_optionAuditeur;
    }
    public function setPartenaireOptionRenovateur(?Partenaire_optionRenovateur $partenaire_optionRenovateur): self
    {
        $this->partenaire_optionRenovateur = $partenaire_optionRenovateur;
        return $this;
    }
    public function getPartenaireOptionRenovateur(): ?Partenaire_optionRenovateur
    {
        return $this->partenaire_optionRenovateur;
    }
    public function addPartenaireContact(Partenaire_contact $partenaire_contact): self
    {
        if (!$this->partenaire_contact->contains($partenaire_contact)) {
            $this->partenaire_contact[] = $partenaire_contact;
        }
        return $this;
    }
    public function removePartenaireContact(Partenaire_contact $partenaire_contact): self
    {
        $this->partenaire_contact->removeElement($partenaire_contact);
        return $this;
    }
    public function getPartenaireContact(): Collection
    {
        return $this->partenaire_contact;
    }
    public function addPartenaireAgence(Partenaire_agence $partenaire_agence): self
    {
        if (!$this->partenaire_agence->contains($partenaire_agence)) {
            $this->partenaire_agence[] = $partenaire_agence;
        }
        return $this;
    }
    public function removePartenaireAgence(Partenaire_agence $partenaire_agence): self
    {
        $this->partenaire_agence->removeElement($partenaire_agence);
        return $this;
    }
    public function getPartenaireAgence(): Collection
    {
        return $this->partenaire_agence;
    }
}

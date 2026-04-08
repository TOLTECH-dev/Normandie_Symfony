<?php

namespace App\Entity;

use App\Repository\Structure_conseillerRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "structure_conseiller")]
#[ORM\Entity(repositoryClass: Structure_conseillerRepository::class)]
class Structure_conseiller
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "nom", type: "string", length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(name: "prenom", type: "string", length: 255, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(name: "telephone", type: "string", length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(name: "email", type: "string", length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\ManyToMany(targetEntity: Structure_conseiller_intervention::class)]
    #[Assert\Count(min: 0, max: 5)]
    protected Collection $departement_intervention;

    #[ORM\Column(name: "enabled", type: "boolean", nullable: true)]
    private ?bool $enabled = null;

    #[ORM\Column(name: "date_inactif", type: "date", nullable: true)]
    private ?\DateTimeInterface $dateInactif = null;

    public function __construct()
    {
        $this->departement_intervention = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEnabled(?bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setDateInactif(?\DateTimeInterface $dateInactif): self
    {
        $this->dateInactif = $dateInactif;
        return $this;
    }

    public function getDateInactif(): ?\DateTimeInterface
    {
        return $this->dateInactif;
    }

    public function addDepartementIntervention(Structure_conseiller_intervention $departementIntervention): self
    {
        if (!$this->departement_intervention->contains($departementIntervention)) {
            $this->departement_intervention[] = $departementIntervention;
        }
        return $this;
    }

    public function removeDepartementIntervention(Structure_conseiller_intervention $departementIntervention): void
    {
        $this->departement_intervention->removeElement($departementIntervention);
    }

    public function getDepartementIntervention(): Collection
    {
        return $this->departement_intervention;
    }
}

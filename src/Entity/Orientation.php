<?php

namespace App\Entity;

use App\Repository\OrientationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrientationRepository::class)]
#[ORM\Table(name: 'orientation')]
#[ORM\Index(name: 'ville_idx', columns: ['ville_id'])]
#[ORM\Index(name: 'EPCI_idx', columns: ['EPCI_id'])]
class Orientation
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

    #[ORM\Column(name: 'ville_id', type: 'integer', unique: true)]
    private int $villeId;

    #[ORM\OneToMany(targetEntity: Orientation_structureInferieur::class, mappedBy: 'orientation', cascade: ['persist'], orphanRemoval: true)]
    private Collection $orientationStructureInferieur;

    #[ORM\OneToMany(targetEntity: Orientation_structureSuperieur::class, mappedBy: 'orientation', cascade: ['persist'], orphanRemoval: true)]
    private Collection $orientationStructureSuperieur;

    #[ORM\Column(name: 'EPCI_id', type: 'integer', nullable: true)]
    private ?int $EPCIId = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModif = new \DateTime();
        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
        $this->orientationStructureInferieur = new ArrayCollection();
        $this->orientationStructureSuperieur = new ArrayCollection();
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

    public function setVilleId(int $villeId): self
    {
        $this->villeId = $villeId;
        return $this;
    }

    public function getVilleId(): int
    {
        return $this->villeId;
    }

    public function setEPCIId(?int $EPCIId): self
    {
        $this->EPCIId = $EPCIId;
        return $this;
    }

    public function getEPCIId(): ?int
    {
        return $this->EPCIId;
    }

    public function addOrientationStructureInferieur(Orientation_structureInferieur $orientationStructureInferieur): self
    {
        if (!$this->orientationStructureInferieur->contains($orientationStructureInferieur)) {
            $this->orientationStructureInferieur[] = $orientationStructureInferieur;
            $orientationStructureInferieur->setOrientation($this);
        }
        return $this;
    }

    public function removeOrientationStructureInferieur(Orientation_structureInferieur $orientationStructureInferieur): self
    {
        if ($this->orientationStructureInferieur->removeElement($orientationStructureInferieur)) {
            if ($orientationStructureInferieur->getOrientation() === $this) {
                $orientationStructureInferieur->setOrientation(null);
            }
        }
        return $this;
    }

    public function getOrientationStructureInferieur(): Collection
    {
        return $this->orientationStructureInferieur;
    }

    public function addOrientationStructureSuperieur(Orientation_structureSuperieur $orientationStructureSuperieur): self
    {
        if (!$this->orientationStructureSuperieur->contains($orientationStructureSuperieur)) {
            $this->orientationStructureSuperieur[] = $orientationStructureSuperieur;
            $orientationStructureSuperieur->setOrientation($this);
        }
        return $this;
    }

    public function removeOrientationStructureSuperieur(Orientation_structureSuperieur $orientationStructureSuperieur): self
    {
        if ($this->orientationStructureSuperieur->removeElement($orientationStructureSuperieur)) {
            if ($orientationStructureSuperieur->getOrientation() === $this) {
                $orientationStructureSuperieur->setOrientation(null);
            }
        }
        return $this;
    }

    public function getOrientationStructureSuperieur(): Collection
    {
        return $this->orientationStructureSuperieur;
    }
}

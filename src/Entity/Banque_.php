<?php

namespace App\Entity;

use App\Repository\Banque_Repository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'banque_')]
#[ORM\Entity(repositoryClass: Banque_Repository::class)]
class Banque_
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTime $dateCreation;

    #[ORM\Column(name: 'auteur_creation', type: 'string', length: 255)]
    private string $auteurCreation;

    #[ORM\Column(name: 'date_modif', type: 'datetime')]
    private \DateTime $dateModif;

    #[ORM\Column(name: 'auteur_modif', type: 'string', length: 255)]
    private string $auteurModif;

    #[ORM\Column(name: 'nom', type: 'string', length: 255)]
    private string $nom;

    #[ORM\OneToOne(targetEntity: Banque_statut::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Banque_statut $banque_statut= null;

    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
    }

    public function __toString(): string
    {
        return (string) $this->id;
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

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setBanqueStatut(?Banque_statut $banqueStatut): self
    {
        $this->banque_statut = $banqueStatut;
        return $this;
    }

    public function getBanqueStatut(): ?Banque_statut
    {
        return $this->banque_statut;
    }
}

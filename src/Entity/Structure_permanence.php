<?php

namespace App\Entity;

use App\Repository\Structure_permanenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "structure_permanence")]
#[ORM\Entity(repositoryClass: Structure_permanenceRepository::class)]
class Structure_permanence
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "nom", type: "string", length: 255, nullable: true)]
    protected ?string $nom = null;

    #[ORM\Column(name: "adresse", type: "string", length: 255, nullable: true)]
    protected ?string $adresse = null;

    #[ORM\Column(name: "code_postal", type: "string", length: 20, nullable: true)]
    protected ?string $codePostal = null;

    #[ORM\Column(name: "ville", type: "string", length: 255, nullable: true)]
    protected ?string $ville = null;

    #[ORM\Column(name: "telephone", type: "string", length: 255, nullable: true)]
    protected ?string $telephone = null;

    #[ORM\Column(name: "email", type: "string", length: 255, nullable: true)]
    protected ?string $email = null;

    #[ORM\Column(name: "jour_ouverture", type: "text", nullable: true)]
    #[Assert\Length(max: 245)]
    protected ?string $jourOuverture = null;

    #[ORM\Column(name: "horaire", type: "text", nullable: true)]
    #[Assert\Length(max: 245)]
    protected ?string $horaire = null;

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

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
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

    public function setJourOuverture(?string $jourOuverture): self
    {
        $this->jourOuverture = $jourOuverture;
        return $this;
    }

    public function getJourOuverture(): ?string
    {
        return $this->jourOuverture;
    }

    public function setHoraire(?string $horaire): self
    {
        $this->horaire = $horaire;
        return $this;
    }

    public function getHoraire(): ?string
    {
        return $this->horaire;
    }
}

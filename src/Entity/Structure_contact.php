<?php

namespace App\Entity;

use App\Repository\Structure_contactRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "structure_contact")]
#[ORM\Entity(repositoryClass: Structure_contactRepository::class)]
class Structure_contact
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "civilite", type: "string", length: 255, nullable: true)]
    private ?string $civilite = null;

    #[ORM\Column(name: "nom", type: "string", length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(name: "prenom", type: "string", length: 255, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(name: "titre", type: "string", length: 255, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(name: "telephone", type: "string", length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(name: "email", type: "string", length: 255, nullable: true)]
    private ?string $email = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCivilite(?string $civilite): self
    {
        $this->civilite = $civilite;
        return $this;
    }

    public function getCivilite(): ?string
    {
        return $this->civilite;
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

    public function setTitre(?string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
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
}

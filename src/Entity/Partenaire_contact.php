<?php

namespace App\Entity;

use App\Repository\Partenaire_contactRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Partenaire_contactRepository::class)]
#[ORM\Table(name: 'partenaire_contact')]
class Partenaire_contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $civilite = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    /**
     * Get id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set civilite
     */
    public function setCivilite(?string $civilite): self
    {
        $this->civilite = $civilite;

        return $this;
    }

    /**
     * Get civilite
     */
    public function getCivilite(): ?string
    {
        return $this->civilite;
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
     * Set prenom
     */
    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    /**
     * Get prenom
     */
    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    /**
     * Set titre
     */
    public function setTitre(?string $titre): self
    {
        $this->titre = $titre;

        return $this;
    }

    /**
     * Get titre
     */
    public function getTitre(): ?string
    {
        return $this->titre;
    }

    /**
     * Set telephone
     */
    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * Get telephone
     */
    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    /**
     * Set email
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }
}

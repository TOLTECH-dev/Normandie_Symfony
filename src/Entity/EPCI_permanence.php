<?php

namespace App\Entity;

use App\Repository\EPCI_permanenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'EPCI_permanence')]
#[ORM\Entity(repositoryClass: EPCI_permanenceRepository::class)]
class EPCI_permanence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 255, nullable: true)]
    protected ?string $nom = null;

    #[ORM\Column(name: 'adresse', type: 'string', length: 255, nullable: true)]
    protected ?string $adresse = null;

    #[ORM\Column(name: 'code_postal', type: 'string', length: 20, nullable: true)]
    protected ?string $codePostal = null;

    #[ORM\Column(name: 'ville', type: 'string', length: 255, nullable: true)]
    protected ?string $ville = null;

    #[ORM\Column(name: 'telephone', type: 'string', length: 255, nullable: true)]
    protected ?string $telephone = null;

    #[ORM\Column(name: 'email', type: 'string', length: 255, nullable: true)]
    protected ?string $email = null;

    #[ORM\Column(name: 'jour_ouverture', type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    protected ?string $jourOuverture = null;

    #[ORM\Column(name: 'horaire', type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    protected ?string $horaire = null;

    /**
     * Get id
     */
    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * Set jourOuverture
     */
    public function setJourOuverture(?string $jourOuverture): self
    {
        $this->jourOuverture = $jourOuverture;

        return $this;
    }

    /**
     * Get jourOuverture
     */
    public function getJourOuverture(): ?string
    {
        return $this->jourOuverture;
    }

    /**
     * Set horaire
     */
    public function setHoraire(?string $horaire): self
    {
        $this->horaire = $horaire;

        return $this;
    }

    /**
     * Get horaire
     */
    public function getHoraire(): ?string
    {
        return $this->horaire;
    }
}

<?php

namespace App\Entity;

use App\Repository\Partenaire_adresseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Partenaire_adresseRepository::class)]
#[ORM\Table(name: 'partenaire_adresse')]
class Partenaire_adresse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $adresse1;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $adresse2 = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $codePostal;

    #[ORM\Column(type: 'string', length: 255)]
    private string $ville;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $departement = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $telFixe = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $telMobile = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $siteInternet = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    private ?string $complement = null;

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
     * Set adresse1
     *
     * @param string $adresse1
     *
     * @return Partenaire_adresse
     */
    public function setAdresse1(string $adresse1): self
    {
        $this->adresse1 = $adresse1;

        return $this;
    }

    /**
     * Get adresse1
     *
     * @return string
     */
    public function getAdresse1(): string
    {
        return $this->adresse1;
    }

    /**
     * Set adresse2
     *
     * @param string $adresse2
     *
     * @return Partenaire_adresse
     */
    public function setAdresse2(?string $adresse2): self
    {
        $this->adresse2 = $adresse2;

        return $this;
    }

    /**
     * Get adresse2
     *
     * @return string
     */
    public function getAdresse2(): ?string
    {
        return $this->adresse2;
    }

    /**
     * Set codePostal
     *
     * @param string $codePostal
     *
     * @return Partenaire_adresse
     */
    public function setCodePostal(string $codePostal): self
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    /**
     * Get codePostal
     *
     * @return string
     */
    public function getCodePostal(): string
    {
        return $this->codePostal;
    }

    /**
     * Set ville
     *
     * @param string $ville
     *
     * @return Partenaire_adresse
     */
    public function setVille(string $ville): self
    {
        $this->ville = $ville;

        return $this;
    }

    /**
     * Get ville
     *
     * @return string
     */
    public function getVille(): string
    {
        return $this->ville;
    }

    /**
     * Set departement
     *
     * @param string $departement
     *
     * @return Partenaire_adresse
     */
    public function setDepartement(?string $departement): self
    {
        $this->departement = $departement;

        return $this;
    }

    /**
     * Get departement
     *
     * @return string
     */
    public function getDepartement(): ?string
    {
        return $this->departement;
    }

    /**
     * Set telFixe
     *
     * @param string $telFixe
     *
     * @return Partenaire_adresse
     */
    public function setTelFixe(?string $telFixe): self
    {
        $this->telFixe = $telFixe;

        return $this;
    }

    /**
     * Get telFixe
     *
     * @return string
     */
    public function getTelFixe(): ?string
    {
        return $this->telFixe;
    }

    /**
     * Set telMobile
     *
     * @param string $telMobile
     *
     * @return Partenaire_adresse
     */
    public function setTelMobile(?string $telMobile): self
    {
        $this->telMobile = $telMobile;

        return $this;
    }

    /**
     * Get telMobile
     *
     * @return string
     */
    public function getTelMobile(): ?string
    {
        return $this->telMobile;
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return Partenaire_adresse
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set siteInternet
     *
     * @param string $siteInternet
     *
     * @return Partenaire_adresse
     */
    public function setSiteInternet(?string $siteInternet): self
    {
        $this->siteInternet = $siteInternet;

        return $this;
    }

    /**
     * Get siteInternet
     *
     * @return string
     */
    public function getSiteInternet(): ?string
    {
        return $this->siteInternet;
    }

    /**
     * Set complement
     *
     * @param string $complement
     *
     * @return Partenaire_adresse
     */
    public function setComplement(?string $complement): self
    {
        $this->complement = $complement;

        return $this;
    }

    /**
     * Get complement
     *
     * @return string
     */
    public function getComplement(): ?string
    {
        return $this->complement;
    }
}

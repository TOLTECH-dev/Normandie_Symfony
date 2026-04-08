<?php

namespace App\Entity;

use App\Repository\Structure_adresseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "structure_adresse")]
#[ORM\Entity(repositoryClass: Structure_adresseRepository::class)]
class Structure_adresse
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "adresse1", type: "string", length: 255)]
    private string $adresse1;

    #[ORM\Column(name: "adresse2", type: "string", length: 255, nullable: true)]
    private ?string $adresse2 = null;

    #[ORM\Column(name: "adresse3", type: "string", length: 255, nullable: true)]
    private ?string $adresse3 = null;

    #[ORM\Column(name: "code_postal", type: "string", length: 20)]
    private string $codePostal;

    #[ORM\Column(name: "ville", type: "string", length: 255)]
    private string $ville;

    #[ORM\Column(name: "telephone", type: "string", length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(name: "site_internet", type: "string", length: 255, nullable: true)]
    private ?string $siteInternet = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setAdresse1(string $adresse1): self
    {
        $this->adresse1 = $adresse1;
        return $this;
    }

    public function getAdresse1(): string
    {
        return $this->adresse1;
    }

    public function setAdresse2(?string $adresse2): self
    {
        $this->adresse2 = $adresse2;
        return $this;
    }

    public function getAdresse2(): ?string
    {
        return $this->adresse2;
    }

    public function setAdresse3(?string $adresse3): self
    {
        $this->adresse3 = $adresse3;
        return $this;
    }

    public function getAdresse3(): ?string
    {
        return $this->adresse3;
    }

    public function setCodePostal(string $codePostal): self
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getCodePostal(): string
    {
        return $this->codePostal;
    }

    public function setVille(string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    public function getVille(): string
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

    public function setSiteInternet(?string $siteInternet): self
    {
        $this->siteInternet = $siteInternet;
        return $this;
    }

    public function getSiteInternet(): ?string
    {
        return $this->siteInternet;
    }
}

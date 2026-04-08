<?php

namespace App\Entity;

use App\Repository\Structure_identificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "structure_identification")]
#[ORM\Entity(repositoryClass: Structure_identificationRepository::class)]
class Structure_identification
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "nom", type: "string", length: 255)]
    private string $nom;

    #[ORM\Column(name: "code", type: "string", length: 255)]
    private string $code;

    public function getId(): ?int
    {
        return $this->id;
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

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}

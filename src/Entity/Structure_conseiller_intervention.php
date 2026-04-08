<?php

namespace App\Entity;

use App\Repository\Structure_conseiller_interventionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "structure_conseiller_intervention")]
#[ORM\Entity(repositoryClass: Structure_conseiller_interventionRepository::class)]
class Structure_conseiller_intervention
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "nom", type: "string", length: 255)]
    private string $name;

    #[ORM\Column(name: "code", type: "string", length: 10)]
    private string $code;

    #[ORM\Column(name: "slug", type: "string", length: 50)]
    private string $slug;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
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

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}

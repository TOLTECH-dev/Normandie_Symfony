<?php

namespace App\Entity;

use App\Repository\Structure_statutRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "structure_statut")]
#[ORM\Entity(repositoryClass: Structure_statutRepository::class)]
class Structure_statut
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "enabled", type: "boolean", nullable: true)]
    private ?bool $enabled = null;

    #[ORM\Column(name: "date_inactif", type: "date", nullable: true)]
    private ?\DateTimeInterface $dateInactif = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setEnabled(?bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setDateInactif(?\DateTimeInterface $dateInactif): self
    {
        $this->dateInactif = $dateInactif;
        return $this;
    }

    public function getDateInactif(): ?\DateTimeInterface
    {
        return $this->dateInactif;
    }
}

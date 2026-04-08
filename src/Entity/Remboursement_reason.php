<?php

namespace App\Entity;

use App\Repository\Remboursement_reasonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Remboursement_reasonRepository::class)]
#[ORM\Table(name: 'remboursement_reason')]
class Remboursement_reason
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $filtre = null;

    #[ORM\Column(type: 'text', length: 255)]
    private string $slug;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $positionLast = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setFiltre(?string $filtre): self
    {
        $this->filtre = $filtre;
        return $this;
    }

    public function getFiltre(): ?string
    {
        return $this->filtre;
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

    public function setPositionLast(?int $positionLast): self
    {
        $this->positionLast = $positionLast;
        return $this;
    }

    public function getPositionLast(): ?int
    {
        return $this->positionLast;
    }
}

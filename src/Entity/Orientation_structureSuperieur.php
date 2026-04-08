<?php

namespace App\Entity;

use App\Repository\Orientation_structureSuperieurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Orientation_structureSuperieurRepository::class)]
#[ORM\Table(name: 'orientation_structure_superieur')]
#[ORM\UniqueConstraint(name: 'orientation_structure_idx', columns: ['orientation_id', 'structure_id'])]
class Orientation_structureSuperieur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Orientation::class, inversedBy: 'orientationStructureSuperieur')]
    #[ORM\JoinColumn(name: 'orientation_id', referencedColumnName: 'id')]
    private ?Orientation $orientation = null;

    #[ORM\ManyToOne(targetEntity: Structure_::class, inversedBy: 'orientation_structureSuperieur')]
    #[ORM\JoinColumn(name: 'structure_id', referencedColumnName: 'id')]
    private ?Structure_ $structure = null;

    public function __construct() {}

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setOrientation(?Orientation $orientation): self
    {
        $this->orientation = $orientation;
        return $this;
    }

    public function getOrientation(): ?Orientation
    {
        return $this->orientation;
    }

    public function setStructure(?Structure_ $structure): self
    {
        $this->structure = $structure;
        return $this;
    }

    public function getStructure(): ?Structure_
    {
        return $this->structure;
    }
}

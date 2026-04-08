<?php

namespace App\Entity;

use App\Repository\Remboursement_travauxRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Remboursement_travauxRepository::class)]
#[ORM\Table(name: 'remboursement_travaux')]
class Remboursement_travaux
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: FicheTechnique::class, cascade: ['persist'])]
    #[Assert\Valid]
    private ?FicheTechnique $ficheTechnique = null;

    #[ORM\OneToOne(targetEntity: Remboursement_travaux_instruction::class, cascade: ['persist'])]
    #[Assert\Valid]
    private ?Remboursement_travaux_instruction $instruction = null;

    #[ORM\OneToOne(targetEntity: Rating::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    #[Assert\Valid]
    private ?Rating $rating = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setFicheTechnique(?FicheTechnique $ficheTechnique): self
    {
        $this->ficheTechnique = $ficheTechnique;
        return $this;
    }

    public function getFicheTechnique(): ?FicheTechnique
    {
        return $this->ficheTechnique;
    }

    public function setInstruction(?Remboursement_travaux_instruction $instruction): self
    {
        $this->instruction = $instruction;
        return $this;
    }

    public function getInstruction(): ?Remboursement_travaux_instruction
    {
        return $this->instruction;
    }

    public function setRating(?Rating $rating): self
    {
        $this->rating = $rating;
        return $this;
    }

    public function getRating(): ?Rating
    {
        return $this->rating;
    }
}

<?php

namespace App\Entity;

use App\Repository\Remboursement_auditEnergie_validationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Remboursement_auditEnergie_validationRepository::class)]
#[ORM\Table(name: 'remboursement_audit_energie_validation')]
class Remboursement_auditEnergie_validation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'is_conforme', type: 'string', length: 20, nullable: true)]
    private ?string $isConforme = null;

    #[ORM\OneToOne(targetEntity: Rating::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    #[Assert\Valid]
    private ?Rating $rating = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setIsConforme(?string $isConforme): self
    {
        $this->isConforme = $isConforme;
        return $this;
    }

    public function getIsConforme(): ?string
    {
        return $this->isConforme;
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

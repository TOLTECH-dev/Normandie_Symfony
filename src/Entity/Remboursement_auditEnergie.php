<?php

namespace App\Entity;

use App\Repository\Remboursement_auditEnergieRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Remboursement_auditEnergieRepository::class)]
#[ORM\Table(name: 'remboursement_audit_energie')]
class Remboursement_auditEnergie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Remboursement_auditEnergie_depot::class, cascade: ['persist'])]
    #[Assert\Valid]
    private ?Remboursement_auditEnergie_depot $depot = null;

    #[ORM\OneToOne(targetEntity: Remboursement_auditEnergie_validation::class, cascade: ['persist'])]
    private ?Remboursement_auditEnergie_validation $validation = null;

    #[ORM\OneToOne(targetEntity: Remboursement_auditEnergie_instruction::class, cascade: ['persist'])]
    #[Assert\Valid]
    private ?Remboursement_auditEnergie_instruction $instruction = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDepot(?Remboursement_auditEnergie_depot $depot): self
    {
        $this->depot = $depot;
        return $this;
    }

    public function getDepot(): ?Remboursement_auditEnergie_depot
    {
        return $this->depot;
    }

    public function setValidation(?Remboursement_auditEnergie_validation $validation): self
    {
        $this->validation = $validation;
        return $this;
    }

    public function getValidation(): ?Remboursement_auditEnergie_validation
    {
        return $this->validation;
    }

    public function setInstruction(?Remboursement_auditEnergie_instruction $instruction): self
    {
        $this->instruction = $instruction;
        return $this;
    }

    public function getInstruction(): ?Remboursement_auditEnergie_instruction
    {
        return $this->instruction;
    }
}

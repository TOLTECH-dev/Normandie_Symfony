<?php

namespace App\Entity;

use App\Repository\Remboursement_auditNumeriqueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Remboursement_auditNumeriqueRepository::class)]
#[ORM\Table(name: 'remboursement_audit_numerique')]
class Remboursement_auditNumerique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Remboursement_auditNumerique_depot::class, cascade: ['persist'])]
    #[Assert\Valid]
    private ?Remboursement_auditNumerique_depot $depot = null;

    #[ORM\OneToOne(targetEntity: Remboursement_auditNumerique_instruction::class, cascade: ['persist'])]
    #[Assert\Valid]
    private ?Remboursement_auditNumerique_instruction $instruction = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDepot(?Remboursement_auditNumerique_depot $depot): self
    {
        $this->depot = $depot;
        return $this;
    }

    public function getDepot(): ?Remboursement_auditNumerique_depot
    {
        return $this->depot;
    }

    public function setInstruction(?Remboursement_auditNumerique_instruction $instruction): self
    {
        $this->instruction = $instruction;
        return $this;
    }

    public function getInstruction(): ?Remboursement_auditNumerique_instruction
    {
        return $this->instruction;
    }
}

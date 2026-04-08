<?php

namespace App\Entity;

use App\Repository\Demande_auditNumeriqueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "demande_audit_numerique")]
#[ORM\Entity(repositoryClass: Demande_auditNumeriqueRepository::class)]
#[ORM\Index(name: "structure_idx", columns: ["structure_id"])]
#[ORM\Index(name: "auditeur_idx", columns: ["auditeur_id"])]
#[ORM\Index(name: "conseiller_idx", columns: ["conseiller_id"])]
class Demande_auditNumerique
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "commitment", type: "boolean")]
    #[Assert\NotBlank]
    private bool $commitment;

    #[ORM\Column(name: "structure_id", type: "integer", nullable: true)]
    private ?int $structure_id = null;

    #[ORM\Column(name: "conseiller_id", type: "integer", nullable: true)]
    private ?int $conseiller_id = null;

    #[ORM\Column(name: "auditeur_id", type: "integer", nullable: true)]
    private ?int $auditeur_id = null;

    #[ORM\Column(name: "signature", type: "boolean")]
    #[Assert\NotBlank]
    private bool $signature;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCommitment(bool $commitment): self
    {
        $this->commitment = $commitment;
        return $this;
    }

    public function getCommitment(): bool
    {
        return $this->commitment;
    }

    public function setStructureId(?int $structureId): self
    {
        $this->structure_id = $structureId;
        return $this;
    }

    public function getStructureId(): ?int
    {
        return $this->structure_id;
    }

    public function setConseillerId(?int $conseillerId): self
    {
        $this->conseiller_id = $conseillerId;
        return $this;
    }

    public function getConseillerId(): ?int
    {
        return $this->conseiller_id;
    }

    public function setAuditeurId(?int $auditeurId): self
    {
        $this->auditeur_id = $auditeurId;
        return $this;
    }

    public function getAuditeurId(): ?int
    {
        return $this->auditeur_id;
    }

    public function setSignature(bool $signature): self
    {
        $this->signature = $signature;
        return $this;
    }

    public function getSignature(): bool
    {
        return $this->signature;
    }
}

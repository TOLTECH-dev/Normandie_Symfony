<?php

namespace App\Entity;

use App\Repository\Instruction_Repository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Instruction_Repository::class)]
#[ORM\Table(name: 'instruction_')]
#[ORM\Index(name: 'demande_idx', columns: ['demande_id'])]
class Instruction_
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $dateCreation;

    #[ORM\Column(type: 'string', length: 255)]
    private string $auteurCreation;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $dateModif;

    #[ORM\Column(type: 'string', length: 255)]
    private string $auteurModif;

    #[ORM\OneToOne(targetEntity: Instruction_auditEnergie::class, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    public ?Instruction_auditEnergie $instruction_auditEnergie = null;

    #[ORM\OneToOne(targetEntity: Instruction_travaux::class, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    public ?Instruction_travaux $instruction_travaux = null;

    #[ORM\Column(type: 'integer', unique: true)]
    private int $demande_id;

    public static array $conformiteJPTypeDocument = [
        'Taxe foncière'         => '0 | taxe_fonciere',
        'Attestation notariale' => '1 | attestation_notariale',
        'Promesse de vente'     => '2 | promesse_vente'
    ];

    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDateCreation(\DateTime $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateCreation(): \DateTime
    {
        return $this->dateCreation;
    }

    public function setAuteurCreation(string $auteurCreation): self
    {
        $this->auteurCreation = $auteurCreation;
        return $this;
    }

    public function getAuteurCreation(): string
    {
        return $this->auteurCreation;
    }

    public function setDateModif(\DateTime $dateModif): self
    {
        $this->dateModif = $dateModif;
        return $this;
    }

    public function getDateModif(): \DateTime
    {
        return $this->dateModif;
    }

    public function setAuteurModif(string $auteurModif): self
    {
        $this->auteurModif = $auteurModif;
        return $this;
    }

    public function getAuteurModif(): string
    {
        return $this->auteurModif;
    }

    public function setDemandeId(int $demandeId): self
    {
        $this->demande_id = $demandeId;
        return $this;
    }

    public function getDemandeId(): int
    {
        return $this->demande_id;
    }

    public function setInstructionAuditEnergie(?Instruction_auditEnergie $instructionAuditEnergie): self
    {
        $this->instruction_auditEnergie = $instructionAuditEnergie;
        return $this;
    }

    public function getInstructionAuditEnergie(): ?Instruction_auditEnergie
    {
        return $this->instruction_auditEnergie;
    }

    public function setInstructionTravaux(?Instruction_travaux $instructionTravaux): self
    {
        $this->instruction_travaux = $instructionTravaux;
        return $this;
    }

    public function getInstructionTravaux(): ?Instruction_travaux
    {
        return $this->instruction_travaux;
    }
}

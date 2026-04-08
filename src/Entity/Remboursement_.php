<?php

namespace App\Entity;

use App\Repository\Remboursement_Repository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Remboursement_Repository::class)]
#[ORM\Table(name: 'remboursement_')]
#[ORM\Index(name: 'statut_idx', columns: ['statut_id'])]
#[ORM\Index(name: 'demande_idx', columns: ['demande_id'])]
#[ORM\Index(name: 'titre_idx', columns: ['titre_id'])]
#[ORM\Index(name: 'rgpdx', columns: ['rgpd'])]
#[ORM\Index(name: 'is_audit_rmb_termine_doc_deletedx', columns: ['is_audit_rmb_termine_doc_deleted'])]
#[ORM\Index(name: 'is_travaux_rmb_termine_doc_deletedx', columns: ['is_travaux_rmb_termine_doc_deleted'])]
class Remboursement_
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTime $dateCreation;

    #[ORM\Column(name: 'auteur_creation', type: 'string', length: 255)]
    private string $auteurCreation;

    #[ORM\Column(name: 'date_modif', type: 'datetime')]
    private \DateTime $dateModif;

    #[ORM\Column(name: 'auteur_modif', type: 'string', length: 255)]
    private string $auteurModif;

    #[ORM\Column(name: 'date_instruction_instructeur', type: 'datetime', nullable: true)]
    private ?\DateTime $dateInstructionInstructeur = null;

    #[ORM\OneToOne(targetEntity: Remboursement_auditEnergie::class, cascade: ['persist'])]
    #[Assert\Valid]
    private ?Remboursement_auditEnergie $remboursement_auditEnergie = null;

    #[ORM\OneToOne(targetEntity: Remboursement_auditNumerique::class, cascade: ['persist'])]
    #[Assert\Valid]
    private ?Remboursement_auditNumerique $remboursement_auditNumerique = null;

    #[ORM\OneToOne(targetEntity: Remboursement_travaux::class, cascade: ['persist'])]
    #[Assert\Valid]
    private ?Remboursement_travaux $remboursement_travaux = null;

    #[ORM\Column(name: 'statut_id', type: 'integer')]
    private int $statut_id;

    #[ORM\Column(name: 'demande_id', type: 'integer')]
    private int $demande_id;

    #[ORM\Column(name: 'titre_id', type: 'integer')]
    private int $titre_id;

    #[ORM\Column(name: 'dateRMH_id', type: 'integer', nullable: true)]
    private ?int $dateRMH_id = null;

    #[ORM\Column(name: 'motif_refus', type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    private ?string $motifRefus = null;

    #[ORM\Column(name: 'statut_description', type: 'text', nullable: true)]
    private ?string $statutDescription = null;

    #[ORM\Column(name: 'rgpd', type: 'boolean', options: ['default' => false])]
    private bool $rgpd = false;

    #[ORM\Column(name: 'is_audit_rmb_termine_doc_deleted', type: 'boolean', options: ['default' => false])]
    private bool $isAuditRmbTermineDocDeleted = false;

    #[ORM\Column(name: 'is_travaux_rmb_termine_doc_deleted', type: 'boolean', options: ['default' => false])]
    private bool $isTravauxRmbTermineDocDeleted = false;

    public static array $arrayRemboursementSeuilCheckMontantFacture = [
        '800.00' => '900',
        '600.00' => '700',
        '500.00' => '600'
    ];

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModif = new \DateTime();
        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
    }

    public function getId(): ?int { return $this->id; }
    public function setDateCreation(\DateTime $dateCreation): self { $this->dateCreation = $dateCreation; return $this; }
    public function getDateCreation(): \DateTime { return $this->dateCreation; }
    public function setAuteurCreation(string $auteurCreation): self { $this->auteurCreation = $auteurCreation; return $this; }
    public function getAuteurCreation(): string { return $this->auteurCreation; }
    public function setDateModif(\DateTime $dateModif): self { $this->dateModif = $dateModif; return $this; }
    public function getDateModif(): \DateTime { return $this->dateModif; }
    public function setAuteurModif(string $auteurModif): self { $this->auteurModif = $auteurModif; return $this; }
    public function getAuteurModif(): string { return $this->auteurModif; }
    public function setDateInstructionInstructeur(?\DateTime $dateInstructionInstructeur): self { $this->dateInstructionInstructeur = $dateInstructionInstructeur; return $this; }
    public function getDateInstructionInstructeur(): ?\DateTime { return $this->dateInstructionInstructeur; }
    public function setStatutId(int $statutId): self { $this->statut_id = $statutId; return $this; }
    public function getStatutId(): int { return $this->statut_id; }
    public function setDemandeId(int $demandeId): self { $this->demande_id = $demandeId; return $this; }
    public function getDemandeId(): int { return $this->demande_id; }
    public function setTitreId(int $titreId): self { $this->titre_id = $titreId; return $this; }
    public function getTitreId(): int { return $this->titre_id; }
    public function setDateRMHId(?int $dateRMHId): self { $this->dateRMH_id = $dateRMHId; return $this; }
    public function getDateRMHId(): ?int { return $this->dateRMH_id; }
    public function setMotifRefus(?string $motifRefus): self { $this->motifRefus = $motifRefus; return $this; }
    public function getMotifRefus(): ?string { return $this->motifRefus; }
    public function setStatutDescription(?string $statutDescription): self { $this->statutDescription = $statutDescription; return $this; }
    public function getStatutDescription(): ?string { return $this->statutDescription; }
    public function setRgpd(bool $rgpd): self { $this->rgpd = $rgpd; return $this; }
    public function getRgpd(): bool { return $this->rgpd; }
    public function setIsAuditRmbTermineDocDeleted(bool $isAuditRmbTermineDocDeleted): self { $this->isAuditRmbTermineDocDeleted = $isAuditRmbTermineDocDeleted; return $this; }
    public function getIsAuditRmbTermineDocDeleted(): bool { return $this->isAuditRmbTermineDocDeleted; }
    public function setIsTravauxRmbTermineDocDeleted(bool $isTravauxRmbTermineDocDeleted): self { $this->isTravauxRmbTermineDocDeleted = $isTravauxRmbTermineDocDeleted; return $this; }
    public function getIsTravauxRmbTermineDocDeleted(): bool { return $this->isTravauxRmbTermineDocDeleted; }
    public function setRemboursementAuditEnergie(?Remboursement_auditEnergie $remboursement_auditEnergie): self { $this->remboursement_auditEnergie = $remboursement_auditEnergie; return $this; }
    public function getRemboursementAuditEnergie(): ?Remboursement_auditEnergie { return $this->remboursement_auditEnergie; }
    public function setRemboursementAuditNumerique(?Remboursement_auditNumerique $remboursement_auditNumerique): self { $this->remboursement_auditNumerique = $remboursement_auditNumerique; return $this; }
    public function getRemboursementAuditNumerique(): ?Remboursement_auditNumerique { return $this->remboursement_auditNumerique; }
    public function setRemboursementTravaux(?Remboursement_travaux $remboursement_travaux): self { $this->remboursement_travaux = $remboursement_travaux; return $this; }
    public function getRemboursementTravaux(): ?Remboursement_travaux { return $this->remboursement_travaux; }
}

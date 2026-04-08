<?php

namespace App\Entity;

use App\Repository\Remboursement_auditNumerique_depotRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Remboursement_auditNumerique_depotRepository::class)]
#[ORM\Table(name: 'remboursement_audit_numerique_depot')]
class Remboursement_auditNumerique_depot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'rib_updated_at', type: 'datetime', nullable: true)]
    private ?\DateTime $ribUpdatedAt = null;

    #[ORM\Column(name: 'audit_url', type: 'string', length: 255)]
    private ?string $auditUrl = '';

    #[ORM\Column(name: 'audit_alt', type: 'string', length: 255)]
    private ?string $auditAlt = '';

    #[Assert\File(
        maxSize: '10240k',
        mimeTypes: ["application/pdf", "image/jpg", "image/jpeg", "image/png"],
        mimeTypesMessage: "Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png"
    )]
    private ?UploadedFile $audit = null;

    private ?string $tempFilename = null;

    public function getId(): ?int { return $this->id; }
    public function setRibUpdatedAt(?\DateTime $ribUpdatedAt): self { $this->ribUpdatedAt = $ribUpdatedAt; return $this; }
    public function getRibUpdatedAt(): ?\DateTime { return $this->ribUpdatedAt; }
    public function setAuditUrl(?string $auditUrl): self { $this->auditUrl = $auditUrl; return $this; }
    public function getAuditUrl(): ?string { return $this->auditUrl; }
    public function setAuditAlt(?string $auditAlt): self { $this->auditAlt = $auditAlt; return $this; }
    public function getAuditAlt(): ?string { return $this->auditAlt; }
    public function getAudit(): ?UploadedFile { return $this->audit; }
    public function setAudit(?UploadedFile $audit): void {
        $this->audit = $audit;

        if (null !== $this->auditUrl) {
            $this->tempFilename = $this->auditUrl;
            $this->auditUrl = null;
            $this->auditAlt = null;
        }

        if ($audit !== null) {
            $this->setRibUpdatedAt(new \DateTime());
            $this->auditUrl = $this->audit->guessExtension();
            $this->auditAlt = $this->audit->getClientOriginalName();
        }
    }
    public function auditGetUploadDir(): string { return 'uploads/remboursement/auditNumerique_depot'; }
    public function audit_getWebPath(): string { return $this->auditGetUploadDir() . '/' . $this->getId() . '_audit.' . $this->getAuditUrl(); }

    public function getTempFilename(): ?string
    {
        return $this->tempFilename;
    }

    public function setTempFilename(?string $tempFilename): void
    {
        $this->tempFilename = $tempFilename;
    }
}

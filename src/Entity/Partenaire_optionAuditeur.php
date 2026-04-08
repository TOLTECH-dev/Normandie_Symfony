<?php

namespace App\Entity;

use App\Repository\Partenaire_optionAuditeurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Partenaire_optionAuditeurRepository::class)]
#[ORM\Table(name: 'partenaire_option_auditeur')]
class Partenaire_optionAuditeur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'rib_updated_at', type: 'datetime', nullable: true)]
    private ?\DateTime $ribUpdatedAt = null;

    #[ORM\Column(name: 'domicile_bancaire', type: 'string', length: 255, nullable: true)]
    private ?string $domicileBancaire = null;

    #[ORM\Column(name: 'titulaire', type: 'string', length: 255, nullable: true)]
    private ?string $titulaire = null;

    #[ORM\Column(name: 'iban', type: 'string', length: 255, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(name: 'bic', type: 'string', length: 255, nullable: true)]
    private ?string $bic = null;

    #[ORM\Column(name: 'rib_url', type: 'string', length: 255, nullable: true)]
    private ?string $ribUrl = null;

    #[ORM\Column(name: 'rib_alt', type: 'string', length: 255, nullable: true)]
    private ?string $ribAlt = null;

    #[Assert\File(
        maxSize: '5120k',
        mimeTypes: ["application/pdf", "image/jpg", "image/jpeg", "image/png"],
        mimeTypesMessage: "Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png"
    )]
    private ?UploadedFile $rib = null;

    private ?string $tempFilename = null;

    /**
     * Get id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set ribUpdatedAt
     */
    public function setRibUpdatedAt(?\DateTime $ribUpdatedAt): self
    {
        $this->ribUpdatedAt = $ribUpdatedAt;
        return $this;
    }

    /**
     * Get ribUpdatedAt
     */
    public function getRibUpdatedAt(): ?\DateTime
    {
        return $this->ribUpdatedAt;
    }

    /**
     * Set domicileBancaire
     */
    public function setDomicileBancaire(?string $domicileBancaire): self
    {
        $this->domicileBancaire = $domicileBancaire;
        return $this;
    }

    /**
     * Get domicileBancaire
     */
    public function getDomicileBancaire(): ?string
    {
        return $this->domicileBancaire;
    }

    /**
     * Set titulaire
     */
    public function setTitulaire(?string $titulaire): self
    {
        $this->titulaire = $titulaire;
        return $this;
    }

    /**
     * Get titulaire
     */
    public function getTitulaire(): ?string
    {
        return $this->titulaire;
    }

    /**
     * Set iban
     */
    public function setIban(?string $iban): self
    {
        $this->iban = $iban;
        return $this;
    }

    /**
     * Get iban
     */
    public function getIban(): ?string
    {
        return $this->iban;
    }

    /**
     * Set bic
     */
    public function setBic(?string $bic): self
    {
        $this->bic = $bic;
        return $this;
    }

    /**
     * Get bic
     */
    public function getBic(): ?string
    {
        return $this->bic;
    }

    /**
     * Set ribUrl
     */
    public function setRibUrl(?string $ribUrl): self
    {
        $this->ribUrl = $ribUrl;
        return $this;
    }

    /**
     * Get ribUrl
     */
    public function getRibUrl(): ?string
    {
        return $this->ribUrl;
    }

    /**
     * Set ribAlt
     */
    public function setRibAlt(?string $ribAlt): self
    {
        $this->ribAlt = $ribAlt;
        return $this;
    }

    /**
     * Get ribAlt
     */
    public function getRibAlt(): ?string
    {
        return $this->ribAlt;
    }

    /**
     * File upload functions for RIB
     */
    public function getRib(): ?UploadedFile
    {
        return $this->rib;
    }

    /**
     * Set RIB file
     */
    public function setRib(?UploadedFile $rib): self
    {
        $this->rib = $rib;

        if ($rib instanceof UploadedFile) {
            $this->setRibUpdatedAt(new \DateTime());
        }

        if (null !== $this->ribUrl) {
            $this->tempFilename = $this->ribUrl;
            $this->ribUrl = null;
            $this->ribAlt = null;
        }

        if(null!== $rib){
            $this->ribUrl = $this->rib->guessExtension();
            $this->ribAlt = $this->rib->getClientOriginalName();
        }

        return $this;
    }

    public function getTempFilename(): ?string
    {
        return $this->tempFilename;
    }

    public function setTempFilename(?string $tempFilename): void
    {
        $this->tempFilename = $tempFilename;
    }

    /**
     * RIB upload paths
     */
    public function rib_getUploadDir(): string
    {
        return 'uploads/partenaire/auditeur';
    }

    /**
     * Get web path for RIB
     */
    public function rib_getWebPath(): string
    {
        return $this->rib_getUploadDir() . '/' . $this->getId() . '_rib.' . $this->getRibUrl();
    }
}

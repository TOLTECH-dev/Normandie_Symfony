<?php

namespace App\Entity;

use App\Repository\NewsletterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NewsletterRepository::class)]
#[ORM\Table(name: 'newsletter')]
class Newsletter
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

    #[ORM\Column(name: 'subject', type: 'string', length: 255)]
    private string $subject;

    #[ORM\Column(name: 'email', type: 'text', nullable: true)]
    #[Assert\Length(max: 1500)]
    private ?string $email = null;

    #[ORM\Column(name: 'is_sent_to_client', type: 'boolean', nullable: true)]
    private ?bool $isSentToClient = null;

    #[ORM\Column(name: 'is_sent_to_auditeur', type: 'boolean', nullable: true)]
    private ?bool $isSentToAuditeur = null;

    #[ORM\Column(name: 'is_sent_to_renovateur', type: 'boolean', nullable: true)]
    private ?bool $isSentToRenovateur = null;

    #[ORM\Column(name: 'is_sent_to_conseiller', type: 'boolean', nullable: true)]
    private ?bool $isSentToConseiller = null;

    #[ORM\Column(name: 'is_sent_to_EPCI', type: 'boolean', nullable: true)]
    private ?bool $isSentToEPCI = null;

    #[ORM\Column(name: 'is_sent_to_beneficiaire', type: 'boolean', nullable: true)]
    private ?bool $isSentToBeneficiaire = null;

    #[ORM\Column(name: 'is_sent_to_administrateur', type: 'boolean', nullable: true)]
    private ?bool $isSentToAdministrateur = null;

    #[ORM\Column(name: 'is_sent_to_instructeur', type: 'boolean', nullable: true)]
    private ?bool $isSentToInstructeur = null;

    #[ORM\Column(name: 'is_sent_to_technique', type: 'boolean', nullable: true)]
    private ?bool $isSentToTechnique = null;

    #[ORM\Column(name: 'partenaire_type', type: 'array', nullable: true)]
    private ?array $partenaireType = null;

    #[ORM\Column(name: 'file_url', type: 'string', length: 255, nullable: true)]
    private ?string $fileUrl = null;

    #[ORM\Column(name: 'file_alt', type: 'string', length: 255, nullable: true)]
    private ?string $fileAlt = null;

    #[Assert\File(
        maxSize: '5120k',
        mimeTypes: ["text/plain", "text/html"],
        mimeTypesMessage: "Format du fichier invalide. Les formats suivants sont acceptés: .txt, .html, .htm"
    )]
    private ?UploadedFile $file = null;

    private ?string $tempFilename = null;

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
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setIsSentToClient(?bool $isSentToClient): self { $this->isSentToClient = $isSentToClient; return $this; }
    public function getIsSentToClient(): ?bool { return $this->isSentToClient; }
    public function setIsSentToAuditeur(?bool $isSentToAuditeur): self { $this->isSentToAuditeur = $isSentToAuditeur; return $this; }
    public function getIsSentToAuditeur(): ?bool { return $this->isSentToAuditeur; }
    public function setIsSentToRenovateur(?bool $isSentToRenovateur): self { $this->isSentToRenovateur = $isSentToRenovateur; return $this; }
    public function getIsSentToRenovateur(): ?bool { return $this->isSentToRenovateur; }
    public function setIsSentToConseiller(?bool $isSentToConseiller): self { $this->isSentToConseiller = $isSentToConseiller; return $this; }
    public function getIsSentToConseiller(): ?bool { return $this->isSentToConseiller; }
    public function setIsSentToEPCI(?bool $isSentToEPCI): self { $this->isSentToEPCI = $isSentToEPCI; return $this; }
    public function getIsSentToEPCI(): ?bool { return $this->isSentToEPCI; }
    public function setIsSentToBeneficiaire(?bool $isSentToBeneficiaire): self { $this->isSentToBeneficiaire = $isSentToBeneficiaire; return $this; }
    public function getIsSentToBeneficiaire(): ?bool { return $this->isSentToBeneficiaire; }
    public function setIsSentToAdministrateur(?bool $isSentToAdministrateur): self { $this->isSentToAdministrateur = $isSentToAdministrateur; return $this; }
    public function getIsSentToAdministrateur(): ?bool { return $this->isSentToAdministrateur; }
    public function setIsSentToInstructeur(?bool $isSentToInstructeur): self { $this->isSentToInstructeur = $isSentToInstructeur; return $this; }
    public function getIsSentToInstructeur(): ?bool { return $this->isSentToInstructeur; }
    public function setIsSentToTechnique(?bool $isSentToTechnique): self { $this->isSentToTechnique = $isSentToTechnique; return $this; }
    public function getIsSentToTechnique(): ?bool { return $this->isSentToTechnique; }
    public function setPartenaireType(?array $partenaireType): self { $this->partenaireType = $partenaireType; return $this; }
    public function getPartenaireType(): ?array { return $this->partenaireType; }
    public function setFileUrl(?string $fileUrl): self { $this->fileUrl = $fileUrl; return $this; }
    public function getFileUrl(): ?string { return $this->fileUrl; }
    public function setFileAlt(?string $fileAlt): self { $this->fileAlt = $fileAlt; return $this; }
    public function getFileAlt(): ?string { return $this->fileAlt; }
    public function getFile(): ?UploadedFile { return $this->file; }
    public function setFile(?UploadedFile $file): void {
        $this->file = $file;

        if (null !== $this->fileUrl) {
            $this->tempFilename = $this->fileUrl;
            $this->fileUrl = null;
            $this->fileAlt = null;
        }

        if ($file !== null) {
            $this->fileUrl = $file->guessExtension();
            $this->fileAlt = $file->getClientOriginalName();
        }

    }

    public function fileGetUploadDir(): string { return 'uploads/newsletter'; }
    public function fileGetWebPath(): string { return $this->fileGetUploadDir() . '/' . $this->getId() . '_file.' . $this->getFileUrl(); }
    public function setSubject(string $subject): self { $this->subject = $subject; return $this; }
    public function getSubject(): string { return $this->subject; }

    public function getTempFilename(): ?string
    {
        return $this->tempFilename;
    }

    public function setTempFilename(?string $tempFilename): void
    {
        $this->tempFilename = $tempFilename;
    }
}

<?php

namespace App\Entity;

use App\Repository\Admin_importRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Admin_importRepository::class)]
#[ORM\Table(name: 'admin_import')]
class Admin_import
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

    #[ORM\Column(name: 'type', type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(name: 'file_url', type: 'string', length: 255, nullable: true)]
    private ?string $file_url = null;

    #[ORM\Column(name: 'file_alt', type: 'string', length: 255, nullable: true)]
    private ?string $file_alt = null;

    #[Assert\File(maxSize: '20480k', mimeTypes: ["text/csv", "application/csv", "text/plain"])]
    private ?UploadedFile $file = null;

    private ?string $tempFilename = null;

    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
    }

    /**
     * Get id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set dateCreation
     */
    public function setDateCreation(\DateTime $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    /**
     * Get dateCreation
     */
    public function getDateCreation(): \DateTime
    {
        return $this->dateCreation;
    }

    /**
     * Set auteurCreation
     */
    public function setAuteurCreation(string $auteurCreation): self
    {
        $this->auteurCreation = $auteurCreation;
        return $this;
    }

    /**
     * Get auteurCreation
     */
    public function getAuteurCreation(): string
    {
        return $this->auteurCreation;
    }

    /**
     * Set dateModif
     */
    public function setDateModif(\DateTime $dateModif): self
    {
        $this->dateModif = $dateModif;
        return $this;
    }

    /**
     * Get dateModif
     */
    public function getDateModif(): \DateTime
    {
        return $this->dateModif;
    }

    /**
     * Set auteurModif
     */
    public function setAuteurModif(string $auteurModif): self
    {
        $this->auteurModif = $auteurModif;
        return $this;
    }

    /**
     * Get auteurModif
     */
    public function getAuteurModif(): string
    {
        return $this->auteurModif;
    }

    /**
     * Set type
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set fileUrl
     */
    public function setFileUrl(?string $fileUrl): self
    {
        $this->file_url = $fileUrl;
        return $this;
    }

    /**
     * Get fileUrl
     */
    public function getFileUrl(): ?string
    {
        return $this->file_url;
    }

    /**
     * Set fileAlt
     */
    public function setFileAlt(?string $fileAlt): self
    {
        $this->file_alt = $fileAlt;
        return $this;
    }

    /**
     * Get fileAlt
     */
    public function getFileAlt(): ?string
    {
        return $this->file_alt;
    }

    /**
     * Get file
     */
    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    /**
     * Set file
     */
    public function setFile(?UploadedFile $file): self
    {
        $this->file = $file;

        if (null !== $this->file_url) {
            $this->tempFilename = $this->file_url;
            $this->file_url = null;
            $this->file_alt = null;
        }

        if ($file !== null) {
            $this->file_url = $this->file->guessExtension();
            $this->file_alt = $this->file->getClientOriginalName();
        }

        return $this;
    }

    /**
     * Get upload directory
     */
    public function file_getUploadDir(): string
    {
        return 'import';
    }

    /**
     * Get web path
     */
    public function file_getWebPath(): string
    {
        $typeKey = explode(' | ', $this->getType());
        return $this->file_getUploadDir() . '/' . $this->getId() . '_' . $typeKey[1] . '.' . $this->getFileUrl();
    }

    public function getTempFilename(): ?string
    {
        return $this->tempFilename;
    }

    public function setTempFilename(?string $tempFilename): void
    {
        $this->tempFilename = $tempFilename;
    }
}

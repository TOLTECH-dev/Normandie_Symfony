<?php

namespace App\Entity;

use App\Repository\Remboursement_travaux_instruction_conformiteRepository;
use App\Service\RollbackDocumentService;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Remboursement_travaux_instruction_conformiteRepository::class)]
#[ORM\Table(name: 'remboursement_travaux_instruction_conformite')]
class Remboursement_travaux_instruction_conformite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'is_conforme', type: 'string', length: 20, nullable: true)]
    private ?string $isConforme = null;

    #[ORM\Column(name: 'document_url', type: 'string', length: 255, nullable: true)]
    private ?string $document_url = null;

    #[ORM\Column(name: 'document_alt', type: 'string', length: 255, nullable: true)]
    private ?string $document_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $document = null;

    private ?string $tempFilename = null;

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

    public function setDocumentUrl(?string $documentUrl): self
    {
        $this->document_url = $documentUrl;
        return $this;
    }

    public function getDocumentUrl(): ?string
    {
        return $this->document_url;
    }

    public function setDocumentAlt(?string $documentAlt): self
    {
        $this->document_alt = $documentAlt;
        return $this;
    }

    public function getDocumentAlt(): ?string
    {
        return $this->document_alt;
    }

    public function getDocument(): ?UploadedFile
    {
        return $this->document;
    }

    public function setDocument(?UploadedFile $document): self
    {
        $this->document = $document;

        if (null !== $this->document_url) {
            $this->tempFilename = $this->document_url;
            $this->document_url = null;
            $this->document_alt = null;
        }

        if ($document !== null) {
            $this->document_url = $document->guessExtension();
            $this->document_alt = $document->getClientOriginalName();
        }

        return $this;
    }

    /* *****************************************************************
                FONCTIONS POUR LES DOCUMENTS TELECHARGES
    *******************************************************************/
    public function document_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/remboursement/travaux_instruction/conformite';
    }

    public function document_getWebPath(): string
    {
        return $this->document_getUploadDir() . '/' . $this->getId() . '_document' . '.' . $this->getDocumentUrl();
    }

    public function document_getRollbackWebPath(): string
    {
        return $this->document_getUploadDir() . '/' . $this->getId() . '_document' . RollbackDocumentService::$suffixWithExtension;
    }

    public function document_getRollbackWebPathPrefix(): string
    {
        return $this->document_getUploadDir() . '/' . $this->getId() . '_document';
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

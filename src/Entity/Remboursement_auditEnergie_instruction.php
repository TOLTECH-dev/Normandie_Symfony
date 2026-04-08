<?php

namespace App\Entity;

use App\Repository\Remboursement_auditEnergie_instructionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Remboursement_auditEnergie_instructionRepository::class)]
#[ORM\Table(name: 'remboursement_audit_energie_instruction')]
class Remboursement_auditEnergie_instruction extends Remboursement_instruction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'recto_cheque_url', type: 'string', length: 255, nullable: true)]
    private ?string $rectoCheque_url = null;

    #[ORM\Column(name: 'recto_cheque_alt', type: 'string', length: 255, nullable: true)]
    private ?string $rectoCheque_alt = null;

    #[Assert\File(
         maxSize: '5120k',
         mimeTypes: [ 'application/pdf', 'image/jpg', 'image/jpeg', 'image/png' ],
         mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png'
     )]
    private ?UploadedFile $rectoCheque = null;

    #[ORM\Column(name: 'verso_cheque_url', type: 'string', length: 255, nullable: true)]
    private ?string $versoCheque_url = null;

    #[ORM\Column(name: 'verso_cheque_alt', type: 'string', length: 255, nullable: true)]
    private ?string $versoCheque_alt = null;

    #[Assert\File(
         maxSize: '5120k',
         mimeTypes: [ 'application/pdf', 'image/jpg', 'image/jpeg', 'image/png' ],
         mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png'
     )]
    private ?UploadedFile $versoCheque = null;

    #[ORM\Column(name: 'facture_url', type: 'string', length: 255, nullable: true)]
    private ?string $facture_url = null;

    #[ORM\Column(name: 'facture_alt', type: 'string', length: 255, nullable: true)]
    private ?string $facture_alt = null;

    #[Assert\File(
         maxSize: '5120k',
         mimeTypes: [ 'application/pdf', 'image/jpg', 'image/jpeg', 'image/png' ],
         mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png'
     )]
    private ?UploadedFile $facture = null;

    #[ORM\Column(name: 'rib_url', type: 'string', length: 255, nullable: true)]
    private ?string $rib_url = null;

    #[ORM\Column(name: 'rib_alt', type: 'string', length: 255, nullable: true)]
    private ?string $rib_alt = null;

    #[Assert\File(
         maxSize: '5120k',
         mimeTypes: [ 'application/pdf', 'image/jpg', 'image/jpeg', 'image/png' ],
         mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png'
     )]
    private ?UploadedFile $rib = null;

    private ?string $tempFilename = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setRectoChequeUrl(?string $rectoChequeUrl): self
    {
        $this->rectoCheque_url = $rectoChequeUrl;

        return $this;
    }

    public function getRectoChequeUrl(): ?string
    {
        return $this->rectoCheque_url;
    }

    public function setRectoChequeAlt(?string $rectoChequeAlt): self
    {
        $this->rectoCheque_alt = $rectoChequeAlt;

        return $this;
    }

    public function getRectoChequeAlt(): ?string
    {
        return $this->rectoCheque_alt;
    }

    public function setVersoChequeUrl(?string $versoChequeUrl): self
    {
        $this->versoCheque_url = $versoChequeUrl;

        return $this;
    }

    public function getVersoChequeUrl(): ?string
    {
        return $this->versoCheque_url;
    }

    public function setVersoChequeAlt(?string $versoChequeAlt): self
    {
        $this->versoCheque_alt = $versoChequeAlt;

        return $this;
    }

    public function getVersoChequeAlt(): ?string
    {
        return $this->versoCheque_alt;
    }

    public function setFactureUrl(?string $factureUrl): self
    {
        $this->facture_url = $factureUrl;

        return $this;
    }

    public function getFactureUrl(): ?string
    {
        return $this->facture_url;
    }

    public function setFactureAlt(?string $factureAlt): self
    {
        $this->facture_alt = $factureAlt;

        return $this;
    }

    public function getFactureAlt(): ?string
    {
        return $this->facture_alt;
    }

    public function setRibUrl(?string $ribUrl): self
    {
        $this->rib_url = $ribUrl;

        return $this;
    }

    public function getRibUrl(): ?string
    {
        return $this->rib_url;
    }

    public function setRibAlt(?string $ribAlt): self
    {
        $this->rib_alt = $ribAlt;

        return $this;
    }

    public function getRibAlt(): ?string
    {
        return $this->rib_alt;
    }

    /* *****************************************************************
                FONCTIONS POUR LES DOCUMENTS TELECHARGES
    *******************************************************************/
    /**
     * Get recto cheque file
     */
    public function getRectoCheque(): ?UploadedFile
    {
        return $this->rectoCheque;
    }

    /**
     * Set recto cheque file
     */
    public function setRectoCheque(?UploadedFile $rectoCheque): self
    {
        $this->rectoCheque = $rectoCheque;

        if (null !== $this->rectoCheque_url) {
            $this->tempFilename = $this->rectoCheque_url;
            $this->rectoCheque_url = null;
            $this->rectoCheque_alt = null;
        }

        if ($rectoCheque !== null) {
            $this->rectoCheque_url = $this->rectoCheque->guessExtension();
            $this->rectoCheque_alt = $this->rectoCheque->getClientOriginalName();
        }

        return $this;
    }

    /**
     * Get verso cheque file
     */
    public function getVersoCheque(): ?UploadedFile
    {
        return $this->versoCheque;
    }

    /**
     * Set verso cheque file
     */
    public function setVersoCheque(?UploadedFile $versoCheque): self
    {
        $this->versoCheque = $versoCheque;

        if (null !== $this->versoCheque_url) {
            $this->tempFilename = $this->versoCheque_url;
            $this->versoCheque_url = null;
            $this->versoCheque_alt = null;
        }

        if ($versoCheque !== null) {
            $this->versoCheque_url = $this->versoCheque->guessExtension();
            $this->versoCheque_alt = $this->versoCheque->getClientOriginalName();
        }

        return $this;
    }

    /**
     * Get facture file
     */
    public function getFacture(): ?UploadedFile
    {
        return $this->facture;
    }

    /**
     * Set facture file
     */
    public function setFacture(?UploadedFile $facture): self
    {
        $this->facture = $facture;

        if (null !== $this->facture_url) {
            $this->tempFilename = $this->facture_url;
            $this->facture_url = null;
            $this->facture_alt = null;
        }

        if($facture !== null ) {
            $this->facture_url = $this->facture->guessExtension();
            $this->facture_alt = $this->facture->getClientOriginalName();
        }

        return $this;
    }

    /**
     * Get rib file
     */
    public function getRib(): ?UploadedFile
    {
        return $this->rib;
    }

    /**
     * Set rib file
     */
    public function setRib(?UploadedFile $rib): self
    {
        $this->rib = $rib;

        if (null !== $this->rib_url) {
            $this->tempFilename = $this->rib_url;
            $this->rib_url = null;
            $this->rib_alt = null;
        }

        if ($rib !== null) {
            $this->rib_url = $this->rib->guessExtension();
            $this->rib_alt = $this->rib->getClientOriginalName();
        }

        return $this;
    }
    /**
     * Recto cheque upload paths
     */
    public function rectoChequeGetUploadDir(): string
    {
        return 'uploads/remboursement/auditEnergie_instruction';
    }

    public function rectoCheque_getUploadDir(): string
    {
        return $this->rectoChequeGetUploadDir();
    }

    public function rectoCheque_getWebPath(): string
    {
        return $this->rectoCheque_getUploadDir() . '/' . $this->getId() . '_recto_cheque.' . $this->getRectoChequeUrl();
    }

    /**
     * Verso cheque upload paths
     */
    public function versoChequeGetUploadDir(): string
    {
        return 'uploads/remboursement/auditEnergie_instruction';
    }

    public function versoCheque_getUploadDir(): string
    {
        return $this->versoChequeGetUploadDir();
    }

    public function versoCheque_getWebPath(): string
    {
        return $this->versoCheque_getUploadDir() . '/' . $this->getId() . '_verso_cheque.' . $this->getVersoChequeUrl();
    }


    /**
     * Facture upload paths
     */
    public function factureGetUploadDir(): string
    {
        return 'uploads/remboursement/auditEnergie_instruction';
    }

    public function facture_getUploadDir(): string
    {
        return $this->factureGetUploadDir();
    }

    public function facture_getWebPath(): string
    {
        return $this->facture_getUploadDir() . '/' . $this->getId() . '_facture.' . $this->getFactureUrl();
    }

    /**
     * RIB upload paths
     */
    public function ribGetUploadDir(): string
    {
        return 'uploads/remboursement/auditEnergie_instruction';
    }

    public function rib_getUploadDir(): string
    {
        return $this->ribGetUploadDir();
    }

    public function rib_getWebPath(): string
    {
        return $this->rib_getUploadDir() . '/' . $this->getId() . '_rib.' . $this->getRibUrl();
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

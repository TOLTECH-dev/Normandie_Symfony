<?php

namespace App\Entity;

use App\Repository\Remboursement_travaux_instructionRepository;
use App\Service\RollbackDocumentService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Remboursement_travaux_instructionRepository::class)]
#[ORM\Table(name: 'remboursement_travaux_instruction')]
class Remboursement_travaux_instruction extends Remboursement_instruction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'recto_cheque_url', type: 'string', length: 255, nullable: true)]
    private ?string $rectoCheque_url = null;

    #[ORM\Column(name: 'recto_cheque_alt', type: 'string', length: 255, nullable: true)]
    private ?string $rectoCheque_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $rectoCheque = null;

    #[ORM\Column(name: 'verso_cheque_url', type: 'string', length: 255, nullable: true)]
    private ?string $versoCheque_url = null;

    #[ORM\Column(name: 'verso_cheque_alt', type: 'string', length: 255, nullable: true)]
    private ?string $versoCheque_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $versoCheque = null;

    #[ORM\Column(name: 'fiche_travaux_url', type: 'string', length: 255, nullable: true)]
    private ?string $ficheTravaux_url = null;

    #[ORM\Column(name: 'fiche_travaux_alt', type: 'string', length: 255, nullable: true)]
    private ?string $ficheTravaux_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $ficheTravaux = null;

    #[ORM\Column(name: 'is_fiche_travaux_conforme', type: 'string', length: 20, nullable: true)]
    private ?string $isFicheTravauxConforme = null;

    #[ORM\Column(name: 'fiche_travaux_reason', type: 'array', nullable: true)]
    private array|ArrayCollection|null $ficheTravauxReason = null;

    #[ORM\Column(name: 'fiche_travaux_reason_autre', type: 'text', nullable: true)]
    private ?string $ficheTravauxReasonAutre = null;

    #[ORM\ManyToMany(targetEntity: Remboursement_travaux_instruction_conformite::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'remboursement_travaux_instruction__conformite')]
    protected Collection $remboursement_travaux_instruction_conformite;

    #[ORM\Column(name: 'rib_url', type: 'string', length: 255, nullable: true)]
    private ?string $rib_url = null;

    #[ORM\Column(name: 'rib_alt', type: 'string', length: 255, nullable: true)]
    private ?string $rib_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $rib = null;

    private ?string $tempFilename = null;

    public function __construct()
    {
        parent::__construct();
        $this->remboursement_travaux_instruction_conformite = new ArrayCollection();
        $this->ficheTravauxReason = null;
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

    public function setRectoCheque(?UploadedFile $rectoCheque): self
    {
        $this->rectoCheque = $rectoCheque;

        if (null !== $this->rectoCheque_url) {
            $this->tempFilename = $this->rectoCheque_url;
            $this->rectoCheque_url = null;
            $this->rectoCheque_alt = null;
        }

        if ($rectoCheque !== null) {
            $this->rectoCheque_url = $rectoCheque->guessExtension();
            $this->rectoCheque_alt = $rectoCheque->getClientOriginalName();
        }
        return $this;
    }

    public function getRectoCheque(): ?UploadedFile
    {
        return $this->rectoCheque;
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

    public function setVersoCheque(?UploadedFile $versoCheque): self
    {
        $this->versoCheque = $versoCheque;

        if (null !== $this->versoCheque_url) {
            $this->tempFilename = $this->versoCheque_url;
            $this->versoCheque_url = null;
            $this->versoCheque_alt = null;
        }

        if ($versoCheque !== null) {
            $this->versoCheque_url = $versoCheque->guessExtension();
            $this->versoCheque_alt = $versoCheque->getClientOriginalName();
        }

        return $this;
    }

    public function getVersoCheque(): ?UploadedFile
    {
        return $this->versoCheque;
    }

    public function setFicheTravauxUrl(?string $ficheTravauxUrl): self
    {
        $this->ficheTravaux_url = $ficheTravauxUrl;
        return $this;
    }

    public function getFicheTravauxUrl(): ?string
    {
        return $this->ficheTravaux_url;
    }

    public function setFicheTravauxAlt(?string $ficheTravauxAlt): self
    {
        $this->ficheTravaux_alt = $ficheTravauxAlt;
        return $this;
    }

    public function getFicheTravauxAlt(): ?string
    {
        return $this->ficheTravaux_alt;
    }

    public function setFicheTravaux(?UploadedFile $ficheTravaux): self
    {
        $this->ficheTravaux = $ficheTravaux;

        if (null !== $this->ficheTravaux_url) {
            $this->tempFilename = $this->ficheTravaux_url;
            $this->ficheTravaux_url = null;
            $this->ficheTravaux_alt = null;
        }

        if ($ficheTravaux !== null) {
            $this->ficheTravaux_url = $ficheTravaux->guessExtension();
            $this->ficheTravaux_alt = $ficheTravaux->getClientOriginalName();
        }

        return $this;
    }

    public function getFicheTravaux(): ?UploadedFile
    {
        return $this->ficheTravaux;
    }

    public function setIsFicheTravauxConforme(?string $isFicheTravauxConforme): self
    {
        $this->isFicheTravauxConforme = $isFicheTravauxConforme;
        return $this;
    }

    public function getIsFicheTravauxConforme(): ?string
    {
        return $this->isFicheTravauxConforme;
    }

    public function setFicheTravauxReason($ficheTravauxReason): self
    {
        if ($ficheTravauxReason instanceof ArrayCollection) {
            $ficheTravauxReason = $ficheTravauxReason->toArray();
        }
        $this->ficheTravauxReason = $ficheTravauxReason;
        return $this;
    }

    public function getFicheTravauxReason(): ?array
    {
        $ficheTravauxReason = $this->ficheTravauxReason;

        if ($ficheTravauxReason instanceof ArrayCollection) {
            return $ficheTravauxReason->toArray();
        }

        return $ficheTravauxReason;
    }

    public function setFicheTravauxReasonAutre(?string $ficheTravauxReasonAutre): self
    {
        $this->ficheTravauxReasonAutre = $ficheTravauxReasonAutre;
        return $this;
    }

    public function getFicheTravauxReasonAutre(): ?string
    {
        return $this->ficheTravauxReasonAutre;
    }

    public function addRemboursementTravauxInstructionConformite($remboursementTravauxInstructionConformite): self
    {
        $this->remboursement_travaux_instruction_conformite[] = $remboursementTravauxInstructionConformite;
        return $this;
    }

    public function removeRemboursementTravauxInstructionConformite($remboursementTravauxInstructionConformite): void
    {
        $this->remboursement_travaux_instruction_conformite->removeElement($remboursementTravauxInstructionConformite);
    }

    public function getRemboursementTravauxInstructionConformite(): Collection
    {
        return $this->remboursement_travaux_instruction_conformite;
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

    public function setRib(?UploadedFile $rib): self
    {
        $this->rib = $rib;

        if (null !== $this->rib_url) {
            $this->tempFilename = $this->rib_url;
            $this->rib_url = null;
            $this->rib_alt = null;
        }

        if ($rib !== null) {
            $this->rib_url = $rib->guessExtension();
            $this->rib_alt = $rib->getClientOriginalName();
        }

        return $this;
    }

    public function getRib(): ?UploadedFile
    {
        return $this->rib;
    }

    /**
     * @return string
     */
    public function rectoCheque_getUploadDir()
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/remboursement/travaux_instruction';
    }

    /**
     * @return string
     */
    public function rectoCheque_getWebPath()
    {
        return $this->rectoCheque_getUploadDir() . '/' . $this->getId() . '_recto_cheque' . '.' . $this->getRectoChequeUrl();
    }

    /**
     * @return string
     */
    public function rectoCheque_getRollbackWebPath()
    {
        return $this->rectoCheque_getUploadDir() . '/' . $this->getId() . '_recto_cheque' . RollbackDocumentService::$suffixWithExtension;
    }

    /**
     * @return string
     */
    public function rectoCheque_getRollbackWebPathPrefix()
    {
        return $this->rectoCheque_getUploadDir() . '/' . $this->getId() . '_recto_cheque';
    }

    /**
     * @return string
     */
    public function versoCheque_getUploadDir()
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/remboursement/travaux_instruction';
    }

    /**
     * @return string
     */
    public function versoCheque_getWebPath()
    {
        return $this->versoCheque_getUploadDir() . '/' . $this->getId() . '_verso_cheque' . '.' . $this->getVersoChequeUrl();
    }

    /**
     * @return string
     */
    public function versoCheque_getRollbackWebPath()
    {
        return $this->versoCheque_getUploadDir() . '/' . $this->getId() . '_verso_cheque' . RollbackDocumentService::$suffixWithExtension;
    }

    /**
     * @return string
     */
    public function versoCheque_getRollbackWebPathPrefix()
    {
        return $this->versoCheque_getUploadDir() . '/' . $this->getId() . '_verso_cheque';
    }

    /**
     * @return string
     */
    public function ficheTravaux_getUploadDir()
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/remboursement/travaux_instruction';
    }

    /**
     * @return string
     */
    public function ficheTravaux_getWebPath()
    {
        return $this->ficheTravaux_getUploadDir() . '/' . $this->getId() . '_fiche_travaux' . '.' . $this->getFicheTravauxUrl();
    }

    /**
     * @return string
     */
    public function ficheTravaux_getRollbackWebPath()
    {
        return $this->ficheTravaux_getUploadDir() . '/' . $this->getId() . '_fiche_travaux' . RollbackDocumentService::$suffixWithExtension;
    }

    /**
     * @return string
     */
    public function ficheTravaux_getRollbackWebPathPrefix()
    {
        return $this->ficheTravaux_getUploadDir() . '/' . $this->getId() . '_fiche_travaux';
    }

    /**
     * @return string
     */
    public function rib_getUploadDir()
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/remboursement/travaux_instruction';
    }

    /**
     * @return string
     */
    public function rib_getWebPath()
    {
        return $this->rib_getUploadDir() . '/' . $this->getId() . '_rib' . '.' . $this->getRibUrl();
    }

    /**
     * @return string
     */
    public function rib_getRollbackWebPath()
    {
        return $this->rib_getUploadDir() . '/' . $this->getId() . '_rib' . RollbackDocumentService::$suffixWithExtension;
    }

    /**
     * @return string
     */
    public function rib_getRollbackWebPathPrefix()
    {
        return $this->rib_getUploadDir() . '/' . $this->getId() . '_rib';
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

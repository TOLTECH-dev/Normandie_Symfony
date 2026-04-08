<?php

namespace App\Entity;

use App\Repository\Remboursement_auditNumerique_instructionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'remboursement_audit_numerique_instruction')]
#[ORM\Entity(repositoryClass: Remboursement_auditNumerique_instructionRepository::class)]
class Remboursement_auditNumerique_instruction extends Remboursement_instruction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'recto_cheque_url', type: 'string', length: 255, nullable: true)]
    private ?string $rectoCheque_url = null;

    #[ORM\Column(name: 'recto_cheque_alt', type: 'string', length: 255, nullable: true)]
    private ?string $rectoCheque_alt = null;

    #[Assert\File(
        maxSize: '5120k',
        mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'],
        mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png'
    )]
    private ?UploadedFile $rectoCheque = null;

    #[ORM\Column(name: 'verso_cheque_url', type: 'string', length: 255, nullable: true)]
    private ?string $versoCheque_url = null;

    #[ORM\Column(name: 'verso_cheque_alt', type: 'string', length: 255, nullable: true)]
    private ?string $versoCheque_alt = null;

    #[Assert\File(
        maxSize: '5120k',
        mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'],
        mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png'
    )]
    private ?UploadedFile $versoCheque = null;

    #[ORM\Column(name: 'facture_url', type: 'string', length: 255, nullable: true)]
    private ?string $facture_url = null;

    #[ORM\Column(name: 'facture_alt', type: 'string', length: 255, nullable: true)]
    private ?string $facture_alt = null;

    #[Assert\File(
        maxSize: '5120k',
        mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'],
        mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png'
    )]
    private ?UploadedFile $facture = null;

    #[ORM\Column(name: 'rib_url', type: 'string', length: 255, nullable: true)]
    private ?string $rib_url = null;

    #[ORM\Column(name: 'rib_alt', type: 'string', length: 255, nullable: true)]
    private ?string $rib_alt = null;

    #[Assert\File(
        maxSize: '5120k',
        mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'],
        mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png'
    )]
    private ?UploadedFile $rib = null;

    private ?string $tempFilename = null;



    /**
     * Remboursement_auditNumerique_instruction constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set rectoChequeUrl
     */
    public function setRectoChequeUrl(?string $rectoChequeUrl): self
    {
        $this->rectoCheque_url = $rectoChequeUrl;

        return $this;
    }

    /**
     * Get rectoChequeUrl
     */
    public function getRectoChequeUrl(): ?string
    {
        return $this->rectoCheque_url;
    }

    /**
     * Set rectoChequeAlt
     */
    public function setRectoChequeAlt(?string $rectoChequeAlt): self
    {
        $this->rectoCheque_alt = $rectoChequeAlt;

        return $this;
    }

    /**
     * Get rectoChequeAlt
     */
    public function getRectoChequeAlt(): ?string
    {
        return $this->rectoCheque_alt;
    }

    /**
     * Set versoChequeUrl
     */
    public function setVersoChequeUrl(?string $versoChequeUrl): self
    {
        $this->versoCheque_url = $versoChequeUrl;

        return $this;
    }

    /**
     * Get versoChequeUrl
     */
    public function getVersoChequeUrl(): ?string
    {
        return $this->versoCheque_url;
    }

    /**
     * Set versoChequeAlt
     */
    public function setVersoChequeAlt(?string $versoChequeAlt): self
    {
        $this->versoCheque_alt = $versoChequeAlt;

        return $this;
    }

    /**
     * Get versoChequeAlt
     */
    public function getVersoChequeAlt(): ?string
    {
        return $this->versoCheque_alt;
    }

    /**
     * Set factureUrl
     */
    public function setFactureUrl(?string $factureUrl): self
    {
        $this->facture_url = $factureUrl;

        return $this;
    }

    /**
     * Get factureUrl
     */
    public function getFactureUrl(): ?string
    {
        return $this->facture_url;
    }

    /**
     * Set factureAlt
     */
    public function setFactureAlt(?string $factureAlt): self
    {
        $this->facture_alt = $factureAlt;

        return $this;
    }

    /**
     * Get factureAlt
     */
    public function getFactureAlt(): ?string
    {
        return $this->facture_alt;
    }

    /**
     * Set ribUrl
     */
    public function setRibUrl(?string $ribUrl): self
    {
        $this->rib_url = $ribUrl;

        return $this;
    }

    /**
     * Get ribUrl
     */
    public function getRibUrl(): ?string
    {
        return $this->rib_url;
    }

    /**
     * Set ribAlt
     */
    public function setRibAlt(?string $ribAlt): self
    {
        $this->rib_alt = $ribAlt;

        return $this;
    }

    /**
     * Get ribAlt
     */
    public function getRibAlt(): ?string
    {
        return $this->rib_alt;
    }

    /* *****************************************************************
                FONCTIONS POUR LES DOCUMENTS TELECHARGES
    *******************************************************************/
    public function getRectoCheque(): ?UploadedFile
    {
        return $this->rectoCheque;
    }

    public function setRectoCheque(?UploadedFile $rectoCheque): self
    {
        $this->rectoCheque = $rectoCheque;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->rectoCheque_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->rectoCheque_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->rectoCheque_url = null;
            $this->rectoCheque_alt = null;
        }

        if ($rectoCheque !== null) {
            $this->rectoCheque_url = $this->rectoCheque->guessExtension();
            $this->rectoCheque_alt = $this->rectoCheque->getClientOriginalName();
        }

        return $this;
    }

    public function getVersoCheque(): ?UploadedFile
    {
        return $this->versoCheque;
    }

    public function setVersoCheque(?UploadedFile $versoCheque): self
    {
        $this->versoCheque = $versoCheque;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->versoCheque_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->versoCheque_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->versoCheque_url = null;
            $this->versoCheque_alt = null;
        }

        if ($versoCheque !== null) {
            $this->versoCheque_url = $this->versoCheque->guessExtension();
            $this->versoCheque_alt = $this->versoCheque->getClientOriginalName();
        }

        return $this;
    }

    public function getFacture(): ?UploadedFile
    {
        return $this->facture;
    }

    public function setFacture(?UploadedFile $facture): self
    {
        $this->facture = $facture;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->facture_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->facture_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->facture_url = null;
            $this->facture_alt = null;
        }

        if ($facture !== null) {
            $this->facture_url = $this->facture->guessExtension();
            $this->facture_alt = $this->facture->getClientOriginalName();
        }

        return $this;
    }

    public function getRib(): ?UploadedFile
    {
        return $this->rib;
    }

    public function setRib(?UploadedFile $rib): self
    {
        $this->rib = $rib;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->rib_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->rib_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->rib_url = null;
            $this->rib_alt = null;
        }

        if ($rib !== null) {
            $this->rib_url = $this->rib->guessExtension();
            $this->rib_alt = $this->rib->getClientOriginalName();
        }

        return $this;
    }

    /* *****************************************************************
                        EVENEMENTS POUR POUR LE RECTO CHEQUE
    *******************************************************************/

    /**
     * @return string
     */
    public function rectoCheque_getUploadDir()
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/remboursement/auditNumerique_instruction';
    }

    /**
     * @return string
     */
    public function rectoCheque_getWebPath()
    {
        return $this->rectoCheque_getUploadDir() . '/' . $this->getId() . '_recto_cheque' . '.' . $this->getRectoChequeUrl();
    }

    /* *****************************************************************
                        EVENEMENTS POUR POUR LE VERSO CHEQUE
    *******************************************************************/
    /**
     * @return string
     */
    public function versoCheque_getUploadDir()
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/remboursement/auditNumerique_instruction';
    }

    /**
     * @return string
     */
    public function versoCheque_getWebPath()
    {
        return $this->versoCheque_getUploadDir() . '/' . $this->getId() . '_verso_cheque' . '.' . $this->getVersoChequeUrl();
    }

    /* *****************************************************************
                        EVENEMENTS POUR LA FACTURE
    *******************************************************************/
    /**
     * @return string
     */
    public function facture_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/remboursement/auditNumerique_instruction';
    }

    /**
     * @return string
     */
    public function facture_getWebPath(): string
    {
        return $this->facture_getUploadDir() . '/' . $this->getId() . '_facture' . '.' . $this->getFactureUrl();
    }

    /* *****************************************************************
                        EVENEMENTS POUR LE RIB
    *******************************************************************/
    public function rib_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/remboursement/auditNumerique_instruction';
    }

    public function rib_getWebPath(): string
    {
        return $this->rib_getUploadDir() . '/' . $this->getId() . '_rib' . '.' . $this->getRibUrl();
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

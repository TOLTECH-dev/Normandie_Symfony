<?php

namespace App\Entity;

use App\Repository\Demande_travauxRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "demande_travaux")]
#[ORM\Entity(repositoryClass: Demande_travauxRepository::class)]
#[ORM\Index(name: "travaux_devis_idx", columns: ["travaux_devis_id"])]
#[ORM\Index(name: "fiche_technique_idx", columns: ["fiche_technique_id"])]
#[ORM\Index(name: "structure_idx", columns: ["structure_id"])]
#[ORM\Index(name: "conseiller_idx", columns: ["conseiller_id"])]
class Demande_travaux
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "travaux_devis_id", type: "integer", nullable: true)]
    private ?int $travauxDevis_id = null;

    #[ORM\Column(name: "fiche_technique_id", type: "integer", nullable: true)]
    private ?int $ficheTechnique_id = null;

    #[ORM\Column(name: "audit", type: "boolean")]
    private bool $audit;

    #[ORM\Column(name: "nb_pers_foyer", type: "integer", nullable: true)]
    private ?int $nbPersFoyer = null;

    #[ORM\Column(name: "revenu_demandeur", type: "string", length: 255, nullable: true)]
    private ?string $revenu1 = null;

    #[ORM\Column(name: "revenu_conjoint", type: "string", length: 255, nullable: true)]
    private ?string $revenu2 = null;

    #[ORM\Column(name: "revenu_foyer", type: "string", length: 255, nullable: true)]
    private ?string $revenu3 = null;

    #[ORM\Column(name: "structure_id", type: "integer")]
    #[Assert\NotBlank]
    private ?int $structure_id = null;

    #[ORM\Column(name: "conseiller_id", type: "integer", nullable: true)]
    #[Assert\NotBlank]
    private ?int $conseiller_id = null;

    #[ORM\Column(name: "signature", type: "boolean")]
    #[Assert\NotBlank]
    private bool $signature;

    #[ORM\Column(name: "is_accompagne_structure", type: "boolean")]
    #[Assert\NotBlank]
    private bool $isAccompagneStructure;

    #[ORM\Column(name: "avis_imposition_url", type: "string", length: 255, nullable: true)]
    private ?string $avisImposition_url = null;

    #[ORM\Column(name: "avis_imposition_alt", type: "string", length: 255, nullable: true)]
    private ?string $avisImposition_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $avisImposition = null;

    #[ORM\Column(name: "avis_imposition_conjoint_url", type: "string", length: 255, nullable: true)]
    private ?string $avisImpositionConjoint_url = null;

    #[ORM\Column(name: "avis_imposition_conjoint_alt", type: "string", length: 255, nullable: true)]
    private ?string $avisImpositionConjoint_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $avisImpositionConjoint = null;

    #[ORM\Column(name: "justificatif_propriete_url", type: "string", length: 255, nullable: true)]
    private ?string $justificatifPropriete_url = null;

    #[ORM\Column(name: "justificatif_propriete_alt", type: "string", length: 255, nullable: true)]
    private ?string $justificatifPropriete_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $justificatifPropriete = null;

    #[ORM\Column(name: "piece_complement_url", type: "string", length: 255, nullable: true)]
    private ?string $pieceComplement_url = null;

    #[ORM\Column(name: "piece_complement_alt", type: "string", length:255, nullable:true)]
    private ?string $pieceComplement_alt = null;

    #[Assert\File(maxSize: '5120k', mimeTypes: ['application/pdf', 'image/jpg', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Format du fichier invalide. Les formats suivants sont acceptés: .pdf, .jpg, .jpeg, .png')]
    private ?UploadedFile $pieceComplement = null;

    private ?string $tempFilename = null;



    /**
     * Get id
     *
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set travauxDevisId
     *
     * @param integer $travauxDevisId
     *
     * @return Demande_travaux
     */
    public function setTravauxDevisId(?int $travauxDevisId): self
    {
        $this->travauxDevis_id = $travauxDevisId;

        return $this;
    }

    /**
     * Get travauxDevisId
     *
     * @return integer
     */
    public function getTravauxDevisId(): ?int
    {
        return $this->travauxDevis_id;
    }

    /**
     * Set ficheTechniqueId
     *
     * @param integer $ficheTechniqueId
     *
     * @return Demande_travaux
     */
    public function setFicheTechniqueId(?int $ficheTechniqueId): self
    {
        $this->ficheTechnique_id = $ficheTechniqueId;

        return $this;
    }

    /**
     * Get ficheTechniqueId
     *
     * @return integer
     */
    public function getFicheTechniqueId(): ?int
    {
        return $this->ficheTechnique_id;
    }

    /**
     * Set audit
     *
     * @param boolean $audit
     *
     * @return Demande_travaux
     */
    public function setAudit(bool $audit): self
    {
        $this->audit = $audit;

        return $this;
    }

    /**
     * Get audit
     *
     * @return boolean
     */
    public function getAudit(): bool
    {
        return $this->audit;
    }

    /**
     * Set nbPersFoyer
     *
     * @param integer $nbPersFoyer
     *
     * @return Demande_travaux
     */
    public function setNbPersFoyer(?int $nbPersFoyer): self
    {
        $this->nbPersFoyer = $nbPersFoyer;

        return $this;
    }

    /**
     * Get nbPersFoyer
     *
     * @return integer
     */
    public function getNbPersFoyer(): ?int
    {
        return $this->nbPersFoyer;
    }

    /**
     * Set revenu1
     *
     * @param string $revenu1
     *
     * @return Demande_travaux
     */
    public function setRevenu1(?string $revenu1): self
    {
        $this->revenu1 = $revenu1;

        return $this;
    }

    /**
     * Get revenu1
     *
     * @return string
     */
    public function getRevenu1(): ?string
    {
        return $this->revenu1;
    }

    /**
     * Set revenu2
     *
     * @param string $revenu2
     *
     * @return Demande_travaux
     */
    public function setRevenu2(?string $revenu2): self
    {
        $this->revenu2 = $revenu2;

        return $this;
    }

    /**
     * Get revenu2
     *
     * @return string
     */
    public function getRevenu2(): ?string
    {
        return $this->revenu2;
    }

    /**
     * Set revenu3
     *
     * @param string $revenu3
     *
     * @return Demande_travaux
     */
    public function setRevenu3(?string $revenu3): self
    {
        $this->revenu3 = $revenu3;

        return $this;
    }

    /**
     * Get revenu3
     *
     * @return string
     */
    public function getRevenu3(): ?string
    {
        return $this->revenu3;
    }

    /**
     * Set structureId
     *
     * @param integer|null $structureId
     *
     * @return Demande_travaux
     */
    public function setStructureId(?int $structureId): self
    {
        $this->structure_id = $structureId;

        return $this;
    }

    /**
     * Get structureId
     *
     * @return integer
     */
    public function getStructureId(): ?int
    {
        return $this->structure_id;
    }

    /**
     * Set conseillerId
     *
     * @param integer $conseillerId
     *
     * @return Demande_travaux
     */
    public function setConseillerId(?int $conseillerId): self
    {
        $this->conseiller_id = $conseillerId;

        return $this;
    }

    /**
     * Get conseillerId
     *
     * @return integer
     */
    public function getConseillerId(): ?int
    {
        return $this->conseiller_id;
    }

    /**
     * Set signature
     *
     * @param boolean $signature
     *
     * @return Demande_travaux
     */
    public function setSignature(bool $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * Get signature
     *
     * @return boolean
     */
    public function getSignature(): bool
    {
        return $this->signature;
    }

    /**
     * Set isAccompagneStructure
     *
     * @param boolean $isAccompagneStructure
     *
     * @return Demande_travaux
     */
    public function setIsAccompagneStructure(bool $isAccompagneStructure): self
    {
        $this->isAccompagneStructure = $isAccompagneStructure;

        return $this;
    }

    /**
     * Get isAccompagneStructure
     *
     * @return boolean
     */
    public function getIsAccompagneStructure(): bool
    {
        return $this->isAccompagneStructure;
    }

    /**
     * Set avisImpositionUrl
     *
     * @param string $avisImpositionUrl
     *
     * @return Demande_travaux
     */
    public function setAvisImpositionUrl(?string $avisImpositionUrl): self
    {
        $this->avisImposition_url = $avisImpositionUrl;

        return $this;
    }

    /**
     * Get avisImpositionUrl
     *
     * @return string
     */
    public function getAvisImpositionUrl(): ?string
    {
        return $this->avisImposition_url;
    }

    /**
     * Set avisImpositionAlt
     *
     * @param string $avisImpositionAlt
     *
     * @return Demande_travaux
     */
    public function setAvisImpositionAlt(?string $avisImpositionAlt): self
    {
        $this->avisImposition_alt = $avisImpositionAlt;

        return $this;
    }

    /**
     * Get avisImpositionAlt
     *
     * @return string
     */
    public function getAvisImpositionAlt(): ?string
    {
        return $this->avisImposition_alt;
    }

    /**
     * Set avisImpositionConjointUrl
     *
     * @param string $avisImpositionConjointUrl
     *
     * @return Demande_travaux
     */
    public function setAvisImpositionConjointUrl(?string $avisImpositionConjointUrl): self
    {
        $this->avisImpositionConjoint_url = $avisImpositionConjointUrl;

        return $this;
    }

    /**
     * Get avisImpositionConjointUrl
     *
     * @return string
     */
    public function getAvisImpositionConjointUrl(): ?string
    {
        return $this->avisImpositionConjoint_url;
    }

    /**
     * Set avisImpositionConjointAlt
     *
     * @param string $avisImpositionConjointAlt
     *
     * @return Demande_travaux
     */
    public function setAvisImpositionConjointAlt(?string $avisImpositionConjointAlt): self
    {
        $this->avisImpositionConjoint_alt = $avisImpositionConjointAlt;

        return $this;
    }

    /**
     * Get avisImpositionConjointAlt
     *
     * @return string
     */
    public function getAvisImpositionConjointAlt(): ?string
    {
        return $this->avisImpositionConjoint_alt;
    }

    /**
     * Set justificatifProprieteUrl
     *
     * @param string $justificatifProprieteUrl
     *
     * @return Demande_travaux
     */
    public function setJustificatifProprieteUrl(?string $justificatifProprieteUrl): self
    {
        $this->justificatifPropriete_url = $justificatifProprieteUrl;

        return $this;
    }

    /**
     * Get justificatifProprieteUrl
     *
     * @return string
     */
    public function getJustificatifProprieteUrl(): ?string
    {
        return $this->justificatifPropriete_url;
    }

    /**
     * Set justificatifProprieteAlt
     *
     * @param string $justificatifProprieteAlt
     *
     * @return Demande_travaux
     */
    public function setJustificatifProprieteAlt(?string $justificatifProprieteAlt): self
    {
        $this->justificatifPropriete_alt = $justificatifProprieteAlt;

        return $this;
    }

    /**
     * Get justificatifProprieteAlt
     *
     * @return string
     */
    public function getJustificatifProprieteAlt(): ?string
    {
        return $this->justificatifPropriete_alt;
    }

    /**
     * Set pieceComplementUrl
     *
     * @param string $pieceComplementUrl
     *
     * @return Demande_travaux
     */
    public function setPieceComplementUrl(?string $pieceComplementUrl): self
    {
        $this->pieceComplement_url = $pieceComplementUrl;

        return $this;
    }

    /**
     * Get pieceComplementUrl
     *
     * @return string
     */
    public function getPieceComplementUrl(): ?string
    {
        return $this->pieceComplement_url;
    }

    /**
     * Set pieceComplementAlt
     *
     * @param string $pieceComplementAlt
     *
     * @return Demande_travaux
     */
    public function setPieceComplementAlt(?string $pieceComplementAlt): self
    {
        $this->pieceComplement_alt = $pieceComplementAlt;

        return $this;
    }

    /**
     * Get pieceComplementAlt
     *
     * @return string
     */
    public function getPieceComplementAlt(): ?string
    {
        return $this->pieceComplement_alt;
    }

    /* *****************************************************************
                FONCTIONS POUR LES DOCUMENTS TELECHARGES
    *******************************************************************/
    /**
     * @return mixed
     */
    public function getAvisImposition(): ?UploadedFile
    {
        return $this->avisImposition;
    }

    /**
     * @param UploadedFile $avisImposition
     */
    public function setAvisImposition(?UploadedFile $avisImposition): void
    {
        $this->avisImposition = $avisImposition;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->avisImposition_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->avisImposition_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->avisImposition_url = null;
            $this->avisImposition_alt = null;
        }

        if ($avisImposition !== null) {
            $this->avisImposition_url = $avisImposition->guessExtension();
            $this->avisImposition_alt = $avisImposition->getClientOriginalName();
        }
    }

    /**
     * @return mixed
     */
    public function getAvisImpositionConjoint(): ?UploadedFile
    {
        return $this->avisImpositionConjoint;
    }

    /**
     * @param UploadedFile $avisImpositionConjoint
     */
    public function setAvisImpositionConjoint(?UploadedFile $avisImpositionConjoint): void
    {
        $this->avisImpositionConjoint = $avisImpositionConjoint;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->avisImpositionConjoint_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->avisImpositionConjoint_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->avisImpositionConjoint_url = null;
            $this->avisImpositionConjoint_alt = null;
        }

        if ($avisImpositionConjoint !== null) {
            $this->avisImpositionConjoint_url = $avisImpositionConjoint->guessExtension();
            $this->avisImpositionConjoint_alt = $avisImpositionConjoint->getClientOriginalName();
        }
    }

    /**
     * @return mixed
     */
    public function getJustificatifPropriete(): ?UploadedFile
    {
        return $this->justificatifPropriete;
    }

    /**
     * @param UploadedFile $justificatifPropriete
     */
    public function setJustificatifPropriete(?UploadedFile $justificatifPropriete): void
    {
        $this->justificatifPropriete = $justificatifPropriete;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->justificatifPropriete_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->justificatifPropriete_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->justificatifPropriete_url = null;
            $this->justificatifPropriete_alt = null;
        }

        if ($justificatifPropriete !== null) {
            $this->justificatifPropriete_url = $justificatifPropriete->guessExtension();
            $this->justificatifPropriete_alt = $justificatifPropriete->getClientOriginalName();
        }
    }

    /**
     * @return mixed
     */
    public function getPieceComplement(): ?UploadedFile
    {
        return $this->pieceComplement;
    }

    /**
     * @param UploadedFile $pieceComplement
     */
    public function setPieceComplement(?UploadedFile $pieceComplement): void
    {
        $this->pieceComplement = $pieceComplement;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->pieceComplement_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->pieceComplement_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->pieceComplement_url = null;
            $this->pieceComplement_alt = null;
        }

        if ($pieceComplement !== null) {
            $this->pieceComplement_url = $pieceComplement->guessExtension();
            $this->pieceComplement_alt = $pieceComplement->getClientOriginalName();
        }
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
     * @return string
     */
    public function justificatifPropriete_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/demande_travaux';
    }

    /**
     * @return string
     */
    public function justificatifPropriete_getWebPath(): string
    {
        return $this->justificatifPropriete_getUploadDir() . '/' . $this->getId() . '_justificatif_propriete' . '.' . $this->getJustificatifProprieteUrl();
    }

    /**
     * @return string
     */
    public function pieceComplement_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/demande_travaux';
    }

    /**
     * @return string
     */
    public function pieceComplement_getWebPath(): string
    {
        return $this->pieceComplement_getUploadDir() . '/' . $this->getId() . '_piece_complement' . '.' . $this->getPieceComplementUrl();
    }

    /**
     * @return string
     */
    public function avisImposition_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/demande_travaux';
    }

    /**
     * @return string
     */
    public function avisImposition_getWebPath(): string
    {
        return $this->avisImposition_getUploadDir() . '/' . $this->getId() . '_avis_imposition' . '.' . $this->getAvisImpositionUrl();
    }

    /**
     * @return string
     */
    public function avisImpositionConjoint_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/demande_travaux';
    }

    /**
     * @return string
     */
    public function avisImpositionConjoint_getWebPath(): string
    {
        return $this->avisImpositionConjoint_getUploadDir() . '/' . $this->getId() . '_avis_imposition_conjoint' . '.' . $this->getAvisImpositionConjointUrl();
    }
}

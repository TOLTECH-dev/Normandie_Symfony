<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
class Remboursement_instruction
{
    #[ORM\Column(name: 'date_cheque', type: 'date', nullable: true)]
    private ?\DateTime $dateCheque = null;

    #[ORM\Column(name: 'numero_remise_RSI', type: 'string', length: 255, nullable: true)]
    private ?string $numeroRemiseRSI = null;

    #[ORM\Column(name: 'is_cheque_conforme', type: 'string', length: 20, nullable: true)]
    private ?string $isChequeConforme = null;

    #[ORM\Column(name: 'cheque_reason', type: 'array', nullable: true)]
    private ?array $chequeReason = null;

    #[ORM\Column(name: 'cheque_reason_autre', type: 'text', nullable: true)]
    private ?string $chequeReasonAutre = null;

    #[ORM\Column(name: 'montant_facture', type: 'float', precision: 10, scale: 2, nullable: true)]
    private ?float $montantFacture = null;

    #[ORM\Column(name: 'is_facture_conforme', type: 'string', length: 20, nullable: true)]
    private ?string $isFactureConforme = null;

    #[ORM\Column(name: 'facture_reason', type: 'array', nullable: true)]
    private ?array $factureReason = null;

    #[ORM\Column(name: 'facture_reason_autre', type: 'text', nullable: true)]
    private ?string $factureReasonAutre = null;

    #[ORM\Column(name: 'destinataire', type: 'string', length: 255, nullable: true)]
    private ?string $destinataire = null;

    #[ORM\Column(name: 'iban', type: 'string', length: 255, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(name: 'bic', type: 'string', length: 255, nullable: true)]
    private ?string $bic = null;

    #[ORM\Column(name: 'domiciliation_bancaire', type: 'string', length: 255, nullable: true)]
    private ?string $domiciliationBancaire = null;

    #[ORM\Column(name: 'is_rib_conforme', type: 'string', length: 20, nullable: true)]
    private ?string $isRibConforme = null;

    #[ORM\Column(name: 'rib_reason', type: 'array', nullable: true)]
    private ?array $ribReason = null;

    #[ORM\Column(name: 'rib_reason_autre', type: 'text', nullable: true)]
    private ?string $ribReasonAutre = null;

    public function __construct()
    {
        $this->chequeReason = null;
        $this->factureReason = null;
        $this->ribReason = null;
    }

    /**
     * Get dateCheque
     *
     * @return \DateTime|null
     */
    public function getDateCheque(): ?\DateTime { return $this->dateCheque; }

    /**
     * Set dateCheque
     *
     * @param \DateTime|null $dateCheque
     *
     * @return Remboursement_instruction
     */
    public function setDateCheque(?\DateTime $dateCheque): self { $this->dateCheque = $dateCheque; return $this; }

    /**
     * Get numeroRemiseRSI
     *
     * @return string|null
     */
    public function getNumeroRemiseRSI(): ?string { return $this->numeroRemiseRSI; }

    /**
     * Set numeroRemiseRSI
     *
     * @param string|null $numeroRemiseRSI
     *
     * @return Remboursement_instruction
     */
    public function setNumeroRemiseRSI(?string $numeroRemiseRSI): self { $this->numeroRemiseRSI = $numeroRemiseRSI; return $this; }

    /**
     * Get isChequeConforme
     *
     * @return string|null
     */
    public function getIsChequeConforme(): ?string { return $this->isChequeConforme; }

    /**
     * Set isChequeConforme
     *
     * @param string|null $isChequeConforme
     *
     * @return Remboursement_instruction
     */
    public function setIsChequeConforme(?string $isChequeConforme): self { $this->isChequeConforme = $isChequeConforme; return $this; }

    /**
     * Get chequeReason
     *
     * @return array|null
     */
    public function getChequeReason(): ?array { return $this->chequeReason; }

    /**
     * Set chequeReason
     *
     * @param array|null $chequeReason
     *
     * @return Remboursement_instruction
     */
    public function setChequeReason($chequeReason): self
    {
        if ($chequeReason instanceof \Doctrine\Common\Collections\ArrayCollection) {
            $chequeReason = $chequeReason->toArray();
        }
        $this->chequeReason = $chequeReason;
        return $this;
    }

    /**
     * Get chequeReasonAutre
     *
     * @return string|null
     */
    public function getChequeReasonAutre(): ?string { return $this->chequeReasonAutre; }

    /**
     * Set chequeReasonAutre
     *
     * @param string|null $chequeReasonAutre
     *
     * @return Remboursement_instruction
     */
    public function setChequeReasonAutre(?string $chequeReasonAutre): self { $this->chequeReasonAutre = $chequeReasonAutre; return $this; }

    /**
     * Get montantFacture
     *
     * @return float|null
     */
    public function getMontantFacture(): ?float { return $this->montantFacture; }

    /**
     * Set montantFacture
     *
     * @param float|null $montantFacture
     *
     * @return Remboursement_instruction
     */
    public function setMontantFacture(?float $montantFacture): self { $this->montantFacture = $montantFacture; return $this; }

    /**
     * Get isFactureConforme
     *
     * @return string|null
     */
    public function getIsFactureConforme(): ?string { return $this->isFactureConforme; }

    /**
     * Set isFactureConforme
     *
     * @param string|null $isFactureConforme
     *
     * @return Remboursement_instruction
     */
    public function setIsFactureConforme(?string $isFactureConforme): self { $this->isFactureConforme = $isFactureConforme; return $this; }

    /**
     * Get factureReason
     *
     * @return array|null
     */
    public function getFactureReason(): ?array { return $this->factureReason; }

    /**
     * Set factureReason
     *
     * @param array|null $factureReason
     *
     * @return Remboursement_instruction
     */
    public function setFactureReason($factureReason): self
    {
        if ($factureReason instanceof \Doctrine\Common\Collections\ArrayCollection) {
            $factureReason = $factureReason->toArray();
        }
        $this->factureReason = $factureReason;
        return $this;
    }

    /**
     * Get factureReasonAutre
     *
     * @return string|null
     */
    public function getFactureReasonAutre(): ?string { return $this->factureReasonAutre; }

    /**
     * Set factureReasonAutre
     *
     * @param string|null $factureReasonAutre
     *
     * @return Remboursement_instruction
     */
    public function setFactureReasonAutre(?string $factureReasonAutre): self { $this->factureReasonAutre = $factureReasonAutre; return $this; }

    /**
     * Get destinataire
     *
     * @return string|null
     */
    public function getDestinataire(): ?string { return $this->destinataire; }

    /**
     * Set destinataire
     *
     * @param string|null $destinataire
     *
     * @return Remboursement_instruction
     */
    public function setDestinataire(?string $destinataire): self { $this->destinataire = $destinataire; return $this; }

    /**
     * Get iban
     *
     * @return string|null
     */
    public function getIban(): ?string { return $this->iban; }

    /**
     * Set iban
     *
     * @param string|null $iban
     *
     * @return Remboursement_instruction
     */
    public function setIban(?string $iban): self { $this->iban = $iban; return $this; }

    /**
     * Get bic
     *
     * @return string|null
     */
    public function getBic(): ?string { return $this->bic; }

    /**
     * Set bic
     *
     * @param string|null $bic
     *
     * @return Remboursement_instruction
     */
    public function setBic(?string $bic): self { $this->bic = $bic; return $this; }

    /**
     * Get domiciliationBancaire
     *
     * @return string|null
     */
    public function getDomiciliationBancaire(): ?string { return $this->domiciliationBancaire; }

    /**
     * Set domiciliationBancaire
     *
     * @param string|null $domiciliationBancaire
     *
     * @return Remboursement_instruction
     */
    public function setDomiciliationBancaire(?string $domiciliationBancaire): self { $this->domiciliationBancaire = $domiciliationBancaire; return $this; }

    /**
     * Get isRibConforme
     *
     * @return string|null
     */
    public function getIsRibConforme(): ?string { return $this->isRibConforme; }

    /**
     * Set isRibConforme
     *
     * @param string|null $isRibConforme
     *
     * @return Remboursement_instruction
     */
    public function setIsRibConforme(?string $isRibConforme): self { $this->isRibConforme = $isRibConforme; return $this; }

    /**
     * Get ribReason
     *
     * @return array|null
     */
    public function getRibReason(): ?array { return $this->ribReason; }

    /**
     * Set ribReason
     *
     * @param array|null $ribReason
     *
     * @return Remboursement_instruction
     */
    public function setRibReason($ribReason): self
    {
        if ($ribReason instanceof \Doctrine\Common\Collections\ArrayCollection) {
            $ribReason = $ribReason->toArray();
        }
        $this->ribReason = $ribReason;
        return $this;
    }

    /**
     * Get ribReasonAutre
     *
     * @return string|null
     */
    public function getRibReasonAutre(): ?string { return $this->ribReasonAutre; }

    /**
     * Set ribReasonAutre
     *
     * @param string|null $ribReasonAutre
     *
     * @return Remboursement_instruction
     */
    public function setRibReasonAutre(?string $ribReasonAutre): self { $this->ribReasonAutre = $ribReasonAutre; return $this; }
}

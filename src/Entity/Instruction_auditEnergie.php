<?php

namespace App\Entity;

use App\Repository\Instruction_auditEnergieRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Instruction_auditEnergieRepository::class)]
#[ORM\Table(name: 'instruction_audit_energie')]
class Instruction_auditEnergie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Conformité du mandat donné au conseiller (D26013). Même forme que les
     * conformités existantes : « 0 | oui », « 1 | non », « 2 | indetermine ».
     */
    #[ORM\Column(name: 'mandat_conformite', type: 'string', length: 20, nullable: true)]
    private ?string $mandatConformite = null;

    /**
     * Motifs de non-conformité retenus. Comme les autres, ce tableau contient
     * les entités de motif sérialisées, et non leurs identifiants.
     */
    #[ORM\Column(name: 'mandat_reason', type: 'array', nullable: true)]
    private ?array $mandatReason = null;

    #[ORM\Column(name: 'mandat_reason_autre', type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    private ?string $mandatReasonAutre = null;

    #[ORM\Column(name: 'JP_conformite', type: 'string', length: 20, nullable: true)]
    private ?string $JPconformite = null;

    #[ORM\Column(name: 'JP_type', type: 'string', length: 30, nullable: true)]
    private ?string $JPtype = null;

    #[ORM\Column(name: 'JP_reason', type: 'array', nullable: true)]
    private ?array $JPreason = null;

    #[ORM\Column(name: 'JP_reason_autre', type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    private ?string $JPreasonAutre = null;

    #[ORM\Column(name: 'KBIS_conformite', type: 'string', length: 255, nullable: true)]
    private ?string $KBISconformite = null;

    #[ORM\Column(name: 'KBIS_reason', type: 'array', nullable: true)]
    private ?array $KBISreason = null;

    #[ORM\Column(name: 'kBIS_reason_autre', type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    private ?string $KBISreasonAutre = null;

    #[ORM\Column(name: 'AI_conformite', type: 'string', length: 20, nullable: true)]
    private ?string $AIconformite = null;

    #[ORM\Column(name: 'AI_reason', type: 'array', nullable: true)]
    private ?array $AIreason = null;

    #[ORM\Column(name: 'AI_reason_autre', type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    private ?string $AIreasonAutre = null;

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
     * Set jPconformite
     *
     * @param string $jPconformite
     *
     * @return Instruction_auditEnergie
     */
    public function setJPconformite(?string $JPconformite): self
    {
        $this->JPconformite = $JPconformite;

        return $this;
    }

    /**
     * Get jPconformite
     *
     * @return string
     */
    public function getJPconformite(): ?string
    {
        return $this->JPconformite;
    }

    /**
     * Set jPtype
     *
     * @param string $jPtype
     *
     * @return Instruction_auditEnergie
     */
    public function setJPtype(?string $JPtype): self
    {
        $this->JPtype = $JPtype;

        return $this;
    }

    /**
     * Get jPtype
     *
     * @return string
     */
    public function getJPtype(): ?string
    {
        return $this->JPtype;
    }

    /**
     * Set jPreason
     *
     * @param array $jPreason
     *
     * @return Instruction_auditEnergie
     */
    public function setJPreason($JPreason): self
    {
        if ($JPreason instanceof \Doctrine\Common\Collections\ArrayCollection) {
            $JPreason = $JPreason->toArray();
        }

        $this->JPreason = $JPreason;

        return $this;
    }

    /**
     * Get jPreason
     *
     * @return array
     */
    public function getJPreason(): ?array
    {
        return $this->JPreason;
    }

    /**
     * Set jPreasonAutre
     *
     * @param string $jPreasonAutre
     *
     * @return Instruction_auditEnergie
     */
    public function setJPreasonAutre(?string $JPreasonAutre): self
    {
        $this->JPreasonAutre = $JPreasonAutre;

        return $this;
    }

    /**
     * Get jPreasonAutre
     *
     * @return string
     */
    public function getJPreasonAutre(): ?string
    {
        return $this->JPreasonAutre;
    }

    /**
     * Set kBISconformite
     *
     * @param string $kBISconformite
     *
     * @return Instruction_auditEnergie
     */
    public function setKBISconformite(?string $KBISconformite): self
    {
        $this->KBISconformite = $KBISconformite;

        return $this;
    }

    /**
     * Get kBISconformite
     *
     * @return string
     */
    public function getKBISconformite(): ?string
    {
        return $this->KBISconformite;
    }

    /**
     * Set kBISreason
     *
     * @param array $kBISreason
     *
     * @return Instruction_auditEnergie
     */
    public function setKBISreason($KBISreason): self
    {
        if ($KBISreason instanceof \Doctrine\Common\Collections\ArrayCollection) {
            $KBISreason = $KBISreason->toArray();
        }

        $this->KBISreason = $KBISreason;

        return $this;
    }

    /**
     * Get kBISreason
     *
     * @return array
     */
    public function getKBISreason(): ?array
    {
        return $this->KBISreason;
    }

    /**
     * Set kBISreasonAutre
     *
     * @param string $kBISreasonAutre
     *
     * @return Instruction_auditEnergie
     */
    public function setKBISreasonAutre(?string $KBISreasonAutre): self
    {
        $this->KBISreasonAutre = $KBISreasonAutre;

        return $this;
    }

    /**
     * Get kBISreasonAutre
     *
     * @return string
     */
    public function getKBISreasonAutre(): ?string
    {
        return $this->KBISreasonAutre;
    }

    /**
     * Set aIconformite
     *
     * @param string $aIconformite
     *
     * @return Instruction_auditEnergie
     */
    public function setAIconformite(?string $AIconformite): self
    {
        $this->AIconformite = $AIconformite;

        return $this;
    }

    /**
     * Get aIconformite
     *
     * @return string
     */
    public function getAIconformite(): ?string
    {
        return $this->AIconformite;
    }

    /**
     * Set aIreason
     *
     * @param array $aIreason
     *
     * @return Instruction_auditEnergie
     */
    public function setAIreason($AIreason): self
    {
        if ($AIreason instanceof \Doctrine\Common\Collections\ArrayCollection) {
            $AIreason = $AIreason->toArray();
        }

        $this->AIreason = $AIreason;

        return $this;
    }

    /**
     * Get aIreason
     *
     * @return array
     */
    public function getAIreason(): ?array
    {
        return $this->AIreason;
    }

    /**
     * Set aIreasonAutre
     *
     * @param string $aIreasonAutre
     *
     * @return Instruction_auditEnergie
     */
    public function setAIreasonAutre(?string $AIreasonAutre): self
    {
        $this->AIreasonAutre = $AIreasonAutre;

        return $this;
    }

    /**
     * Get aIreasonAutre
     *
     * @return string
     */
    public function getAIreasonAutre(): ?string
    {
        return $this->AIreasonAutre;
    }

    public function setMandatConformite(?string $mandatConformite): self
    {
        $this->mandatConformite = $mandatConformite;

        return $this;
    }

    public function getMandatConformite(): ?string
    {
        return $this->mandatConformite;
    }

    public function setMandatReason($mandatReason): self
    {
        if ($mandatReason instanceof \Doctrine\Common\Collections\ArrayCollection) {
            $mandatReason = $mandatReason->toArray();
        }

        $this->mandatReason = $mandatReason;

        return $this;
    }

    public function getMandatReason(): ?array
    {
        return $this->mandatReason;
    }

    public function setMandatReasonAutre(?string $mandatReasonAutre): self
    {
        $this->mandatReasonAutre = $mandatReasonAutre;

        return $this;
    }

    public function getMandatReasonAutre(): ?string
    {
        return $this->mandatReasonAutre;
    }
}

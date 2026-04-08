<?php

namespace App\Entity;

use App\Repository\Remboursement_statutRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Remboursement_statutRepository::class)]
#[ORM\Table(name: 'remboursement_statut')]
class Remboursement_statut
{
    public const STATUS_1 = 1;
    public const STATUS_2 = 2;
    public const STATUS_3 = 3;
    public const STATUS_4 = 4;
    public const STATUS_5 = 5;
    public const STATUS_6 = 6;
    public const STATUS_7 = 7;
    public const STATUS_8 = 8;
    public const STATUS_9 = 9;
    public const STATUS_10 = 10;
    public const STATUS_11 = 11;
    public const STATUS_12 = 12;
    public const STATUS_13 = 13;
    public const STATUS_14 = 14;
    public const STATUS_15 = 15;
    public const STATUS_16 = 16;
    public const STATUS_17 = 17;
    public const STATUS_18 = 18;
    public const STATUS_19 = 19;
    public const STATUS_20 = 20;
    public const STATUS_21 = 21;
    public const STATUS_22 = 22;
    public const STATUS_23 = 23;
    public const STATUS_24 = 24;
    public const STATUS_25 = 25;
    public const STATUS_26 = 26;
    public const STATUS_27 = 27;
    public const STATUS_28 = 28;
    public const STATUS_29 = 29;
    public const STATUS_30 = 30;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $statut;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $slug;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $color = null;

    #[ORM\Column(type: 'integer')]
    private int $colorStep;

    /**
     * Get id
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set statut
     *
     * @param int $statut
     *
     * @return Remboursement_statut
     */
    public function setStatut(int $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * Get statut
     *
     * @return int
     */
    public function getStatut(): int
    {
        return $this->statut;
    }

    /**
     * Set description
     *
     * @param string|null $description
     *
     * @return Remboursement_statut
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set slug
     *
     * @param string $slug
     *
     * @return Remboursement_statut
     */
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug
     *
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Set color
     *
     * @param boolean|null $color
     *
     * @return Remboursement_statut
     */
    public function setColor(?bool $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return boolean|null
     */
    public function getColor(): ?bool
    {
        return $this->color;
    }

    /**
     * Set colorStep
     *
     * @param int $colorStep
     *
     * @return Remboursement_statut
     */
    public function setColorStep(int $colorStep): self
    {
        $this->colorStep = $colorStep;

        return $this;
    }

    /**
     * Get colorStep
     *
     * @return int
     */
    public function getColorStep(): int
    {
        return $this->colorStep;
    }
}

<?php

namespace App\Entity;

use App\Repository\Demande_statutRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "demande_statut")]
#[ORM\Entity(repositoryClass: Demande_statutRepository::class)]
class Demande_statut
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "statut", type: "integer")]
    private int $statut;

    #[ORM\Column(name: "description", type: "string", length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: "slug", type: "string", length: 255)]
    private string $slug;

    #[ORM\Column(name: "color", type: "boolean", nullable: true)]
    private ?bool $color = null;

    #[ORM\Column(name: "color_step", type: "integer")]
    private int $colorStep;

    const STATUS_1 = 1;
    const STATUS_2 = 2;
    const STATUS_3 = 3;
    const STATUS_4 = 4;
    const STATUS_5 = 5;
    const STATUS_6 = 6;
    const STATUS_7 = 7;
    const STATUS_8 = 8;
    const STATUS_9 = 9;
    const STATUS_10 = 10;
    const STATUS_11 = 11;
    const STATUS_12 = 12;
    const STATUS_13 = 13;
    const STATUS_14 = 14;
    const STATUS_15 = 15;
    const STATUS_16 = 16;
    const STATUS_17 = 17;
    const STATUS_18 = 18;
    const STATUS_19 = 19;
    const STATUS_20 = 20;
    const STATUS_21 = 21;
    const STATUS_22 = 22;
    const STATUS_23 = 23;
    const STATUS_24 = 24;
    const STATUS_25 = 25;
    const STATUS_26 = 26;
    const STATUS_27 = 27;
    const STATUS_28 = 28;
    const STATUS_29 = 29;
    const STATUS_30 = 30;
    const STATUS_31 = 31;
    const STATUS_32 = 32;
    const STATUS_33 = 33;
    const STATUS_34 = 34;
    const STATUS_35 = 35;
    const STATUS_36 = 36;
    const STATUS_37 = 37;
    const STATUS_38 = 38;
    const STATUS_39 = 39;
    const STATUS_40 = 40;
    const STATUS_41 = 41;
    const STATUS_42 = 42;
    const STATUS_43 = 43;
    const STATUS_44 = 44;
    const STATUS_45 = 45;
    const STATUS_46 = 46;
    const STATUS_47 = 47;

    const LABEL_SLUG_DEMANDE_REFUSEE = 'Demande refusée';



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
     * Set statut
     *
     * @param integer $statut
     *
     * @return Demande_statut
     */
    public function setStatut(int $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * Get statut
     *
     * @return integer
     */
    public function getStatut(): int
    {
        return $this->statut;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Demande_statut
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
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
     * @return Demande_statut
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
     * @param boolean $color
     *
     * @return Demande_statut
     */
    public function setColor(?bool $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return boolean
     */
    public function getColor(): ?bool
    {
        return $this->color;
    }

    /**
     * Set colorStep
     *
     * @param integer $colorStep
     *
     * @return Demande_statut
     */
    public function setColorStep(int $colorStep): self
    {
        $this->colorStep = $colorStep;

        return $this;
    }

    /**
     * Get colorStep
     *
     * @return integer
     */
    public function getColorStep(): int
    {
        return $this->colorStep;
    }
}

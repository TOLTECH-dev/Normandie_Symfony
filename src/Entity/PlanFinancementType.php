<?php

namespace App\Entity;

use App\Repository\PlanFinancementTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanFinancementTypeRepository::class)]
#[ORM\Table(name: 'plan_financement_type')]
class PlanFinancementType
{
    public const CATEGORY_AIDE_AUTRE_COLLECTIVITE_ID = 1;
    public const CATEGORY_MAPRIMERENOV_ID = 2;
    public const CATEGORY_MAPRIMERENOV_SERENITE_ID = 3;
    public const CATEGORY_CEE_ID = 4;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $nom;

    #[ORM\Column(name: 'category_id', type: 'integer')]
    private int $categoryId;

    public function __construct() {}

    public function __toString(): string
    {
        return (string)$this->nom;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setCategoryId(int $categoryId): self
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }
}

<?php

namespace App\Entity;

use App\Repository\Partenaire_optionRenovateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Partenaire_optionRenovateurRepository::class)]
#[ORM\Table(name: 'partenaire_option_renovateur')]
class Partenaire_optionRenovateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'type_acteur', type: 'string', length: 20, nullable: true)]
    private ?string $typeActeur = null;

    #[ORM\Column(name: 'complement', type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    private ?string $complement = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setTypeActeur(?string $typeActeur): self
    {
        $this->typeActeur = $typeActeur;
        return $this;
    }

    public function getTypeActeur(): ?string
    {
        return $this->typeActeur;
    }

    public function setComplement(?string $complement): self
    {
        $this->complement = $complement;
        return $this;
    }

    public function getComplement(): ?string
    {
        return $this->complement;
    }
}

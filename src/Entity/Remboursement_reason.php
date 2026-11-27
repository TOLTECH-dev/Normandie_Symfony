<?php

namespace App\Entity;

use App\Repository\Remboursement_reasonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Remboursement_reasonRepository::class)]
#[ORM\Table(name: 'remboursement_reason')]
class Remboursement_reason
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $filtre = null;

    #[ORM\Column(type: 'text', length: 255)]
    private string $slug;

    /**
     * Un motif inactif n'est plus PROPOSÉ dans les listes de choix, mais
     * reste choisi là où il l'a déjà été.
     *
     * La valeur par défaut n'est pas décorative : les colonnes `*_reason`
     * des tables d'instruction stockent ces entités SÉRIALISÉES. Les
     * enregistrements antérieurs ne portent pas cette propriété, et c'est
     * ce défaut qui leur est appliqué à la désérialisation — sans lui, un
     * accès sur une propriété non initialisée lèverait une erreur.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $positionLast = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setFiltre(?string $filtre): self
    {
        $this->filtre = $filtre;
        return $this;
    }

    public function getFiltre(): ?string
    {
        return $this->filtre;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setPositionLast(?int $positionLast): self
    {
        $this->positionLast = $positionLast;
        return $this;
    }

    public function getPositionLast(): ?int
    {
        return $this->positionLast;
    }

    public function estActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }
}

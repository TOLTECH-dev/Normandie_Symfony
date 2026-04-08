<?php

namespace App\Entity;

use App\Repository\Banque_statutRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'banque_statut')]
#[ORM\Entity(repositoryClass: Banque_statutRepository::class)]
class Banque_statut
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'enabled', type: 'boolean', nullable: true)]
    private ?bool $enabled = null;

    #[ORM\Column(name: 'date_inactif', type: 'date', nullable: true)]
    private ?\DateTime $dateInactif = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setEnabled(?bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setDateInactif(?\DateTime $dateInactif): self
    {
        $this->dateInactif = $dateInactif;
        return $this;
    }

    public function getDateInactif(): ?\DateTime
    {
        return $this->dateInactif;
    }
}

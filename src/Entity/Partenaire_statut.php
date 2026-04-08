<?php

namespace App\Entity;

use App\Repository\Partenaire_statutRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Partenaire_statutRepository::class)]
#[ORM\Table(name: 'partenaire_statut')]
class Partenaire_statut
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_rattachement', type: 'date', nullable: true)]
    private ?\DateTime $dateRattachement = null;

    #[ORM\Column(name: 'enabled', type: 'boolean', nullable: true)]
    private ?bool $enabled = null;

    #[ORM\Column(name: 'date_inactif', type: 'date', nullable: true)]
    private ?\DateTime $dateInactif = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDateRattachement(?\DateTime $dateRattachement): self
    {
        $this->dateRattachement = $dateRattachement;
        return $this;
    }

    public function getDateRattachement(): ?\DateTime
    {
        return $this->dateRattachement;
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

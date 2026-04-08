<?php

namespace App\Entity;

use App\Repository\DateRMHRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'date_RMH')]
#[ORM\Entity(repositoryClass: DateRMHRepository::class)]
class DateRMH
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTime $dateCreation;

    #[ORM\Column(name: 'auteur_creation', type: 'string', length: 255)]
    private string $auteurCreation;

    #[ORM\Column(name: 'date_modif', type: 'datetime')]
    private \DateTime $dateModif;

    #[ORM\Column(name: 'auteur_modif', type: 'string', length: 255)]
    private string $auteurModif;

    #[ORM\Column(name: 'date_RMH', type: 'date', nullable: true)]
    private ?\DateTime $dateRMH = null;

    #[ORM\Column(name: 'enabled', type: 'boolean', nullable: true)]
    private ?bool $enabled = null;

    #[ORM\Column(name: 'date_inactif', type: 'date', nullable: true)]
    private ?\DateTime $dateInactif = null;

    #[ORM\Column(name: 'date_export', type: 'datetime', nullable: true)]
    private ?\DateTime $dateExport = null;

    #[ORM\Column(name: 'rgpd', type: 'boolean', options: ['default' => false])]
    private bool $rgpd = false;

    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDateCreation(\DateTime $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateCreation(): \DateTime
    {
        return $this->dateCreation;
    }

    public function setAuteurCreation(string $auteurCreation): self
    {
        $this->auteurCreation = $auteurCreation;
        return $this;
    }

    public function getAuteurCreation(): string
    {
        return $this->auteurCreation;
    }

    public function setDateModif(\DateTime $dateModif): self
    {
        $this->dateModif = $dateModif;
        return $this;
    }

    public function getDateModif(): \DateTime
    {
        return $this->dateModif;
    }

    public function setAuteurModif(string $auteurModif): self
    {
        $this->auteurModif = $auteurModif;
        return $this;
    }

    public function getAuteurModif(): string
    {
        return $this->auteurModif;
    }

    public function setDateRMH(?\DateTime $dateRMH): self
    {
        $this->dateRMH = $dateRMH;
        return $this;
    }

    public function getDateRMH(): ?\DateTime
    {
        return $this->dateRMH;
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

    public function setDateExport(?\DateTime $dateExport): self
    {
        $this->dateExport = $dateExport;
        return $this;
    }

    public function getDateExport(): ?\DateTime
    {
        return $this->dateExport;
    }

    public function setRgpd(bool $rgpd): self
    {
        $this->rgpd = $rgpd;
        return $this;
    }

    public function getRgpd(): bool
    {
        return $this->rgpd;
    }
}

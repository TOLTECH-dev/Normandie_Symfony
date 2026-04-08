<?php

namespace App\Entity;

use App\Repository\Admin_coordonneeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Admin_coordonneeRepository::class)]
#[ORM\Table(name: 'admin_coordonnee')]
#[ORM\Index(name: 'object_idx', columns: ['object_id'])]
class Admin_coordonnee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $dateCreation;

    #[ORM\Column(type: 'string', length: 255)]
    private string $auteurCreation;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $dateModif;

    #[ORM\Column(type: 'string', length: 255)]
    private string $auteurModif;

    #[ORM\Column(type: 'integer')]
    private int $objectId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $type;

    #[ORM\Column(type: 'float')]
    private float $latitude;

    #[ORM\Column(type: 'float')]
    private float $longitude;


    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModif = new \DateTime();

        if (isset($_SESSION)) {
            $this->auteurCreation = $_SESSION['login']->getUsername();
            $this->auteurModif = $_SESSION['login']->getUsername();
        } else {
            $this->auteurCreation = 'COMMAND LINE';
            $this->auteurModif = 'COMMAND LINE';
        }
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

    public function setObjectId(int $objectId): self
    {
        $this->objectId = $objectId;
        return $this;
    }

    public function getObjectId(): int
    {
        return $this->objectId;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }
}
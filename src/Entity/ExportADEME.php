<?php

namespace App\Entity;

use App\Repository\ExportADEMERepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'export_ademe')]
#[ORM\Entity(repositoryClass: ExportADEMERepository::class)]
class ExportADEME extends Log
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_reference', type: 'date', nullable: true)]
    private ?\DateTime $dateReference = null;

    public static string $filename = 'exportADEME';

    public function __construct()
    {
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDateReference(?\DateTime $dateReference): self
    {
        $this->dateReference = $dateReference;
        return $this;
    }

    public function getDateReference(): ?\DateTime
    {
        return $this->dateReference;
    }
}

<?php

namespace App\Entity;

use App\Repository\ExportDemandeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'export_demande')]
#[ORM\Entity(repositoryClass: ExportDemandeRepository::class)]
class ExportDemande extends Log
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'destinataire_user_id', type: 'integer')]
    protected int $destinataireUserId;

    #[ORM\Column(name: 'where_query', type: 'text', nullable: true)]
    private ?string $whereQuery = null;

    public static string $filename = 'exportDemande';

    public function __construct()
    {
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDestinataireUserId(int $destinataireUserId): self
    {
        $this->destinataireUserId = $destinataireUserId;
        return $this;
    }

    public function getDestinataireUserId(): int
    {
        return $this->destinataireUserId;
    }

    public function setWhereQuery(?string $whereQuery): self
    {
        $this->whereQuery = $whereQuery;
        return $this;
    }

    public function getWhereQuery(): ?string
    {
        return $this->whereQuery;
    }
}

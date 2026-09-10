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

    /**
     * Recherches de la liste au moment de la demande, en JSON. La colonne garde son nom historique :
     * elle contenait auparavant un fragment SQL, exécuté tel quel par la commande d'export.
     */
    #[ORM\Column(name: 'where_query', type: 'text', nullable: true)]
    private ?string $filters = null;

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

    /**
     * @param array<string, string> $filters
     */
    public function setFilters(array $filters): self
    {
        $this->filters = json_encode($filters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        return $this;
    }

    /**
     * @return array<mixed>
     *
     * @throws \UnexpectedValueException contenu illisible, par exemple un export antérieur au format SQL
     */
    public function getFilters(): array
    {
        if (null === $this->filters || '' === $this->filters) {
            return [];
        }

        try {
            $filters = json_decode($this->filters, true, 2, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $filters = null;
        }

        if (!is_array($filters)) {
            throw new \UnexpectedValueException(
                'Recherches de l\'export illisibles (ancien format ?) : relancer l\'export depuis la liste des dossiers'
            );
        }

        return $filters;
    }
}

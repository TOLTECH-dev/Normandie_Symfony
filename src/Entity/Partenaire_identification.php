<?php

namespace App\Entity;

use App\Repository\Partenaire_identificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Partenaire_identificationRepository::class)]
#[ORM\Table(name: 'partenaire_identification')]
class Partenaire_identification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'raison_sociale', type: 'string', length: 255)]
    private string $raisonSociale;

    #[ORM\Column(name: 'thematique', type: 'string', length: 20)]
    private string $thematique;

    #[ORM\Column(name: 'siret', type: 'string', length: 255)]
    private string $siret;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setRaisonSociale(string $raisonSociale): self
    {
        $this->raisonSociale = $raisonSociale;
        return $this;
    }

    public function getRaisonSociale(): string
    {
        return $this->raisonSociale;
    }

    public function setThematique(string $thematique): self
    {
        $this->thematique = $thematique;
        return $this;
    }

    public function getThematique(): string
    {
        return $this->thematique;
    }

    public function setSiret(string $siret): self
    {
        $this->siret = $siret;
        return $this;
    }

    public function getSiret(): string
    {
        return $this->siret;
    }
}

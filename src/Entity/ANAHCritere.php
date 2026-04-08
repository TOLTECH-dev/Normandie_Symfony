<?php

namespace App\Entity;

use App\Repository\ANAHCritereRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'ANAH_critere')]
#[ORM\Entity(repositoryClass: ANAHCritereRepository::class)]
class ANAHCritere
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

    #[ORM\Column(name: 'nombre_personne', type: 'smallint')]
    private int $nbPersonne;

    #[ORM\Column(name: 'plafond_tres_modeste', type: 'integer')]
    private int $plafondTresModeste;

    #[ORM\Column(name: 'supplement_tres_modeste', type: 'integer')]
    private int $supplementTresModeste;

    #[ORM\Column(name: 'plafond_modeste', type: 'integer')]
    private int $plafondModeste;

    #[ORM\Column(name: 'supplement_modeste', type: 'integer')]
    private int $supplementModeste;

    #[ORM\Column(name: 'plafond_intermediaire', type: 'integer')]
    private int $plafondIntermediaire;

    #[ORM\Column(name: 'supplement_intermediaire', type: 'integer')]
    private int $supplementIntermediaire;

    const NOMBRE_PERSONNE_PARAMETRE_SUPPLEMENT = 5;

    const TYPE_MENAGE_TRES_MODESTE_CODE  = 1;
    const TYPE_MENAGE_MODESTE_CODE       = 2;
    const TYPE_MENAGE_INTERMEDIAIRE_CODE = 3;
    const TYPE_MENAGE_SUPERIEUR_CODE     = 4;

    const TYPE_MENAGE_TRES_MODESTE_LABEL  = 'Très modeste';
    const TYPE_MENAGE_MODESTE_LABEL       = 'Modeste';
    const TYPE_MENAGE_INTERMEDIAIRE_LABEL = 'Intermédiaire';
    const TYPE_MENAGE_SUPERIEUR_LABEL     = 'Supérieur';

    public static array $TYPE_MENAGE = [
        self::TYPE_MENAGE_TRES_MODESTE_CODE  => self::TYPE_MENAGE_TRES_MODESTE_LABEL,
        self::TYPE_MENAGE_MODESTE_CODE       => self::TYPE_MENAGE_MODESTE_LABEL,
        self::TYPE_MENAGE_INTERMEDIAIRE_CODE => self::TYPE_MENAGE_INTERMEDIAIRE_LABEL,
        self::TYPE_MENAGE_SUPERIEUR_CODE     => self::TYPE_MENAGE_SUPERIEUR_LABEL,
    ];

    const ANAHCritere_PLAFOND_TRES_MODESTE_KEY     = 'PLAFOND_TRES_MODESTE';
    const ANAHCritere_SUPPLEMENT_TRES_MODESTE_KEY  = 'SUPPLEMENT_TRES_MODESTE';
    const ANAHCritere_PLAFOND_MODESTE_KEY          = 'PLAFOND_MODESTE';
    const ANAHCritere_SUPPLEMENT_MODESTE_KEY       = 'SUPPLEMENT_MODESTE';
    const ANAHCritere_PLAFOND_INTERMEDIAIRE_KEY    = 'PLAFOND_INTERMEDIAIRE';
    const ANAHCritere_SUPPLEMENT_INTERMEDIAIRE_KEY = 'SUPPLEMENT_INTERMEDIAIRE';

    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();
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

    public function setNbPersonne(int $nbPersonne): self
    {
        $this->nbPersonne = $nbPersonne;
        return $this;
    }

    public function getNbPersonne(): int
    {
        return $this->nbPersonne;
    }

    public function setPlafondTresModeste(int $plafondTresModeste): self
    {
        $this->plafondTresModeste = $plafondTresModeste;
        return $this;
    }

    public function getPlafondTresModeste(): int
    {
        return $this->plafondTresModeste;
    }

    public function setSupplementTresModeste(int $supplementTresModeste): self
    {
        $this->supplementTresModeste = $supplementTresModeste;
        return $this;
    }

    public function getSupplementTresModeste(): int
    {
        return $this->supplementTresModeste;
    }

    public function setPlafondModeste(int $plafondModeste): self
    {
        $this->plafondModeste = $plafondModeste;
        return $this;
    }

    public function getPlafondModeste(): int
    {
        return $this->plafondModeste;
    }

    public function setSupplementModeste(int $supplementModeste): self
    {
        $this->supplementModeste = $supplementModeste;
        return $this;
    }

    public function getSupplementModeste(): int
    {
        return $this->supplementModeste;
    }

    public function setPlafondIntermediaire(int $plafondIntermediaire): self
    {
        $this->plafondIntermediaire = $plafondIntermediaire;
        return $this;
    }

    public function getPlafondIntermediaire(): int
    {
        return $this->plafondIntermediaire;
    }

    public function setSupplementIntermediaire(int $supplementIntermediaire): self
    {
        $this->supplementIntermediaire = $supplementIntermediaire;
        return $this;
    }

    public function getSupplementIntermediaire(): int
    {
        return $this->supplementIntermediaire;
    }
}

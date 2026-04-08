<?php

namespace App\Entity;

use App\Repository\TitreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "titre")]
#[ORM\Entity(repositoryClass: TitreRepository::class)]
#[ORM\Index(name: "demande_idx", columns: ["demande_id"])]
#[ORM\Index(name: "production_idx", columns: ["production_id"])]
class Titre
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "date_creation", type: "datetime")]
    private \DateTime $dateCreation;

    #[ORM\Column(name: "auteur_creation", type: "string", length: 255)]
    private string $auteurCreation;

    #[ORM\Column(name: "numero_operation", type: "integer")]
    private int $numeroOperation;

    #[ORM\Column(name: "demande_id", type: "integer")]
    private int $demandeId;

    #[ORM\Column(name: "production_id", type: "integer")]
    private int $productionId;

    #[ORM\Column(name: "numero_chequier", type: "integer")]
    private int $numeroChequier;

    #[ORM\Column(name: "numero_cheque", type: "integer", unique: true)]
    private int $numeroCheque;

    #[ORM\Column(name: "type_cheque", type: "string", length: 255)]
    private string $typeCheque;

    #[ORM\Column(name: "valeur_titre", type: "decimal", scale: 2)]
    private string $valeurTitre;

    #[ORM\Column(name: "date_emission", type: "date")]
    private \DateTime $dateEmission;

    #[ORM\Column(name: "date_validite", type: "date")]
    private \DateTime $dateValidite;

    #[ORM\Column(name: "code_etat", type: "integer")]
    private int $codeEtat;

    public static string $filenameRetourProduction = 'flux/retour_production';

    const PATTERN_DATE = '/^((0[1-9]|1\d|2[0-8])\.(0\d|1[012])\.(1[6-9]|[2-9]\d)\d{2}|(29|30)\.(0[13-9]|1[012])\.(1[6-9]|[2-9]\d)\d{2}|31\.(0[13578]|1[02])\.(1[6-9]|[2-9]\d)\d{2}|29\.02\.((1[6-9]|[2-9]\d)(0[48]|[2468][048]|[13579][26])|(16|[2468][048]|[3579][26])00))$/';
    const PATTERN_FILE_AS400 = "#^EXT";
    const PREFIX_FILE_AS400 = "EXT";
    const ROW_LENGTH_RETOUR_PRODUCTION = 80;

    public function __construct(?string $auteurCreation = null)
    {
        $this->dateCreation = new \DateTime();
        $this->auteurCreation = $auteurCreation ?? 'Automate';
    }

    public function getId(): ?int { return $this->id; }
    public function setDateCreation(\DateTime $dateCreation): self { $this->dateCreation = $dateCreation; return $this; }
    public function getDateCreation(): \DateTime { return $this->dateCreation; }
    public function setAuteurCreation(string $auteurCreation): self { $this->auteurCreation = $auteurCreation; return $this; }
    public function getAuteurCreation(): string { return $this->auteurCreation; }
    public function setNumeroOperation(int $numeroOperation): self { $this->numeroOperation = $numeroOperation; return $this; }
    public function getNumeroOperation(): int { return $this->numeroOperation; }
    public function setDemandeId(int $demandeId): self { $this->demandeId = $demandeId; return $this; }
    public function getDemandeId(): int { return $this->demandeId; }
    public function setProductionId(int $productionId): self { $this->productionId = $productionId; return $this; }
    public function getProductionId(): int { return $this->productionId; }
    public function setNumeroChequier(int $numeroChequier): self { $this->numeroChequier = $numeroChequier; return $this; }
    public function getNumeroChequier(): int { return $this->numeroChequier; }
    public function setNumeroCheque(int $numeroCheque): self { $this->numeroCheque = $numeroCheque; return $this; }
    public function getNumeroCheque(): int { return $this->numeroCheque; }
    public function setTypeCheque(string $typeCheque): self { $this->typeCheque = $typeCheque; return $this; }
    public function getTypeCheque(): string { return $this->typeCheque; }
    public function setValeurTitre(string $valeurTitre): self { $this->valeurTitre = $valeurTitre; return $this; }
    public function getValeurTitre(): string { return $this->valeurTitre; }
    public function setDateEmission(\DateTime $dateEmission): self { $this->dateEmission = $dateEmission; return $this; }
    public function getDateEmission(): \DateTime { return $this->dateEmission; }
    public function setDateValidite(\DateTime $dateValidite): self { $this->dateValidite = $dateValidite; return $this; }
    public function getDateValidite(): \DateTime { return $this->dateValidite; }
    public function setCodeEtat(int $codeEtat): self { $this->codeEtat = $codeEtat; return $this; }
    public function getCodeEtat(): int { return $this->codeEtat; }
}

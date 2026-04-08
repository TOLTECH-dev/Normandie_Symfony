<?php

namespace App\Entity;

use App\Repository\FicheTechniqueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Table(name: 'fiche_technique')]
#[ORM\Entity(repositoryClass: FicheTechniqueRepository::class)]
class FicheTechnique
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

    #[ORM\OneToOne(targetEntity: FicheTechniqueField::class, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    public ?FicheTechniqueField $ficheTechnique_initial = null;

    #[ORM\OneToOne(targetEntity: FicheTechniqueField::class, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    public ?FicheTechniqueField $ficheTechnique_BBC = null;

    #[ORM\OneToOne(targetEntity: FicheTechniqueField::class, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    public ?FicheTechniqueField $ficheTechnique_prescription = null;

    #[ORM\OneToOne(targetEntity: FicheTechniqueField::class, cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    public ?FicheTechniqueField $ficheTechnique_finChantier = null;

    #[ORM\Column(name: 'statut_ficheTechnique', type: 'string', length: 10, nullable: true)]
    private ?string $statutFicheTechnique = null;

    #[ORM\Column(name: 'is_validation_conseiller', type: 'boolean', nullable: true)]
    private ?bool $isValidationConseiller = null;

    const EXAMINE_FICHE_TECHNIQUE_PART_DEMANDE = 'INSTRUCTION_TECHNIQUE';
    const EXAMINE_FICHE_TECHNIQUE_PART_REMBOURSEMENT = 'REMBOURSEMENT';

    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
    }

    public function getId(): ?int { return $this->id; }
    public function setDateCreation(\DateTime $dateCreation): self { $this->dateCreation = $dateCreation; return $this; }
    public function getDateCreation(): \DateTime { return $this->dateCreation; }
    public function setAuteurCreation(string $auteurCreation): self { $this->auteurCreation = $auteurCreation; return $this; }
    public function getAuteurCreation(): string { return $this->auteurCreation; }
    public function setDateModif(\DateTime $dateModif): self { $this->dateModif = $dateModif; return $this; }
    public function getDateModif(): \DateTime { return $this->dateModif; }
    public function setAuteurModif(string $auteurModif): self { $this->auteurModif = $auteurModif; return $this; }
    public function getAuteurModif(): string { return $this->auteurModif; }
    public function setFicheTechniqueInitial(?FicheTechniqueField $ficheTechniqueInitial): self { $this->ficheTechnique_initial = $ficheTechniqueInitial; return $this; }
    public function getFicheTechniqueInitial(): ?FicheTechniqueField { return $this->ficheTechnique_initial; }
    public function setFicheTechniqueBBC(?FicheTechniqueField $ficheTechniqueBBC): self { $this->ficheTechnique_BBC = $ficheTechniqueBBC; return $this; }
    public function getFicheTechniqueBBC(): ?FicheTechniqueField { return $this->ficheTechnique_BBC; }
    public function setFicheTechniquePrescription(?FicheTechniqueField $ficheTechniquePrescription): self { $this->ficheTechnique_prescription = $ficheTechniquePrescription; return $this; }
    public function getFicheTechniquePrescription(): ?FicheTechniqueField { return $this->ficheTechnique_prescription; }
    public function setFicheTechniqueFinChantier(?FicheTechniqueField $ficheTechniqueFinChantier): self { $this->ficheTechnique_finChantier = $ficheTechniqueFinChantier; return $this; }
    public function getFicheTechniqueFinChantier(): ?FicheTechniqueField { return $this->ficheTechnique_finChantier; }
    public function setStatutFicheTechnique(?string $statutFicheTechnique): self { $this->statutFicheTechnique = $statutFicheTechnique; return $this; }
    public function getStatutFicheTechnique(): ?string { return $this->statutFicheTechnique; }
    public function setIsValidationConseiller(?bool $isValidationConseiller): self { $this->isValidationConseiller = $isValidationConseiller; return $this; }
    public function getIsValidationConseiller(): ?bool { return $this->isValidationConseiller; }
}

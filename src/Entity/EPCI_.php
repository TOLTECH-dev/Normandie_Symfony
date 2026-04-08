<?php

namespace App\Entity;

use App\Repository\EPCI_Repository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\Collection;

#[ORM\Table(name: 'EPCI_')]
#[ORM\Entity(repositoryClass: EPCI_Repository::class)]
class EPCI_
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

    #[ORM\Column(name: 'nom', type: 'string', length: 255)]
    private string $nom;

    #[ORM\Column(name: 'adresse_1', type: 'string', length: 255)]
    private string $adresse1;

    #[ORM\Column(name: 'adresse_2', type: 'string', length: 255, nullable: true)]
    private ?string $adresse2 = null;

    #[ORM\Column(name: 'adresse_3', type: 'string', length: 255, nullable: true)]
    private ?string $adresse3 = null;

    #[ORM\Column(name: 'code_postal', type: 'string', length: 20)]
    private string $codePostal;

    #[ORM\Column(name: 'ville', type: 'string', length: 255)]
    private string $ville;

    #[ORM\Column(name: 'telephone', type: 'string', length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(name: 'site_internet', type: 'string', length: 255, nullable: true)]
    private ?string $siteInternet = null;

    #[ORM\Column(name: 'email', type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'participation_sare', type: 'boolean', nullable: true)]
    private ?bool $participation_SARE = null;

    #[ORM\Column(name: 'point_entree_structure', type: 'boolean', nullable: true)]
    private ?bool $pointEntreeStructure = null;

    #[ORM\Column(name: 'nom_affichage', type: 'string', length: 255, nullable: true)]
    private ?string $nomAffichage = null;

    #[ORM\ManyToMany(targetEntity: EPCI_contact::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'EPCI__EPCI_contact')]
    protected Collection $EPCI_contact;

    #[ORM\ManyToMany(targetEntity: EPCI_permanence::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'EPCI__EPCI_permanence')]
    #[Assert\Valid]
    protected Collection $EPCI_permanence;

    #[ORM\Column(name: 'enabled', type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'date_inactif', type: 'date', nullable: true)]
    private ?\DateTime $dateInactif = null;

    public function __construct()
    {
        $this->dateCreation = new \Datetime();
        $this->dateModif = new \Datetime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();

        $this->EPCI_contact = new ArrayCollection();
        $this->EPCI_permanence = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string)$this->id;
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
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setAdresse1(string $adresse1): self { $this->adresse1 = $adresse1; return $this; }
    public function getAdresse1(): string { return $this->adresse1; }
    public function setAdresse2(?string $adresse2): self { $this->adresse2 = $adresse2; return $this; }
    public function getAdresse2(): ?string { return $this->adresse2; }
    public function setAdresse3(?string $adresse3): self { $this->adresse3 = $adresse3; return $this; }
    public function getAdresse3(): ?string { return $this->adresse3; }
    public function setCodePostal(string $codePostal): self { $this->codePostal = $codePostal; return $this; }
    public function getCodePostal(): string { return $this->codePostal; }
    public function setVille(string $ville): self { $this->ville = $ville; return $this; }
    public function getVille(): string { return $this->ville; }
    public function setTelephone(?string $telephone): self { $this->telephone = $telephone; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setSiteInternet(?string $siteInternet): self { $this->siteInternet = $siteInternet; return $this; }
    public function getSiteInternet(): ?string { return $this->siteInternet; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setParticipationSARE(?bool $participationSARE): self { $this->participation_SARE = $participationSARE; return $this; }
    public function getParticipationSARE(): ?bool { return $this->participation_SARE; }
    public function setPointEntreeStructure(?bool $pointEntreeStructure): self { $this->pointEntreeStructure = $pointEntreeStructure; return $this; }
    public function getPointEntreeStructure(): ?bool { return $this->pointEntreeStructure; }
    public function setNomAffichage(?string $nomAffichage): self { $this->nomAffichage = $nomAffichage; return $this; }
    public function getNomAffichage(): ?string { return $this->nomAffichage; }
    public function addEPCIContact($ePCIContact): self { $this->EPCI_contact[] = $ePCIContact; return $this; }
    public function removeEPCIContact($ePCIContact): void { $this->EPCI_contact->removeElement($ePCIContact); }
    public function getEPCIContact(): Collection { return $this->EPCI_contact; }
    public function addEPCIPermanence($EPCI_permanence): self { $this->EPCI_permanence[] = $EPCI_permanence; return $this; }
    public function removeEPCIPermanence($EPCI_permanence): void { $this->EPCI_permanence->removeElement($EPCI_permanence); }
    public function getEPCIPermanence(): Collection { return $this->EPCI_permanence; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }
    public function getEnabled(): bool { return $this->enabled; }
    public function setDateInactif(?\DateTime $dateInactif): self { $this->dateInactif = $dateInactif; return $this; }
    public function getDateInactif(): ?\DateTime { return $this->dateInactif; }
}

<?php

namespace App\Entity;

use App\Repository\Production_Repository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Demande_;

#[ORM\Entity(repositoryClass: Production_Repository::class)]
#[ORM\Table(name: 'production_')]
class Production_
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTime $dateCreation;

    #[ORM\Column(name: 'auteur_creation', type: 'string', length: 255)]
    private string $auteurCreation;

    #[ORM\Column(name: 'date_modif', type: 'datetime')]
    private \DateTime $dateModif;

    #[ORM\Column(name: 'auteur_modif', type: 'string', length: 255)]
    private string $auteurModif;

    #[ORM\Column(name: 'date_lancement', type: 'datetime', nullable: true)]
    private ?\DateTime $dateLancement = null;

    #[ORM\Column(name: 'date_production', type: 'datetime', nullable: true)]
    private ?\DateTime $dateProduction = null;

    #[ORM\Column(name: 'date_expedition', type: 'datetime', nullable: true)]
    private ?\DateTime $dateExpedition = null;

    #[ORM\ManyToMany(targetEntity: Demande_::class, cascade: ['persist'])]
    private Collection $demande;

    #[ORM\Column(name: 'type', type: 'json', nullable: true)]
    private ?array $type = null;

    #[ORM\Column(name: 'niveau', type: 'json', nullable: true)]
    private ?array $niveau = null;

    /*
     * CONSTANTES
     */
    const TYPE_AUDIT_ENERGETIQUE_ET_SCENARIO_LABEL = Demande_::DEMANDE_AUDIT_ENERGIE_LABEL;
    const TYPE_AUDIT_ENERGETIQUE_ET_SCENARIO_KEY   = 'audit_energie';
    const TYPE_AUDIT_NUMERIQUE_LABEL               = Demande_::DEMANDE_AUDIT_NUMERIQUE_LABEL;
    const TYPE_AUDIT_NUMERIQUE_KEY                 = 'audit_numerique';
    const TYPE_AUDIT_ENERGIE_REGION_LABEL          = Demande_::DEMANDE_AUDIT_ENERGIE_REGION_LABEL;
    const TYPE_AUDIT_ENERGIE_REGION_KEY            = 'audit_energie_region';
    const TYPE_MISE_A_JOUR_AUDIT_ENERGIE_LABEL     = Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_LABEL;
    const TYPE_MISE_A_JOUR_AUDIT_ENERGIE_KEY       = 'mise_a_jour_audit_energie';

    const NIVEAU_CHEQUE_TRAVAUX_NIVEAU_1_LABEL                     = 'Chèque Travaux Niveau 1';
    const NIVEAU_CHEQUE_TRAVAUX_NIVEAU_1_KEY                       = 'niveau1';
    const NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_LABEL                     = 'Chèque Travaux Niveau 2';
    const NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_KEY                       = 'niveau2';
    const NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_RENOVATEUR_LABEL          = 'Chèque Travaux Niveau 2 avec Rénovateur';
    const NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_RENOVATEUR_KEY            = 'niveauRenov';
    const NIVEAU_CHEQUE_TRAVAUX_BBC_LABEL                          = 'Chèque Travaux BBC';
    const NIVEAU_CHEQUE_TRAVAUX_BBC_KEY                            = 'niveauBBC';
    const NIVEAU_CHEQUE_TRAVAUX_BBC_BIOSOURCE_LABEL                = 'Chèque Travaux BBC Biosourcé';
    const NIVEAU_CHEQUE_TRAVAUX_BBC_BIOSOURCE_KEY                  = 'niveauBBCbiosource';
    const NIVEAU_CHEQUE_TRAVAUX_SORTIE_PASSOIRE_LABEL              = 'Sortie de passoire';
    const NIVEAU_CHEQUE_TRAVAUX_SORTIE_PASSOIRE_KEY                = 'sortiePassoire';
    const NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RGE_LABEL               = 'Première étape BBC avec RGE';
    const NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RGE_KEY                 = 'premiereEtapeBBCRGE';
    const NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RENOVATEUR_LABEL        = 'Première étape BBC avec Rénovateur';
    const NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RENOVATEUR_KEY          = 'premiereEtapeBBCRenovateur';
    const NIVEAU_CHEQUE_TRAVAUX_RENOVATION_GLOBALE_BBC_LABEL       = 'Rénovation globale BBC';
    const NIVEAU_CHEQUE_TRAVAUX_RENOVATION_GLOBALE_BBC_KEY         = 'renovationGobaleBBC';

    public static $arrayProductionType = [
        self::TYPE_AUDIT_ENERGETIQUE_ET_SCENARIO_LABEL => self::TYPE_AUDIT_ENERGETIQUE_ET_SCENARIO_KEY,
        self::TYPE_AUDIT_NUMERIQUE_LABEL               => self::TYPE_AUDIT_NUMERIQUE_KEY,
        self::TYPE_AUDIT_ENERGIE_REGION_LABEL          => self::TYPE_AUDIT_ENERGIE_REGION_KEY,
        self::TYPE_MISE_A_JOUR_AUDIT_ENERGIE_LABEL     => self::TYPE_MISE_A_JOUR_AUDIT_ENERGIE_KEY
    ];

    public static $arrayProductionNiveau = [
        self::NIVEAU_CHEQUE_TRAVAUX_NIVEAU_1_LABEL               => self::NIVEAU_CHEQUE_TRAVAUX_NIVEAU_1_KEY,
        self::NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_LABEL               => self::NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_KEY,
        self::NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_RENOVATEUR_LABEL    => self::NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_RENOVATEUR_KEY,
        self::NIVEAU_CHEQUE_TRAVAUX_BBC_LABEL                    => self::NIVEAU_CHEQUE_TRAVAUX_BBC_KEY,
        self::NIVEAU_CHEQUE_TRAVAUX_BBC_BIOSOURCE_LABEL          => self::NIVEAU_CHEQUE_TRAVAUX_BBC_BIOSOURCE_KEY,
        self::NIVEAU_CHEQUE_TRAVAUX_SORTIE_PASSOIRE_LABEL        => self::NIVEAU_CHEQUE_TRAVAUX_SORTIE_PASSOIRE_KEY,
        self::NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RGE_LABEL         => self::NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RGE_KEY,
        self::NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RENOVATEUR_LABEL  => self::NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RENOVATEUR_KEY,
        self::NIVEAU_CHEQUE_TRAVAUX_RENOVATION_GLOBALE_BBC_LABEL => self::NIVEAU_CHEQUE_TRAVAUX_RENOVATION_GLOBALE_BBC_KEY
    ];



    /**
     * Constructor
     */
    public function __construct()
    {
        $this->demande = new ArrayCollection();

        $this->dateCreation = new \DateTime();
        $this->dateModif = new \DateTime();

        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
    }



    /**
     * Get id
     *
     * @return integer
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Production_
     */
    public function setDateCreation(\DateTime $dateCreation): self
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation
     *
     * @return \DateTime
     */
    public function getDateCreation(): \DateTime
    {
        return $this->dateCreation;
    }

    /**
     * Set auteurCreation
     *
     * @param string $auteurCreation
     *
     * @return Production_
     */
    public function setAuteurCreation(string $auteurCreation): self
    {
        $this->auteurCreation = $auteurCreation;

        return $this;
    }

    /**
     * Get auteurCreation
     *
     * @return string
     */
    public function getAuteurCreation(): string
    {
        return $this->auteurCreation;
    }

    /**
     * Set dateLancement
     *
     * @param \DateTime $dateLancement
     *
     * @return Production_
     */
    public function setDateLancement(?\DateTime $dateLancement): self
    {
        $this->dateLancement = $dateLancement;

        return $this;
    }

    /**
     * Get dateLancement
     *
     * @return \DateTime
     */
    public function getDateLancement(): ?\DateTime
    {
        return $this->dateLancement;
    }

    /**
     * Set dateProduction
     *
     * @param \DateTime $dateProduction
     *
     * @return Production_
     */
    public function setDateProduction(?\DateTime $dateProduction): self
    {
        $this->dateProduction = $dateProduction;

        return $this;
    }

    /**
     * Get dateProduction
     *
     * @return \DateTime
     */
    public function getDateProduction(): ?\DateTime
    {
        return $this->dateProduction;
    }

    /**
     * Set dateExpedition
     *
     * @param \DateTime $dateExpedition
     *
     * @return Production_
     */
    public function setDateExpedition(?\DateTime $dateExpedition): self
    {
        $this->dateExpedition = $dateExpedition;

        return $this;
    }

    /**
     * Get dateExpedition
     *
     * @return \DateTime
     */
    public function getDateExpedition(): ?\DateTime
    {
        return $this->dateExpedition;
    }

    /**
     * Set type
     *
     * @param array $type
     *
     * @return Production_
     */
    public function setType(?array $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return array
     */
    public function getType(): ?array
    {
        return $this->type;
    }

    /**
     * Set niveau
     *
     * @param array $niveau
     *
     * @return Production_
     */
    public function setNiveau(?array $niveau): self
    {
        $this->niveau = $niveau;

        return $this;
    }

    /**
     * Get niveau
     *
     * @return array
     */
    public function getNiveau(): ?array
    {
        return $this->niveau;
    }

    /**
     * Add demande
     *
     * @param Demande_ $demande
     *
     * @return Production_
     */
    public function addDemande(Demande_ $demande): self
    {
        if (!$this->demande->contains($demande)) {
            $this->demande[] = $demande;
        }

        return $this;
    }

    /**
     * Remove demande
     *
     * @param Demande_ $demande
     */
    public function removeDemande(Demande_ $demande): self
    {
        $this->demande->removeElement($demande);

        return $this;
    }

    /**
     * Get demande
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getDemande(): Collection
    {
        return $this->demande;
    }

    /**
     * Set dateModif
     *
     * @param \DateTime $dateModif
     *
     * @return Production_
     */
    public function setDateModif(\DateTime $dateModif): self
    {
        $this->dateModif = $dateModif;

        return $this;
    }

    /**
     * Get dateModif
     *
     * @return \DateTime
     */
    public function getDateModif(): \DateTime
    {
        return $this->dateModif;
    }

    /**
     * Set auteurModif
     *
     * @param string $auteurModif
     *
     * @return Production_
     */
    public function setAuteurModif(string $auteurModif): self
    {
        $this->auteurModif = $auteurModif;

        return $this;
    }

    /**
     * Get auteurModif
     *
     * @return string
     */
    public function getAuteurModif(): string
    {
        return $this->auteurModif;
    }
}

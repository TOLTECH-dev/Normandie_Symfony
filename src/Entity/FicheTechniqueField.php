<?php

namespace App\Entity;

use App\Repository\FicheTechniqueFieldRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Table(name: 'fiche_technique_field')]
#[ORM\Entity(repositoryClass: FicheTechniqueFieldRepository::class)]
class FicheTechniqueField
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: "type", type: "string", length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: "surface_SRT", type: "string", length: 255, nullable: true)]
    private ?string $surfaceSRT = null;

    #[ORM\Column(name: "surface_habitable", type: "string", length: 255)]
    #[Assert\NotBlank]
    private string $surfaceHabitable;

    #[ORM\Column(name: "surface_pathologies", type: "array", nullable: true)]
    private ?array $surfacePathologies = null;

    #[ORM\Column(name: "surface_pathologies_autre", type: "string", length: 255, nullable: true)]
    private ?string $surfacePathologiesAutre = null;

    #[ORM\Column(name: "toiture_surface", type: "string", length: 255, nullable: true)]
    private ?string $toitureSurface = null;

    #[ORM\Column(name: "toiture_R", type: "string", length: 255, nullable: true)]
    private ?string $toitureR = null;

    #[ORM\Column(name: "toiture_etancheite", type: "string", length: 255, nullable: true)]
    private ?string $toitureEtancheite = null;

    #[ORM\Column(name: "murs_surface", type: "string", length: 255, nullable: true)]
    private ?string $mursSurface = null;

    #[ORM\Column(name: "murs_R", type: "string", length: 255, nullable: true)]
    private ?string $mursR = null;

    #[ORM\Column(name: "murs_etancheite", type: "string", length: 255, nullable: true)]
    private ?string $mursEtancheite = null;

    #[ORM\Column(name: "murs_jonction_murs_planchers", type: "string", length: 255, nullable: true)]
    private ?string $mursJonctionMursPlanchers = null;

    #[ORM\Column(name: "menuiseries_exterieures_surface", type: "string", length: 255, nullable: true)]
    private ?string $menuiseriesExterieuresSurface = null;

    #[ORM\Column(name: "menuiseries_exterieures_UW", type: "string", length: 255, nullable: true)]
    private ?string $menuiseriesExterieuresUW = null;

    #[ORM\Column(name: "menuiseries_mode_pose", type: "string", length: 255, nullable: true)]
    private ?string $menuiseriesModePose = null;

    #[ORM\Column(name: "menuiseries_type_protections_solaires", type: "string", length: 255, nullable: true)]
    private ?string $menuiseriesTypeProtectionsSolaires = null;

    #[ORM\Column(name: "plancher_bas_surface", type: "string", length: 255, nullable: true)]
    private ?string $plancherBasSurface = null;

    #[ORM\Column(name: "plancher_bas_R", type: "string", length: 255, nullable: true)]
    private ?string $plancherBasR = null;

    #[ORM\Column(name: "plancher_bas_etancheite", type: "string", length: 255, nullable: true)]
    private ?string $plancherBasEtancheite = null;

    #[ORM\Column(name: "chauffage_energie", type: "array", nullable: true)]
    private ?array $chauffageEnergie = null;

    #[ORM\Column(name: "chauffage_equipement", type: "string", length: 255, nullable: true)]
    private ?string $chauffageEquipement = null;

    #[ORM\Column(name: "ECS_energie", type: "array", nullable: true)]
    private ?array $ECSEnergie = null;

    #[ORM\Column(name: "ECS_equipement", type: "string", length: 255, nullable: true)]
    private ?string $ECSEquipement = null;

    #[ORM\Column(name: "climatisation_", type: "string", length: 255, nullable: true)]
    private ?string $climatisation = null;

    #[ORM\Column(name: "climatisation_type_ventilation", type: "string", length: 255, nullable: true)]
    private ?string $climatisationTypeVentilation = null;

    #[ORM\Column(name: "CEP_", type: "string", length: 255)]
    #[Assert\NotBlank]
    private string $CEP;

    #[ORM\Column(name: "CEP_gain", type: "string", length: 255, nullable: true)]
    private ?string $CEPGain = null;

    #[ORM\Column(name: "CEP_ubat", type: "string", length: 255, nullable: true)]
    private ?string $CEPUbat = null;

    #[ORM\Column(name: "CEP_ubat_base", type: "string", length: 255, nullable: true)]
    private ?string $CEPUbatBase = null;

    #[ORM\Column(name: "CEP_ubat_gain", type: "string", length: 255, nullable: true)]
    private ?string $CEPUbatGain = null;

    #[ORM\Column(name: "CEP_Q4Pa_surf", type: "string", length: 255, nullable: true)]
    private ?string $CEPQ4Pa_surf = null;

    #[ORM\Column(name: "CEP_GES", type: "string", length: 255, nullable: true)]
    private ?string $CEPGES = null;

    #[ORM\Column(name: "CEP_etiquette_energetique", type: "string", length: 255, nullable: true)]
    private ?string $CEPEtiquetteEnergetique = null;

    #[ORM\Column(name: "information_controleur_chantier", type: "string", length: 255, nullable: true)]
    private ?string $informationControleurChantier = null;

    #[ORM\Column(name: "information_validation", type: "boolean", nullable: true)]
    private ?bool $informationValidation = null;

    #[ORM\Column(name: "is_valeur_q4_calculee_conforme", type: "boolean", nullable: true)]
    private ?bool $isValeurQ4CalculeeConforme = null;

    #[ORM\Column(name: "is_systeme_ventilation_conforme", type: "boolean", nullable: true)]
    private ?bool $isSystemeVentilationConforme = null;

    #[ORM\Column(name: "is_valoriser_renovation", type: "boolean", nullable: true)]
    private ?bool $isValoriserRenovation = null;

    #[ORM\Column(name: "valoriser_renovation_justification", type: "string", length: 255, nullable: true)]
    private ?string $valoriserRenovationJustification = null;

    #[ORM\Column(name: "ficheTechnique_document_url", type: "string", length: 255, nullable: true)]
    private ?string $ficheTechniqueDocument_url = null;

    #[ORM\Column(name: "ficheTechnique_document_alt", type: "string", length: 255, nullable: true)]
    private ?string $ficheTechniqueDocument_alt = null;

    #[Assert\File(
        maxSize: "5120k",
        mimeTypes: ["application/xml", "text/xml"],
        mimeTypesMessage: "Format du fichier invalide. Les formats suivants sont acceptés: .xml"
    )]
    private ?UploadedFile $ficheTechniqueDocument = null;

    #[ORM\Column(name: "infiltrometrie_document_url", type: "string", length: 255, nullable: true)]
    private ?string $infiltrometrieDocument_url = null;

    #[ORM\Column(name: "infiltrometrie_document_alt", type: "string", length: 255, nullable: true)]
    private ?string $infiltrometrieDocument_alt = null;

    #[Assert\File(
        maxSize: "5120k",
        mimeTypes: ["application/pdf"],
        mimeTypesMessage: "Format du fichier invalide. Les formats suivants sont acceptés: .pdf"
    )]
    private ?UploadedFile $infiltrometrieDocument = null;

    #[ORM\Column(name: "ventilation_document_url", type: "string", length: 255, nullable: true)]
    private ?string $ventilationDocument_url = null;

    #[ORM\Column(name: "ventilation_document_alt", type: "string", length: 255, nullable: true)]
    private ?string $ventilationDocument_alt = null;

    #[Assert\File(
        maxSize: "5120k",
        mimeTypes: ["application/pdf"],
        mimeTypesMessage: "Format du fichier invalide. Les formats suivants sont acceptés: .pdf"
    )]
    private ?UploadedFile $ventilationDocument = null;

    #[ORM\Column(name: "audit_apres_travaux_document_url", type: "string", length: 255, nullable: true)]
    private ?string $auditApresTravauxDocument_url = null;

    #[ORM\Column(name: "audit_apres_travaux_document_alt", type: "string", length: 255, nullable: true)]
    private ?string $auditApresTravauxDocument_alt = null;

    #[Assert\File(
        maxSize: "5120k",
        mimeTypes: ["application/pdf"],
        mimeTypesMessage: "Format du fichier invalide. Les formats suivants sont acceptés: .pdf"
    )]
    private ?UploadedFile $auditApresTravauxDocument = null;

    #[ORM\Column(name: "document_updated_at", type: "datetime", nullable: true)]
    private ?\DateTime $documentUpdatedAt = null;

    private ?string $tempFilename = null;



    /**
     * Get id
     *
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setSurfaceSRT(?string $surfaceSRT): self
    {
        $this->surfaceSRT = $surfaceSRT;

        return $this;
    }

    public function getSurfaceSRT(): ?string
    {
        return $this->surfaceSRT;
    }

    public function setSurfaceHabitable(string $surfaceHabitable): self
    {
        $this->surfaceHabitable = $surfaceHabitable;

        return $this;
    }

    public function getSurfaceHabitable(): string
    {
        return $this->surfaceHabitable;
    }

    public function setSurfacePathologies(?array $surfacePathologies): self
    {
        $this->surfacePathologies = $surfacePathologies;

        return $this;
    }

    public function getSurfacePathologies(): ?array
    {
        return $this->surfacePathologies;
    }

    public function setSurfacePathologiesAutre(?string $surfacePathologiesAutre): self
    {
        $this->surfacePathologiesAutre = $surfacePathologiesAutre;

        return $this;
    }

    public function getSurfacePathologiesAutre(): ?string
    {
        return $this->surfacePathologiesAutre;
    }

    public function setToitureSurface(?string $toitureSurface): self
    {
        $this->toitureSurface = $toitureSurface;

        return $this;
    }

    public function getToitureSurface(): ?string
    {
        return $this->toitureSurface;
    }

    public function setToitureR(?string $toitureR): self
    {
        $this->toitureR = $toitureR;

        return $this;
    }

    public function getToitureR(): ?string
    {
        return $this->toitureR;
    }

    public function setToitureEtancheite(?string $toitureEtancheite): self
    {
        $this->toitureEtancheite = $toitureEtancheite;

        return $this;
    }

    public function getToitureEtancheite(): ?string
    {
        return $this->toitureEtancheite;
    }

    public function setMursSurface(?string $mursSurface): self
    {
        $this->mursSurface = $mursSurface;

        return $this;
    }

    public function getMursSurface(): ?string
    {
        return $this->mursSurface;
    }

    public function setMursR(?string $mursR): self
    {
        $this->mursR = $mursR;

        return $this;
    }

    public function getMursR(): ?string
    {
        return $this->mursR;
    }

    public function setMursEtancheite(?string $mursEtancheite): self
    {
        $this->mursEtancheite = $mursEtancheite;

        return $this;
    }

    public function getMursEtancheite(): ?string
    {
        return $this->mursEtancheite;
    }

    public function setMursJonctionMursPlanchers(?string $mursJonctionMursPlanchers): self
    {
        $this->mursJonctionMursPlanchers = $mursJonctionMursPlanchers;

        return $this;
    }

    public function getMursJonctionMursPlanchers(): ?string
    {
        return $this->mursJonctionMursPlanchers;
    }

    public function setMenuiseriesExterieuresSurface(?string $menuiseriesExterieuresSurface): self
    {
        $this->menuiseriesExterieuresSurface = $menuiseriesExterieuresSurface;

        return $this;
    }

    public function getMenuiseriesExterieuresSurface(): ?string
    {
        return $this->menuiseriesExterieuresSurface;
    }

    public function setMenuiseriesExterieuresUW(?string $menuiseriesExterieuresUW): self
    {
        $this->menuiseriesExterieuresUW = $menuiseriesExterieuresUW;

        return $this;
    }

    public function getMenuiseriesExterieuresUW(): ?string
    {
        return $this->menuiseriesExterieuresUW;
    }

    public function setMenuiseriesModePose(?string $menuiseriesModePose): self
    {
        $this->menuiseriesModePose = $menuiseriesModePose;

        return $this;
    }

    public function getMenuiseriesModePose(): ?string
    {
        return $this->menuiseriesModePose;
    }

    public function setMenuiseriesTypeProtectionsSolaires(?string $menuiseriesTypeProtectionsSolaires): self
    {
        $this->menuiseriesTypeProtectionsSolaires = $menuiseriesTypeProtectionsSolaires;

        return $this;
    }

    public function getMenuiseriesTypeProtectionsSolaires(): ?string
    {
        return $this->menuiseriesTypeProtectionsSolaires;
    }

    public function setPlancherBasSurface(?string $plancherBasSurface): self
    {
        $this->plancherBasSurface = $plancherBasSurface;

        return $this;
    }

    public function getPlancherBasSurface(): ?string
    {
        return $this->plancherBasSurface;
    }

    public function setPlancherBasR(?string $plancherBasR): self
    {
        $this->plancherBasR = $plancherBasR;

        return $this;
    }

    public function getPlancherBasR(): ?string
    {
        return $this->plancherBasR;
    }

    public function setPlancherBasEtancheite(?string $plancherBasEtancheite): self
    {
        $this->plancherBasEtancheite = $plancherBasEtancheite;

        return $this;
    }

    public function getPlancherBasEtancheite(): ?string
    {
        return $this->plancherBasEtancheite;
    }

    /**
     * Set chauffageEnergie
     */
    public function setChauffageEnergie(?array $chauffageEnergie): self
    {
        $this->chauffageEnergie = $chauffageEnergie;

        return $this;
    }

    /**
     * Get chauffageEnergie
     */
    public function getChauffageEnergie(): ?array
    {
        return $this->chauffageEnergie;
    }

    /**
     * Set chauffageEquipement
     */
    public function setChauffageEquipement(?string $chauffageEquipement): self
    {
        $this->chauffageEquipement = $chauffageEquipement;

        return $this;
    }

    /**
     * Get chauffageEquipement
     */
    public function getChauffageEquipement(): ?string
    {
        return $this->chauffageEquipement;
    }

    /**
     * Set eCSEnergie
     */
    public function setECSEnergie(?array $eCSEnergie): self
    {
        $this->ECSEnergie = $eCSEnergie;

        return $this;
    }

    /**
     * Get eCSEnergie
     */
    public function getECSEnergie(): ?array
    {
        return $this->ECSEnergie;
    }

    /**
     * Set eCSEquipement
     */
    public function setECSEquipement(?string $eCSEquipement): self
    {
        $this->ECSEquipement = $eCSEquipement;

        return $this;
    }

    /**
     * Get eCSEquipement
     */
    public function getECSEquipement(): ?string
    {
        return $this->ECSEquipement;
    }

    public function setClimatisation(?string $climatisation): self
    {
        $this->climatisation = $climatisation;

        return $this;
    }

    public function getClimatisation(): ?string
    {
        return $this->climatisation;
    }

    public function setClimatisationTypeVentilation(?string $climatisationTypeVentilation): self
    {
        $this->climatisationTypeVentilation = $climatisationTypeVentilation;

        return $this;
    }

    public function getClimatisationTypeVentilation(): ?string
    {
        return $this->climatisationTypeVentilation;
    }

    public function setCEP(string $cEP): self
    {
        $this->CEP = $cEP;

        return $this;
    }

    public function getCEP(): string
    {
        return $this->CEP;
    }

    public function setCEPGain(?string $cEPGain): self
    {
        $this->CEPGain = $cEPGain;

        return $this;
    }

    public function getCEPGain(): ?string
    {
        return $this->CEPGain;
    }

    public function setCEPUbat(?string $cEPUbat): self
    {
        $this->CEPUbat = $cEPUbat;

        return $this;
    }

    public function getCEPUbat(): ?string
    {
        return $this->CEPUbat;
    }

    public function setCEPUbatBase(?string $cEPUbatBase): self
    {
        $this->CEPUbatBase = $cEPUbatBase;

        return $this;
    }

    public function getCEPUbatBase(): ?string
    {
        return $this->CEPUbatBase;
    }

    public function setCEPUbatGain(?string $cEPUbatGain): self
    {
        $this->CEPUbatGain = $cEPUbatGain;

        return $this;
    }

    public function getCEPUbatGain(): ?string
    {
        return $this->CEPUbatGain;
    }

    public function setCEPQ4PaSurf(?string $cEPQ4PaSurf): self
    {
        $this->CEPQ4Pa_surf = $cEPQ4PaSurf;

        return $this;
    }

    public function getCEPQ4PaSurf(): ?string
    {
        return $this->CEPQ4Pa_surf;
    }

    public function setCEPGES(?string $cEPGES): self
    {
        $this->CEPGES = $cEPGES;

        return $this;
    }

    public function getCEPGES(): ?string
    {
        return $this->CEPGES;
    }

    /**
     * Set cEPEtiquetteEnergetique
     *
     * @param string $cEPEtiquetteEnergetique
     *
     * @return FicheTechniqueField
     */
    public function setCEPEtiquetteEnergetique(?string $cEPEtiquetteEnergetique): self
    {
        $this->CEPEtiquetteEnergetique = $cEPEtiquetteEnergetique;

        return $this;
    }

    /**
     * Get cEPEtiquetteEnergetique
     *
     * @return string
     */
    public function getCEPEtiquetteEnergetique(): ?string
    {
        return $this->CEPEtiquetteEnergetique;
    }

    /**
     * Set informationControleurChantier
     *
     * @param string $informationControleurChantier
     *
     * @return FicheTechniqueField
     */
    public function setInformationControleurChantier(?string $informationControleurChantier): self
    {
        $this->informationControleurChantier = $informationControleurChantier;

        return $this;
    }

    /**
     * Get informationControleurChantier
     *
     * @return string
     */
    public function getInformationControleurChantier(): ?string
    {
        return $this->informationControleurChantier;
    }

    /**
     * Set informationValidation
     *
     * @param boolean $informationValidation
     *
     * @return FicheTechniqueField
     */
    public function setInformationValidation(?bool $informationValidation): self
    {
        $this->informationValidation = $informationValidation;

        return $this;
    }

    /**
     * Get informationValidation
     *
     * @return boolean
     */
    public function getInformationValidation(): ?bool
    {
        return $this->informationValidation;
    }

    /**
     * Set isValeurQ4CalculeeConforme
     *
     * @param boolean $isValeurQ4CalculeeConforme
     *
     * @return FicheTechniqueField
     */
    public function setIsValeurQ4CalculeeConforme(?bool $isValeurQ4CalculeeConforme): self
    {
        $this->isValeurQ4CalculeeConforme = $isValeurQ4CalculeeConforme;

        return $this;
    }

    /**
     * Get isValeurQ4CalculeeConforme
     *
     * @return boolean
     */
    public function getIsValeurQ4CalculeeConforme(): ?bool
    {
        return $this->isValeurQ4CalculeeConforme;
    }

    /**
     * Set isSystemeVentilationConforme
     *
     * @param boolean $isSystemeVentilationConforme
     *
     * @return FicheTechniqueField
     */
    public function setIsSystemeVentilationConforme(?bool $isSystemeVentilationConforme): self
    {
        $this->isSystemeVentilationConforme = $isSystemeVentilationConforme;

        return $this;
    }

    /**
     * Get isSystemeVentilationConforme
     *
     * @return boolean
     */
    public function getIsSystemeVentilationConforme(): ?bool
    {
        return $this->isSystemeVentilationConforme;
    }

    /**
     * Set isValoriserRenovation
     *
     * @param boolean $isValoriserRenovation
     *
     * @return FicheTechniqueField
     */
    public function setIsValoriserRenovation(?bool $isValoriserRenovation): self
    {
        $this->isValoriserRenovation = $isValoriserRenovation;

        return $this;
    }

    /**
     * Get isValoriserRenovation
     *
     * @return boolean
     */
    public function getIsValoriserRenovation(): ?bool
    {
        return $this->isValoriserRenovation;
    }

    /**
     * Set valoriserRenovationJustification
     *
     * @param string $valoriserRenovationJustification
     *
     * @return FicheTechniqueField
     */
    public function setValoriserRenovationJustification(?string $valoriserRenovationJustification): self
    {
        $this->valoriserRenovationJustification = $valoriserRenovationJustification;

        return $this;
    }

    /**
     * Get valoriserRenovationJustification
     *
     * @return string
     */
    public function getValoriserRenovationJustification(): ?string
    {
        return $this->valoriserRenovationJustification;
    }

    /**
     * Set ficheTechniqueDocumentUrl
     *
     * @param string $ficheTechniqueDocumentUrl
     *
     * @return FicheTechniqueField
     */
    public function setFicheTechniqueDocumentUrl(?string $ficheTechniqueDocumentUrl): self
    {
        $this->ficheTechniqueDocument_url = $ficheTechniqueDocumentUrl;

        return $this;
    }

    /**
     * Get ficheTechniqueDocumentUrl
     *
     * @return string
     */
    public function getFicheTechniqueDocumentUrl(): ?string
    {
        return $this->ficheTechniqueDocument_url;
    }

    /**
     * Set ficheTechniqueDocumentAlt
     *
     * @param string $ficheTechniqueDocumentAlt
     *
     * @return FicheTechniqueField
     */
    public function setFicheTechniqueDocumentAlt(?string $ficheTechniqueDocumentAlt): self
    {
        $this->ficheTechniqueDocument_alt = $ficheTechniqueDocumentAlt;

        return $this;
    }

    /**
     * Get ficheTechniqueDocumentAlt
     *
     * @return string
     */
    public function getFicheTechniqueDocumentAlt(): ?string
    {
        return $this->ficheTechniqueDocument_alt;
    }

    /**
     * Set infiltrometrieDocumentUrl
     *
     * @param string $infiltrometrieDocumentUrl
     *
     * @return FicheTechniqueField
     */
    public function setInfiltrometrieDocumentUrl(?string $infiltrometrieDocumentUrl): self
    {
        $this->infiltrometrieDocument_url = $infiltrometrieDocumentUrl;

        return $this;
    }

    /**
     * Get infiltrometrieDocumentUrl
     *
     * @return string
     */
    public function getInfiltrometrieDocumentUrl(): ?string
    {
        return $this->infiltrometrieDocument_url;
    }

    /**
     * Set infiltrometrieDocumentAlt
     *
     * @param string $infiltrometrieDocumentAlt
     *
     * @return FicheTechniqueField
     */
    public function setInfiltrometrieDocumentAlt(?string $infiltrometrieDocumentAlt): self
    {
        $this->infiltrometrieDocument_alt = $infiltrometrieDocumentAlt;

        return $this;
    }

    /**
     * Get infiltrometrieDocumentAlt
     *
     * @return string
     */
    public function getInfiltrometrieDocumentAlt(): ?string
    {
        return $this->infiltrometrieDocument_alt;
    }

    /**
     * Set ventilationDocumentUrl
     *
     * @param string $ventilationDocumentUrl
     *
     * @return FicheTechniqueField
     */
    public function setVentilationDocumentUrl(?string $ventilationDocumentUrl): self
    {
        $this->ventilationDocument_url = $ventilationDocumentUrl;

        return $this;
    }

    /**
     * Get ventilationDocumentUrl
     *
     * @return string
     */
    public function getVentilationDocumentUrl(): ?string
    {
        return $this->ventilationDocument_url;
    }

    /**
     * Set ventilationDocumentAlt
     *
     * @param string $ventilationDocumentAlt
     *
     * @return FicheTechniqueField
     */
    public function setVentilationDocumentAlt(?string $ventilationDocumentAlt): self
    {
        $this->ventilationDocument_alt = $ventilationDocumentAlt;

        return $this;
    }

    /**
     * Get ventilationDocumentAlt
     *
     * @return string
     */
    public function getVentilationDocumentAlt(): ?string
    {
        return $this->ventilationDocument_alt;
    }

    /**
     * Set auditApresTravauxDocumentUrl
     *
     * @param string $auditApresTravauxDocumentUrl
     *
     * @return FicheTechniqueField
     */
    public function setAuditApresTravauxDocumentUrl(?string $auditApresTravauxDocumentUrl): self
    {
        $this->auditApresTravauxDocument_url = $auditApresTravauxDocumentUrl;

        return $this;
    }

    /**
     * Get auditApresTravauxDocumentUrl
     *
     * @return string
     */
    public function getAuditApresTravauxDocumentUrl(): ?string
    {
        return $this->auditApresTravauxDocument_url;
    }

    /**
     * Set auditApresTravauxDocumentAlt
     *
     * @param string $auditApresTravauxDocumentAlt
     *
     * @return FicheTechniqueField
     */
    public function setAuditApresTravauxDocumentAlt(?string $auditApresTravauxDocumentAlt): self
    {
        $this->auditApresTravauxDocument_alt = $auditApresTravauxDocumentAlt;

        return $this;
    }

    /**
     * Get auditApresTravauxDocumentAlt
     *
     * @return string
     */
    public function getAuditApresTravauxDocumentAlt(): ?string
    {
        return $this->auditApresTravauxDocument_alt;
    }

    public function setDocumentUpdatedAt(?\DateTime $documentUpdatedAt): self
    {
        $this->documentUpdatedAt = $documentUpdatedAt;

        return $this;
    }

    public function getDocumentUpdatedAt(): ?\DateTime
    {
        return $this->documentUpdatedAt;
    }

    /* *****************************************************************
                FONCTIONS POUR LES DOCUMENTS TELECHARGES
    *******************************************************************/
    public function getFicheTechniqueDocument(): ?UploadedFile
    {
        return $this->ficheTechniqueDocument;
    }


    public function setFicheTechniqueDocument(?UploadedFile $ficheTechniqueDocument): void
    {
        $this->ficheTechniqueDocument = $ficheTechniqueDocument;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->ficheTechniqueDocument_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->ficheTechniqueDocument_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->ficheTechniqueDocument_url = null;
            $this->ficheTechniqueDocument_alt = null;
        }

        if ($ficheTechniqueDocument !== null) {
            $this->setDocumentUpdatedAt(new \DateTime());
            $this->ficheTechniqueDocument_url = $ficheTechniqueDocument->guessExtension();
            $this->ficheTechniqueDocument_alt = $ficheTechniqueDocument->getClientOriginalName();
        }
    }

    /* *****************************************************************
           FONCTIONS POUR LES DOCUMENTS INFILTROMETRIE TELECHARGES
    *******************************************************************/
    public function getInfiltrometrieDocument(): ?UploadedFile
    {
        return $this->infiltrometrieDocument;
    }

    public function setInfiltrometrieDocument(?UploadedFile $infiltrometrieDocument): void
    {
        $this->infiltrometrieDocument = $infiltrometrieDocument;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->infiltrometrieDocument_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->infiltrometrieDocument_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->infiltrometrieDocument_url = null;
            $this->infiltrometrieDocument_alt = null;
        }

        if ($infiltrometrieDocument !== null) {
            $this->setDocumentUpdatedAt(new \DateTime());
            $this->infiltrometrieDocument_url = $infiltrometrieDocument->guessExtension();
            $this->infiltrometrieDocument_alt = $infiltrometrieDocument->getClientOriginalName();
        }
    }

    public function getVentilationDocument(): ?UploadedFile
    {
        return $this->ventilationDocument;
    }

    public function setVentilationDocument(?UploadedFile $ventilationDocument): void
    {
        $this->ventilationDocument = $ventilationDocument;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->ventilationDocument_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->ventilationDocument_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->ventilationDocument_url = null;
            $this->ventilationDocument_alt = null;
        }

        if ($ventilationDocument !== null) {
            $this->setDocumentUpdatedAt(new \DateTime());
            $this->ventilationDocument_url = $ventilationDocument->guessExtension();
            $this->ventilationDocument_alt = $ventilationDocument->getClientOriginalName();
        }
    }

    /* *****************************************************************
           FONCTIONS POUR LES DOCUMENTS AUDIT APRES TRAVAUX TELECHARGES
    *******************************************************************/
    public function getAuditApresTravauxDocument(): ?UploadedFile
    {
        return $this->auditApresTravauxDocument;
    }

    public function setAuditApresTravauxDocument(?UploadedFile $auditApresTravauxDocument): void
    {
        $this->auditApresTravauxDocument = $auditApresTravauxDocument;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->auditApresTravauxDocument_url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->auditApresTravauxDocument_url;

            // On réinitialise les valeurs des attributs url et alt
            $this->auditApresTravauxDocument_url = null;
            $this->auditApresTravauxDocument_alt = null;
        }

        if ($auditApresTravauxDocument !== null) {
            $this->setDocumentUpdatedAt(new \DateTime());
            $this->auditApresTravauxDocument_url = $auditApresTravauxDocument->guessExtension();
            $this->auditApresTravauxDocument_alt = $auditApresTravauxDocument->getClientOriginalName();
        }
    }

    /**
     * @return string
     */
    public function ficheTechniqueDocument_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/fiche_technique';
    }

    /**
     * @return string
     */
    public function ficheTechniqueDocument_getWebPath(): string
    {
        return $this->ficheTechniqueDocument_getUploadDir() . '/' . $this->getId() . '_xml_document' . '.' . $this->getFicheTechniqueDocumentUrl();
    }

    /**
     * @return string
     */
    public function ficheTechniqueDocument_getRollbackWebPath(): string
    {
        $suffix = '.rollback.xml';
        return $this->ficheTechniqueDocument_getUploadDir() . '/' . $this->getId() . '_xml_document' . $suffix;
    }

    /**
     * @return string
     */
    public function ficheTechniqueDocument_getRollbackWebPathPrefix(): string
    {
        return $this->ficheTechniqueDocument_getUploadDir() . '/' . $this->getId() . '_xml_document';
    }

    /**
     * @return string
     */
    public function infiltrometrieDocument_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/fiche_technique';
    }

    /**
     * @return string
     */
    public function infiltrometrieDocument_getWebPath(): string
    {
        return $this->infiltrometrieDocument_getUploadDir() . '/' . $this->getId() . '_infiltrometrie_document' . '.' . $this->getInfiltrometrieDocumentUrl();
    }

    /**
     * @return string
     */
    public function ventilationDocument_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/fiche_technique';
    }

    /**
     * @return string
     */
    public function ventilationDocument_getWebPath(): string
    {
        return $this->ventilationDocument_getUploadDir() . '/' . $this->getId() . '_ventilation_document' . '.' . $this->getVentilationDocumentUrl();
    }

    /**
     * @return string
     */
    public function auditApresTravauxDocument_getUploadDir(): string
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/fiche_technique';
    }

    /**
     * @return string
     */
    public function auditApresTravauxDocument_getWebPath(): string
    {
        return $this->auditApresTravauxDocument_getUploadDir() . '/' . $this->getId() . '_audit_apres_travaux_document' . '.' . $this->getAuditApresTravauxDocumentUrl();
    }

    public function getTempFilename(): ?string
    {
        return $this->tempFilename;
    }

    public function setTempFilename(?string $tempFilename): void
    {
        $this->tempFilename = $tempFilename;
    }
}

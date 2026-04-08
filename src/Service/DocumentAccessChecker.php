<?php

namespace App\Service;


use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux;
use App\Entity\FicheTechnique;
use App\Repository\BeneficiaireRepository;
use App\Repository\Demande_Repository;
use App\Repository\Demande_auditEnergieRepository;
use App\Repository\Demande_travauxRepository;
use App\Repository\Demande_travaux_devisRepository;
use App\Repository\Demande_travaux_devis_uploadRepository;
use App\Repository\EPCI_Repository;
use App\Repository\FicheTechniqueFieldRepository;
use App\Repository\FicheTechniqueRepository;
use App\Repository\LogementRepository;
use App\Repository\Partenaire_Repository;
use App\Repository\Remboursement_Repository;
use App\Repository\Remboursement_auditEnergieRepository;
use App\Repository\Remboursement_auditEnergie_depotRepository;
use App\Repository\Remboursement_auditEnergie_instructionRepository;
use App\Repository\Remboursement_auditNumeriqueRepository;
use App\Repository\Remboursement_auditNumerique_depotRepository;
use App\Repository\Remboursement_auditNumerique_instructionRepository;
use App\Repository\Remboursement_travauxRepository;
use App\Repository\Remboursement_travaux_instructionRepository;
use App\Repository\Structure_Repository;
use App\Repository\TitreRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DocumentAccessChecker
{
    private const DEVIS_DOCUMENT_UPLOAD_TYPE = '5';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private string $appDateUsNouvelInstructeur,
        private Demande_Repository $demandeRepository,
        private Demande_auditEnergieRepository $demandeAuditEnergieRepository,
        private Demande_travauxRepository $demandeTravauxRepository,
        private BeneficiaireRepository $beneficiaireRepository,
        private Structure_Repository $structureRepository,
        private Demande_travaux_devisRepository $demandeTravauxDevisRepository,
        private Demande_travaux_devis_uploadRepository $demandeTravauxDevisUploadRepository,
        private Remboursement_Repository $remboursementRepository,
        private Remboursement_auditEnergieRepository $remboursementAuditEnergieRepository,
        private Remboursement_auditEnergie_depotRepository $remboursementAuditEnergieDepotRepository,
        private Remboursement_auditEnergie_instructionRepository $remboursementAuditEnergieInstructionRepository,
        private Remboursement_auditNumeriqueRepository $remboursementAuditNumeriqueRepository,
        private Remboursement_auditNumerique_depotRepository $remboursementAuditNumeriqueDepotRepository,
        private Remboursement_auditNumerique_instructionRepository $remboursementAuditNumeriqueInstructionRepository,
        private Remboursement_travauxRepository $remboursementTravauxRepository,
        private Remboursement_travaux_instructionRepository $remboursementTravauxInstructionRepository,
        private FicheTechniqueRepository $fichetechniqueRepository,
        private FicheTechniqueFieldRepository $fichetechniqueFieldRepository,
        private EPCI_Repository $EPCIRepository,
        private Partenaire_Repository $partenaireRepository,
        private TitreRepository $titreRepository,
        private LogementRepository $logementRepository,
    ) {}

    private function getAdminId(): int
    {
        $user = $this->tokenStorage->getToken()->getUser();
        $username = $user->getUsername();
        return (int)substr($username, 1);
    }

    private function getRoles(): array
    {
        $user = $this->tokenStorage->getToken()->getUser();
        return $user->getRoles();
    }

    /**
     * Vérifie l'accès selon typeDemande et rôles
     *
     * @param int $childId ID de l'objet enfant lié au document
     * @param int $typeDemande
     * @param int $typeFile
     *
     * @return bool
     */
    public function canAccess(int $childId, int $typeDemande, int $typeFile): bool
    {
        $adminId = $this->getAdminId();
        $roles = $this->getRoles();

        return match ($typeDemande) {
            0, 1 => $this->checkDemandeAccess($childId, $typeDemande, $adminId, $roles),
            2 => $this->checkDevisAccess($childId, $adminId, $roles, $typeFile),
            3 => $this->checkFicheTechniqueAccess($childId, $adminId, $roles),
            4, 5, 7, 8, 9, 10 => $this->checkRemboursementAccess($childId, $typeDemande, $adminId, $roles),
            6 => $this->checkPartenaireAccess($childId, $adminId, $roles),
            default => throw new AccessDeniedException("Type de document inconnu."),
        };
    }

    private function checkDemandeAccess(int $childId, int $typeDemande, int $adminId, array $roles): bool
    {
        if ($typeDemande === 0) { // Audit Energie
            $demandeAuditEnergie = $this->demandeAuditEnergieRepository->find($childId);
            $demande = $this->demandeRepository->findOneBy(['demande_auditEnergie' => $demandeAuditEnergie]);
        } else { // Travaux
            $demandeTravaux = $this->demandeTravauxRepository->find($childId);
            $demande = $this->demandeRepository->findOneBy(['demande_travaux' => $demandeTravaux]);
        }

        if (!$demande) {
            throw new AccessDeniedException('Demande introuvable.');
        }

        // ROLE_CONSEILLER
        if (in_array('ROLE_CONSEILLER', $roles, true)) {
            if ($this->isConseillerAccessDenied($adminId, $demande->getBeneficiaireId())) {
                throw new AccessDeniedException('Accès refusé.');
            }
        }

        // ROLE_AUDITEUR
        if (in_array('ROLE_AUDITEUR', $roles, true)) {
            if ($typeDemande === 0) { // Audit Energie
                $auditeurId = $demandeAuditEnergie?->getAuditeurId();
            } elseif (!empty($demandeTravaux) && $demandeTravaux->getAudit() === '1') {
                // Travaux
                $demandeTravauxDevis = $this->demandeTravauxDevisRepository->find($demandeTravaux->getTravauxDevisId());
                $auditeurId = $demandeTravauxDevis?->getAuditeurId();
            }

            if (($auditeurId ?? null) !== $adminId) {
                throw new AccessDeniedException('Accès refusé.');
            }
        }

        // ROLE_RENOVATEUR (Travaux uniquement)
        if (in_array('ROLE_RENOVATEUR', $roles, true) && $typeDemande === 1) {
            if (!empty($demandeTravaux)) {
                $demandeTravauxDevis = $this->demandeTravauxDevisRepository->find($demandeTravaux->getTravauxDevisId());
                $renovateurId = $demandeTravauxDevis?->getRenovateurId();
                if ($renovateurId !== $adminId) {
                    throw new AccessDeniedException('Accès refusé.');
                }
            }
        }

        // ROLE_EPCI
        if (in_array('ROLE_EPCI', $roles, true)) {
            $rowEPCI = $this->EPCIRepository->findByContactId($adminId);
            if (!$rowEPCI) {
                throw new AccessDeniedException('EPCI non trouvé pour cet utilisateur.');
            }
            $epciId = $rowEPCI['id'];

            if ($epciId && $demande->getLogementId()) {
                $logement = $this->logementRepository->find($demande->getLogementId());
                if (!empty($logement)) {
                    $insee = $logement->getINSEE();
                    $hasAccess = $this->EPCIRepository->checkEpciAccessByInsee($epciId, $insee);
                    if (!$hasAccess) {
                        throw new AccessDeniedException('Accès refusé.');
                    }
                }
            }
        }

        // ROLE_TECHNIQUE
        if (in_array('ROLE_TECHNIQUE', $roles, true)) {
            if (
                !$demandeTravaux
                || $demande->getStatutId() === Demande_statut::STATUS_15
                || (empty($demandeTravaux->getFicheTechnique()) && empty($demandeTravaux->getTravauxDevis()))
            ) {
                throw new AccessDeniedException('Accès refusé.');
            }
        }

        return true;
    }

    private function checkDevisAccess(int $childId, int $adminId, array $roles, int $typeFile): bool
    {
        if (self::DEVIS_DOCUMENT_UPLOAD_TYPE === (string)$typeFile) {
            $demandeTravauxDevisUpload = $this->demandeTravauxDevisUploadRepository->find($childId);
            if (!empty($demandeTravauxDevisUpload)) {
                $demandeTravauxDevis = $this->demandeTravauxDevisRepository->findByIdTravauxDevisUploadId($demandeTravauxDevisUpload->getId());
                if (empty($demandeTravauxDevis)) {
                    throw new AccessDeniedException('Devis introuvable.');
                }
            }
        } else {
            $demandeTravauxDevis = $this->demandeTravauxDevisRepository->find($childId);
            if (empty($demandeTravauxDevis)) {
                throw new AccessDeniedException('Devis introuvable.');
            }
        }

        // ROLE_CONSEILLER
        if (in_array('ROLE_CONSEILLER', $roles, true)) {
            $demandeTravaux = $this->demandeTravauxRepository->findOneBy([
                'travauxDevis_id' => $demandeTravauxDevis->getId(),
            ]);
            if (!empty($demandeTravaux)) {
                $demande = $this->demandeRepository->findOneBy([
                    'demande_travaux' => $demandeTravaux
                ]);
                if (!empty($demande)) {
                    if ($this->isConseillerAccessDenied($adminId, $demande->getBeneficiaireId())) {
                        throw new AccessDeniedException('Accès refusé.');
                    }
                }
            }
        }

        if (in_array('ROLE_AUDITEUR', $roles, true) && $demandeTravauxDevis->getAuditeurId() !== $adminId) {
            throw new AccessDeniedException('Accès refusé.');
        }
        if (in_array('ROLE_RENOVATEUR', $roles, true) && $demandeTravauxDevis->getRenovateurId() !== $adminId) {
            throw new AccessDeniedException('Accès refusé.');
        }

        return true;
    }

    private function checkFicheTechniqueAccess(int $fieldId, int $adminId, array $roles): bool
    {
        $rowFicheTechniqueField = $this->fichetechniqueFieldRepository->find($fieldId);
        if (!$rowFicheTechniqueField) {
            throw new AccessDeniedException('Fiche technique field introuvable.');
        }

        $ficheTechniqueFieldTypeSearch = match ($rowFicheTechniqueField->getType()) {
            '0 | situationInitiale' => 'ficheTechnique_initial',
            '1 | scenarioBBC' => 'ficheTechnique_BBC',
            '2 | prescriptionTravaux' => 'ficheTechnique_prescription',
            '3 | finChantier' => 'ficheTechnique_finChantier',
            default => null,
        };

        $ficheTechnique = $this->fichetechniqueRepository->findOneBy([
            $ficheTechniqueFieldTypeSearch => $fieldId,
        ]);

        $demandeTravaux = null;
        $demande = null;
        if (!empty($ficheTechnique)) {
            $resultDemandeAndDemandeTravaux = $this->findDemandeAndDemandeTravauxByFicheTechnique($ficheTechnique);
            $demandeTravaux = $resultDemandeAndDemandeTravaux['demandeTravaux'] ?? null;
            $demande = $resultDemandeAndDemandeTravaux['demande'] ?? null;
        }

        // ROLE_CONSEILLER
        if (in_array('ROLE_CONSEILLER', $roles, true)) {
            if ($demande && $this->isConseillerAccessDenied($adminId, $demande->getBeneficiaireId())) {
                throw new AccessDeniedException('Accès refusé.');
            }
        }

        // ROLE_AUDITEUR
        if (in_array('ROLE_AUDITEUR', $roles, true)) {
            if ($demandeTravaux) {
                $demandeTravauxDevis = $this->demandeTravauxDevisRepository->find($demandeTravaux->getTravauxDevisId());
                $auditeurId = $demandeTravauxDevis?->getAuditeurId();
                if ($auditeurId !== $adminId) {
                    throw new AccessDeniedException('Accès refusé.');
                }
            }
        }

        // ROLE_RENOVATEUR
        if (in_array('ROLE_RENOVATEUR', $roles, true)) {
            if ($demandeTravaux) {
                $demandeTravauxDevis = $this->demandeTravauxDevisRepository->find($demandeTravaux->getTravauxDevisId());
                $renovateurId = $demandeTravauxDevis?->getRenovateurId();
                if ($renovateurId !== $adminId) {
                    throw new AccessDeniedException('Accès refusé.');
                }
            }
        }

        return true;
    }

    private function checkRemboursementAccess(int $childId, int $typeDemande, int $adminId, array $roles): bool
    {
        $auditeurId = null;
        $renovateurId = null;
        $remboursement = null;
        $demande = null;

        switch ($typeDemande) {
            case 4: // Remboursement Audit Energie Depot
                $remboursementAuditEnergieDepot = $this->remboursementAuditEnergieDepotRepository->find($childId);
                if (!empty($remboursementAuditEnergieDepot)) {
                    $remboursementAuditEnergie = $this->remboursementAuditEnergieRepository->findOneBy([
                        'depot' => $remboursementAuditEnergieDepot,
                    ]);
                    if (!empty($remboursementAuditEnergie)) {
                        $remboursement = $this->remboursementRepository->findOneBy([
                            'remboursement_auditEnergie' => $remboursementAuditEnergie,
                        ]);
                        if (!empty($remboursement)) {
                            $demande = $this->demandeRepository->find($remboursement->getDemandeId());
                            $auditeurId = $demande?->getDemandeAuditEnergie()?->getAuditeurId();
                        }
                    }
                }
                break;
            case 5: // Remboursement Audit Energie Instruction
                $remboursementAuditEnergieInstruction = $this->remboursementAuditEnergieInstructionRepository->find($childId);
                if (!empty($remboursementAuditEnergieInstruction)) {
                    $remboursementAuditEnergie = $this->remboursementAuditEnergieRepository->findOneBy([
                        'instruction' => $remboursementAuditEnergieInstruction,
                    ]);
                    if (!empty($remboursementAuditEnergie)) {
                        $remboursement = $this->remboursementRepository->findOneBy([
                            'remboursement_auditEnergie' => $remboursementAuditEnergie,
                        ]);
                        if (!empty($remboursement)) {
                            $demande = $this->demandeRepository->find($remboursement->getDemandeId());
                            $auditeurId = $demande?->getDemandeAuditEnergie()?->getAuditeurId();
                        }
                    }
                }
                break;
            case 7: // Remboursement Audit Numérique Depot
                $remboursementAuditNumeriqueDepot = $this->remboursementAuditNumeriqueDepotRepository->find($childId);
                if (!empty($remboursementAuditNumeriqueDepot)) {
                    $remboursementAuditNumerique = $this->remboursementAuditNumeriqueRepository->findOneBy([
                        'depot' => $remboursementAuditNumeriqueDepot,
                    ]);
                    if (!empty($remboursementAuditNumerique)) {
                        $remboursement = $this->remboursementRepository->findOneBy([
                            'remboursement_auditNumerique' => $remboursementAuditNumerique,
                        ]);
                        if (!empty($remboursement)) {
                            $demande = $this->demandeRepository->find($remboursement->getDemandeId());
                            $auditeurId = $demande?->getDemandeAuditNumerique()?->getAuditeurId();
                        }
                    }
                }
                break;
            case 8: // Remboursement Audit Numérique Instruction
                $remboursementAuditNumeriqueInstruction = $this->remboursementAuditNumeriqueInstructionRepository->find($childId);
                if (!empty($remboursementAuditNumeriqueInstruction)) {
                    $remboursementAuditNumerique = $this->remboursementAuditNumeriqueRepository->findOneBy([
                        'instruction' => $remboursementAuditNumeriqueInstruction,
                    ]);
                    if (!empty($remboursementAuditNumerique)) {
                        $remboursement = $this->remboursementRepository->findOneBy([
                            'remboursement_auditNumerique' => $remboursementAuditNumerique,
                        ]);
                        if (!empty($remboursement)) {
                            $demande = $this->demandeRepository->find($remboursement->getDemandeId());
                            $auditeurId = $demande?->getDemandeAuditNumerique()?->getAuditeurId();
                        }
                    }
                }
                break;
            case 9: // Remboursement Travaux Instruction
                $remboursementTravauxInstruction = $this->remboursementTravauxInstructionRepository->find($childId);
                if (!empty($remboursementTravauxInstruction)) {
                    $remboursementTravaux = $this->remboursementTravauxRepository->findOneBy([
                        'instruction' => $remboursementTravauxInstruction,
                    ]);
                    if (!empty($remboursementTravaux)) {
                        $remboursement = $this->remboursementRepository->findOneBy([
                            'remboursement_travaux' => $remboursementTravaux,
                        ]);
                        if (!empty($remboursement)) {
                            $demande = $this->demandeRepository->find($remboursement->getDemandeId());
                            if (!empty($demande->getDemandeTravaux()) && $demande->getDemandeTravaux()->getAudit() === '1') {
                                $demandeTravauxDevis = $this->demandeTravauxDevisRepository->find(
                                    $demande->getDemandeTravaux()->getTravauxDevisId()
                                );
                                $auditeurId = $demandeTravauxDevis?->getAuditeurId();
                                $renovateurId = $demandeTravauxDevis?->getRenovateurId();
                            }
                        }
                    }
                }
                break;
            case 10: // Remboursement Travaux Instruction Conformité
                $remboursementTravauxInstruction = $this->remboursementTravauxInstructionRepository->findOneByConformiteId($childId);
                if (!empty($remboursementTravauxInstruction)) {
                    $remboursementTravaux = $this->remboursementTravauxRepository->findOneBy([
                        'instruction' => $remboursementTravauxInstruction,
                    ]);
                    if (!empty($remboursementTravaux)) {
                        $remboursement = $this->remboursementRepository->findOneBy([
                            'remboursement_travaux' => $remboursementTravaux,
                        ]);
                        if (!empty($remboursement)) {
                            $demande = $this->demandeRepository->find($remboursement->getDemandeId());
                            if (!empty($demande->getDemandeTravaux()) && $demande->getDemandeTravaux()->getAudit() === '1') {
                                $demandeTravauxDevis = $this->demandeTravauxDevisRepository->find(
                                    $demande->getDemandeTravaux()->getTravauxDevisId()
                                );
                                $auditeurId = $demandeTravauxDevis?->getAuditeurId();
                                $renovateurId = $demandeTravauxDevis?->getRenovateurId();
                            }
                        }
                    }
                }
                break;
            default:
                throw new AccessDeniedException('Type de document remboursement inconnu.');
        }

        if (!$remboursement) {
            throw new AccessDeniedException('Remboursement introuvable.');
        }

        // ROLE_CONSEILLER
        if (in_array('ROLE_CONSEILLER', $roles, true)) {
            if ($demande && $this->isConseillerAccessDenied($adminId, $demande->getBeneficiaireId())) {
                throw new AccessDeniedException('Accès refusé.');
            }
        }

        // ROLE_AUDITEUR
        if (in_array('ROLE_AUDITEUR', $roles, true)) {
            if ($auditeurId && $auditeurId !== $adminId) {
                throw new AccessDeniedException('Accès refusé.');
            }
        }

        // ROLE_RENOVATEUR
        if (in_array('ROLE_RENOVATEUR', $roles, true)) {
            if ($renovateurId && $renovateurId !== $adminId) {
                throw new AccessDeniedException('Accès refusé.');
            }
        }

        // ROLE_INSTRUCTEUR / ROLE_INSTRUCTEUR_UP
        if (in_array('ROLE_INSTRUCTEUR', $roles, true) || in_array('ROLE_INSTRUCTEUR_UP', $roles, true)) {
            $titre = $remboursement ? $this->titreRepository->find($remboursement->getTitreId()) : null;
            $dateEmission = $titre?->getDateEmission();
            if ($dateEmission) {
                $dateUsNouvelInstructeur = new \DateTime($this->appDateUsNouvelInstructeur);

                if (in_array('ROLE_INSTRUCTEUR', $roles, true) && $dateEmission < $dateUsNouvelInstructeur) {
                    throw new AccessDeniedException('Accès refusé.');
                }
                if (in_array('ROLE_INSTRUCTEUR_UP', $roles, true) && $dateEmission >= $dateUsNouvelInstructeur) {
                    throw new AccessDeniedException('Accès refusé.');
                }
            }
        }

        return true;
    }

    private function checkPartenaireAccess(int $childId, int $adminId, array $roles): bool
    {
        // ROLE_AUDITEUR
        if (in_array('ROLE_AUDITEUR', $roles, true)) {
            $partenaire = $this->partenaireRepository->findOneBy([
                'partenaire_optionAuditeur' => $childId,
                'type' => '0 | auditeur'
            ]);

            if ($partenaire && $adminId !== $partenaire->getId()) {
                throw new AccessDeniedException('Accès refusé.');
            }
        }

        return true;
    }

    private function isConseillerAccessDenied(int $adminId, int $beneficiaireId): bool
    {
        $rowStructure = $this->structureRepository->findByConseillerId($adminId);
        $structureId = $rowStructure['id'] ?? null;

        $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);
        if (!empty($beneficiaire) && $beneficiaire->getStructureRattachementId() === $structureId) {
            return false;
        }
        return true;
    }

    /**
     * @return array{demande: ?Demande_, demandeTravaux: ?Demande_travaux}
     */
    private function findDemandeAndDemandeTravauxByFicheTechnique(FicheTechnique $ficheTechnique): array
    {
        $demande = null;
        $demandeTravaux = null;

        // On check si la fiche technique appartient au Remboursement travaux
        $remboursementTravaux = $this->remboursementTravauxRepository->findOneBy([
            'ficheTechnique' => $ficheTechnique,
        ]);
        if (!empty($remboursementTravaux)) {
            $remboursement = $this->remboursementRepository->findOneBy([
                'remboursement_travaux' => $remboursementTravaux,
            ]);
            if (!empty($remboursement)) {
                $demande = $this->demandeRepository->find($remboursement->getDemandeId());
                if (!empty($demande)) {
                    $demandeTravaux = $demande->getDemandeTravaux();
                }
            }
        } else {
            // On recherche la fiche technique dans la partie demande travaux
            $demandeTravaux = $this->demandeTravauxRepository->findOneBy([
                'ficheTechnique_id' => $ficheTechnique->getId(),
            ]);
            if (!empty($demandeTravaux)) {
                $demande = $this->demandeRepository->findOneBy([
                    'demande_travaux' => $demandeTravaux,
                ]);
            }
        }

        return [
            'demande' => $demande,
            'demandeTravaux' => $demandeTravaux
        ];
    }
}

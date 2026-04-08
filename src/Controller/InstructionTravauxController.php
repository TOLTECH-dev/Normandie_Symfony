<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use App\Entity\FicheTechnique;
use App\Entity\Instruction_;
use App\Form\Instruction_Type;
use App\Service\DemandeServiceFO;
use App\Service\HistoriqueService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

#[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class InstructionTravauxController extends AbstractController
{
    private EntityManagerInterface $em;
    private DemandeServiceFO $demandeServiceFO;
    private HistoriqueService $historiqueService;

    public function __construct(
        EntityManagerInterface $em,
        DemandeServiceFO $demandeServiceFO,
        HistoriqueService $historiqueService
    ) {
        $this->em = $em;
        $this->demandeServiceFO = $demandeServiceFO;
        $this->historiqueService = $historiqueService;
    }
    public function examine(Request $request, int $demandeId): RedirectResponse|Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demandeRepository = $this->em->getRepository(Demande_::class);
        $demande = $demandeRepository->find($demandeId);

        /* *****************************************************************
                    S E C U R I T Y   D E M A N D E   (R E) E X A M I N E
        ***************************************************************** */
        $this->demandeServiceFO->checkDemandeInstructionExamineReexamine($demandeId, $demande->getType(), null);

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireRepository = $this->em->getRepository(Beneficiaire::class);
        $beneficiaire = $beneficiaireRepository->find($demande->getBeneficiaireId());

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logementRepository = $this->em->getRepository(\App\Entity\Logement::class);
        $logement = $logementRepository->find($demande->getLogementId());

        $instruction = new Instruction_();
        $form = $this->createForm(Instruction_Type::class, $instruction);
        $form->remove('instruction_auditEnergie');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            /* /////////////////////////////////////////////////////////////////
                                    GET INSTRUCTION
            ///////////////////////////////////////////////////////////////// */
            $instructionRepository = $this->em->getRepository(Instruction_::class);
            $instructionExisted = $instructionRepository->findOneBy([
                'demande_id' => $demandeId
            ]);

            if ($instructionExisted) {
                $this->addFlash(
                    'warning',
                    'L\'instruction a déjà été réalisée.'
                );

                return $this->redirectToRoute('demande_list_bo');
            }

            $instruction->setDemandeId($demandeId);
            /* /////////////////////////////////////////////////////////////////
                                    SET REASON DATA
            ///////////////////////////////////////////////////////////////// */
            $arrayJPreason = array();
            foreach ($instruction->getInstructionTravaux()->getJPreason() as $item) {
                $arrayJPreason[] = $item;
            }
            $instruction->getInstructionTravaux()->setJPreason($arrayJPreason);

            $arrayKBISreason = array();
            foreach ($instruction->getInstructionTravaux()->getKBISreason() as $item) {
                $arrayKBISreason[] = $item;
            }
            $instruction->getInstructionTravaux()->setKBISreason($arrayKBISreason);

            $arrayAIreason = array();
            foreach ($instruction->getInstructionTravaux()->getAIreason() as $item) {
                $arrayAIreason[] = $item;
            }
            $instruction->getInstructionTravaux()->setAIreason($arrayAIreason);

            /* /////////////////////////////////////////////////////////////////
                                    SET DEMANDE STATUT
            ///////////////////////////////////////////////////////////////// */
            if ($instruction) {
                $conformiteJP = explode(" | ", $instruction->getInstructionTravaux()->getJPconformite() ?? '0 | oui');
                $conformiteKBIS = explode(" | ", $instruction->getInstructionTravaux()->getKBISconformite() ?? '0 | oui');
                $conformiteAI = explode(" | ", $instruction->getInstructionTravaux()->getAIconformite() ?? '0 | oui');
            } else {
                $conformiteJP = ['0'];
                $conformiteKBIS = ['0'];
                $conformiteAI = ['0'];
                $instruction = null;
            }

            $instructionDevis = null;
            $ficheTechniqueStatut = null;
            $ficheTechniqueIsValidationConseiller = null;
            if ($demande) {
                $demandeTravaux_justificatifPropriete = $demande->getDemandeTravaux()->getJustificatifPropriete();
                $demandeTravaux_pieceComplement = $demande->getDemandeTravaux()->getPieceComplement();
                $demandeTravaux_avisImposition = $demande->getDemandeTravaux()->getAvisImposition();
                $demandeTravaux_justificatifProprieteAlt = $demande->getDemandeTravaux()->getJustificatifProprieteAlt();
                $demandeTravaux_pieceComplementAlt = $demande->getDemandeTravaux()->getPieceComplementAlt();
                $demandeTravaux_avisImpositionAlt = $demande->getDemandeTravaux()->getAvisImpositionAlt();
                $demandeTravaux_travauxDevisId = $demande->getDemandeTravaux()->getTravauxDevisId();
                $demandeTravaux_audit = $demande->getDemandeTravaux()->getAudit();
                if ($demandeTravaux_travauxDevisId) {
                    $travauxDevisRepository = $this->em->getRepository(Demande_travaux_devis::class);
                    $travauxDevis = $travauxDevisRepository->find($demandeTravaux_travauxDevisId);
                    $instructionDevis = $travauxDevis->getInstructionDossierConforme();

                    if ($demande->getDemandeTravaux()->getFicheTechniqueId()) {
                        /* /////////////////////////////////////////////////////////////////
                                                    GET  FICHE TECHNIQUE
                        ///////////////////////////////////////////////////////////////// */
                        $ficheTechniqueRepository = $this->em->getRepository(FicheTechnique::class);
                        $ficheTechnique = $ficheTechniqueRepository->find($demande->getDemandeTravaux()->getFicheTechniqueId());

                        if ($ficheTechnique) {
                            $ficheTechniqueStatut = $ficheTechnique->getStatutFicheTechnique();
                            $ficheTechniqueIsValidationConseiller = $ficheTechnique->getIsValidationConseiller();
                        }
                    }
                }
            } else {
                $demandeTravaux_justificatifPropriete = null;
                $demandeTravaux_pieceComplement = null;
                $demandeTravaux_avisImposition = null;
                $demandeTravaux_justificatifProprieteAlt = null;
                $demandeTravaux_pieceComplementAlt = null;
                $demandeTravaux_avisImpositionAlt = null;
                $demandeTravaux_travauxDevisId = null;
                $demandeTravaux_audit = null;
            }

            $statut = $this->demandeServiceFO->searchStatutForDemandeTravauxAndDevis(
                $conformiteJP[0],
                $conformiteKBIS[0],
                $conformiteAI[0],
                $demandeTravaux_justificatifPropriete,
                $demandeTravaux_pieceComplement,
                $demandeTravaux_avisImposition,
                $demandeTravaux_justificatifProprieteAlt,
                $demandeTravaux_pieceComplementAlt,
                $demandeTravaux_avisImpositionAlt,
                $instruction,
                $demandeTravaux_travauxDevisId,
                $demandeTravaux_audit,
                $ficheTechniqueStatut,
                $beneficiaire->getType(),
                $instructionDevis,
                $ficheTechniqueIsValidationConseiller
            );
            $demande->setStatutId($statut);

            $this->em->persist($demande);
            $this->em->persist($instruction);
            $this->em->flush();

            // MISE A JOUR DEMANDE STATUT DESCRIPTION
            $demande->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demande->getId()));
            $this->em->persist($demande);
            $this->em->flush();

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $type = Demande_::DEMANDE_TRAVAUX_TYPE;
            $userRoles = $this->getUser()->getRoles();
            $beneficiaireEmail = $beneficiaire->getEmail();
            $beneficiaireType = $beneficiaire->getType();

            $this->historiqueService->save(
                $demandeId,
                $statut,
                $type,
                $userRoles,
                true,
                'Instruction de Travaux',
                $beneficiaireEmail,
                $beneficiaireType,
                $demandeTravaux_justificatifProprieteAlt,
                $demandeTravaux_pieceComplementAlt,
                $demandeTravaux_avisImpositionAlt,
                false,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                ['isFromInstructionAdministrative' => true]
            );

            $this->addFlash(
                'success',
                'L\'instruction a été réalisée avec succès.'
            );

            return $this->redirectToRoute('demande_list_bo', []);
        }

        return $this->render('BackOffice/Demande/Travaux/examine.html.twig', [
            'form_instruction' => $form->createView(),
            'demande' => $demande,
            'beneficiaire' => $beneficiaire,
            'logement' => $logement,
        ]);
    }

    public function reexamine(Request $request, int $demandeId, int $instructionId): RedirectResponse|Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demandeRepository = $this->em->getRepository(Demande_::class);
        $demande = $demandeRepository->find($demandeId);

        /* *****************************************************************
                    S E C U R I T Y   D E M A N D E   (R E) E X A M I N E
        ***************************************************************** */
        $this->demandeServiceFO->checkDemandeInstructionExamineReexamine($demandeId, $demande->getType(), $instructionId);

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaireRepository = $this->em->getRepository(Beneficiaire::class);
        $beneficiaire = $beneficiaireRepository->find($demande->getBeneficiaireId());

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logementRepository = $this->em->getRepository(\App\Entity\Logement::class);
        $logement = $logementRepository->find($demande->getLogementId());

        /* /////////////////////////////////////////////////////////////////
                                GET INSTRUCTION
        ///////////////////////////////////////////////////////////////// */
        $instructionRepository = $this->em->getRepository(Instruction_::class);
        $instruction = $instructionRepository->find($instructionId);

        $form = $this->createForm(Instruction_Type::class, $instruction);

        $form->remove('instruction_auditEnergie');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $instruction->setDateModif(new \DateTime());
            $instruction->setAuteurModif($this->getUser()->getUsername());

            /* /////////////////////////////////////////////////////////////////
                                    SET REASON DATA
            ///////////////////////////////////////////////////////////////// */
            $arrayJPreason = array();
            foreach ($instruction->getInstructionTravaux()->getJPreason() as $item) {
                $arrayJPreason[] = $item;
            }
            $instruction->getInstructionTravaux()->setJPreason($arrayJPreason);

            $arrayKBISreason = array();
            foreach ($instruction->getInstructionTravaux()->getKBISreason() as $item) {
                $arrayKBISreason[] = $item;
            }
            $instruction->getInstructionTravaux()->setKBISreason($arrayKBISreason);

            $arrayAIreason = array();
            foreach ($instruction->getInstructionTravaux()->getAIreason() as $item) {
                $arrayAIreason[] = $item;
            }
            $instruction->getInstructionTravaux()->setAIreason($arrayAIreason);

            /* /////////////////////////////////////////////////////////////////
                                SET DEMANDE STATUT
            ///////////////////////////////////////////////////////////////// */
            if ($instruction) {
                $conformiteJP = explode(" | ", $instruction->getInstructionTravaux()->getJPconformite() ?? '0 | oui');
                $conformiteKBIS = explode(" | ", $instruction->getInstructionTravaux()->getKBISconformite() ?? '0 | oui');
                $conformiteAI = explode(" | ", $instruction->getInstructionTravaux()->getAIconformite() ?? '0 | oui');
            } else {
                $conformiteJP = ['0'];
                $conformiteKBIS = ['0'];
                $conformiteAI = ['0'];
                $instruction = null;
            }

            $instructionDevis = null;
            $ficheTechniqueStatut = null;
            $ficheTechniqueIsValidationConseiller = null;
            if ($demande) {
                $demandeTravaux_justificatifPropriete = $demande->getDemandeTravaux()->getJustificatifPropriete();
                $demandeTravaux_pieceComplement = $demande->getDemandeTravaux()->getPieceComplement();
                $demandeTravaux_avisImposition = $demande->getDemandeTravaux()->getAvisImposition();
                $demandeTravaux_justificatifProprieteAlt = $demande->getDemandeTravaux()->getJustificatifProprieteAlt();
                $demandeTravaux_pieceComplementAlt = $demande->getDemandeTravaux()->getPieceComplementAlt();
                $demandeTravaux_avisImpositionAlt = $demande->getDemandeTravaux()->getAvisImpositionAlt();
                $demandeTravaux_travauxDevisId = $demande->getDemandeTravaux()->getTravauxDevisId();
                $demandeTravaux_audit = $demande->getDemandeTravaux()->getAudit();
                if ($demandeTravaux_travauxDevisId) {
                    $travauxDevisRepository = $this->em->getRepository(Demande_travaux_devis::class);
                    $travauxDevis = $travauxDevisRepository->find($demandeTravaux_travauxDevisId);
                    $instructionDevis = $travauxDevis->getInstructionDossierConforme();

                    if ($demande->getDemandeTravaux()->getFicheTechniqueId()) {
                        /* /////////////////////////////////////////////////////////////////
                                                    GET  FICHE TECHNIQUE
                        ///////////////////////////////////////////////////////////////// */
                        $ficheTechniqueRepository = $this->em->getRepository(FicheTechnique::class);
                        $ficheTechnique = $ficheTechniqueRepository->find($demande->getDemandeTravaux()->getFicheTechniqueId());

                        if ($ficheTechnique) {
                            $ficheTechniqueStatut = $ficheTechnique->getStatutFicheTechnique();
                            $ficheTechniqueIsValidationConseiller = $ficheTechnique->getIsValidationConseiller();
                        }
                    }
                }
            } else {
                $demandeTravaux_justificatifPropriete = null;
                $demandeTravaux_pieceComplement = null;
                $demandeTravaux_avisImposition = null;
                $demandeTravaux_justificatifProprieteAlt = null;
                $demandeTravaux_pieceComplementAlt = null;
                $demandeTravaux_avisImpositionAlt = null;
                $demandeTravaux_travauxDevisId = null;
                $demandeTravaux_audit = null;
            }

            $statut = $this->demandeServiceFO->searchStatutForDemandeTravauxAndDevis(
                $conformiteJP[0],
                $conformiteKBIS[0],
                $conformiteAI[0],
                $demandeTravaux_justificatifPropriete,
                $demandeTravaux_pieceComplement,
                $demandeTravaux_avisImposition,
                $demandeTravaux_justificatifProprieteAlt,
                $demandeTravaux_pieceComplementAlt,
                $demandeTravaux_avisImpositionAlt,
                $instruction,
                $demandeTravaux_travauxDevisId,
                $demandeTravaux_audit,
                $ficheTechniqueStatut,
                $beneficiaire->getType(),
                $instructionDevis,
                $ficheTechniqueIsValidationConseiller
            );

            $demande->setStatutId($statut);

            $this->em->persist($demande);
            $this->em->persist($instruction);
            $this->em->flush();

            // MISE A JOUR DEMANDE STATUT DESCRIPTION
            $demande->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demande->getId()));
            $this->em->persist($demande);
            $this->em->flush();

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $type = Demande_::DEMANDE_TRAVAUX_TYPE;
            $userRoles = $this->getUser()->getRoles();
            $beneficiaireEmail = $beneficiaire->getEmail();
            $beneficiaireType = $beneficiaire->getType();

            $this->historiqueService->save(
                $demandeId,
                $statut,
                $type,
                $userRoles,
                true,
                'Instruction de Travaux',
                $beneficiaireEmail,
                $beneficiaireType,
                $demandeTravaux_justificatifProprieteAlt,
                $demandeTravaux_pieceComplementAlt,
                $demandeTravaux_avisImpositionAlt,
                false,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                ['isFromInstructionAdministrative' => true]
            );

            $request->getSession()->getFlashBag()->add(
                'success',
                'L\'instruction a été complétée avec succès.'
            );

            return $this->redirectToRoute('demande_list_bo', []);
        }

        return $this->render('BackOffice/Demande/Travaux/examine.html.twig', [
            'form_instruction' => $form->createView(),
            'demande' => $demande,
            'beneficiaire' => $beneficiaire,
            'logement' => $logement,
        ]);
    }
}

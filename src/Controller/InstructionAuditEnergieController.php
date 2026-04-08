<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Instruction_;
use App\Form\Instruction_Type;
use App\Service\DemandeServiceFO;
use App\Service\HistoriqueService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

#[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class InstructionAuditEnergieController extends AbstractController
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

    /**
     * @throws Exception
     */
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

        /* /////////////////////////////////////////////////////////////////
                                GET INSTRUCTION
        ///////////////////////////////////////////////////////////////// */
        $instruction = new Instruction_();
        $form = $this->createForm(Instruction_Type::class, $instruction);
        $form->remove('instruction_travaux');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            /* /////////////////////////////////////////////////////////////////
                                GET INSTRUCTION
            ///////////////////////////////////////////////////////////////// */
            $instructionRepository = $this->em->getRepository(Instruction_::class);
            $instructionExisted = $instructionRepository->findOneBy([
                'demande_id' => $demandeId
            ]);
            if ($instructionExisted) {
                $this->addFlash('warning', 'L\'instruction a déjà été réalisée.');
                return $this->redirectToRoute('demande_list_bo');
            }

            $instruction->setDemandeId($demandeId);

            /* /////////////////////////////////////////////////////////////////
                                    SET REASON DATA
            ///////////////////////////////////////////////////////////////// */
            // Convert Instruction_reason entities or IDs to array of IDs
            $arrayJPreason = [];
            foreach ($instruction->getInstructionAuditEnergie()->getJPreason() as $item) {
                $arrayJPreason[] = $item;
            }
            $instruction->getInstructionAuditEnergie()->setJPreason($arrayJPreason);

            $arrayKBISreason = [];
            foreach ($instruction->getInstructionAuditEnergie()->getKBISreason() as $item) {
                $arrayKBISreason[] = $item;
            }
            $instruction->getInstructionAuditEnergie()->setKBISreason($arrayKBISreason);

            $arrayAIreason = [];
            foreach ($instruction->getInstructionAuditEnergie()->getAIreason() as $item) {
                $arrayAIreason[] = $item;
            }
            $instruction->getInstructionAuditEnergie()->setAIreason($arrayAIreason);

            /* /////////////////////////////////////////////////////////////////
                                    SET DEMANDE STATUT
            ///////////////////////////////////////////////////////////////// */
            $conformiteJP = explode(" | ", $instruction->getInstructionAuditEnergie()->getJPconformite() ?? '0 | oui');
            $conformiteKBIS = explode(" | ", $instruction->getInstructionAuditEnergie()->getKBISconformite() ?? '0 | oui');
            $conformiteAI = explode(" | ", $instruction->getInstructionAuditEnergie()->getAIconformite() ?? '0 | oui');
            $documentJP = $demande->getDemandeAuditEnergie()->getJustificatifPropriete();
            $documentKBIS = $demande->getDemandeAuditEnergie()->getPieceComplement();
            $documentAI = $demande->getDemandeAuditEnergie()->getAvisImposition();
            $documentJPAlt = $demande->getDemandeAuditEnergie()->getJustificatifProprieteAlt();
            $documentKBISAlt = $demande->getDemandeAuditEnergie()->getPieceComplementAlt();
            $documentAIAlt = $demande->getDemandeAuditEnergie()->getAvisImpositionAlt();

            $statut = $this->demandeServiceFO->searchStatutForDemandeAuditEnergie(
                $conformiteJP[0],
                $conformiteKBIS[0],
                $conformiteAI[0],
                $documentJP,
                $documentKBIS,
                $documentAI,
                $documentJPAlt,
                $documentKBISAlt,
                $documentAIAlt,
                $instruction,
                $beneficiaire->getType(),
                $demande->getDemandeAuditEnergie()->getAuditeurId()
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
            $type = Demande_::DEMANDE_AUDIT_ENERGIE_TYPE;
            $userRoles = $this->getUser()->getRoles();
            $beneficiaireEmail = $beneficiaire->getEmail();
            $beneficiaireType = $beneficiaire->getType();

            $this->historiqueService->save(
                $demandeId,
                $statut,
                $demande->getType(),
                $userRoles,
                true,
                'Instruction de ' . Demande_::$demandeType[$demande->getType()],
                $beneficiaireEmail,
                $beneficiaireType,
                $documentJPAlt,
                $documentKBISAlt,
                $documentAIAlt,
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

            $this->addFlash('success', 'L\'instruction a été réalisée avec succès.');

            return $this->redirectToRoute('demande_list_bo');
        }

        return $this->render('BackOffice/Demande/AuditEnergie/examine.html.twig', [
            'form_instruction' => $form->createView(),
            'demande' => $demande,
            'beneficiaire' => $beneficiaire,
            'logement' => $logement,
            'demandeTypeLabel' => Demande_::$demandeType[$demande->getType()] ?? 'Demande',
        ]);
    }
    /**
     * @param Request $request
     * @param $demandeId
     * @param $instructionId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws \Doctrine\DBAL\DBALException
     */
    /**
     * @throws Exception
     */
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
        $form->remove('instruction_travaux');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $instruction->setDateModif(new \DateTime());
            $instruction->setAuteurModif($this->getUser()->getUsername());

            /* /////////////////////////////////////////////////////////////////
                                    SET REASON DATA
            ///////////////////////////////////////////////////////////////// */
            // Convert Instruction_reason entities or IDs to array of IDs
            $arrayJPreason = [];
            foreach ($instruction->getInstructionAuditEnergie()->getJPreason() as $item) {
                $arrayJPreason[] = $item;
            }
            $instruction->getInstructionAuditEnergie()->setJPreason($arrayJPreason);

            $arrayKBISreason = [];
            foreach ($instruction->getInstructionAuditEnergie()->getKBISreason() as $item) {
                $arrayKBISreason[] = $item;
            }
            $instruction->getInstructionAuditEnergie()->setKBISreason($arrayKBISreason);

            $arrayAIreason = [];
            foreach ($instruction->getInstructionAuditEnergie()->getAIreason() as $item) {
                $arrayAIreason[] = $item;
            }
            $instruction->getInstructionAuditEnergie()->setAIreason($arrayAIreason);

            /* /////////////////////////////////////////////////////////////////
                                    SET DEMANDE STATUT
            ///////////////////////////////////////////////////////////////// */
            $conformiteJP = explode(" | ", $instruction->getInstructionAuditEnergie()->getJPconformite() ?? '0 | oui');
            $conformiteKBIS = explode(" | ", $instruction->getInstructionAuditEnergie()->getKBISconformite() ?? '0 | oui');
            $conformiteAI = explode(" | ", $instruction->getInstructionAuditEnergie()->getAIconformite() ?? '0 | oui');
            $documentJP = $demande->getDemandeAuditEnergie()->getJustificatifPropriete();
            $documentKBIS = $demande->getDemandeAuditEnergie()->getPieceComplement();
            $documentAI = $demande->getDemandeAuditEnergie()->getAvisImposition();
            $documentJPAlt = $demande->getDemandeAuditEnergie()->getJustificatifProprieteAlt();
            $documentKBISAlt = $demande->getDemandeAuditEnergie()->getPieceComplementAlt();
            $documentAIAlt = $demande->getDemandeAuditEnergie()->getAvisImpositionAlt();

            $statut = $this->demandeServiceFO->searchStatutForDemandeAuditEnergie(
                $conformiteJP[0],
                $conformiteKBIS[0],
                $conformiteAI[0],
                $documentJP,
                $documentKBIS,
                $documentAI,
                $documentJPAlt,
                $documentKBISAlt,
                $documentAIAlt,
                $instruction,
                $beneficiaire->getType(),
                $demande->getDemandeAuditEnergie()->getAuditeurId()
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
            $type = Demande_::DEMANDE_AUDIT_ENERGIE_TYPE;
            $userRoles = $this->getUser()->getRoles();
            $beneficiaireEmail = $beneficiaire->getEmail();
            $beneficiaireType = $beneficiaire->getType();

            $this->historiqueService->save(
                $demandeId,
                $statut,
                $demande->getType(),
                $userRoles,
                true,
                'Instruction de ' . Demande_::$demandeType[$demande->getType()],
                $beneficiaireEmail,
                $beneficiaireType,
                $documentJPAlt,
                $documentKBISAlt,
                $documentAIAlt,
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

            $this->addFlash('success', 'L\'instruction a été complétée avec succès.');

            return $this->redirectToRoute('demande_list_bo');
        }

        return $this->render('BackOffice/Demande/AuditEnergie/examine.html.twig', [
            'form_instruction' => $form->createView(),
            'demande' => $demande,
            'demandeTypeLabel' => Demande_::$demandeType[$demande->getType()],
            'beneficiaire' => $beneficiaire,
            'logement' => $logement,
        ]);
    }
}

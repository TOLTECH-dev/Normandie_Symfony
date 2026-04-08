<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\Demande_travaux_devis;
use App\Entity\Logement;
use App\Entity\Remboursement_travaux;
use App\Entity\Remboursement_;
use App\Entity\Demande_;
use App\Entity\FicheTechnique;
use App\Entity\FicheTechniqueField;
use App\Form\Remboursement_Type;
use App\Repository\Remboursement_Repository;
use App\Service\RemboursementService;
use App\Service\HistoriqueService;
use App\Service\DemandeServiceBO;
use App\Service\AdminService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TitreRepository;
use App\Repository\Demande_Repository;
use App\Repository\Remboursement_travauxRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RemboursementTravauxController extends AbstractController
{
    public function __construct(
        private readonly RemboursementService            $remboursementService,
        private readonly EntityManagerInterface          $em,
        private readonly TitreRepository                 $titreRepository,
        private readonly Demande_Repository              $demandeRepository,
        private readonly Remboursement_Repository $remboursementRepository,
        private readonly HistoriqueService               $historiqueService,
        private readonly DemandeServiceBO                $demandeServiceBO,
        private readonly AdminService $adminService
    ) {
    }

    /**
     * Examine remboursement travaux - instruction
     */
    #[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_INSTRUCTEUR_UP') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function examine(Request $request, int $titreId): RedirectResponse|Response
    {
        /* ***************************************************************************
                        S E C U R I T Y   (R E) E X A M I N E
        *************************************************************************** */
        $this->remboursementService->checkExamineReexamine(Demande_::DEMANDE_TRAVAUX_TYPE, $titreId, null);

        /* *****************************************************************
                    S E C U R I T Y   R E T O U R   A R R I E R E
        ***************************************************************** */
        if (true === $request->getSession()->get($titreId.'timestamp_remboursement_travaux_instruction')) {
            return $this->redirectToRoute('remboursement_list', []);
        }

        /* /////////////////////////////////////////////////////////////////
                                    GET TITRE
        ///////////////////////////////////////////////////////////////// */
        $titre = $this->titreRepository->find($titreId);

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demande = $this->demandeRepository->find($titre->getDemandeId());

        /* /////////////////////////////////////////////////////////////////
                                    GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaire = $this->em->getRepository(Beneficiaire::class)->find($demande->getBeneficiaireId());

        /* /////////////////////////////////////////////////////////////////
                                    GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->em->getRepository(Logement::class)->find($demande->getLogementId());

        /* /////////////////////////////////////////////////////////////////
                                    GET TITRE
        ///////////////////////////////////////////////////////////////// */
        $rowTitre = $this->titreRepository->findByDemandeIdAndNumeroOperation($titreId);

        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursement = $this->remboursementRepository->findOneBy([
            'demande_id'    => $rowTitre['demandeId'],
            'titre_id'      => $rowTitre['titreId']
        ]);

        if (!$remboursement) {
            $remboursement = new Remboursement_();
            $remboursement->setDemandeId($rowTitre['demandeId']);
            $remboursement->setTitreId($rowTitre['titreId']);
        }

        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $optionDepot = [
            false,
            in_array('ROLE_AUDITEUR', $this->getUser()->getRoles() ?? [])
        ];
        $optionFicheTechnique = [
            [],
            [],
            [],
            null,
            null,
            null
        ];
        $optionTravauxInstruction = [$rowTitre['totalDevis']];

        $formOption = [
            'optionDepot'               => $optionDepot,
            'optionFicheTechnique'      => $optionFicheTechnique,
            'optionTravauxInstruction'  => $optionTravauxInstruction
        ];

        $form = $this->createForm(Remboursement_Type::class, $remboursement, [
            'trait_choices' => $formOption
        ]);
        $form->remove('remboursement_auditEnergie');
        $form->remove('remboursement_auditNumerique');
        $form->get('remboursement_travaux')->remove('ficheTechnique');

        $paramBBC1 = $this->getParameter('production_travauxNiveau_BBC1');
        if ($paramBBC1 == $titre->getNumeroOperation()) {
            $form->get('remboursement_travaux')->get('instruction')->remove('montantFacture');
            $form->get('remboursement_travaux')->get('instruction')->remove('isFactureConforme');
            $form->get('remboursement_travaux')->get('instruction')->remove('factureReason');
            $form->get('remboursement_travaux')->get('instruction')->remove('factureReasonAutre');
            $form->get('remboursement_travaux')->get('instruction')->remove('remboursement_travaux_instruction_conformite');
        } else {
            $form->get('remboursement_travaux')->get('instruction')->remove('ficheTravaux');
            $form->get('remboursement_travaux')->get('instruction')->remove('isFicheTravauxConforme');
            $form->get('remboursement_travaux')->get('instruction')->remove('ficheTravauxReason');
            $form->get('remboursement_travaux')->get('instruction')->remove('ficheTravauxReasonAutre');
        }

        $isBBC1 = false;
        if ($paramBBC1 == $rowTitre['titreNumeroOperation']) {
            $isBBC1 = true;
        }

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $instruction = $remboursement->getRemboursementTravaux()->getInstruction();

            if ($instruction) {
                // Format IBAN to persist
                $formatIBAN = preg_replace('/\s+/', '', $instruction->getIban() ?? '');
                $instruction->setIban($formatIBAN);

                // Format cheque date to persist
                $post_dateCheque = $instruction->getDateCheque();
                if ($post_dateCheque instanceof \DateTimeInterface) {
                    $convert_dateCheque = $post_dateCheque;
                } elseif (is_string($post_dateCheque)) {
                    $convert_dateCheque = \DateTime::createFromFormat('d/m/Y', $post_dateCheque);
                } else {
                    $convert_dateCheque = null;
                }
                $instruction->setDateCheque($convert_dateCheque);

                /* /////////////////////////////////////////////////////////////////
                                        SET REASON DATA
                ///////////////////////////////////////////////////////////////// */
                $arrayChequeReason = [];
                foreach ($instruction->getChequeReason() as $item) {
                    $arrayChequeReason[] = $item;
                }
                $instruction->setChequeReason($arrayChequeReason);

                if ($paramBBC1 == $titre->getNumeroOperation()) {
                    $arrayFicheTravauxReason = [];
                    foreach ($instruction->getFicheTravauxReason() as $item) {
                        $arrayFicheTravauxReason[] = $item;
                    }

                    $instruction->setFicheTravauxReason(new ArrayCollection($arrayFicheTravauxReason));
                } else {
                    $arrayFactureReason = [];
                    foreach ($instruction->getFactureReason() as $item) {
                        $arrayFactureReason[] = $item;
                    }
                    $instruction->setFactureReason($arrayFactureReason);
                }

                $arrayRibReason = [];
                foreach ($instruction->getRibReason() as $item) {
                    $arrayRibReason[] = $item;
                }
                $instruction->setRibReason($arrayRibReason);

                /* /////////////////////////////////////////////////////////////////
                                        SET REMBOURSEMENT STATUS
                ///////////////////////////////////////////////////////////////// */
                $ficheTechnique = $remboursement->getRemboursementTravaux()->getFicheTechnique();
                $informationValidationFinChantier = null;
                if ($ficheTechnique) {
                    $informationValidationFinChantier = $ficheTechnique->getFicheTechniqueFinChantier()->getInformationValidation();
                }

                $facture = null;
                $ficheTravaux = null;
                $factureAlt = null;
                $ficheTravauxAlt = null;

                if ($paramBBC1 == $titre->getNumeroOperation()) {
                    $ficheTravaux = $instruction->getFicheTravaux();
                    $ficheTravauxAlt = $instruction->getFicheTravauxAlt();
                } else {
                    $isTravauxInstructionFactureComplet = $this->remboursementService->isTravauxInstructionFactureComplet($instruction->getRemboursementTravauxInstructionConformite());
                    if ($isTravauxInstructionFactureComplet) {
                        $facture = true;
                        $factureAlt = true;
                    }
                }

                $statut = $this->remboursementService->searchStatutForRemboursementTravaux(
                    $rowTitre['demandeTravauxNiveau'],
                    $titre->getNumeroOperation(),
                    $informationValidationFinChantier,
                    $instruction,
                    $facture,
                    $ficheTravaux,
                    $ficheTravauxAlt
                );

                $remboursement->setStatutId($statut);

                // Hack issue examine() without facture document
                if (1 <= count($instruction->getRemboursementTravauxInstructionConformite())) {
                    foreach ($instruction->getRemboursementTravauxInstructionConformite() as $item) {
                        if (!$item) {
                            $instruction->removeRemboursementTravauxInstructionConformite($item);
                        } elseif (!$item->getDocument()) {
                            $instruction->removeRemboursementTravauxInstructionConformite($item);
                        }
                    }
                }

                $this->remboursementService->setDateInstruction($remboursement, $this->getUser()->getRoles() ?? []);
            }

            $this->em->persist($remboursement);
            $this->em->flush();

            // MISE A JOUR REMBOURSEMENT STATUT DESCRIPTION
            $statutDescription = $this->remboursementService->findStatutDescriptionByRemboursement(
                $remboursement->getId(),
                $this->getParameter('production_travauxNiveau_BBC1')
            );
            $remboursement->setStatutDescription($statutDescription);
            $this->em->persist($remboursement);
            $this->em->flush();

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $rowTitre['demandeId'],
                $remboursement->getStatutId(),
                $demande->getType(),
                $this->getUser()->getRoles() ?? [],
                true,
                'Remboursement - Travaux - Instruction',
                $beneficiaire->getEmail(),
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId(),
                $instruction->getRibAlt(),
                $factureAlt,
                $instruction->getRectoChequeAlt(),
                $instruction->getVersoChequeAlt(),
                $ficheTravauxAlt,
                $isBBC1,
                $titre->getDateEmission()->format('Y-m-d')
            );

            $request->getSession()->set($titreId.'timestamp_remboursement_travaux_instruction', true);
            $request->getSession()->getFlashBag()->add(
                'success',
                'L\'instruction a été réalisée avec succès.'
            );

            return $this->redirectToRoute('remboursement_list', []);
        }

        return $this->render('BackOffice/Remboursement/Travaux/examine.html.twig', [
            'form'          => $form->createView(),
            'demande'       => $demande,
            'beneficiaire'  => $beneficiaire,
            'logement'      => $logement,
            'numeroCheque'  => $rowTitre['titreNumeroCheque'],
            'totalDevis'    => $rowTitre['totalDevis'],
            'isBBC1'        => $isBBC1
        ]);
    }

    /**
     * Re-examine remboursement travaux - instruction
     */
    #[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_INSTRUCTEUR_UP') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function reexamine(Request $request, int $remboursementId): RedirectResponse|Response
    {
        /* ***************************************************************************
                        S E C U R I T Y   (R E) E X A M I N E
        *************************************************************************** */
        $this->remboursementService->checkExamineReexamine(Demande_::DEMANDE_TRAVAUX_TYPE, null, $remboursementId);

        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursement = $this->remboursementRepository->find($remboursementId);
        $instruction = $remboursement->getRemboursementTravaux()->getInstruction();
        $destinataireData = $remboursement->getRemboursementTravaux()->getInstruction()->getDestinataire();

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demande = $this->demandeRepository->find($remboursement->getDemandeId());

        /* /////////////////////////////////////////////////////////////////
                                    GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaire = $this->em->getRepository(Beneficiaire::class)->find($demande->getBeneficiaireId());

        /* /////////////////////////////////////////////////////////////////
                                    GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->em->getRepository(Logement::class)->find($demande->getLogementId());

        /* /////////////////////////////////////////////////////////////////
                                    GET TITRE
        ///////////////////////////////////////////////////////////////// */
        $rowTitre = $this->titreRepository->findByDemandeIdAndNumeroOperation(
            $remboursement->getTitreId()
        );


        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $optionDepot = [
            false,
            in_array('ROLE_AUDITEUR', $this->getUser()->getRoles() ?? []) ? true : false
        ];
        $optionFicheTechnique = [
            [],
            [],
            [],
            null,
            null,
            null
        ];
        $optionTravauxInstruction = [$rowTitre['totalDevis']];

        $formOption = [
            'optionDepot'               => $optionDepot,
            'optionFicheTechnique'      => $optionFicheTechnique,
            'optionTravauxInstruction'  => $optionTravauxInstruction
        ];

        $form = $this->createForm(Remboursement_Type::class, $remboursement, [
            'trait_choices' => $formOption
        ]);
        $form->remove('remboursement_auditEnergie');
        $form->remove('remboursement_auditNumerique');
        $form->get('remboursement_travaux')->remove('ficheTechnique');

        $paramBBC1 = $this->getParameter('production_travauxNiveau_BBC1');
        if ($paramBBC1 == $rowTitre['titreNumeroOperation']) {
            $form->get('remboursement_travaux')->get('instruction')->remove('montantFacture');
            $form->get('remboursement_travaux')->get('instruction')->remove('isFactureConforme');
            $form->get('remboursement_travaux')->get('instruction')->remove('factureReason');
            $form->get('remboursement_travaux')->get('instruction')->remove('factureReasonAutre');
            $form->get('remboursement_travaux')->get('instruction')->remove('remboursement_travaux_instruction_conformite');
        } else {
            $form->get('remboursement_travaux')->get('instruction')->remove('ficheTravaux');
            $form->get('remboursement_travaux')->get('instruction')->remove('isFicheTravauxConforme');
            $form->get('remboursement_travaux')->get('instruction')->remove('ficheTravauxReason');
            $form->get('remboursement_travaux')->get('instruction')->remove('ficheTravauxReasonAutre');
        }

        $isBBC1 = false;
        if ($paramBBC1 == $rowTitre['titreNumeroOperation']) {
            $isBBC1 = true;
        }

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $remboursement->setDateModif(new \DateTime());
            $remboursement->setAuteurModif($_SESSION['login']->getUsername());

            if ($instruction) {
                // Format IBAN to persist
                $formatIBAN = preg_replace('/\s+/', '', $instruction->getIban() ?? '');
                $instruction->setIban($formatIBAN);

                // Format cheque date to persist
                $post_dateCheque = $instruction->getDateCheque();
                if ($post_dateCheque instanceof \DateTimeInterface) {
                    $convert_dateCheque = $post_dateCheque;
                } elseif (is_string($post_dateCheque)) {
                    $convert_dateCheque = \DateTime::createFromFormat('d/m/Y', $post_dateCheque);
                } else {
                    $convert_dateCheque = null;
                }
                $instruction->setDateCheque($convert_dateCheque);

                $destinatairePost = $instruction->getDestinataire();
                $instruction_rib = $instruction->getRib();
                if (
                    !$instruction_rib
                    && $destinataireData != $destinatairePost
                ) {
                    $instruction->setRibUrl(null);
                    $instruction->setRibAlt(null);
                }

                /* /////////////////////////////////////////////////////////////////
                                        SET REASON DATA
                ///////////////////////////////////////////////////////////////// */
                $arrayChequeReason = [];
                foreach ($instruction->getChequeReason() as $item) {
                    $arrayChequeReason[] = $item;
                }
                $instruction->setChequeReason($arrayChequeReason);

                if ($paramBBC1 == $rowTitre['titreNumeroOperation']) {
                    $arrayFicheTravauxReason = [];
                    foreach ($instruction->getFicheTravauxReason() as $item) {
                        $arrayFicheTravauxReason[] = $item;
                    }
                    $instruction->setFicheTravauxReason(new ArrayCollection($arrayFicheTravauxReason));
                } else {
                    $arrayFactureReason = [];
                    foreach ($instruction->getFactureReason() as $item) {
                        $arrayFactureReason[] = $item;
                    }
                    $instruction->setFactureReason($arrayFactureReason);
                }

                $arrayRibReason = [];
                foreach ($instruction->getRibReason() as $item) {
                    $arrayRibReason[] = $item;
                }
                $instruction->setRibReason($arrayRibReason);

                /* /////////////////////////////////////////////////////////////////
                                        SET REMBOURSEMENT STATUS
                ///////////////////////////////////////////////////////////////// */
                $ficheTechnique = $remboursement->getRemboursementTravaux()->getFicheTechnique();
                $informationValidationFinChantier = null;
                if ($ficheTechnique) {
                    $informationValidationFinChantier = $ficheTechnique->getFicheTechniqueFinChantier()->getInformationValidation();
                }

                $facture = null;
                $ficheTravaux = null;
                $factureAlt = null;
                $ficheTravauxAlt = null;

                if ($paramBBC1 == $rowTitre['titreNumeroOperation']) {
                    $ficheTravaux = $instruction->getFicheTravaux();
                    $ficheTravauxAlt = $instruction->getFicheTravauxAlt();
                } else {
                    $isTravauxInstructionFactureComplet = $this->remboursementService->isTravauxInstructionFactureComplet($instruction->getRemboursementTravauxInstructionConformite());
                    if ($isTravauxInstructionFactureComplet) {
                        $facture = true;
                        $factureAlt = true;
                    }
                }

                $statut = $this->remboursementService->searchStatutForRemboursementTravaux(
                    $rowTitre['demandeTravauxNiveau'],
                    $rowTitre['titreNumeroOperation'],
                    $informationValidationFinChantier,
                    $instruction,
                    $facture,
                    $ficheTravaux,
                    $ficheTravauxAlt
                );
                $remboursement->setStatutId($statut);

                // Hack issue re-examine() without facture document
                if (1 <= count($instruction->getRemboursementTravauxInstructionConformite())) {
                    foreach ($instruction->getRemboursementTravauxInstructionConformite() as $item) {
                        if (!$item) {
                            $instruction->removeRemboursementTravauxInstructionConformite($item);
                        } elseif (!$item->getDocument() && !$item->getDocumentUrl() && !$item->getDocumentAlt()) {
                            $instruction->removeRemboursementTravauxInstructionConformite($item);
                        }
                    }
                }

                $this->remboursementService->setDateInstruction($remboursement, $this->getUser()->getRoles() ?? []);
            }

            $this->em->persist($remboursement);
            $this->em->flush();

            // MISE A JOUR REMBOURSEMENT STATUT DESCRIPTION
            $statutDescription = $this->remboursementService->findStatutDescriptionByRemboursement(
                $remboursement->getId(),
                $this->getParameter('production_travauxNiveau_BBC1')
            );
            $remboursement->setStatutDescription($statutDescription);
            $this->em->persist($remboursement);
            $this->em->flush();

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $remboursement->getDemandeId(),
                $remboursement->getStatutId(),
                $demande->getType(),
                $this->getUser()->getRoles() ?? [],
                true,
                'Remboursement - Travaux - Instruction',
                $beneficiaire->getEmail(),
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId(),
                $instruction->getRibAlt(),
                $factureAlt,
                $instruction->getRectoChequeAlt(),
                $instruction->getVersoChequeAlt(),
                $ficheTravauxAlt,
                $isBBC1,
                $rowTitre['titreDateEmission']
            );

            $request->getSession()->getFlashBag()->add(
                'success',
                'L\'instruction a été complétée avec succès.'
            );

            return $this->redirectToRoute('remboursement_list', []);
        }

        return $this->render('BackOffice/Remboursement/Travaux/examine.html.twig', [
            'form'          => $form->createView(),
            'demande'       => $demande,
            'beneficiaire'  => $beneficiaire,
            'logement'      => $logement,
            'numeroCheque'  => $rowTitre['titreNumeroCheque'],
            'totalDevis'    => $rowTitre['totalDevis'],
            'isBBC1'        => $isBBC1,
            'remboursement' => $remboursement
        ]);
    }

    /**
     * Examine fiche technique remboursement travaux
     */
    #[Security("is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function examineFicheTechnique(Request $request, int $titreId): RedirectResponse|Response
    {
        /* ***************************************************************************
                        S E C U R I T Y   F I C H E   T E C H N I Q U E
        *************************************************************************** */
        $this->remboursementService->checkFicheTechnique(
            Demande_::DEMANDE_TRAVAUX_TYPE,
            $this->getParameter('production_travauxNiveau_BBC2'),
            $titreId,
            null
        );

        /* *****************************************************************
                    S E C U R I T Y   R E T O U R   A R R I E R E
        ***************************************************************** */
        if (true === $request->getSession()->get($titreId.'timestamp_remboursement_travaux_ficheTechnique')) {
            return $this->redirectToRoute('remboursement_list', []);
        }

        /* /////////////////////////////////////////////////////////////////
                                    GET TITRE
        ///////////////////////////////////////////////////////////////// */
        $titre = $this->titreRepository->find($titreId);

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demandeId = $titre->getDemandeId();
        $demande = $this->demandeRepository->find($demandeId);

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $option = [
            'roles' => $this->getUser()->getRoles() ?? [],
            'username' => $this->getUser()->getUsername()
        ];
        $this->demandeServiceBO->checkAccesByRole($demande, $option);

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        $demandeTravaux = $demande->getDemandeTravaux();

        $devis = null;
        if ($demandeTravaux) {
            /* /////////////////////////////////////////////////////////////////
                                        GET DEVIS
            ///////////////////////////////////////////////////////////////// */
            $repo_devis = $this->em->getRepository(Demande_travaux_devis::class);
            $devis = $repo_devis->find($demandeTravaux->getTravauxDevisId());

            /* *****************************************************************
                                    S E C U R I T Y
            ***************************************************************** */
            if (!$this->adminService->isGranted('ROLE_CLIENT', $this->getUser()) && !$this->adminService->isGranted('ROLE_ADMIN', $this->getUser())) {
                $userId_session = $_SESSION['login']->getUsername();
                $format_userId_session = substr($userId_session, 1);

                $userId_admin = -1;
                $user_id_current = -2;
                if ($this->adminService->isGranted('ROLE_RENOVATEUR', $this->getUser())) {
                    $user_id_current = (int)$format_userId_session;
                    $userId_admin = $devis->getRenovateurId();
                }

                $this->adminService->checkAdmin($user_id_current, $userId_admin);
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursement = $this->remboursementRepository->findOneBy([
            'demande_id'    => $demandeId,
            'titre_id'      => $titreId
        ], [
            'id' => 'DESC'
        ]);

        if (!$remboursement) {
            $remboursement = new Remboursement_();
            $remboursement->setDemandeId($demandeId);
            $remboursement->setTitreId($titreId);
        }

        /* /////////////////////////////////////////////////////////////////
                            GET DEMANDE TRAVAUX FICHE TECHNIQUE
        ///////////////////////////////////////////////////////////////// */
        $ficheTechniqueDemandeTravaux = $this->em->getRepository(FicheTechnique::class)->find($demandeTravaux->getFicheTechniqueId());

        $varsNotTreat = [
            'id',
            'ficheTechniqueDocument_url',
            'ficheTechniqueDocument_alt',
            'ficheTechniqueDocument',
            'tempFilename'
        ];

        $metadata = $this->em->getClassMetadata(get_class($ficheTechniqueDemandeTravaux->getFicheTechniqueInitial()));
        $ficheTechniqueFieldNames = $metadata->getFieldNames();

        $ficheTechnique = new FicheTechnique();

        $ficheTechniqueInitialDemandeTravaux = $ficheTechniqueDemandeTravaux->getFicheTechniqueInitial();
        $ficheTechniqueInitial = new FicheTechniqueField();
        if ($ficheTechniqueInitialDemandeTravaux) {
            foreach ($ficheTechniqueFieldNames as $value) {
                $propertyForMethod = str_replace('_', '', $value);

                if (!in_array($value, $varsNotTreat)) {
                    $propertyVal = call_user_func([$ficheTechniqueInitialDemandeTravaux, 'get' . ucfirst($propertyForMethod)]);
                    call_user_func_array([$ficheTechniqueInitial, 'set' . ucfirst(strtolower($propertyForMethod))], [$propertyVal]);
                }
            }
        }
        $ficheTechnique->setFicheTechniqueInitial($ficheTechniqueInitial);

        $ficheTechniqueBBCDemandeTravaux = $ficheTechniqueDemandeTravaux->getFicheTechniqueBBC();
        $ficheTechniqueBBC = new FicheTechniqueField();
        if ($ficheTechniqueBBCDemandeTravaux) {
            foreach ($ficheTechniqueFieldNames as $value) {
                $propertyForMethod = str_replace('_', '', $value);

                if (!in_array($value, $varsNotTreat)) {
                    $propertyVal = call_user_func([$ficheTechniqueBBCDemandeTravaux, 'get' . ucfirst($propertyForMethod)]);
                    call_user_func_array([$ficheTechniqueBBC, 'set' . ucfirst(strtolower($propertyForMethod))], [$propertyVal]);
                }
            }
        }
        $ficheTechnique->setFicheTechniqueBBC($ficheTechniqueBBC);

        $ficheTechniquePrescriptionDemandeTravaux = $ficheTechniqueDemandeTravaux->getFicheTechniquePrescription();
        $ficheTechniquePrescription = new FicheTechniqueField();
        if ($ficheTechniquePrescriptionDemandeTravaux) {
            foreach ($ficheTechniqueFieldNames as $value) {
                $propertyForMethod = str_replace('_', '', $value);

                if (!in_array($value, $varsNotTreat)) {
                    $propertyVal = call_user_func([$ficheTechniquePrescriptionDemandeTravaux, 'get' . ucfirst($propertyForMethod)]);
                    call_user_func_array([$ficheTechniquePrescription, 'set' . ucfirst(strtolower($propertyForMethod))], [$propertyVal]);
                }
            }
        }
        $ficheTechnique->setFicheTechniquePrescription($ficheTechniquePrescription);

        $ficheTechniqueFinChantier = new FicheTechniqueField();
        $ficheTechnique->setFicheTechniqueFinChantier($ficheTechniqueFinChantier);

        if (!$remboursement->getRemboursementTravaux()) {
            $remboursement->setRemboursementTravaux(new Remboursement_travaux());
        }
        $remboursement->getRemboursementTravaux()->setFicheTechnique($ficheTechnique);

        /* /////////////////////////////////////////////////////////////////
                        GET DATA FICHE TECHNIQUE FORM FIELD PATHOLOGIE
        ///////////////////////////////////////////////////////////////// */
        $pathologie = $this->em->getRepository(FicheTechnique::class)->search('pathologie');
        $array_pathologie = [];
        foreach ($pathologie as $item) {
            $array_pathologie[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                        GET DATA FICHE TECHNIQUE FORM FIELD ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $energie = $this->em->getRepository(FicheTechnique::class)->search('energie');
        $array_energie = [];
        foreach ($energie as $item) {
            $array_energie[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                        GET DATA FICHE TECHNIQUE FORM FIELD VENTILATION
        ///////////////////////////////////////////////////////////////// */
        $ventilation = $this->em->getRepository(FicheTechnique::class)->search('ventilation');
        $array_ventilation = [];
        foreach ($ventilation as $item) {
            $array_ventilation[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $isReexamineFicheTechnique = false;
        $optionDepot = [
            false,
            in_array('ROLE_AUDITEUR', $this->getUser()->getRoles() ?? []) ? true : false
        ];
        $optionFicheTechnique = [
            $array_pathologie,
            $array_energie,
            $array_ventilation,
            $isReexamineFicheTechnique,
            null,
            FicheTechnique::EXAMINE_FICHE_TECHNIQUE_PART_REMBOURSEMENT
        ];
        $optionTravauxInstruction = [null];

        $formOption = [
            'optionDepot'               => $optionDepot,
            'optionFicheTechnique'      => $optionFicheTechnique,
            'optionTravauxInstruction'  => $optionTravauxInstruction
        ];

        $form = $this->createForm(Remboursement_Type::class, $remboursement, [
            'trait_choices' => $formOption
        ]);
        $form->remove('remboursement_auditNumerique');
        $form->remove('remboursement_auditEnergie');
        $form->get('remboursement_travaux')->remove('instruction');
        $form->get('remboursement_travaux')->get('ficheTechnique')->remove('valider');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $ficheTechnique = $remboursement->getRemboursementTravaux()->getFicheTechnique();

            /* /////////////////////////////////////////////////////////////////
                                    SET STATUT FICHE TECHNIQUE
            ///////////////////////////////////////////////////////////////// */
            if ($ficheTechnique->getFicheTechniqueFinChantier()->getInformationValidation()) {
                $ficheTechnique->setStatutFicheTechnique('1');
            } else {
                $ficheTechnique->setStatutFicheTechnique('0');
            }

            /* /////////////////////////////////////////////////////////////////
                                    SET REMBOURSEMENT STATUS
            ///////////////////////////////////////////////////////////////// */
            $informationValidationFinChantier = null;
            if ($ficheTechnique) {
                $informationValidationFinChantier = $ficheTechnique->getFicheTechniqueFinChantier()->getInformationValidation();
            }

            $instruction = ($remboursement->getRemboursementTravaux() && $remboursement->getRemboursementTravaux()->getInstruction()) ? $remboursement->getRemboursementTravaux()->getInstruction() : null;
            $facture = ($instruction) ? $this->remboursementService->isTravauxInstructionFactureComplet($instruction->getRemboursementTravauxInstructionConformite()) : false;

            // ficheTravaux only for BBC1 / fiche technique : Chèque Travaux II rénov ou III chèque n°2 donc accès champ collection Facture
            $statut = $this->remboursementService->searchStatutForRemboursementTravaux(
                $devis->getNiveau(),
                null,
                $informationValidationFinChantier,
                $instruction,
                $facture,
                null,
                null
            );
            $remboursement->setStatutId($statut);

            $this->em->persist($remboursement);
            $this->em->flush();

            // MISE A JOUR REMBOURSEMENT STATUT DESCRIPTION
            $statutDescription = $this->remboursementService->findStatutDescriptionByRemboursement(
                $remboursement->getId(),
                $this->getParameter('production_travauxNiveau_BBC1')
            );
            $remboursement->setStatutDescription($statutDescription);
            $this->em->persist($remboursement);
            $this->em->flush();

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $demandeId,
                $remboursement->getStatutId(),
                $demande->getType(),
                $this->getUser()->getRoles() ?? [],
                true,
                'Remboursement - Travaux - Instruction de la fiche technique',
                null,
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId()
            );

            $request->getSession()->set($titreId.'timestamp_remboursement_travaux_ficheTechnique', true);
            $request->getSession()->getFlashBag()->add(
                'success',
                'Votre Fiche Technique a bien été prise en compte.'
            );

            return $this->redirectToRoute('remboursement_list', []);
        }

        return $this->render('BackOffice/Remboursement/Travaux/examineFicheTechnique.html.twig', [
            'form'                  => $form->createView(),
            'demandeId'             => $demandeId,
            'demandeTravauxAudit'   => $demandeTravaux->getAudit()
        ]);
    }

    /**
     * Re-examine fiche technique remboursement travaux
     */
    #[Security("is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function reexamineFicheTechnique(Request $request, int $remboursementId): RedirectResponse|Response
    {
        /* ***************************************************************************
                        S E C U R I T Y   F I C H E   T E C H N I Q U E
        *************************************************************************** */
        $this->remboursementService->checkFicheTechnique(
            Demande_::DEMANDE_TRAVAUX_TYPE,
            $this->getParameter('production_travauxNiveau_BBC2'),
            null,
            $remboursementId
        );

        $option = [
            'roles'     => $this->getUser()->getRoles() ?? [],
            'username'  => $this->getUser()->getUsername()
        ];

        /* /////////////////////////////////////////////////////////////////
                                GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursement = $this->remboursementRepository->find($remboursementId);

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE TRAVAUX
        ///////////////////////////////////////////////////////////////// */
        $demande = $this->demandeRepository->find($remboursement->getDemandeId());
        $demandeTravaux = $demande->getDemandeTravaux();

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesByRole($demande, $option);

        $devis = null;
        if ($demandeTravaux) {
            /* /////////////////////////////////////////////////////////////////
                                        GET DEVIS
            ///////////////////////////////////////////////////////////////// */
            $repo_devis = $this->em->getRepository(Demande_travaux_devis::class);
            $devis = $repo_devis->find($demandeTravaux->getTravauxDevisId());

            /* *****************************************************************
                                    S E C U R I T Y
            ***************************************************************** */
            if (!$this->adminService->isGranted('ROLE_CLIENT', $this->getUser()) && !$this->adminService->isGranted('ROLE_ADMIN', $this->getUser())) {
                $userId_session = $_SESSION['login']->getUsername();
                $format_userId_session = substr($userId_session, 1);

                $userId_admin = -1;
                $user_id_current = -2;
                if ($this->adminService->isGranted('ROLE_RENOVATEUR', $this->getUser())) {
                    $user_id_current = (int)$format_userId_session;
                    $userId_admin = $devis->getRenovateurId();
                }

                $this->adminService->checkAdmin($user_id_current, $userId_admin);
            }
        }

        /* /////////////////////////////////////////////////////////////////
                    GET DATA FICHE TECHNIQUE FORM FIELD PATHOLOGIE
        ///////////////////////////////////////////////////////////////// */
        $pathologie = $this->em->getRepository(FicheTechnique::class)->search('pathologie');
        $array_pathologie = [];
        foreach ($pathologie as $item) {
            $array_pathologie[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                    GET DATA FICHE TECHNIQUE FORM FIELD ENERGIE
        ///////////////////////////////////////////////////////////////// */
        $energie = $this->em->getRepository(FicheTechnique::class)->search('energie');
        $array_energie = [];
        foreach ($energie as $item) {
            $array_energie[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                    GET DATA FICHE TECHNIQUE FORM FIELD VENTILATION
        ///////////////////////////////////////////////////////////////// */
        $ventilation = $this->em->getRepository(FicheTechnique::class)->search('ventilation');
        $array_ventilation = [];
        foreach ($ventilation as $item) {
            $array_ventilation[$item['slug']] = $item['id'] . ' | ' . $item['libelle'];
        }

        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $optionDepot = [
            false,
            in_array('ROLE_AUDITEUR', $this->getUser()->getRoles() ?? [])
        ];
        $isReexamineFicheTechnique = true;
        $optionFicheTechnique = [
            $array_pathologie,
            $array_energie,
            $array_ventilation,
            $isReexamineFicheTechnique,
            null,
            FicheTechnique::EXAMINE_FICHE_TECHNIQUE_PART_REMBOURSEMENT
        ];
        $optionTravauxInstruction = [null];

        $formOption = [
            'optionDepot'               => $optionDepot,
            'optionFicheTechnique'      => $optionFicheTechnique,
            'optionTravauxInstruction'  => $optionTravauxInstruction
        ];

        $form = $this->createForm(Remboursement_Type::class, $remboursement, [
            'trait_choices' => $formOption
        ]);
        $form->remove('remboursement_auditNumerique');
        $form->remove('remboursement_auditEnergie');
        $form->get('remboursement_travaux')->remove('instruction');
        $form->get('remboursement_travaux')->get('ficheTechnique')->remove('valider');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $remboursement->setDateModif(new \DateTime());
            $remboursement->setAuteurModif($_SESSION['login']->getUsername());

            /* /////////////////////////////////////////////////////////////////
                                    SET STATUT FICHE TECHNIQUE
            ///////////////////////////////////////////////////////////////// */
            $ficheTechnique = $remboursement->getRemboursementTravaux()->getFicheTechnique();
            if ($ficheTechnique->getFicheTechniqueFinChantier()->getInformationValidation()) {
                $ficheTechnique->setStatutFicheTechnique('1');
            } else {
                $ficheTechnique->setStatutFicheTechnique('0');
            }

            /* /////////////////////////////////////////////////////////////////
                                    SET REMBOURSEMENT STATUS
            ///////////////////////////////////////////////////////////////// */
            $informationValidationFinChantier = null;
            if ($ficheTechnique) {
                $informationValidationFinChantier = $ficheTechnique->getFicheTechniqueFinChantier()->getInformationValidation();
            }

            $instruction = ($remboursement->getRemboursementTravaux() && $remboursement->getRemboursementTravaux()->getInstruction()) ? $remboursement->getRemboursementTravaux()->getInstruction() : null;
            $facture = ($instruction) ? $this->remboursementService->isTravauxInstructionFactureComplet($instruction->getRemboursementTravauxInstructionConformite()) : false;

            $statut = $this->remboursementService->searchStatutForRemboursementTravaux(
                $devis->getNiveau(),
                null,
                $informationValidationFinChantier,
                $instruction,
                $facture,
                null,
                null
            );
            $remboursement->setStatutId($statut);

            $this->em->persist($remboursement);
            $this->em->flush();

            // MISE A JOUR REMBOURSEMENT STATUT DESCRIPTION
            $statutDescription = $this->remboursementService->findStatutDescriptionByRemboursement(
                $remboursement->getId(),
                $this->getParameter('production_travauxNiveau_BBC1')
            );
            $remboursement->setStatutDescription($statutDescription);
            $this->em->persist($remboursement);
            $this->em->flush();

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $remboursement->getDemandeId(),
                $remboursement->getStatutId(),
                $demande->getType(),
                $this->getUser()->getRoles() ?? [],
                true,
                'Remboursement - Travaux - Instruction de la fiche technique',
                null,
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId()
            );

            $request->getSession()->getFlashBag()->add(
                'success',
                'Votre Fiche Technique a bien été modifiée.'
            );

            return $this->redirectToRoute('remboursement_list', []);
        }

        return $this->render('BackOffice/Remboursement/Travaux/examineFicheTechnique.html.twig', [
            'form'                  => $form->createView(),
            'remboursement'         => $remboursement,
            'demandeTravauxAudit'   => $demandeTravaux->getAudit()
        ]);
    }
}

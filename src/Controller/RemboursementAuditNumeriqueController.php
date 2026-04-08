<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\Logement;
use App\Entity\Remboursement_;
use App\Entity\Titre;
use App\Entity\Demande_;
use App\Form\Remboursement_Type;
use App\Repository\Remboursement_Repository;
use App\Service\RemboursementService;
use App\Service\HistoriqueService;
use App\Service\DemandeServiceBO;
use App\Service\AdminService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TitreRepository;
use App\Repository\Demande_Repository;
use App\Repository\Partenaire_Repository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RemboursementAuditNumeriqueController extends AbstractController
{
    public function __construct(
        private RemboursementService $remboursementService,
        private EntityManagerInterface $em,
        private TitreRepository $titreRepository,
        private Demande_Repository $demandeRepository,
        private Remboursement_Repository $remboursementRepository,
        private Partenaire_Repository $partenaireRepository,
        private ParameterBagInterface $params,
        private HistoriqueService $historiqueService,
        private DemandeServiceBO $demandeServiceBO,
        private AdminService $adminService
    ) {
    }

    /**
     * @param Request $request
     * @param $titreId
     * @return Response
     */
    #[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_INSTRUCTEUR_UP') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function examine(Request $request, int $titreId): RedirectResponse|Response
    {
        /* /////////////////////////////////////////////////////////////////
                                    GET TITRE
        ///////////////////////////////////////////////////////////////// */
        $titre = $this->titreRepository->find($titreId);

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demande = $this->demandeRepository->find($titre->getDemandeId());

        /* ***************************************************************************
                        S E C U R I T Y   (R E) E X A M I N E
        *************************************************************************** */
        $this->remboursementService->checkExamineReexamine($demande->getType(), $titreId, null);

        /* *****************************************************************
                    S E C U R I T Y   R E T O U R   A R R I E R E
        ***************************************************************** */
        if (true == $request->getSession()->get($titreId.'timestamp_remboursement_instruction')) {
            return $this->redirectToRoute('remboursement_list', array());
        }

        /* /////////////////////////////////////////////////////////////////
                                    GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo_beneficiaire = $this->em->getRepository(Beneficiaire::class);
        $beneficiaire = $repo_beneficiaire->find($demande->getBeneficiaireId());

        /* /////////////////////////////////////////////////////////////////
                                    GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $repo_logement = $this->em->getRepository(Logement::class);
        $logement = $repo_logement->find($demande->getLogementId());

        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursement = $this->remboursementRepository->findOneBy([
            'demande_id' => $titre->getDemandeId()
        ]);

        /* /////////////////////////////////////////////////////////////////
                                    GET TITRE
        ///////////////////////////////////////////////////////////////// */
        if (!$remboursement) {
            $remboursement = new Remboursement_();
            $remboursement->setDemandeId($titre->getDemandeId());
            $remboursement->setTitreId($titre->getId());
        } else {
            $titre = $this->titreRepository->find($remboursement->getTitreId());
        }

        /* /////////////////////////////////////////////////////////////////
                                    GET PARTENAIRE
        ///////////////////////////////////////////////////////////////// */
        $auditeur = $this->partenaireRepository->find($demande->getDemandeAuditNumerique()->getAuditeurId());
        $optionAuditeur = $auditeur->getPartenaireOptionAuditeur();

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
            null
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
        $form->remove('remboursement_auditEnergie');
        $form->remove('remboursement_travaux');
        $form->get('remboursement_auditNumerique')->remove('depot');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $instruction = $remboursement->getRemboursementAuditNumerique()->getInstruction();

            if ($instruction) {
                // Format IBAN no space
                $IBANNoSpace = preg_replace('/\s+/', '', $instruction->getIban() ?? '');
                $instruction->setIban($IBANNoSpace);

                /* /////////////////////////////////////////////////////////////////
                                        SET REASON DATA
                ///////////////////////////////////////////////////////////////// */
                $arrayChequeReason = [];
                foreach ($instruction->getChequeReason() as $item) {
                    $arrayChequeReason[] = $item;
                }
                $instruction->setChequeReason($arrayChequeReason);

                $arrayFactureReason = [];
                foreach ($instruction->getFactureReason() as $item) {
                    $arrayFactureReason[] = $item;
                }
                $instruction->setFactureReason($arrayFactureReason);

                $arrayRibReason = [];
                foreach ($instruction->getRibReason() as $item) {
                    $arrayRibReason[] = $item;
                }
                $instruction->setRibReason($arrayRibReason);

                /* /////////////////////////////////////////////////////////////////
                                        SET REMBOURSEMENT STATUS
                ///////////////////////////////////////////////////////////////// */
                $depot = $remboursement->getRemboursementAuditNumerique()->getDepot();
                $documentAudit = null;
                $documentAuditAlt = null;
                if ($depot) {
                    $documentAudit = $depot->getAudit();
                    $documentAuditAlt = $depot->getAuditAlt();
                }

                $statut = $this->remboursementService->searchStatutForRemboursementAuditNumerique(
                    $documentAudit,
                    $documentAuditAlt,
                    $instruction,
                    $optionAuditeur
                );
                $remboursement->setStatutId($statut);

                $this->remboursementService->setDateInstruction($remboursement, $this->getUser()->getRoles() ?? []);

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
                                    COPY UPLOAD FILE - RIB AUDITEUR
                ///////////////////////////////////////////////////////////////// */
                $destinataire = $instruction->getDestinataire();
                $destinataireArray = [];
                if ($destinataire) {
                    $destinataireArray = explode(' | ', $destinataire);
                }

                $ribAlt = $instruction->getRibAlt();
                if ('0' == $destinataireArray[0] ?? null) {
                    $projectDir = $this->getParameter('app_root_dossier_data_symfony');

                    // Update Auditeur RIB
                    if ($instruction->getRib()) {
                        $optionAuditeur->setRibUrl($instruction->getRibUrl());
                        $optionAuditeur->setRibAlt($instruction->getRibAlt());

                        // Copy file from REMBOURSEMENT to AUDITEUR
                        $auditeur_RIB_path = $projectDir . $optionAuditeur->rib_getUploadDir();
                        if (!file_exists($auditeur_RIB_path)) mkdir($auditeur_RIB_path, 0755, true);

                        copy(
                            $projectDir . $instruction->rib_getWebPath(),
                            $projectDir . $optionAuditeur->rib_getWebPath()
                        );
                    }

                    $ibanPost = trim($instruction->getIban() ?? '');
                    $bicPost = trim($instruction->getBic() ?? '');
                    $domiciliationBancairePost = trim($instruction->getDomiciliationBancaire() ?? '');

                    // Update Auditeur data
                    if (($ibanPost && ($ibanPost != $optionAuditeur->getIban()))
                        || ($bicPost && ($bicPost != $optionAuditeur->getBic()))
                        || ($domiciliationBancairePost && ($domiciliationBancairePost != $optionAuditeur->getDomicileBancaire()))
                    ) {
                        $optionAuditeur->setIban($ibanPost);
                        $optionAuditeur->setBic($bicPost);
                        $optionAuditeur->setDomicileBancaire($domiciliationBancairePost);
                    }

                    $this->em->persist($optionAuditeur);
                    $this->em->flush();

                    $ribAlt = $optionAuditeur->getRibAlt();

                    // Remove file RIB
                    $remboursement_RIB = $projectDir . $instruction->rib_getWebPath();
                    if (file_exists($remboursement_RIB)) unlink($remboursement_RIB);
                }

                /* /////////////////////////////////////////////////////////////////
                                        FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                $this->historiqueService->save(
                    $titre->getDemandeId(),
                    $remboursement->getStatutId(),
                    $demande->getType(),
                    $this->getUser()->getRoles() ?? [],
                    true,
                    'Remboursement - ' . (Demande_::$demandeType[$demande->getType()] ?? ''),
                    null,
                    null,
                    null,
                    null,
                    null,
                    false,
                    $remboursement->getId(),
                    $ribAlt,
                    $instruction->getFactureAlt(),
                    $instruction->getRectoChequeAlt(),
                    $instruction->getVersoChequeAlt(),
                    null,
                    null,
                    $titre->getDateEmission()->format('Y-m-d')
                );
            }

            $request->getSession()->set($titreId.'timestamp_remboursement_instruction', true);
            $request->getSession()->getFlashBag()->add(
                'success',
                'L\'instruction a été réalisée avec succès.'
            );

            return $this->redirectToRoute('remboursement_list', []);
        }

        return $this->render('BackOffice/Remboursement/AuditNumerique/examine.html.twig', [
            'form'          => $form->createView(),
            'demande'       => $demande,
            'beneficiaire'  => $beneficiaire,
            'logement'      => $logement,
            'numeroCheque'  => $titre->getNumeroCheque(),
            'auditeur'      => $auditeur
        ]);
    }

    /**
     * Re-examine remboursement audit numérique - instruction
     */
    #[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_INSTRUCTEUR_UP') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function reexamine(Request $request, int $remboursementId): RedirectResponse|Response
    {
        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursement = $this->remboursementRepository->find($remboursementId);

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demande = $this->demandeRepository->find($remboursement->getDemandeId());

        /* ***************************************************************************
                        S E C U R I T Y   (R E) E X A M I N E
        *************************************************************************** */
        $this->remboursementService->checkExamineReexamine($demande->getType(), null, $remboursementId);

        $instruction = $remboursement->getRemboursementAuditNumerique()->getInstruction();

        /* /////////////////////////////////////////////////////////////////
                                    GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaire = $this->em->getRepository(Beneficiaire::class)->find($demande->getBeneficiaireId());

        /* /////////////////////////////////////////////////////////////////
                                    GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->em->getRepository(Logement::class)->find($demande->getLogementId());

        /* /////////////////////////////////////////////////////////////////
                                    GET PARTENAIRE
        ///////////////////////////////////////////////////////////////// */
        $auditeur = $this->partenaireRepository->find($demande->getDemandeAuditNumerique()->getAuditeurId());
        $optionAuditeur = $auditeur->getPartenaireOptionAuditeur();

        /* /////////////////////////////////////////////////////////////////
                                    GET TITRE
        ///////////////////////////////////////////////////////////////// */
        $titre = $this->titreRepository->find($remboursement->getTitreId());

        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $optionDepot = array(
            false,
            in_array('ROLE_AUDITEUR', $this->getUser()->getRoles()) ? true : false
        );
        $optionFicheTechnique = array(
            array(),
            array(),
            array(),
            null
        );
        $optionTravauxInstruction = array(null);

        $formOption = array(
            'optionDepot'               => $optionDepot,
            'optionFicheTechnique'      => $optionFicheTechnique,
            'optionTravauxInstruction'  => $optionTravauxInstruction
        );

        $form = $this->createForm(Remboursement_Type::class, $remboursement, array(
            'trait_choices' => $formOption
        ));
        $form->remove('remboursement_auditEnergie');
        $form->remove('remboursement_travaux');
        $form->get('remboursement_auditNumerique')->remove('depot');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid())
        {
            $remboursement->setDateModif(new \DateTime());
            $remboursement->setAuteurModif($_SESSION['login']->getUsername());

            // Format IBAN no space
            $IBANNoSpace = preg_replace('/\s+/', '', $instruction->getIban());
            $instruction->setIban($IBANNoSpace);

            /* /////////////////////////////////////////////////////////////////
                                    SET REASON DATA
            ///////////////////////////////////////////////////////////////// */
            $arrayChequeReason = array();
            foreach ($instruction->getChequeReason() as $item) {
                $arrayChequeReason[] = $item;
            }
            $instruction->setChequeReason($arrayChequeReason);

            $arrayFactureReason = array();
            foreach ($instruction->getFactureReason() as $item) {
                $arrayFactureReason[] = $item;
            }
            $instruction->setFactureReason($arrayFactureReason);

            $arrayRibReason = array();
            foreach ($instruction->getRibReason() as $item) {
                $arrayRibReason[] = $item;
            }
            $instruction->setRibReason($arrayRibReason);

            /* /////////////////////////////////////////////////////////////////
                                    SET REMBOURSEMENT STATUS
            ///////////////////////////////////////////////////////////////// */
            $depot = $remboursement->getRemboursementAuditNumerique()->getDepot();
            $documentAudit = null;
            $documentAuditAlt = null;
            if ($depot) {
                $documentAudit = $depot->getAudit();
                $documentAuditAlt = $depot->getAuditAlt();
            }

            $statut = $this->remboursementService->searchStatutForRemboursementAuditNumerique(
                $documentAudit,
                $documentAuditAlt,
                $instruction,
                $optionAuditeur
            );
            $remboursement->setStatutId($statut);

            $this->remboursementService->setDateInstruction($remboursement, $this->getUser()->getRoles());

            $this->em->persist($remboursement);
            $this->em->flush();

            // MISE A JOUR REMBOURSEMENT STATUT DESCRIPTION
            $statutDescription = $this->remboursementService->findStatutDescriptionByRemboursement(
                $remboursement->getId(),
                $this->params->get('production_travauxNiveau_BBC1')
            );
            $remboursement->setStatutDescription($statutDescription);
            $this->em->persist($remboursement);
            $this->em->flush();

            /* /////////////////////////////////////////////////////////////////
                                COPY UPLOAD FILE - RIB AUDITEUR
            ///////////////////////////////////////////////////////////////// */

            $destinataire = $instruction->getDestinataire();
            $destinataireArray = array();
            if ($destinataire) {
                $destinataireArray = explode(' | ', $destinataire);
            }

            $ribAlt = $instruction->getRibAlt();
            if ('0' == $destinataireArray[0]) {
                $projectDir = $this->getParameter('app_root_dossier_data_symfony');

                // Update Auditeur RIB
                if ($instruction->getRib()) {
                    $optionAuditeur->setRibUrl($instruction->getRibUrl());
                    $optionAuditeur->setRibAlt($instruction->getRibAlt());

                    // Copy file from REMBOURSEMENT to AUDITEUR
                    $auditeur_RIB_path = $projectDir . $optionAuditeur->rib_getUploadDir();
                    if (!file_exists($auditeur_RIB_path)) mkdir($auditeur_RIB_path, 0755, true);

                    copy(
                        $projectDir . $instruction->rib_getWebPath(),
                        $projectDir . $optionAuditeur->rib_getWebPath()
                    );
                }

                $ibanPost = trim($instruction->getIban());
                $bicPost = trim($instruction->getBic());
                $domiciliationBancairePost = trim($instruction->getDomiciliationBancaire());

                // Update Auditeur data
                if (($ibanPost && ($ibanPost != $optionAuditeur->getIban()))
                    || ($bicPost && ($bicPost != $optionAuditeur->getBic()))
                    || ($domiciliationBancairePost && ($domiciliationBancairePost != $optionAuditeur->getDomicileBancaire()))
                ) {
                    $optionAuditeur->setIban($ibanPost);
                    $optionAuditeur->setBic($bicPost);
                    $optionAuditeur->setDomicileBancaire($domiciliationBancairePost);
                }

                $this->em->persist($optionAuditeur);
                $this->em->flush();

                $ribAlt = $optionAuditeur->getRibAlt();

                // Remove file RIB
                $remboursement_RIB = $projectDir . $instruction->rib_getWebPath();
                if (file_exists($remboursement_RIB)) unlink($remboursement_RIB);
            }

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $remboursement->getDemandeId(),
                $remboursement->getStatutId(),
                $demande->getType(),
                $this->getUser()->getRoles() ?? [],
                true,
                'Remboursement - ' . (Demande_::$demandeType[$demande->getType()] ?? ''),
                null,
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId(),
                $ribAlt,
                $instruction->getFactureAlt(),
                $instruction->getRectoChequeAlt(),
                $instruction->getVersoChequeAlt(),
                null,
                null,
                $titre->getDateEmission()->format('Y-m-d')
            );

            $request->getSession()->getFlashBag()->add(
                'success',
                'L\'instruction a été complétée avec succès.'
            );

            return $this->redirectToRoute('remboursement_list', array());
        }

        return $this->render('BackOffice/Remboursement/AuditNumerique/examine.html.twig', array(
            'form'          => $form->createView(),
            'demande'       => $demande,
            'beneficiaire'  => $beneficiaire,
            'logement'      => $logement,
            'numeroCheque'  => $titre->getNumeroCheque(),
            'auditeur'      => $auditeur,
            'remboursement' => $remboursement
        ));
    }

    /**
     * Add depot audit numérique
     */
    #[Security("is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function addDepot(Request $request, int $demandeId): RedirectResponse|Response
    {

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->em->getRepository(Demande_::class);
        /**
         * @var Demande_ $demande
         */
        $demande = $repo_demande->find($demandeId);

        /* ***************************************************************************
                            S E C U R I T Y   D E P O T
        *************************************************************************** */
        $this->remboursementService->checkDepot($demande->getType(), $demandeId, null);

        $option = array(
            'roles'     => $this->getUser()->getRoles(),
            'username'  => $this->getUser()->getUsername()
        );

        /* *****************************************************************
                    S E C U R I T Y   R E T O U R   A R R I E R E
        ***************************************************************** */
        if (true === $request->getSession()->get($demandeId.'timestamp_remboursement_auditNumerique_depot')) {
            return $this->redirectToRoute('remboursement_list', []);
        }

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesByRole($demande, $option);

        /* /////////////////////////////////////////////////////////////////
                                    GET PARTENAIRE
        ///////////////////////////////////////////////////////////////// */
        $auditeur = $this->partenaireRepository->find($demande->getDemandeAuditNumerique()->getAuditeurId());
        $optionAuditeur = $auditeur->getPartenaireOptionAuditeur();

        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $this->remboursementRepository->findOneBy([
            'demande_id' => $demandeId
        ]);

        if (!$remboursement) {
            /* /////////////////////////////////////////////////////////////////
                                        GET TITRE
            ///////////////////////////////////////////////////////////////// */
            $repo_titre = $this->em->getRepository(Titre::class);
            $titre = $repo_titre->findOneBy(array(
                'demandeId' => $demandeId
            ));

            $remboursement = new Remboursement_();
            $remboursement->setDemandeId($demandeId);
            $remboursement->setTitreId($titre->getId());
        }

        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $optionDepot = array(
            true,
            in_array('ROLE_AUDITEUR', $this->getUser()->getRoles()) ? true : false
        );
        $optionFicheTechnique = array(
            array(),
            array(),
            array(),
            null
        );
        $optionTravauxInstruction = array(null);

        $formOption = array(
            'optionDepot'               => $optionDepot,
            'optionFicheTechnique'      => $optionFicheTechnique,
            'optionTravauxInstruction'  => $optionTravauxInstruction
        );

        $form = $this->createForm(Remboursement_Type::class, $remboursement, array(
            'trait_choices' => $formOption
        ));
        $form->remove('remboursement_auditEnergie');
        $form->remove('remboursement_travaux');
        $form->get('remboursement_auditNumerique')->get('instruction')
            ->remove('dateCheque')
            ->remove('numeroRemiseRSI')
            ->remove('isChequeConforme')
            ->remove('chequeReason')
            ->remove('chequeReasonAutre')
            ->remove('montantFacture')
            ->remove('isFactureConforme')
            ->remove('factureReason')
            ->remove('factureReasonAutre')
            ->remove('destinataire')
            ->remove('isRibConforme')
            ->remove('ribReason')
            ->remove('ribReasonAutre');

        $instructionDestinataire = null;
        $isDestinataireAuditeur = false;
        if (
            $remboursement->getRemboursementAuditNumerique()
            and $remboursement->getRemboursementAuditNumerique()->getInstruction()
            and $remboursement->getRemboursementAuditNumerique()->getInstruction()->getDestinataire()
        ) {
            $instructionDestinataire = $remboursement->getRemboursementAuditNumerique()->getInstruction()->getDestinataire();
            $destinataireArray = explode(' | ', $instructionDestinataire);
            // Si Beneficiaire => On cache les champs de la partie RIB
            if ('1' == $destinataireArray[0]) {
                $form->get('remboursement_auditNumerique')->get('instruction')
                    ->remove('rib')
                    ->remove('iban')
                    ->remove('bic')
                    ->remove('domiciliationBancaire');
            } else if ('0' == $destinataireArray[0]) {
                $isDestinataireAuditeur = true;
            }
        }

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid())
        {
            $instruction = $remboursement->getRemboursementAuditNumerique()->getInstruction();

            if ($instruction->getIban()) {
                // Format IBAN no space
                $IBANNoSpace = preg_replace('/\s+/', '', $instruction->getIban());
                $instruction->setIban($IBANNoSpace);
            }

            if ($instruction->getRectoCheque() || $instruction->getVersoCheque()) {
                $instruction->setIsChequeConforme(null);
                $instruction->setChequeReason(array());
                $instruction->setChequeReasonAutre(null);
            }

            if ($instruction->getFacture()) {
                $instruction->setIsFactureConforme(null);
                $instruction->setFactureReason(array());
                $instruction->setFactureReasonAutre(null);
            }

            if ($instruction->getRib()) {
                $instruction->setIsRibConforme(null);
                $instruction->setRibReason(array());
                $instruction->setRibReasonAutre(null);
            }

            /* /////////////////////////////////////////////////////////////////
                                    SET REMBOURSEMENT STATUS
            ///////////////////////////////////////////////////////////////// */
            $depot = $remboursement->getRemboursementAuditNumerique()->getDepot();
            $documentAudit = $depot->getAudit();
            $documentAuditAlt = $depot->getAuditAlt();

            $instruction = $remboursement->getRemboursementAuditNumerique()->getInstruction();
            $statut = $this->remboursementService->searchStatutForRemboursementAuditNumerique(
                $documentAudit,
                $documentAuditAlt,
                $instruction,
                $optionAuditeur
            );
            $remboursement->setStatutId($statut);

            $this->em->persist($remboursement);

            /* /////////////////////////////////////////////////////////////////
                                COPY UPLOAD FILE - RIB AUDITEUR
            ///////////////////////////////////////////////////////////////// */
            if (!$instructionDestinataire) {
                $instruction->setDestinataire('0 | auditeur');
                $isDestinataireAuditeur = true;
            }
            if ($isDestinataireAuditeur) {
                $projectDir = $this->getParameter('app_root_dossier_data_symfony');

                // Update Auditeur RIB if new file is uploaded
                if ($instruction->getRib()) {

                    $optionAuditeur->setRibUrl($instruction->getRib()->guessExtension());
                    $optionAuditeur->setRibAlt($instruction->getRib()->getClientOriginalName());

                    // Copy file from REMBOURSEMENT to AUDITEUR
                    $auditeur_RIB_path = $projectDir . $optionAuditeur->rib_getUploadDir();
                    if (!file_exists($auditeur_RIB_path)) mkdir($auditeur_RIB_path, 0755, true);

                    copy(
                        $instruction->getRib(),
                        $projectDir . $optionAuditeur->rib_getWebPath()
                    );
                }

                $ibanPost = trim($instruction->getIban());
                $bicPost = trim($instruction->getBic());
                $domiciliationBancairePost = trim($instruction->getDomiciliationBancaire());

                // Update Auditeur data
                if (($ibanPost && ($ibanPost != $optionAuditeur->getIban()))
                    || ($bicPost && ($bicPost != $optionAuditeur->getBic()))
                    || ($domiciliationBancairePost && ($domiciliationBancairePost != $optionAuditeur->getDomicileBancaire()))
                ) {
                    $optionAuditeur->setIban($ibanPost);
                    $optionAuditeur->setBic($bicPost);
                    $optionAuditeur->setDomicileBancaire($domiciliationBancairePost);
                }

                $this->em->persist($optionAuditeur);
                $this->em->flush();

                // Remove file RIB
                $remboursement_RIB = $projectDir . $instruction->rib_getWebPath();
                if (file_exists($remboursement_RIB)) unlink($remboursement_RIB);
            } else {
                $this->em->flush();
            }

            // MISE A JOUR REMBOURSEMENT STATUT DESCRIPTION
            $statutDescription = $this->remboursementService->findStatutDescriptionByRemboursement(
                $remboursement->getId(),
                $this->params->get('production_travauxNiveau_BBC1')
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
                false,
                'Remboursement - ' . (Demande_::$demandeType[$demande->getType()] ?? '') . ' - Téléchargement des pièces justificatives',
                null,
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId()
            );

            $request->getSession()->set($demandeId.'timestamp_remboursement_auditNumerique_depot', true);
            $request->getSession()->getFlashBag()->add(
                'success',
                'Votre dépôt de pièces justificatives a bien été pris en compte.'
            );

            return $this->redirectToRoute('remboursement_list', array());
        }

        return $this->render('BackOffice/Remboursement/AuditNumerique/depot.html.twig', array(
            'form'          => $form->createView(),
            'auditeur'      => $auditeur,
            'remboursement' => $remboursement,
            'demandeId'     => $demandeId
        ));
    }

    /**
     * Edit depot audit numérique
     */
    #[Security("is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function editDepot(Request $request, int $remboursementId): RedirectResponse|Response
    {
        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursement = $this->remboursementRepository->find($remboursementId);

        $demandeRepository = $this->em->getRepository(Demande_::class);

        /**
         * @var Demande_ $demande
         */
        $demande = $demandeRepository->find($remboursement->getDemandeId());

        /* ***************************************************************************
                            S E C U R I T Y   D E P O T
        *************************************************************************** */
        $this->remboursementService->checkDepot($demande->getType(), null, $remboursementId);

        $option = array(
            'roles'     => $this->getUser()->getRoles(),
            'username'  => $this->getUser()->getUsername()
        );

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->em->getRepository(Demande_::class);
        /**
         * @var Demande_ $demande
         */
        $demande = $repo_demande->find($remboursement->getDemandeId());

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesByRole($demande, $option);

        /* /////////////////////////////////////////////////////////////////
                                    GET PARTENAIRE
        ///////////////////////////////////////////////////////////////// */
        $auditeur = $this->partenaireRepository->find($demande->getDemandeAuditNumerique()->getAuditeurId());
        $optionAuditeur = $auditeur->getPartenaireOptionAuditeur();

        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $optionDepot = array(
            false,
            in_array('ROLE_AUDITEUR', $this->getUser()->getRoles()) ? true : false
        );
        $optionFicheTechnique = array(
            array(),
            array(),
            array(),
            null
        );
        $optionTravauxInstruction = array(null);

        $formOption = array(
            'optionDepot'               => $optionDepot,
            'optionFicheTechnique'      => $optionFicheTechnique,
            'optionTravauxInstruction'  => $optionTravauxInstruction
        );

        $form = $this->createForm(Remboursement_Type::class, $remboursement, array(
            'trait_choices' => $formOption
        ));
        $form->remove('remboursement_auditEnergie');
        $form->remove('remboursement_travaux');
        $form->get('remboursement_auditNumerique')->get('instruction')
            ->remove('dateCheque')
            ->remove('numeroRemiseRSI')
            ->remove('isChequeConforme')
            ->remove('chequeReason')
            ->remove('chequeReasonAutre')
            ->remove('montantFacture')
            ->remove('isFactureConforme')
            ->remove('factureReason')
            ->remove('factureReasonAutre')
            ->remove('destinataire')
            ->remove('isRibConforme')
            ->remove('ribReason')
            ->remove('ribReasonAutre');

        $instructionDestinataire = null;
        $isDestinataireAuditeur = false;
        if (
            $remboursement->getRemboursementAuditNumerique()
            and $remboursement->getRemboursementAuditNumerique()->getInstruction()
            and $remboursement->getRemboursementAuditNumerique()->getInstruction()->getDestinataire()
        ) {
            $instructionDestinataire = $remboursement->getRemboursementAuditNumerique()->getInstruction()->getDestinataire();
            $destinataireArray = explode(' | ', $instructionDestinataire);
            // Si Beneficiaire => On cache les champs de la partie RIB
            if ('1' == $destinataireArray[0]) {
                $form->get('remboursement_auditNumerique')->get('instruction')
                    ->remove('rib')
                    ->remove('iban')
                    ->remove('bic')
                    ->remove('domiciliationBancaire');
            } else if ('0' == $destinataireArray[0]) {
                $isDestinataireAuditeur = true;
            }
        }

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid())
        {
            $remboursement->setDateModif(new \DateTime());
            $remboursement->setAuteurModif($_SESSION['login']->getUsername());

            $instruction = $remboursement->getRemboursementAuditNumerique()->getInstruction();

            if ($instruction->getIban()) {
                // Format IBAN no space
                $IBANNoSpace = preg_replace('/\s+/', '', $instruction->getIban());
                $instruction->setIban($IBANNoSpace);
            }

            if ($instruction->getRectoCheque() || $instruction->getVersoCheque()) {
                $instruction->setIsChequeConforme(null);
                $instruction->setChequeReason(array());
                $instruction->setChequeReasonAutre(null);

                if ($instruction->getRectoCheque()) {
                    $instruction->setRectoChequeUrl($instruction->getRectoCheque()->guessExtension());
                    $instruction->setRectoChequeAlt($instruction->getRectoCheque()->getClientOriginalName());
                }
                if ($instruction->getVersoCheque()) {
                    $instruction->setVersoChequeUrl($instruction->getVersoCheque()->guessExtension());
                    $instruction->setVersoChequeAlt($instruction->getVersoCheque()->getClientOriginalName());
                }
            }

            if ($instruction->getFacture()) {
                $instruction->setIsFactureConforme(null);
                $instruction->setFactureReason(array());
                $instruction->setFactureReasonAutre(null);

                $instruction->setFactureUrl($instruction->getFacture()->guessExtension());
                $instruction->setFactureAlt($instruction->getFacture()->getClientOriginalName());
            }

            if ($instruction->getRib()) {
                $instruction->setIsRibConforme(null);
                $instruction->setRibReason(array());
                $instruction->setRibReasonAutre(null);
            }

            /* /////////////////////////////////////////////////////////////////
                                    SET REMBOURSEMENT STATUS
            ///////////////////////////////////////////////////////////////// */
            $depot = $remboursement->getRemboursementAuditNumerique()->getDepot();
            $documentAudit = $depot->getAudit();
            $documentAuditAlt = $depot->getAuditAlt();

            $instruction = $remboursement->getRemboursementAuditNumerique()->getInstruction();
            $statut = $this->remboursementService->searchStatutForRemboursementAuditNumerique(
                $documentAudit,
                $documentAuditAlt,
                $instruction,
                $optionAuditeur
            );
            $remboursement->setStatutId($statut);

            $this->em->persist($remboursement);

            /* /////////////////////////////////////////////////////////////////
                                COPY UPLOAD FILE - RIB AUDITEUR
            ///////////////////////////////////////////////////////////////// */
            if (!$instructionDestinataire) {
                $instruction->setDestinataire('0 | auditeur');
                $isDestinataireAuditeur = true;
            }
            if ($isDestinataireAuditeur) {
                $projectDir = $this->getParameter('app_root_dossier_data_symfony');

                // Update Auditeur RIB if new file is uploaded
                if ($instruction->getRib()) {

                    $optionAuditeur->setRibUrl($instruction->getRib()->guessExtension());
                    $optionAuditeur->setRibAlt($instruction->getRib()->getClientOriginalName());

                    // Copy file from REMBOURSEMENT to AUDITEUR
                    $auditeur_RIB_path = $projectDir . $optionAuditeur->rib_getUploadDir();
                    if (!file_exists($auditeur_RIB_path)) mkdir($auditeur_RIB_path, 0755, true);

                    copy(
                        $instruction->getRib(),
                        $projectDir . $optionAuditeur->rib_getWebPath()
                    );
                }

                $ibanPost = trim($instruction->getIban());
                $bicPost = trim($instruction->getBic());
                $domiciliationBancairePost = trim($instruction->getDomiciliationBancaire());

                // Update Auditeur data
                if (($ibanPost && ($ibanPost != $optionAuditeur->getIban()))
                    || ($bicPost && ($bicPost != $optionAuditeur->getBic()))
                    || ($domiciliationBancairePost && ($domiciliationBancairePost != $optionAuditeur->getDomicileBancaire()))
                ) {
                    $optionAuditeur->setIban($ibanPost);
                    $optionAuditeur->setBic($bicPost);
                    $optionAuditeur->setDomicileBancaire($domiciliationBancairePost);
                }

                $this->em->persist($optionAuditeur);
                $this->em->flush();

                // Remove file RIB
                $remboursement_RIB = $projectDir . $instruction->rib_getWebPath();
                if (file_exists($remboursement_RIB)) unlink($remboursement_RIB);
            } else {
                $this->em->flush();
            }

            // MISE A JOUR REMBOURSEMENT STATUT DESCRIPTION
            $statutDescription = $this->remboursementService->findStatutDescriptionByRemboursement(
                $remboursement->getId(),
                $this->params->get('production_travauxNiveau_BBC1')
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
                false,
                'Remboursement - ' . (Demande_::$demandeType[$demande->getType()] ?? '') . ' - Téléchargement des pièces justificatives',
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
                'Votre dépôt de pièces justificatives a bien été modifié.'
            );

            return $this->redirectToRoute('remboursement_list', array());
        }

        return $this->render('BackOffice/Remboursement/AuditNumerique/depot.html.twig', array(
            'form'          => $form->createView(),
            'remboursement' => $remboursement,
            'auditeur'      => $auditeur
        ));
    }
}

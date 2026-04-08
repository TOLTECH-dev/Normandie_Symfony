<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\Logement;
use App\Entity\Remboursement_;
use App\Entity\Titre;
use App\Entity\Demande_;
use App\Form\Remboursement_Type;
use App\Service\RemboursementService;
use App\Service\HistoriqueService;
use App\Service\DemandeServiceBO;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TitreRepository;
use App\Repository\Demande_Repository;
use App\Repository\Remboursement_Repository;
use App\Repository\Partenaire_Repository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RemboursementAuditEnergieController extends AbstractController
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
    ) {
    }

    /**
     * Examine remboursement audit énergie - instruction
     */
    #[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_INSTRUCTEUR_UP') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function examine(Request $request, int $titreId): RedirectResponse|Response
    {

        /* /////////////////////////////////////////////////////////////////
                                    GET TITRE
        ///////////////////////////////////////////////////////////////// */
        $repo_titre = $this->titreRepository;
        /**
         * @var Titre $titre
         */
        $titre = $repo_titre->find($titreId);

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demandeRepository = $this->demandeRepository;
        /**
         * @var Demande_ $demande
         */
        $demande = $demandeRepository->find($titre->getDemandeId());

        /* ***************************************************************************
                        S E C U R I T Y   (R E) E X A M I N E
        *************************************************************************** */
        $this->remboursementService->checkExamineReexamine($demande->getType(), $titreId, null);

        /* *****************************************************************
                    S E C U R I T Y   R E T O U R   A R R I E R E
        ***************************************************************** */
        if (true == $request->getSession()->get($titreId.'timestamp_remboursement_auditEnergie_instruction')) {
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
        $remboursementRepository = $this->remboursementRepository;
        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $remboursementRepository->findOneBy([
            'demande_id' => $titre->getDemandeId()
        ]);

        if (!$remboursement) {
            $remboursement = new Remboursement_();
            $remboursement->setDemandeId($titre->getDemandeId());
            $remboursement->setTitreId($titre->getId());
        } else {
            $titre = $repo_titre->find($remboursement->getTitreId());
        }

        /* /////////////////////////////////////////////////////////////////
                                GET PARTENAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo_partenaire = $this->partenaireRepository;
        $auditeur = $repo_partenaire->find($demande->getDemandeAuditEnergie()->getAuditeurId());
        $optionAuditeur = $auditeur->getPartenaireOptionAuditeur();

        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $optionDepot = array(
            false,
            in_array('ROLE_AUDITEUR', $this->getUser()->getRoles())
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
        $form->remove('remboursement_auditNumerique');
        $form->remove('remboursement_travaux');
        $form->get('remboursement_auditEnergie')->remove('depot');

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid())
        {
            $instruction = $remboursement->getRemboursementAuditEnergie()->getInstruction();

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
            $depot = $remboursement->getRemboursementAuditEnergie()->getDepot();
            $documentAudit = null;
            $documentAuditAlt = null;
            if ($depot) {
                $documentAudit = $depot->getAudit();
                $documentAuditAlt = $depot->getAuditAlt();
            }

            $statut = $this->remboursementService->searchStatutForRemboursementAuditEnergie(
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

                    // Correction copy() : s'assurer que les paramètres sont des chemins
                    if ($instruction->getRib() && method_exists($instruction, 'rib_getWebPath') && method_exists($optionAuditeur, 'rib_getWebPath')) {
                        $src = $projectDir . $instruction->rib_getWebPath();
                        $dest = $projectDir . $optionAuditeur->rib_getWebPath();
                        if (is_string($src) && is_string($dest) && file_exists($src)) {
                            copy($src, $dest);
                        }
                    }
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
                // Correction unlink
                if (isset($remboursement_RIB) && is_string($remboursement_RIB) && file_exists($remboursement_RIB)) {
                    unlink($remboursement_RIB);
                }
            }

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $titre->getDemandeId(),
                $remboursement->getStatutId(),
                $demande->getType(),
                $this->getUser()->getRoles(),
                true,
                'Remboursement - ' . Demande_::$demandeType[$demande->getType()] . ' - Instruction',
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

            $request->getSession()->set($titreId.'timestamp_remboursement_auditEnergie_instruction', true);
            $request->getSession()->getFlashBag()->add(
                'success',
                'L\'instruction a été réalisée avec succès.'
            );

            return $this->redirectToRoute('remboursement_list', array());
        }

        $seuilCheckMontantFacture = Remboursement_::$arrayRemboursementSeuilCheckMontantFacture[$titre->getValeurTitre()];

        // Correction chemin template
        return $this->render('BackOffice/Remboursement/AuditEnergie/examine.html.twig', [
            'form'                     => $form->createView(),
            'demande'                  => $demande,
            'beneficiaire'             => $beneficiaire,
            'logement'                 => $logement,
            'numeroCheque'             => $titre->getNumeroCheque(),
            'auditeur'                 => $auditeur,
            'seuilCheckMontantFacture' => $seuilCheckMontantFacture
        ]);
    }

    /**
     * Re-examine remboursement audit énergie - instruction
     */
    #[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_INSTRUCTEUR_UP') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function reexamine(Request $request, int $remboursementId): RedirectResponse|Response
    {

        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursementRepository = $this->remboursementRepository;
        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $remboursementRepository->find($remboursementId);

        $demandeRepository = $this->demandeRepository;
        /**
         * @var Demande_ $demande
         */
        $demande = $demandeRepository->find($remboursement->getDemandeId());

        /* ***************************************************************************
                        S E C U R I T Y   (R E) E X A M I N E
        *************************************************************************** */
        $this->remboursementService->checkExamineReexamine($demande->getType(), null, $remboursementId);

        $instruction = $remboursement->getRemboursementAuditEnergie()->getInstruction();

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $repo = $this->em->getRepository(Demande_::class);
        /**
         * @var Demande_ $demande
         */
        $demande = $repo->find($remboursement->getDemandeId());

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
                                    GET TITRE
        ///////////////////////////////////////////////////////////////// */
        $repo_titre = $this->titreRepository;
        $titre = $repo_titre->find($remboursement->getTitreId());

        /* /////////////////////////////////////////////////////////////////
                                    GET AUDITEUR
        ///////////////////////////////////////////////////////////////// */
        $repo_partenaire = $this->partenaireRepository;
        $auditeur = $repo_partenaire->find($demande->getDemandeAuditEnergie()->getAuditeurId());
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
        $form->remove('remboursement_auditNumerique');
        $form->remove('remboursement_travaux');
        $form->get('remboursement_auditEnergie')->remove('depot');

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
            $depot = $remboursement->getRemboursementAuditEnergie()->getDepot();
            $documentAudit = null;
            $documentAuditAlt = null;
            if ($depot) {
                $documentAudit = $depot->getAudit();
                $documentAuditAlt = $depot->getAuditAlt();
            }

            $statut = $this->remboursementService->searchStatutForRemboursementAuditEnergie(
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

                    // Correction copy() : s'assurer que les paramètres sont des chemins
                    if ($instruction->getRib() && method_exists($instruction, 'rib_getWebPath') && method_exists($optionAuditeur, 'rib_getWebPath')) {
                        $src = $projectDir . $instruction->rib_getWebPath();
                        $dest = $projectDir . $optionAuditeur->rib_getWebPath();
                        if (is_string($src) && is_string($dest) && file_exists($src)) {
                            copy($src, $dest);
                        }
                    }
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
                // Correction unlink
                if (isset($remboursement_RIB) && is_string($remboursement_RIB) && file_exists($remboursement_RIB)) {
                    unlink($remboursement_RIB);
                }
            }

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $remboursement->getDemandeId(),
                $remboursement->getStatutId(),
                $demande->getType(),
                $this->getUser()->getRoles(),
                true,
                'Remboursement - ' . Demande_::$demandeType[$demande->getType()] . ' - Instruction',
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

        $seuilCheckMontantFacture = Remboursement_::$arrayRemboursementSeuilCheckMontantFacture[$titre->getValeurTitre()];

        // Correction chemin template
        return $this->render('BackOffice/Remboursement/AuditEnergie/examine.html.twig', [
            'form'                     => $form->createView(),
            'demande'                  => $demande,
            'beneficiaire'             => $beneficiaire,
            'logement'                 => $logement,
            'numeroCheque'             => $titre->getNumeroCheque(),
            'auditeur'                 => $auditeur,
            'remboursement'            => $remboursement,
            'seuilCheckMontantFacture' => $seuilCheckMontantFacture
        ]);
    }

    /**
     * Add depot audit énergie
     */
    #[Security("is_granted('ROLE_AUDITEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
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
        if (true == $request->getSession()->get($demandeId.'timestamp_remboursement_auditEnergie_depot')) {
            return $this->redirectToRoute('remboursement_list', array());
        }

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesByRole($demande, $option);

        /* /////////////////////////////////////////////////////////////////
                                    GET PARTENAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo_partenaire = $this->partenaireRepository;
        $auditeur = $repo_partenaire->find($demande->getDemandeAuditEnergie()->getAuditeurId());
        $optionAuditeur = $auditeur->getPartenaireOptionAuditeur();

        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursementRepository = $this->remboursementRepository;
        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $remboursementRepository->findOneBy([
            'demande_id' => $demandeId
        ]);

        if (!$remboursement) {
            /* /////////////////////////////////////////////////////////////////
                                        GET TITRE
            ///////////////////////////////////////////////////////////////// */
            $repo_titre = $this->titreRepository;
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
        $form->remove('remboursement_auditNumerique');
        $form->remove('remboursement_travaux');
        $form->get('remboursement_auditEnergie')->get('instruction')
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
            $remboursement->getRemboursementAuditEnergie()
            and $remboursement->getRemboursementAuditEnergie()->getInstruction()
            and $remboursement->getRemboursementAuditEnergie()->getInstruction()->getDestinataire()
        ) {
            $instructionDestinataire = $remboursement->getRemboursementAuditEnergie()->getInstruction()->getDestinataire();
            $destinataireArray = explode(' | ', $instructionDestinataire);
            // Si Beneficiaire => On cache les champs de la partie RIB
            if ('1' == $destinataireArray[0]) {
                $form->get('remboursement_auditEnergie')->get('instruction')
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
            $instruction = $remboursement->getRemboursementAuditEnergie()->getInstruction();

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
            $depot = $remboursement->getRemboursementAuditEnergie()->getDepot();
            $documentAudit = $depot->getAudit();
            $documentAuditAlt = $depot->getAuditAlt();

            $statut = $this->remboursementService->searchStatutForRemboursementAuditEnergie(
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

                    // Correction copy() : s'assurer que les paramètres sont des chemins
                    if ($instruction->getRib() && method_exists($instruction, 'rib_getWebPath') && method_exists($optionAuditeur, 'rib_getWebPath')) {
                        $src = $projectDir . $instruction->rib_getWebPath();
                        $dest = $projectDir . $optionAuditeur->rib_getWebPath();
                        if (is_string($src) && is_string($dest) && file_exists($src)) {
                            copy($src, $dest);
                        }
                    }
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
                // Correction unlink
                if (isset($remboursement_RIB) && is_string($remboursement_RIB) && file_exists($remboursement_RIB)) {
                    unlink($remboursement_RIB);
                }
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
                $this->getUser()->getRoles(),
                false,
                'Remboursement - ' . Demande_::$demandeType[$demande->getType()] . ' - Téléchargement des pièces justificatives',
                null,
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId()
            );

            $request->getSession()->set($demandeId.'timestamp_remboursement_auditEnergie_depot', true);
            $request->getSession()->getFlashBag()->add(
                'success',
                'Votre dépôt de pièces justificatives a bien été pris en compte.'
            );

            return $this->redirectToRoute('remboursement_list', array());
        }

        // Correction chemin template
        return $this->render('BackOffice/Remboursement/AuditEnergie/depot.html.twig', [
            'form'          => $form->createView(),
            'auditeur'      => $auditeur,
            'remboursement' => $remboursement,
            'demandeId'     => $demandeId,
        ]);
    }

    /**
     * Edit depot audit énergie
     */
    #[Security("is_granted('ROLE_AUDITEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function editDepot(Request $request, int $remboursementId): RedirectResponse|Response
    {

        /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursementRepository = $this->remboursementRepository;
        /**
         * @var Remboursement_ $remboursement
         */
        $remboursement = $remboursementRepository->find($remboursementId);

        $demandeRepository = $this->demandeRepository;

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
        $repo_partenaire = $this->partenaireRepository;
        $auditeur = $repo_partenaire->find($demande->getDemandeAuditEnergie()->getAuditeurId());
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
        $form->remove('remboursement_auditNumerique');
        $form->remove('remboursement_travaux');
        $form->get('remboursement_auditEnergie')->get('instruction')
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
            $remboursement->getRemboursementAuditEnergie()
            and $remboursement->getRemboursementAuditEnergie()->getInstruction()
            and $remboursement->getRemboursementAuditEnergie()->getInstruction()->getDestinataire()
        ) {
            $instructionDestinataire = $remboursement->getRemboursementAuditEnergie()->getInstruction()->getDestinataire();
            $destinataireArray = explode(' | ', $instructionDestinataire);
            // Si Beneficiaire => On cache les champs de la partie RIB
            if ('1' == $destinataireArray[0]) {
                $form->get('remboursement_auditEnergie')->get('instruction')
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

            $instruction = $remboursement->getRemboursementAuditEnergie()->getInstruction();

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
            $depot = $remboursement->getRemboursementAuditEnergie()->getDepot();
            $documentAudit = $depot->getAudit();
            $documentAuditAlt = $depot->getAuditAlt();

            $statut = $this->remboursementService->searchStatutForRemboursementAuditEnergie(
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

                    // Correction copy() : s'assurer que les paramètres sont des chemins
                    if ($instruction->getRib() && method_exists($instruction, 'rib_getWebPath') && method_exists($optionAuditeur, 'rib_getWebPath')) {
                        $src = $projectDir . $instruction->rib_getWebPath();
                        $dest = $projectDir . $optionAuditeur->rib_getWebPath();
                        if (is_string($src) && is_string($dest) && file_exists($src)) {
                            copy($src, $dest);
                        }
                    }
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
                // Correction unlink
                if (isset($remboursement_RIB) && is_string($remboursement_RIB) && file_exists($remboursement_RIB)) {
                    unlink($remboursement_RIB);
                }
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
                $this->getUser()->getRoles(),
                false,
                'Remboursement - ' . Demande_::$demandeType[$demande->getType()] . ' - Téléchargement des pièces justificatives',
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

        // Correction chemin template
        return $this->render('BackOffice/Remboursement/AuditEnergie/depot.html.twig', [
            'form'          => $form->createView(),
            'remboursement' => $remboursement,
            'auditeur'      => $auditeur
        ]);
    }
}

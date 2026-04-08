<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DateRMH;
use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use App\Entity\Remboursement_statut;
use App\Entity\User;
use App\Form\DateRMHType;
use App\Repository\DateRMHRepository;
use App\Repository\Demande_Repository;
use App\Repository\Demande_travaux_devisRepository;
use App\Repository\Remboursement_Repository;
use App\Repository\Remboursement_statutRepository;
use App\Repository\TitreRepository;
use App\Repository\BeneficiaireRepository;
use App\Repository\LogementRepository;
use App\Repository\Partenaire_Repository;
use App\Repository\UserRepository;
use App\Service\AdminFormService;
use App\Service\DateRMHService;
use App\Service\HistoriqueService;
use App\Service\RemboursementService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

#[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class DateRMHController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private DateRMHRepository $dateRMHRepository,
        private DateRMHService $dateRMHService,
        private HistoriqueService $historiqueService,
        private AdminFormService $adminFormService,
        private Demande_Repository $demandeRepository,
        private Remboursement_Repository $remboursementRepository,
        private Remboursement_statutRepository $remboursementStatutRepository,
        private TitreRepository $titreRepository,
        private BeneficiaireRepository $beneficiaireRepository,
        private LogementRepository $logementRepository,
        private Demande_travaux_devisRepository $devisRepository,
        private Partenaire_Repository $partenaireRepository,
        private UserRepository $userRepository,
        private RemboursementService $remboursementService
    ) {}

    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function list(Request $request): Response
    {
        // Get list of DateRMH ordered by date descending
        $list_dateRMH = $this->dateRMHRepository->findBy(
            [],
            ['dateRMH' => 'DESC']
        );

        // Get number of reimbursements by DateRMH
        $list_remboursement = $this->dateRMHRepository->findRemboursement();

        // Create form for adding new DateRMH
        $dateRMH = new DateRMH();
        $form_add = $this->createForm(DateRMHType::class, $dateRMH);

        $form_add->handleRequest($request);
        if ($form_add->isSubmitted() && $form_add->isValid()) {
            $referenceDate = $this->getParameter('date_reference');

            // DateType already provides DateTime objects
            $dateRMH->setDateInactif($form_add->get('dateInactif')->getData() ?? new \DateTime($referenceDate));
            $dateRMH->setDateRMH($form_add->get('dateRMH')->getData());
            $dateRMH->setRgpd(false);

            $this->em->persist($dateRMH);
            $this->em->flush();

            $this->addFlash('success', 'La Date RMH a été créée avec succès.');

            return $this->redirectToRoute('dateRMH_list');
        }

        // Create edit forms for each existing DateRMH
        $arrayForm_edit = [];
        foreach ($list_dateRMH as $item) {
            if (!$request->request->has('whitelabel_backofficebundle_datermh')) {
                $formFactory = $this->get('form.factory');
                $form_edit = $formFactory->createNamed(
                    'formDateRMH_edit_' . $item->getId(),
                    DateRMHType::class,
                    $item
                );

                $arrayForm_edit[$item->getId()] = $form_edit->createView();

                $form_edit->handleRequest($request);
                if ($form_edit->isSubmitted() && $form_edit->isValid()) {
                    $referenceDate = $this->getParameter('date_reference');

                    $item->setDateModif(new \DateTime());
                    $item->setAuteurModif($_SESSION['login']->getUsername());

                    // DateType already provides DateTime objects
                    $item->setDateInactif($form_edit->get('dateInactif')->getData() ?? new \DateTime($referenceDate));
                    $item->setDateRMH($form_edit->get('dateRMH')->getData());

                    $this->em->persist($item);
                    $this->em->flush();

                    $this->addFlash('success', 'La Date RMH a été modifiée avec succès.');

                    return $this->redirectToRoute('dateRMH_list');
                }
            }
        }

        // Handle cancel form (RMH cancellation)
        $form_cancel = $this->adminFormService->cancelDateRMHType();

        $form_cancel->handleRequest($request);
        if ($form_cancel->isSubmitted() && $form_cancel->isValid()) {
            $dateRMHId = $_POST['form']['dateRMH_id'] ?? null;

            if ($dateRMHId && (int)$dateRMHId > 0) {
                $dateRMH = $this->dateRMHRepository->find($dateRMHId);

                if ($dateRMH) {
                    $list_remboursement_to_cancel = $this->remboursementRepository->findBy([
                        'dateRMH_id' => $dateRMHId
                    ]);

                    $remboursementStatutRemboursementAVenir = $this->remboursementStatutRepository->findOneByStatut(Remboursement_statut::STATUS_21);

                    foreach ($list_remboursement_to_cancel as $remboursement) {
                        $demande = $this->demandeRepository->find($remboursement->getDemandeId());

                        // Update reimbursement status
                        $remboursement->setStatutId(Remboursement_statut::STATUS_21);
                        $remboursement->setStatutDescription($remboursementStatutRemboursementAVenir->getDescription());

                        $this->em->persist($remboursement);

                        // Fill historique
                        $userRoles = $this->getUser()->getRoles();
                        $this->historiqueService->save(
                            $remboursement->getDemandeId(),
                            $remboursement->getStatutId(),
                            $demande->getType(),
                            $userRoles,
                            false,
                            'Remboursement - Annulation du RMH',
                            null,
                            null,
                            null,
                            null,
                            null,
                            false,
                            $remboursement->getId()
                        );
                    }

                    // Update DateRMH
                    $dateRMH->setDateModif(new \DateTime());
                    $dateRMH->setAuteurModif($_SESSION['login']->getUsername());
                    $dateRMH->setDateExport(null);

                    $this->em->persist($dateRMH);
                    $this->em->flush();
                    $path  = $this->getParameter('app_root_dossier_data_symfony');
                    // Delete RMH files
                    $filesRMH_path = $path . 'uploads/remboursement/RMH/' . $dateRMHId . '/';
                    $this->dateRMHService->deleteFiles($filesRMH_path);

                    $this->addFlash('success', 'La date RMH a été annulée avec succès.');
                } else {
                    $this->addFlash('danger', 'Erreur interne.');
                }
            } else {
                $this->addFlash('danger', 'Erreur interne.');
            }

            return $this->redirectToRoute('dateRMH_list');
        }

        return $this->render('BackOffice/DateRMH/list.html.twig', [
            'formAdd' => $form_add->createView(),
            'formCancel' => $form_cancel->createView(),
            'formEdit' => $arrayForm_edit,
            'dateRMH' => $dateRMH,
            'list_dateRMH' => $list_dateRMH,
            'list_remboursement' => $list_remboursement
        ]);
    }

    /**
     * @param Request $request
     * @param int $remboursementId
     * @param int $beneficiaireId
     * @param int $logementId
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function assign(
        Request $request,
        int $remboursementId,
        int $beneficiaireId,
        int $logementId
    ): RedirectResponse|Response {
        /* /////////////////////////////////////////////////////////////////
                                GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $remboursement = $this->remboursementRepository->find($remboursementId);
        if (!$remboursement) {
            throw $this->createNotFoundException("Remboursement not found.");
        }

        /* ***************************************************************************
                    S E C U R I T Y   A F F E C T E R   D A T E    R M H
        *************************************************************************** */
        if (!in_array($remboursement->getStatutId(), [
            Remboursement_statut::STATUS_14,
            Remboursement_statut::STATUS_21
        ])) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }

        /* /////////////////////////////////////////////////////////////////
                                GET TITRE
        ///////////////////////////////////////////////////////////////// */
        $titre = $this->titreRepository->find($remboursement->getTitreId());
        if (!$titre) {
            throw $this->createNotFoundException("Titre not found.");
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demande = $this->demandeRepository->find($remboursement->getDemandeId());
        if (!$demande) {
            throw $this->createNotFoundException("Demande not found.");
        }

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);
        if (!$beneficiaire) {
            throw $this->createNotFoundException("Beneficiaire not found.");
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $logement = $this->logementRepository->find($logementId);

        /* /////////////////////////////////////////////////////////////////
                                CREATE FORM
        ///////////////////////////////////////////////////////////////// */
        $enabledDateRMH = $this->dateRMHRepository->findBy(
            ['enabled' => 1],
            ['dateRMH' => 'DESC']
        );

        $form_dateRMH = [];
        foreach ($enabledDateRMH as $item) {
            $form_dateRMH[$item->getDateRMH()->format('d/m/Y')] = $item->getId();
        }

        $form = $this->adminFormService->assignDateRMHType($form_dateRMH, (string)$remboursement->getDateRMHId());

        $isBBC1 = false;
        if ($this->getParameter('production_travauxNiveau_BBC1') == $titre->getNumeroOperation()) {
            $isBBC1 = true;
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE TRAVAUX DEVIS
        ///////////////////////////////////////////////////////////////// */
        $renovateur = null;
        $isNotation = false;
        if (!in_array($demande->getType(), [
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE
        ])) {
            $demandeTravauxDevis = $this->devisRepository->find($demande->getDemandeTravaux()->getTravauxDevisId());
            if ($demandeTravauxDevis) {
                $demandeNiveau = $demandeTravauxDevis->getNiveau();

                /* /////////////////////////////////////////////////////////////////
                                   GET PARTENAIRE RENOVATEUR
                ///////////////////////////////////////////////////////////////// */
                if (
                    Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE == $demandeNiveau
                    || (
                        in_array($demandeNiveau, [
                            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE,
                            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE,
                            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE,
                            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE
                        ])
                        && $this->getParameter('production_travauxNiveau_BBC1') != $titre->getNumeroOperation()
                    )
                ) {
                    $isNotation = true;
                    $renovateur = $this->partenaireRepository->find($demandeTravauxDevis->getRenovateurId());
                } else {
                    $form->remove('rating');
                }
            } else {
                $form->remove('rating');
            }
        } else {
            $form->remove('rating');
        }

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            /* /////////////////////////////////////////////////////////////////
                                SET REMBOUSEMENT STATUT
            ///////////////////////////////////////////////////////////////// */
            if ($form["dateRMH"]->getData()) {
                $post_object = explode(" | ", (string)$form["dateRMH"]->getData());
                $remboursement->setDateRMHId((int)$post_object[0]);
                $statut = Remboursement_statut::STATUS_21;
            } else {
                $remboursement->setDateRMHId(null);
                $statut = Remboursement_statut::STATUS_14;
            }
            $remboursement->setStatutId($statut);

            // Set rating
            if ($renovateur) {
                $userRenovateur = $this->userRepository->findOneBy([
                    'username' => 'R' . str_pad((string)$renovateur->getId(), 5, '0', STR_PAD_LEFT)
                ]);

                if ($userRenovateur) {
                    $ratingForm = $form["rating"]->getData();
                    $rating = $remboursement->getRemboursementTravaux()->getRating();
                    if ($rating) {
                        $rating->setDateModif(new \DateTime());
                        $rating->setAuteurModif($_SESSION['login']->getUsername());
                        $rating->setScore($ratingForm->getScore());
                        $rating->setCommentaire($ratingForm->getCommentaire());
                    } else {
                        $ratingForm->setType((int)$this->getParameter('rating_from_region_to_renovateur'));
                        $currentUser = $this->getUser();
                        if ($currentUser instanceof User) {
                            $ratingForm->setFromUserId($currentUser->getId());
                        }
                        $ratingForm->setToUserId($userRenovateur->getId());
                        $remboursement->getRemboursementTravaux()->setRating($ratingForm);
                    }
                } else {
                    throw new \Exception("Rénovateur Id incorrecte.");
                }
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
                $this->getUser()->getRoles(),
                false,
                'Attribution date RMH',
                null,
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId()
            );

            $this->addFlash(
                'success',
                'La date RMH du remboursement n°' . $remboursementId . ' a bien été enregistrée.'
            );

            return $this->redirectToRoute('remboursement_list', []);
        }

        return $this->render('BackOffice/DateRMH/assign.html.twig', [
            'form'          => $form->createView(),
            'beneficiaire'  => $beneficiaire,
            'logement'      => $logement,
            'demande'       => $demande,
            'numeroCheque'  => $titre->getNumeroCheque(),
            'valeurTitre'   => $titre->getValeurTitre(),
            'remboursement' => $remboursement,
            'isBBC1'        => $isBBC1,
            'renovateur'    => $renovateur,
            'isNotation'    => $isNotation
        ]);
    }

    /**
     * @param $dateRMHId
     * @return Response
     * @throws Exception
     */
    public function exportRecapitulatifPreRMH($dateRMHId): Response
    {

        /* /////////////////////////////////////////////////////////////////
                        GET DATA FOR RECAPITULATIF PRE RMH
        ///////////////////////////////////////////////////////////////// */
        $recapitulatifPreRMH = $this->dateRMHRepository->findDataRecapitulatifPreRMH($dateRMHId);

        /* /////////////////////////////////////////////////////////////////
                                EXPORT PDF
        ///////////////////////////////////////////////////////////////// */
        $fpdf = $this->dateRMHService->createRecapitulatifPreRMH($recapitulatifPreRMH);

        return new Response(
            $fpdf->Output(),
            200,
            [
                'Content-Type' => 'application/pdf'
            ]
        );
    }

    /**
     * @param Request $request
     * @param int $dateRMHId
     * @return RedirectResponse
     * @throws Exception
     * @throws \Exception
     */
    public function exportDocumentRMH(Request $request, int $dateRMHId): RedirectResponse
    {
        $roles = $this->getUser()->getRoles();

        $remboursement_list = $this->remboursementRepository->findByDateRMH($dateRMHId);
        $remboursementStatutRemboursementTermine = $this->remboursementStatutRepository->findOneByStatut(Remboursement_statut::STATUS_22);

        foreach ($remboursement_list as $item) {
            $remboursement = $this->remboursementRepository->find($item['remboursementId']);
            $remboursement->setStatutId(Remboursement_statut::STATUS_22);
            $remboursement->setStatutDescription($remboursementStatutRemboursementTermine->getDescription());

            $this->em->persist($remboursement);

            $demande = $this->demandeRepository->find($remboursement->getDemandeId());

            $this->historiqueService->save(
                $remboursement->getDemandeId(),
                $remboursement->getStatutId(),
                $demande->getType(),
                $roles,
                true,
                'Remboursement - Remboursé',
                $item['beneficiaireEmail'],
                null,
                null,
                null,
                null,
                false,
                $remboursement->getId()
            );
        }
        $this->em->flush();

        $this->dateRMHService->createFileRMH($dateRMHId);
        $this->dateRMHService->createFileSynthese($dateRMHId);
        $this->dateRMHService->createFileXemelios($dateRMHId);

        $dateRMH = $this->dateRMHRepository->find($dateRMHId);
        $dateRMH->setDateExport(new \DateTime());
        $this->em->persist($dateRMH);
        $this->em->flush();

        $this->addFlash('success', 'Les documents RMH ont été générés avec succès.');

        return $this->redirectToRoute('dateRMH_list', []);
    }

    /**
     * @param int $dateRMHId
     * @return Response
     * @throws \Exception
     */
    public function downloadDocumentRMH(int $dateRMHId): Response
    {
        $zipName = $this->dateRMHService->createZipRMH($dateRMHId);

        if (!file_exists($zipName)) {
            throw $this->createNotFoundException('Le fichier ZIP ne peut pas être créé.');
        }

        $zipContent = file_get_contents($zipName);

        $response = new Response($zipContent);
        $response->headers->set('Content-Transfert-encoding', 'binary');
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . pathinfo($zipName)['filename'] . '_' . date('YmdHis') . '.zip"');
        $response->headers->set('Content-Length', (string)filesize($zipName));

        return $response;
    }
}

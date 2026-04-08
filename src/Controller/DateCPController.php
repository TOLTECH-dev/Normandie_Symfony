<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DateCP;
use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Form\DateCPType;
use App\Repository\DateCPRepository;
use App\Repository\Demande_Repository;
use App\Repository\BeneficiaireRepository;
use App\Repository\LogementRepository;
use App\Repository\Instruction_Repository;
use App\Repository\Demande_travaux_devisRepository;
use App\Repository\FicheTechniqueRepository;
use App\Service\DateCPService;
use App\Service\AdminFormService;
use App\Service\DemandeServiceFO;
use App\Service\HistoriqueService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

#[Security("is_granted('ROLE_CLIENT')")]
class DateCPController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private DateCPRepository $dateCpRepository,
        private DateCPService $dateCPService,
        private Demande_Repository $demandeRepository,
        private BeneficiaireRepository $beneficiaireRepository,
        private LogementRepository $logementRepository,
        private Instruction_Repository $instructionRepository,
        private Demande_travaux_devisRepository $devisRepository,
        private FicheTechniqueRepository $ficheTechniqueRepository,
        private AdminFormService $adminFormService,
        private DemandeServiceFO $demandeServiceFO,
        private HistoriqueService $historiqueService
    ) {}

    /**
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function list(Request $request): RedirectResponse|Response
    {
        // Get list of DateCP ordered by date descending
        $list_dateCP = $this->dateCpRepository->findBy(
            [],
            ['dateCP' => 'DESC']
        );

        // Get number of demands by DateCP
        $list_demande = $this->dateCpRepository->findDemande(
            $this->getParameter('app_date_us_passage_montant_auditEnergie'),
            $this->getParameter('app_date_passage_montant_auditRegion')
        );

        // Create form for adding new DateCP
        $dateCP = new DateCP();
        $form_add = $this->createForm(DateCPType::class, $dateCP);

        $form_add->handleRequest($request);
        if ($form_add->isSubmitted() && $form_add->isValid()) {
            $referenceDate = $this->getParameter('date_reference');

            // DateType already provides DateTime objects
            $dateCP->setDateInactif($form_add->get('dateInactif')->getData() ?? new \DateTime($referenceDate));
            $dateCP->setDateCP($form_add->get('dateCP')->getData());

            $this->em->persist($dateCP);
            $this->em->flush();

            $this->addFlash('success', 'La Date CP a été créée avec succès.');

            return $this->redirectToRoute('dateCP_list');
        }

        // Create edit forms for each existing DateCP
        $arrayForm_edit = [];
        foreach ($list_dateCP as $item) {
            if (!$request->request->has('whitelabel_backofficebundle_datecp')) {
                $formFactory = $this->get('form.factory');
                $form_edit = $formFactory->createNamed(
                    'formDateCP_edit_' . $item->getId(),
                    DateCPType::class,
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
                    $item->setDateCP($form_edit->get('dateCP')->getData());

                    $this->em->persist($item);
                    $this->em->flush();

                    $this->addFlash('success', 'La Date CP a été modifiée avec succès.');

                    return $this->redirectToRoute('dateCP_list');
                }
            }
        }

        return $this->render('BackOffice/DateCP/list.html.twig', [
            'formAdd' => $form_add->createView(),
            'formEdit' => $arrayForm_edit,
            'dateCP' => $dateCP,
            'list_dateCP' => $list_dateCP,
            'list_demande' => $list_demande
        ]);
    }

    /**
     * @param Request $request
     * @param int $demandeId
     * @param int $beneficiaireId
     * @param int $logementId
     * @return RedirectResponse|Response
     */
    public function assign(
        Request $request,
        int $demandeId,
        int $beneficiaireId,
        int $logementId
    ): RedirectResponse|Response {
        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demande = $this->demandeRepository->find($demandeId);
        if (!$demande) {
            throw $this->createNotFoundException("Demande not found.");
        }

        /* *****************************************************************
                    S E C U R I T Y    A S S I G N   D A T E   CP
        ***************************************************************** */
        if (!in_array($demande->getStatutId(), [Demande_statut::STATUS_8, Demande_statut::STATUS_11])) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }

        $demandeAuditN = null;
        if (Demande_::DEMANDE_AUDIT_ENERGIE_TYPE == $demande->getType()) {
            $demandeAuditN = $this->demandeRepository->findOneBy([
                'logement_id' => $logementId,
                'type'        => Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE
            ]);
        }

        $devis = null;
        $ficheTechnique = null;
        if (Demande_::DEMANDE_TRAVAUX_TYPE == $demande->getType()) {
            if ($demande->getDemandeTravaux()->getTravauxDevisId()) {
                $devis = $this->devisRepository->find($demande->getDemandeTravaux()->getTravauxDevisId());
            }

            if ($demande->getDemandeTravaux()->getFicheTechniqueId()) {
                $ficheTechnique = $this->ficheTechniqueRepository->find($demande->getDemandeTravaux()->getFicheTechniqueId());
            }
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
                                GET INSTRUCTION
        ///////////////////////////////////////////////////////////////// */
        $instruction = $this->instructionRepository->findOneBy([
            'demande_id' => $demandeId
        ]);

        /* /////////////////////////////////////////////////////////////////
                                CREATE FORM
        ///////////////////////////////////////////////////////////////// */
        $enabledDatesCp = $this->dateCpRepository->findBy(
            ['enabled' => 1],
            ['dateCP'  => 'DESC']
        );

        $form_dateCP = [];
        foreach ($enabledDatesCp as $item) {
            $numeroDeliberation = $item->getNumeroDeliberation() ?? 0;
            $form_dateCP[$item->getDateCP()->format('d/m/Y')] = $item->getId() . ' | ' . $numeroDeliberation;
        }

        $dateCP_object = $this->dateCpRepository->findOneBy([
            'id' => $demande->getDateCPId()
        ]);
        if ($dateCP_object && $dateCP_object->getNumeroDeliberation()) {
            $numeroDeliberation_form = $dateCP_object->getNumeroDeliberation();
        } else {
            $numeroDeliberation_form = 0;
        }

        $form = $this->adminFormService->assignDateCPType(
            $form_dateCP,
            $demande->getDateCPId() . ' | ' . $numeroDeliberation_form
        );

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            /* /////////////////////////////////////////////////////////////////
                                    SET DEMANDE STATUT
            ///////////////////////////////////////////////////////////////// */
            if ($form["dateCP"]->getData()) {
                $post_object = explode(" | ", $form["dateCP"]->getData());
                $demande->setDateCPId((int)$post_object[0]);
                $statut = $this->demandeServiceFO->searchStatutAuditEnergieForDateCP($demande->getStatutId());
            } else {
                $demande->setDateCPId(null);
                $statut = $this->demandeServiceFO->searchStatutForNoDateCP();
            }
            $demande->setStatutId($statut);

            $this->em->persist($demande);
            $this->em->flush();

            // MISE A JOUR DEMANDE STATUT DESCRIPTION
            $demande->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demande->getId()));
            $this->em->persist($demande);
            $this->em->flush();

            $userRoles = $this->getUser()->getRoles();

            if (Demande_::DEMANDE_AUDIT_ENERGIE_TYPE == $demande->getType()) {
                if ($demandeAuditN) {
                    /* /////////////////////////////////////////////////////////////////
                                    UPDATE DEMANDE AUDIT NUMERIQUE
                    ///////////////////////////////////////////////////////////////// */
                    $demandeAuditN->getDemandeAuditNumerique()->setStructureId($demande->getDemandeAuditEnergie()->getStructureId());
                    $demandeAuditN->getDemandeAuditNumerique()->setConseillerId($demande->getDemandeAuditEnergie()->getConseillerId());

                    $this->em->persist($demandeAuditN);
                    $this->em->flush();

                    /* /////////////////////////////////////////////////////////////////
                                            HISTORIQUE
                    ///////////////////////////////////////////////////////////////// */
                    $oldDemandeAuditNStatutId = $demandeAuditN->getStatutId();
                    $statut = $this->demandeServiceFO->searchStatutAuditNumeriqueForDateCP($oldDemandeAuditNStatutId);
                    $demandeAuditN->setStatutId($statut);

                    $demandeAuditN->getDemandeAuditNumerique()->setAuditeurId($demande->getDemandeAuditEnergie()->getAuditeurId());

                    $this->em->persist($demandeAuditN);
                    $this->em->flush();

                    // MISE A JOUR DEMANDE STATUT DESCRIPTION
                    $demandeAuditN->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demandeAuditN->getId()));
                    $this->em->persist($demandeAuditN);
                    $this->em->flush();

                    if (Demande_statut::STATUS_16 == $oldDemandeAuditNStatutId) {
                        $this->historiqueService->save(
                            $demandeAuditN->getId(),
                            $demandeAuditN->getStatutId(),
                            $demandeAuditN->getType(),
                            $userRoles,
                            false,
                            'Demande validé par la région'
                        );
                    }
                }
            }

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $demandeId,
                $demande->getStatutId(),
                $demande->getType(),
                $userRoles,
                true,
                'Attribution date de commission',
                $beneficiaire->getEmail(),
                $beneficiaire->getType()
            );

            $this->addFlash(
                'success',
                'La date de commission de la demande n°' . $demandeId . ' a bien été enregistrée.'
            );

            return $this->redirectToRoute('demande_list_all', []);
        }

        return $this->render('BackOffice/DateCP/assign.html.twig', [
            'form'          => $form->createView(),
            'demande'       => $demande,
            'beneficiaire'  => $beneficiaire,
            'logement'      => $logement,
            'instruction'   => $instruction,
            'devis'         => $devis,
            'ficheTechnique' => $ficheTechnique
        ]);
    }
    /**
     * @param int $dateCPId
     * @return Response
     * @throws Exception
     */
    public function export(int $dateCPId): Response
    {
        return $this->dateCPService->export($dateCPId);
    }
}

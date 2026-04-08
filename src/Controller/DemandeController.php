<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\DateRMH;
use App\Entity\Demande_;
use App\Entity\Demande_auditEnergie;
use App\Entity\Demande_auditNumerique;
use App\Entity\Demande_travaux;
use App\Entity\Demande_travaux_devis;
use App\Entity\Demande_travaux_devis_upload;
use App\Entity\ExportDemande;
use App\Entity\FicheTechnique;
use App\Entity\Historique_email;
use App\Entity\Historique_;
use App\Entity\Instruction_;
use App\Entity\Logement;
use App\Entity\Remboursement_;
use App\Entity\Remboursement_statut;
use App\Repository\Demande_Repository;
use App\Repository\Historique_Repository;
use App\Repository\BeneficiaireRepository;
use App\Form\Historique_emailType;
use App\Service\ANAHService;
use App\Service\CommentaireService;
use App\Service\DemandeServiceBO;
use App\Service\DemandeServiceFO;
use App\Service\HistoriqueService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Demande_statut;
use App\Service\AdminFormService;
use App\Form\Demande_Type;
use App\Service\DemandeAuditEnergieService;
use App\Service\DemandeAuditNumeriqueService;
use App\Service\DemandeTravauxService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

class DemandeController extends AbstractController
{
    private EntityManagerInterface $em;
    private FormFactoryInterface $formFactory;
    private DemandeServiceBO $demandeServiceBO;
    private AdminFormService $adminFormService;
    private ANAHService $ANAHService;
    private DemandeAuditEnergieService $demandeAuditEnergieService;
    private DemandeAuditNumeriqueService $demandeAuditNumeriqueService;
    private DemandeTravauxService $demandeTravauxService;
    private DemandeServiceFO $demandeServiceFO;
    private HistoriqueService $historiqueService;

    public function __construct(
        EntityManagerInterface $em,
        FormFactoryInterface $formFactory,
        DemandeServiceBO $demandeServiceBO,
        AdminFormService $adminFormService,
        ANAHService $ANAHService,
        DemandeAuditEnergieService $demandeAuditEnergieService,
        DemandeAuditNumeriqueService $demandeAuditNumeriqueService,
        DemandeTravauxService $demandeTravauxService,
        DemandeServiceFO $demandeServiceFO,
        HistoriqueService $historiqueService
    )
    {
        $this->em = $em;
        $this->formFactory = $formFactory;
        $this->demandeServiceBO = $demandeServiceBO;
        $this->adminFormService = $adminFormService;
        $this->ANAHService = $ANAHService;
        $this->demandeAuditEnergieService = $demandeAuditEnergieService;
        $this->demandeAuditNumeriqueService = $demandeAuditNumeriqueService;
        $this->demandeTravauxService = $demandeTravauxService;
        $this->demandeServiceFO = $demandeServiceFO;
        $this->historiqueService = $historiqueService;
    }

    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_EPCI') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN') or is_granted('ROLE_TECHNIQUE')")]
    public function listAll(): Response
    {
        $option = [
            'roles'                         => $this->getUser()->getRoles(),
            'username'                      => $this->getUser()->getUsername(),
            'production_travauxNiveau_BBC1' => $this->getParameter('production_travauxNiveau_BBC1'),
            'production_travauxNiveau_BBC2' => $this->getParameter('production_travauxNiveau_BBC2')
        ];

        /* /////////////////////////////////////////////////////////////////
                                GET COUNT DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demandeRepository = $this->em->getRepository(Demande_::class);
        $recordsTotal = $demandeRepository->countAll($option);

        /* /////////////////////////////////////////////////////////////////
                                GET FORM LIST
        ///////////////////////////////////////////////////////////////// */
        $form_export = $this->formFactory->createNamed('form_export');

        return $this->render('BackOffice/Demande/listAll.html.twig', [
            'recordsTotal' => $recordsTotal,
            'form_export'  => $form_export->createView()
        ]);
    }


    #[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function list(): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demandeRepository = $this->em->getRepository(Demande_::class);
        $list = $demandeRepository->findByType();

        return $this->render('BackOffice/Demande/list.html.twig', [
            'list_demande' => $list
        ]);
    }
    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_EPCI') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN') or is_granted('ROLE_TECHNIQUE')")]
    public function listAllAjax(Request $request): JsonResponse|bool
    {
        $postData = $request->request->all();

        if (!empty($postData)) {
            $option = [
                'roles' => $this->getUser()->getRoles(),
                'username' => $this->getUser()->getUsername(),
                'production_travauxNiveau_BBC1' => $this->getParameter('production_travauxNiveau_BBC1'),
                'production_travauxNiveau_BBC2' => $this->getParameter('production_travauxNiveau_BBC2')
            ];

            $demandeRepository = $this->em->getRepository(Demande_::class);
            $recordsTotal = $demandeRepository->countAll($option);

            /* START of POST variables coming from datatable */
            $draw = (int)$postData['draw'];
            $orderByColumnIndex = (int)$postData['order'][0]['column'];
            $orderBy = $postData['columns'][$orderByColumnIndex]['data'];
            $orderType = $postData['order'][0]['dir'];
            $start = (int)$postData['start'];
            $length = (int)$postData['length'];
            /* END of POST variables */

            /* START INIT of column search */
            $columnWhereTmp = [];
            for ($i = 0; $i < count($postData['columns']); $i++) {
                if ('' !== ($postData['columns'][$i]['search']['value'] ?? '')) {
                    $columnWhereTmp[] = $postData['columns'][$i]['search']['value'];
                }
            }
            /* END INIT of column search */

            /* START of search */
            $columnWhere = '';
            if (!empty($columnWhereTmp)) {
                $columnWhere = [];

                for ($i = 0; $i < count($postData['columns']); $i++) {
                    if ('' !== ($postData['columns'][$i]['search']['value'] ?? '')) {
                        $columnSearch = $postData['columns'][$i]['data'];
                        $searchValue = $postData['columns'][$i]['search']['value'];

                        switch ($columnSearch) {
                            case 'demandeId':
                                $columnWhere[] = "d.id LIKE \"%" . $searchValue . "%\"";
                                break;
                            case 'demandeType':
                                $columnDemandeType = 'd.type';
                                $columnDemandeNiveauSubstring = 'SUBSTRING(dtd.niveau, 1, 1)';

                                if (isset($searchValue)) {
                                    switch ($searchValue) {
                                        case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
                                        case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
                                        case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                                        case Demande_::DEMANDE_TRAVAUX_TYPE:
                                        case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                                            $columnDemandeType = $columnDemandeType . " = " . $searchValue;
                                            $columnDemandeNiveau = " dtd.niveau IS NULL ";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE:
                                            $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '0'";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE:
                                            $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '1'";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE:
                                            $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '2'";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_CODE:
                                            $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '3'";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_CODE:
                                            $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '4'";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE:
                                            $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '6'";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE:
                                            $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '7'";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_CODE:
                                            $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '8'";
                                            break;
                                        case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_CODE:
                                            $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                            $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '9'";
                                            break;
                                        default:
                                            $columnDemandeNiveau = '';
                                            $columnDemandeType = '';
                                            break;
                                    }
                                }
                                if ($columnDemandeType !== '' && $columnDemandeNiveau !== '') {
                                    $columnWhere[] = "(" . $columnDemandeType . " AND " . $columnDemandeNiveau . ")";
                                }
                                break;
                            case 'beneficiaireIdentifiant':
                                $columnWhere[] =
                                    "(b.nom LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "b.prenom LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "b.nom_SCI LIKE \"%" . $searchValue . "%\")";
                                break;
                            case 'logement':
                                $columnWhere[] =
                                    "(l.code_postal LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "l.ville LIKE \"%" . $searchValue . "%\")";
                                break;
                            case 'demandeDateCreation':
                                $columnWhere[] = "DATE_FORMAT(d.date_creation, '%d/%m/%Y') LIKE \"%" . $searchValue . "%\"";
                                break;
                            case 'demandeStatutSlug':
                                $columnWhere[] =
                                    "IF(rs.slug IS NOT NULL, rs.slug LIKE \"%" . $searchValue . "%\", ds.slug LIKE \"%" . $searchValue . "%\")";
                                break;
                            case 'structureConseiller':
                                $columnWhere[] =
                                    "(si_dae.nom LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "sc_dae.nom LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "si_dan.nom LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "sc_dan.nom LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "si_dt.nom LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "sc_dt.nom LIKE \"%" . $searchValue . "%\")";
                                break;
                            case 'partenaire':
                                $columnWhere[] =
                                    "(pi_dae.raison_sociale LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "pi_dan.raison_sociale LIKE \"%" . $searchValue . "%\"" .
                                    " OR " .
                                    "pi_dtd.raison_sociale LIKE \"%" . $searchValue . "%\")";
                                break;
                            case 'commissionDate':
                                $columnWhere[] = "DATE_FORMAT(dCP.date_CP, '%d/%m/%Y') LIKE \"%" . $searchValue . "%\"";
                                break;
                            case 'remboursementDate':
                                $columnWhere[] = "DATE_FORMAT(dRMH.date_RMH, '%d/%m/%Y') LIKE \"%" . $searchValue . "%\"";
                                break;
                        }
                    }
                }

                if (!empty($columnWhere)) {
                    $columnCopy = implode(" AND ", $columnWhere);
                    $columnWhere = " AND " . $columnCopy;
                } else {
                    $columnWhere = "";
                }

                // Search filtered result with limit and orderBy clauses
                $data = $demandeRepository->findAllAjax($option, $orderBy, $orderType, $start, $length, $columnWhere);
                $recordsFiltered = $demandeRepository->countAll($option, $columnWhere);
            } else {
                // Search all result with limit and orderBy clauses
                $data = $demandeRepository->findAllAjax($option, $orderBy, $orderType, $start, $length);
                $recordsFiltered = $recordsTotal;
            }
            /* END of search */

            $response = [
                "draw" => $draw,
                "recordsTotal" => $recordsTotal,
                "recordsFiltered" => $recordsFiltered,
                "data" => $data
            ];

            return new JsonResponse($response);
        } else {
            return false;
        }
    }
    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_INSTRUCTEUR_UP') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_EPCI') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN') or is_granted('ROLE_TECHNIQUE')")]
    public function createCommentaire(
        Request $request,
        int $demandeId,
        Demande_Repository $demandeRepository,
        Historique_Repository $historiqueRepository,
        BeneficiaireRepository $beneficiaireRepository,
        CommentaireService $commentaireService
    ): JsonResponse|Response {
        /* /////////////////////////////////////////////////////////////////
                                GET HISTORIQUE COMMENTAIRE
        ///////////////////////////////////////////////////////////////// */
        if ($demandeId <= 0) {
            throw new \Exception('Id de la Demande non valide');
        }

        $successMessage = 'Le commentaire de la Demande %s a été créé avec succès.';

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demande = $demandeRepository->find($demandeId);

        /* /////////////////////////////////////////////////////////////////
                        GET REMBOURSEMENT ID AND STATUT
        ///////////////////////////////////////////////////////////////// */
        $queryOption = [
            'demandeId' => $demandeId,
            'production_travauxNiveau_BBC1' => $this->getParameter('production_travauxNiveau_BBC1'),
            'production_travauxNiveau_BBC2' => $this->getParameter('production_travauxNiveau_BBC2')
        ];
        $statutData = $demandeRepository->findStatutByDemande($queryOption);
        $remboursementId = $statutData['remboursementId'] ?? null;
        $statutId = $statutData['statutId'];

        /* /////////////////////////////////////////////////////////////////
                                GET FORM OPTION
        ///////////////////////////////////////////////////////////////// */
        $formOption = $commentaireService->searchRecipientFormList($demandeId, $demande->getType());

        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $historiqueCommentaire = new Historique_email('');
        $form = $this->createForm(Historique_emailType::class, $historiqueCommentaire, [
            'trait_choices' => [$formOption]
        ]);

        // Check for form submission with correct form name
        $formCheckName = 'whitelabel_backofficebundle_historique_email';

        if ($request->request->has($formCheckName) && $request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $recipient = $form->get('recipient')->getData();
                $content = $form->get('content')->getData();

                /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                // Create historique with proper parameters
                $historique = $this->historiqueService->save(
                    $demandeId,
                    $statutId,
                    $demande->getType(),
                    $this->getUser()->getRoles(),
                    false,
                    'commentaire',
                    null,
                    null,
                    null,
                    null,
                    null,
                    true,
                    $remboursementId
                );

                /* /////////////////////////////////////////////////////////////////
                                        SAVE COMMENTAIRE
                ///////////////////////////////////////////////////////////////// */
                $beneficiaire = $beneficiaireRepository->find($demande->getBeneficiaireId());

                $emailData = $commentaireService->findEmailData(
                    $demandeId,
                    $demande->getType(),
                    $beneficiaire,
                    $this->getParameter('mailer_address_from')
                );

                // Update historiqueCommentaire with form data and email info
                $historiqueCommentaire->setContent($content);
                $historiqueCommentaire->setSender($emailData['from']);
                $historiqueCommentaire->setSubject($emailData['subject']);
                $historiqueCommentaire->setFormat($emailData['contentType']);
                $historiqueCommentaire->setRecipient($recipient);
                $historiqueCommentaire->setHistorique($historique);

                $isEmailSent = (count($recipient ?? []) > 0);
                $historique->setIsEmailSent($isEmailSent);

                $this->em->persist($historique);
                $this->em->persist($historiqueCommentaire);
                $this->em->flush();

                /* /////////////////////////////////////////////////////////////////
                                        SEND COMMENTAIRE
                ///////////////////////////////////////////////////////////////// */
                if (!empty($recipient)) {
                    $commentaireService->sendEmailComment($content, $recipient, $emailData);
                }

                $this->addFlash('success', sprintf($successMessage, $demandeId));
                return new JsonResponse([], 200);
            }

            // Form is invalid, return form view with errors
            return new JsonResponse(['form' => $this->renderView('BackOffice/Demande/inc/formCommentaire/_formView.html.twig', [
                'form' => $form->createView(),
                'listCommentaire' => [],
                'arrayRecipient' => []
            ])], 400);
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LIST COMMENTAIRE
        ///////////////////////////////////////////////////////////////// */
        $listCommentaire = $historiqueRepository->findCommentaire($demandeId);

        $arrayRecipient = [];
        foreach ($listCommentaire as $row) {
            $arrayRecipient[$row['historiqueId']] = unserialize($row['commentaireRecipient']);
        }

        $viewForm = $this->renderView('BackOffice/Demande/inc/formCommentaire/_formView.html.twig', [
            'form' => $form->createView(),
            'listCommentaire' => $listCommentaire,
            'arrayRecipient' => $arrayRecipient
        ]);

        return ($request->isMethod('POST')) ? new JsonResponse(['form' => $viewForm], 400) : new Response($viewForm, 200);
    }

    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function listDevis(DemandeServiceBO $demandeService): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET ALL DEVIS
        ///////////////////////////////////////////////////////////////// */
        $arrayDemandeStatutToExclude = [
            Demande_statut::STATUS_11,
            Demande_statut::STATUS_12,
            Demande_statut::STATUS_13,
            Demande_statut::STATUS_14,
            Demande_statut::STATUS_15
        ];

        /**
         * @var Demande_Repository $demandeRepository
         */
        $demandeRepository = $this->em->getRepository(Demande_::class);
        $list = $demandeRepository->findAllDevis(
            $this->getUser()->getRoles(),
            $this->getUser()->getUsername(),
            $arrayDemandeStatutToExclude
        );

        $listStatutForFicheTechniqueAccess = $demandeService->getListStatutForFicheTechniqueAccess();

        return $this->render('BackOffice/Demande/Devis/list.html.twig', [
            'listDevis'                         => $list,
            'listStatutForFicheTechniqueAccess' => $listStatutForFicheTechniqueAccess
        ]);
    }


     #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_EPCI') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN') or is_granted('ROLE_TECHNIQUE')")]
     public function view(string $demandeId): Response
     {
        $option = [
            'roles'     => $this->getUser()->getRoles(),
            'username'  => $this->getUser()->getUsername()
        ];

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->em->getRepository(Demande_::class);
        /**
         * @var Demande_ $demande_
         */
        $demande_ = $repo_demande->find($demandeId);

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo_beneficiaire = $this->em->getRepository(Beneficiaire::class);
        $beneficiaire = $repo_beneficiaire->findAllCustomById($demande_->getBeneficiaireId());

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesByRole($demande_, $option);

        $demande = null;
        $devis= null;
        $informationANAH = null;
        $devis_upload = null;
        $ficheTechnique = null;
        $remboursement = null;
        $remboursement2 = null;
        $isBBC1 = false;
        $isBBC2 = false;
        $auditeurId = null;
        $formTravauxDevisUpdateNiveauAide = null;
        $formAssignDemandeContacts = null;
        $remboursementStatutSlug = null;
        $remboursementDateRMH = null;
        $remboursement2StatutSlug = null;
        $remboursement2DateRMH = null;
        $showAuditeur = false;
        $showRenovateur = false;

        /* /////////////////////////////////////////////////////////////////
                                GET ADMIN FORM
        ///////////////////////////////////////////////////////////////// */

        $isNotEligible = false;
        $demandeTypeLabel = Demande_::$demandeType[$demande_->getType()];

        switch ($demande_->getType()) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                        GET DATA AUDIT ENERGIE
                ///////////////////////////////////////////////////////////////// */
                $repo_auditEnergie = $this->em->getRepository(Demande_auditEnergie::class);
                $demande = $repo_auditEnergie->findByIdCustom($demandeId);

                /* /////////////////////////////////////////////////////////////////
                                        GET ASSIGN AUDITEUR FORM DEMANDE
                ///////////////////////////////////////////////////////////////// */
                $showAuditeur = true;
                $formAssignDemandeContacts = $this->adminFormService->assignContactsType(
                    $showAuditeur,
                    false,
                    $demande_->getDemandeAuditEnergie()->getAuditeurId(),
                    null,
                    $beneficiaire['beneficiaireEmail'],
                    $demande_->getDemandeAuditEnergie()->getStructureId()
                );
                break;

            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                        GET DATA AUDIT NUMERIQUE
                ///////////////////////////////////////////////////////////////// */
                $repo_auditNumerique = $this->em->getRepository(Demande_auditNumerique::class);
                $demande = $repo_auditNumerique->findByIdCustom($demandeId);

                /* /////////////////////////////////////////////////////////////////
                                        GET ASSIGN AUDITEUR FORM DEMANDE
                ///////////////////////////////////////////////////////////////// */
                $showAuditeur = true;
                $formAssignDemandeContacts = $this->adminFormService->assignContactsType(
                    $showAuditeur,
                    false,
                    $demande_->getDemandeAuditNumerique()->getAuditeurId(),
                    null,
                    $beneficiaire['beneficiaireEmail'],
                    $demande_->getDemandeAuditNumerique()->getStructureId()
                );
                break;

            case Demande_::DEMANDE_TRAVAUX_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                        GET DATA TRAVAUX
                ///////////////////////////////////////////////////////////////// */
                $repo_travaux = $this->em->getRepository(Demande_travaux::class);
                $demande = $repo_travaux->findByIdCustom($demandeId, $this->getParameter('production_travauxNiveau_BBC2'));

                /* /////////////////////////////////////////////////////////////////
                                        GET DEVIS
                ///////////////////////////////////////////////////////////////// */
                if (null != $demande['devisId'])  {
                    $repo_devis = $this->em->getRepository(Demande_travaux_devis::class);
                    $devis = $repo_devis->findByIdCustom($demande['devisId']);

                    /* /////////////////////////////////////////////////////////////////
                                    CALCUL REVENU FISCAL DE REFERENCE
                    ///////////////////////////////////////////////////////////////// */
                    $demandeTravaux_nbPersFoyer = $demande['demandeNbPersFoyer'];
                    $demandeTravaux_revenuReference = $demande['demandeRevenuFoyer'];

                    $ANAH = $this->ANAHService->findPlafond($demandeTravaux_nbPersFoyer);

                    if ($demandeTravaux_revenuReference < $ANAH) {
                        $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_INFERIEUR_ANAH_KEY;
                    } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH*2)) {
                        $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_1_ET_2_FOIS_ANAH_KEY;
                    } elseif ($demandeTravaux_revenuReference > $ANAH && $demandeTravaux_revenuReference < ($ANAH*4)) {
                        $informationANAH = Demande_travaux_devis::REVENU_REFERENCE_COMPRIS_ENTRE_2_ET_4_FOIS_ANAH_KEY;
                    }

                    if(
                        $demandeTravaux_revenuReference
                        && $informationANAH == '2'
                        && !$demande['demandeAudit']
                    ) {
                        $isNotEligible = true;
                    }

                    /* /////////////////////////////////////////////////////////////////
                                            GET  DEVIS UPLOAD
                    ///////////////////////////////////////////////////////////////// */
                    $repo_devis_upload = $this->em->getRepository(Demande_travaux_devis_upload::class);
                    if ($devis)  {
                        $devis_upload = $repo_devis_upload->findAllCustomByDevisId($devis['devisId']);
                    }

                    $formTravauxDevisUpdateNiveauAide = $this->adminFormService->travauxDevisUpdateNiveauAideType(
                        $devis['devisNiveau'],
                        $devis['renovateurId']
                    );
                }

                /* /////////////////////////////////////////////////////////////////
                                        GET ASSIGN AUDITEUR FORM DEMANDE
                ///////////////////////////////////////////////////////////////// */
                $demandeTravauxNiveauArray = [];
                if ($devis && !empty($devis['devisNiveau'])) {
                    $demandeTravauxNiveauArray = explode(' | ', $devis['devisNiveau']);
                }

                if (!empty($demandeTravauxNiveauArray) && in_array($demandeTravauxNiveauArray[0], ['2', '3', '4'])) {
                    $showRenovateur = true;
                }
                $formAssignDemandeContacts = $this->adminFormService->assignContactsType(
                    false,
                    $showRenovateur,
                    null,
                    $devis['renovateurId'] ?? null,
                    $beneficiaire['beneficiaireEmail'],
                    $demande['structureId']
                );

                /* /////////////////////////////////////////////////////////////////
                        GET FICHE TECHNIQUE / GET REMBOURSEMENT FICHE TECHNIQUE
                ///////////////////////////////////////////////////////////////// */
                $repo_ficheTechnique = $this->em->getRepository(FicheTechnique::class);
                if (null != $demande['remboursementFicheTechniqueId']) {
                    $ficheTechnique = $repo_ficheTechnique->find($demande['remboursementFicheTechniqueId']);
                } else {
                    if (null != $demande['ficheTechniqueId']) {
                        $ficheTechnique = $repo_ficheTechnique->find($demande['ficheTechniqueId']);
                    }
                }

                if ($this->getParameter('production_travauxNiveau_BBC1') == $demande['titreNumeroOperation']) {
                    $isBBC1 = true;
                }

                if ($this->getParameter('production_travauxNiveau_BBC2') == $demande['titreNumeroOperation2']) {
                    $isBBC2 = true;

                    /* /////////////////////////////////////////////////////////////////
                                        GET REMBOURSEMENT CHEQUE 2
                    ///////////////////////////////////////////////////////////////// */
                    if (null != $demande['remboursementId2']) {
                        $repo_remboursement = $this->em->getRepository(Remboursement_::class);
                        $remboursement2 = $repo_remboursement->find($demande['remboursementId2']);

                        $repo_remboursementStatut = $this->em->getRepository(Remboursement_statut::class);
                        $remboursement2StatutObject = $repo_remboursementStatut->find($remboursement2->getStatutId());
                        $remboursement2StatutSlug = $remboursement2StatutObject->getSlug();

                        if ($remboursement2->getDateRMHId()) {
                            $repo_DateRMH = $this->em->getRepository(DateRMH::class);
                            $remboursement2DateRMHObject = $repo_DateRMH->find($remboursement2->getDateRMHId());
                            $remboursement2DateRMH = $remboursement2DateRMHObject->getDateRMH();
                        }
                    }
                }
                break;
        }

        /* /////////////////////////////////////////////////////////////////
                                GET REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        if ($demande && null != $demande['remboursementId']) {
            $repo_remboursement = $this->em->getRepository(Remboursement_::class);
            $remboursement = $repo_remboursement->find($demande['remboursementId']);

            $repo_remboursementStatut = $this->em->getRepository(Remboursement_statut::class);
            $remboursementStatutObject = $repo_remboursementStatut->find($remboursement->getStatutId());
            $remboursementStatutSlug = $remboursementStatutObject->getSlug();

            if ($remboursement->getDateRMHId()) {
                $repo_DateRMH = $this->em->getRepository(DateRMH::class);
                $remboursementDateRMHObject = $repo_DateRMH->find($remboursement->getDateRMHId());
                $remboursementDateRMH = $remboursementDateRMHObject->getDateRMH();
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $repo_logement = $this->em->getRepository(Logement::class);
        $logement = $repo_logement->find($demande_->getLogementId());

        /* /////////////////////////////////////////////////////////////////
                                GET DELETE FORM
        ///////////////////////////////////////////////////////////////// */
        $form_delete = $this
            ->get('form.factory')
            ->create()
        ;

        /* /////////////////////////////////////////////////////////////////
                                GET DENY FORM DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $form_deny_demande = $this->adminFormService->denyDemandeType();

        /* /////////////////////////////////////////////////////////////////
                                GET REACTIVE FORM
        ///////////////////////////////////////////////////////////////// */
        $form_reactive_demande = $this->formFactory->createNamed('form_reactive_demande');

        /* /////////////////////////////////////////////////////////////////
                                GET DENY FORM REMBOURSEMENT
        ///////////////////////////////////////////////////////////////// */
        $form_deny_remboursement = $this->adminFormService->denyRemboursementType();

        if (isset($beneficiaire['beneficiaireType'])) {
            $beneficiaireTypeKey = explode(' | ', $beneficiaire['beneficiaireType'])[0];
        }

        return $this->render('BackOffice/Demande/view.html.twig', [
            'demande'                               => $demande,
            'devis'                                 => $devis,
            'informationANAH'                       => $informationANAH,
            'devis_upload'                          => $devis_upload,
            'ficheTechnique'                        => $ficheTechnique,
            'remboursement'                         => $remboursement,
            'beneficiaire'                          => $beneficiaire,
            'beneficiaireTypeKey'                   => $beneficiaireTypeKey,
            'logement'                              => $logement,
            'form_delete'                           => $form_delete->createView(),
            'form_deny_demande'                     => $form_deny_demande->createView(),
            'form_reactive_demande'                 => $form_reactive_demande->createView(),
            'form_deny_remboursement'               => $form_deny_remboursement->createView(),
            'form_assign_demande_contacts'          => ($formAssignDemandeContacts) ? $formAssignDemandeContacts->createView() : null,
            'form_travaux_devis_update_niveau_aide' => ($formTravauxDevisUpdateNiveauAide) ? $formTravauxDevisUpdateNiveauAide->createView() : null,
            'isBBC1'                                => $isBBC1,
            'isBBC2'                                => $isBBC2,
            'remboursement2'                        => $remboursement2,
            'remboursementStatutSlug'               => $remboursementStatutSlug,
            'remboursementDateRMH'                  => $remboursementDateRMH,
            'remboursement2StatutSlug'              => $remboursement2StatutSlug,
            'remboursement2DateRMH'                 => $remboursement2DateRMH,
            'showAuditeur'                          => $showAuditeur,
            'showRenovateur'                        => $showRenovateur,
            'isNotEligible'                         => $isNotEligible,
            'arrayDemandeTypeNiveau'                => array_flip(Demande_travaux_devis::$arrayDemandeTypeNiveau),
            'demandeTravauxDevisInstance'           => new Demande_travaux_devis(),
            'demandeTypeLabel'                      => isset($demandeTypeLabel) ? $demandeTypeLabel : null,
            'demandeConseillerId'                   => $demande['conseillerId']
        ]);
    }

     #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function edit(Request $request, $demandeId): Response
    {
        $arrayDemandeStatutKeep = [
            Demande_statut::STATUS_11,
            Demande_statut::STATUS_12,
        ];
        $remboursementAuditStatutId = '';
        $demandeData = [];
        $demandeConseillerId = null;

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demande_Repository = $this->em->getRepository(Demande_::class);
        /**
         * @var Demande_ $demande
         */
        $demande = $demande_Repository->find($demandeId);

        switch ($demande->getType()) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                CAS DEMANDE AUDIT ENERGETIQUE ET SCENARIOS
                                ET AUDIT ENERGETIQUE REGION
                ///////////////////////////////////////////////////////////////// */
                $dataForEditAction = $this->demandeAuditEnergieService->getDataForEditAction(
                    false,
                    $demandeId,
                    $this->getUser(),
                    true
                );

                // On recupere les objets à jour
                $beneficiaire = $dataForEditAction['beneficiaire'];
                $logement = $dataForEditAction['logement'];
                $demande = $dataForEditAction['demande'];
                $demandeData = $dataForEditAction['demandeData'];

                $form = $this->createForm(Demande_Type::class, $demande, [
                    'trait_choices' => $dataForEditAction['formOption']
                ]);
                $form->remove('demande_auditNumerique');
                $form->remove('demande_travaux');
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $dataForEditActionSubmitted = $this->demandeAuditEnergieService->manageAndGetDataForEditActionSubmitted(
                        $request,
                        false,
                        $beneficiaire,
                        $logement,
                        $demande,
                        $this->getUser()->getRoles(),
                        $dataForEditAction['nbPersFoyerOld'],
                        $dataForEditAction['revenuFoyerOld'],
                        $arrayDemandeStatutKeep
                    );

                    if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                        return $this->redirectToRoute(
                            $dataForEditActionSubmitted['routeName'],
                            $dataForEditActionSubmitted['routeParams']
                        );
                    }
                }
                $demandeConseillerId = $demande->getDemandeAuditEnergie()->getConseillerId();
                break;
            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                /* /////////////////////////////////////////////////////////////////
                                  CAS DEMANDE AUDIT NUMERIQUE
                             ET MISE A JOUR AUDIT ENERGETIQUE ET SCENARIOS
                ///////////////////////////////////////////////////////////////// */
                $dataForEditAction = $this->demandeAuditNumeriqueService->getDataForEditAction(
                    false,
                    $demandeId,
                    $this->getUser(),
                    true
                );

                // On recupere les objets à jour
                $beneficiaire = $dataForEditAction['beneficiaire'];
                $logement = $dataForEditAction['logement'];
                $demande = $dataForEditAction['demande'];
                $isDoublon = $dataForEditAction['isDoublon'];
                $demandeData = $dataForEditAction['demandeData'];

                /* /////////////////////////////////////////////////////////////////
                                            GET FORM
                ///////////////////////////////////////////////////////////////// */
                $form = $this->createForm(Demande_Type::class, $demande, [
                    'trait_choices' => $dataForEditAction['formOption']
                ]);
                $form->remove('demande_auditEnergie');
                $form->remove('demande_travaux');
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $dataForEditActionSubmitted = $this->demandeAuditNumeriqueService->manageAndGetDataForEditActionSubmitted(
                        $request,
                        false,
                        $beneficiaire,
                        $demande,
                        $this->getUser()->getRoles(),
                        $isDoublon,
                        $arrayDemandeStatutKeep
                    );
                    if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                        return $this->redirectToRoute(
                            $dataForEditActionSubmitted['routeName'],
                            $dataForEditActionSubmitted['routeParams']
                        );
                    }
                }
                $demandeConseillerId = $demande->getDemandeAuditNumerique()->getConseillerId();
                break;
            case Demande_::DEMANDE_TRAVAUX_TYPE:
                $dataForEditAction = $this->demandeTravauxService->getDataForEditAction(
                    false,
                    $demandeId,
                    $this->getUser(),
                    true
                );

                // On recupere les objets à jour
                $beneficiaire = $dataForEditAction['beneficiaire'];
                $logement = $dataForEditAction['logement'];
                $demande = $dataForEditAction['demande'];
                $auditE = $dataForEditAction['auditE'];
                $remboursementAuditStatutId = $dataForEditAction['remboursementAuditStatutId'];
                $demandeData = $dataForEditAction['demandeData'];

                $form = $this->createForm(Demande_Type::class, $demande, [
                    'trait_choices' => $dataForEditAction['formOption']
                ]);
                $form->remove('demande_auditEnergie');
                $form->remove('demande_auditNumerique');
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $dataForEditActionSubmitted = $this->demandeTravauxService->manageAndGetDataForEditActionSubmitted(
                        $request,
                        false,
                        $beneficiaire,
                        $logement,
                        $demande,
                        $this->getUser()->getRoles(),
                        $auditE,
                        $dataForEditAction['nbPersFoyerOld'],
                        $dataForEditAction['revenuFoyerOld'],
                        $arrayDemandeStatutKeep
                    );

                    if (!empty($dataForEditActionSubmitted['isRedirectToRoute'])) {
                        return $this->redirectToRoute(
                            $dataForEditActionSubmitted['routeName'],
                            $dataForEditActionSubmitted['routeParams']
                        );
                    }
                }
                $demandeConseillerId = $demande->getDemandeTravaux()->getConseillerId();
                break;
        }

        $demandeTypeLabel = Demande_::$demandeType[$demande->getType()];

        return $this->render('BackOffice/Demande/edit.html.twig', [
            'form'                       => $form->createView(),
            'demandeData'                => $demandeData,
            'beneficiaire'               => $beneficiaire,
            'logement'                   => $logement,
            'remboursementAuditStatutId' => $remboursementAuditStatutId,
            'demandeTypeLabel'           => isset($demandeTypeLabel) ? $demandeTypeLabel : null,
            'demandeConseillerId'        => $demandeConseillerId
        ]);
    }


     #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_EPCI') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN') or is_granted('ROLE_TECHNIQUE')")]
    public function generateFicheLiaison(string $demandeId): Response
     {
        $option = array(
            'roles'     => $this->getUser()->getRoles(),
            'username'  => $this->getUser()->getUsername()
        );

        /* /////////////////////////////////////////////////////////////////
                            GET DATA FOR FICHE LIAISON
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->em->getRepository(Demande_::class);
        $dataFicheLiaison = $repo_demande->findDataFicheLiaison($demandeId);

        /**
         * @var Demande_ $demande
         */
        $demande = $repo_demande->find($demandeId);

        /* /////////////////////////////////////////////////////////////////
                    CHECK DEMANDE ACCESS CONTROLE BY DEMANDE STATUT
        ///////////////////////////////////////////////////////////////// */
        if ($demande->getStatutId() != Demande_statut::STATUS_14) {
            throw new AccessDeniedHttpException();
        }

        /* /////////////////////////////////////////////////////////////////
                           CHECK DEMANDE ACCESS CONTROLE BY ROLES
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceBO->checkAccesByRole($demande, $option);

        /* /////////////////////////////////////////////////////////////////
                                EXPORT PDF
        ///////////////////////////////////////////////////////////////// */
        $this->demandeServiceFO->createFicheLiaison($dataFicheLiaison);

        return new Response(null, 200);
    }

     #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function deny(Request $request, string $demandeId): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->em->getRepository(Demande_::class);
        $demande = $repo_demande->find($demandeId);

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo_beneficiaire = $this->em->getRepository(Beneficiaire::class);
        $beneficiaire = $repo_beneficiaire->findOneBy(array(
            'id' => $demande->getBeneficiaireId()
        ));

        /* /////////////////////////////////////////////////////////////////
                                GET LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        $repo_logement = $this->em->getRepository(Logement::class);
        $logement = $repo_logement->findOneBy(array(
            'id' => $demande->getLogementId()
        ));

        /* /////////////////////////////////////////////////////////////////
                                GET DENY FORM
        ///////////////////////////////////////////////////////////////// */
        $form_deny = $this->adminFormService->denyDemandeType();
        $form_deny->handleRequest($request);

        if ($form_deny->isSubmitted() && $form_deny->isValid()) {
            $demande->setDateModif(new \DateTime());
            $demande->setAuteurModif($_SESSION['login']->getUsername());

            $demande->setMotifRefus(htmlspecialchars($form_deny["motifRefus"]->getData()));

            $statut = $this->demandeServiceFO->searchStatutRefus();
            $demande->setStatutId($statut);

            /* /////////////////////////////////////////////////////////////////
                                    AUDIT NUMERIQUE
            ///////////////////////////////////////////////////////////////// */
            $demande_auditNumerique = $repo_demande->findOneBy(
                array(
                    'type'        => Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
                    'logement_id' => $demande->getLogementId()
                ), array('id' => 'DESC')
            );
            if ($demande_auditNumerique) {
                $demande_auditNumerique->setStatutId($this->demandeServiceFO->searchStatutRefus());
                $this->em->persist($demande_auditNumerique);
            }

            $this->em->persist($demande);
            $this->em->flush();

            // MISE A JOUR DEMANDE STATUT DESCRIPTION
            $demande->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demande->getId()));
            $this->em->persist($demande);
            $this->em->flush();

            // MISE A JOUR DEMANDE STATUT DESCRIPTION
            if (!empty($demande_auditNumerique)) {
                $demande_auditNumerique->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demande_auditNumerique->getId()));
                $this->em->persist($demande_auditNumerique);
                $this->em->flush();
            }

            /* /////////////////////////////////////////////////////////////////
                                    FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $demandeId = $demande->getId();
            $userRoles = $this->getUser()->getRoles();
            $beneficiaireEmail = $beneficiaire->getEmail();
            $beneficiaireType = $beneficiaire->getType();

            $documentJPAlt = null;
            $documentKBISAlt = null;
            $documentAIAlt = null;

            if (Demande_::DEMANDE_AUDIT_ENERGIE_TYPE == $demande->getType()
                || Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE == $demande->getType()
            ) {
                $documentJPAlt = $demande->getDemandeAuditEnergie()->getJustificatifProprieteAlt();
                $documentKBISAlt = $demande->getDemandeAuditEnergie()->getPieceComplementAlt();
            } elseif (Demande_::DEMANDE_TRAVAUX_TYPE == $demande->getType()) {
                $documentJPAlt = $demande->getDemandeTravaux()->getJustificatifProprieteAlt();
                $documentKBISAlt = $demande->getDemandeTravaux()->getPieceComplementAlt();
                $documentAIAlt = $demande->getDemandeTravaux()->getAvisImpositionAlt();
            }

            $this->historiqueService->save(
                $demandeId,
                $statut,
                $demande->getType(),
                $userRoles,
                true,
                'Demande refusée directement par la Région',
                $beneficiaireEmail,
                $beneficiaireType,
                $documentJPAlt,
                $documentKBISAlt,
                $documentAIAlt
            );

            $request->getSession()->getFlashBag()->add(
                'success',
                'La Demande n°' . $demandeId . ' a bien été refusée.'
            );

            return $this->redirectToRoute('demande_list_all', array());
        }

        return $this->redirectToRoute('demande_list_all', array());
    }


     #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function assignContacts(Request $request, string $demandeId): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->em->getRepository(Demande_::class);
        /**
         * @var Demande_ $demande
         */
        $demande = $repo_demande->find($demandeId);

        $demande_auditEnergie = null;

        /* /////////////////////////////////////////////////////////////////
                                GET ASSIGN AUDITEUR FORM DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $form_assign_demande_contacts = $this->adminFormService->assignContactsType();
        $form_assign_demande_contacts->handleRequest($request);

        if ($form_assign_demande_contacts->isSubmitted() && $form_assign_demande_contacts->isValid()) {

            $demande->setDateModif(new \DateTime());
            $demande->setAuteurModif($this->getUser()->getUsername());

            /* /////////////////////////////////////////////////////////////////
                                    GET BENEFICIAIRE
            ///////////////////////////////////////////////////////////////// */
            $repo_beneficiaire = $this->em->getRepository(Beneficiaire::class);
            /**
             * @var Beneficiaire $beneficiaire
             */
            $beneficiaire = $repo_beneficiaire->find($demande->getBeneficiaireId());

            $beneficiaire->setEmail($form_assign_demande_contacts['beneficiaireEmail']->getData());

            $auditeurId = ($form_assign_demande_contacts['auditeur_id']->getData()) ? $form_assign_demande_contacts['auditeur_id']->getData()->getId() : null;
            $renovateurId = ($form_assign_demande_contacts['renovateur_id']->getData()) ? $form_assign_demande_contacts['renovateur_id']->getData()->getId() : null;
            $structureId = ($form_assign_demande_contacts['structure_id']->getData()) ? $form_assign_demande_contacts['structure_id']->getData()->getId() : null;
            $conseillerId = ($form_assign_demande_contacts['conseiller_id']->getData()) ? $form_assign_demande_contacts['conseiller_id']->getData()->getId() : null;

            switch ($demande->getType()) {
                case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
                case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
                case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                    if (Demande_::DEMANDE_AUDIT_ENERGIE_TYPE == $demande->getType()
                        || Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE == $demande->getType()
                    ) {
                        $demande_audit = $demande->getDemandeAuditEnergie();
                    } else {
                        $demande_audit = $demande->getDemandeAuditNumerique();
                    }

                    $demande_audit->setStructureId($structureId);
                    $demande_audit->setConseillerId($conseillerId);

                    $beneficiaire->setStructureRattachementId($structureId);
                    $beneficiaire->setConseillerRattachementId($conseillerId);

                    if ($demande_audit && $auditeurId) {
                        $demande_audit->setAuditeurId($auditeurId);

                        /**
                         * @var Demande_ $demandeForTravaux
                         */
                        $demandeForTravaux = $repo_demande->findOneBy(
                            [
                                'logement_id' => $demande->getLogementId(),
                                'type'        => Demande_::DEMANDE_TRAVAUX_TYPE
                            ],
                            [
                                'id' => 'DESC'
                            ]
                        );

                        if (!empty($demandeForTravaux)) {
                            $repo_demande_travaux_devis = $this->em->getRepository(Demande_travaux_devis::class);
                            /**
                             * @var Demande_travaux_devis $demandeTravauxDevis
                             */
                            if($demandeForTravaux->getDemandeTravaux()->getTravauxDevisId()){
                                $demandeTravauxDevis = $repo_demande_travaux_devis->find($demandeForTravaux->getDemandeTravaux()->getTravauxDevisId());
                                if (!empty($demandeTravauxDevis)) {
                                    $demandeTravauxDevis->setAuditeurId($auditeurId);
                                    $this->em->persist($demandeTravauxDevis);
                                }
                            }
                        }
                    }
                    break;
                case Demande_::DEMANDE_TRAVAUX_TYPE:
                    $demandeTravaux = $demande->getDemandeTravaux();

                    $demandeTravaux->setStructureId($structureId);
                    $demandeTravaux->setConseillerId($conseillerId);

                    $beneficiaire->setStructureRattachementId($structureId);
                    $beneficiaire->setConseillerRattachementId($conseillerId);

                    $demande_travauxDevisId = ($demandeTravaux) ? $demandeTravaux->getTravauxDevisId() : null;
                    if ($demande_travauxDevisId && $renovateurId) {
                        $repo_travaux_devis = $this->em->getRepository(Demande_travaux_devis::class);

                        /**
                         * @var Demande_travaux_devis $demande_travauxDevis;
                         */
                        $demande_travauxDevis = $repo_travaux_devis->find($demande_travauxDevisId);
                        $demande_travauxDevis->setRenovateurId($renovateurId);

                        $this->em->persist($demande_travauxDevis);
                    }
                    break;

                default:
                    throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
                    break;
            }

            $this->em->persist($beneficiaire);

            $this->em->flush();
            $this->em->clear();

            /* /////////////////////////////////////////////////////////////////
                        FILL UP HISTORIQUE DEMANDE
            ///////////////////////////////////////////////////////////////// */
            /* /////////////////////////////////////////////////////////////////
                                    GET REMBOURSEMENT
            ///////////////////////////////////////////////////////////////// */
            $repo_remboursement = $this->em->getRepository(Remboursement_::class);
            $remboursement = $repo_remboursement->findOneBy(array(
                'demande_id' => $demandeId
            ));

            if ($remboursement) {
                $this->historiqueService->save(
                    $demandeId,
                    $remboursement->getStatutId(),
                    $demande->getType(),
                    $this->getUser()->getRoles(),
                    false,
                    'Modification des contacts',
                    null,
                    null,
                    null,
                    null,
                    null,
                    false,
                    $remboursement->getId()
                );

            } else {
                $this->historiqueService->save(
                    $demandeId,
                    $demande->getStatutId(),
                    $demande->getType(),
                    $this->getUser()->getRoles(),
                    false,
                    'Modification des contacts'
                );
            }

            $request->getSession()->getFlashBag()->add(
                'success',
                'Les contacts ont bien été mis à jour.'
            );

            return $this->redirectToRoute('demande_list_all', array());
        }

        return $this->redirectToRoute('demande_list_all', array());
    }

     #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function updateNiveauAideTravauxDevis(Request $request, string $demandeId, string $devisId): Response
    {
        $formTravauxDevisUpdateNiveauAide = $this->adminFormService->travauxDevisUpdateNiveauAideType();
        $formTravauxDevisUpdateNiveauAide->handleRequest($request);

        if ($formTravauxDevisUpdateNiveauAide->isSubmitted() && $formTravauxDevisUpdateNiveauAide->isValid()) {
            $this->demandeServiceBO->saveUpdateNiveauAideTravauxDevisAction(
                $demandeId,
                $devisId,
                $request,
                $formTravauxDevisUpdateNiveauAide,
                $this->getUser()->getRoles()
            );
        }

        return $this->redirectToRoute('demande_list_all', []);
    }

     #[Security("is_granted('ROLE_ADMIN')")]
    public function delete(Request $request, string $demandeId): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->em->getRepository(Demande_::class);
        $demande = $repo_demande->find($demandeId);

        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE FILE
        ///////////////////////////////////////////////////////////////// */
        $uploadDir = $this->getParameter('app_root_dossier_data_symfony');
        $oldFile_justificatifPropriete = null;
        $oldFile_pieceComplement = null;
        $oldFile_avisImposition = null;
        $oldFile_audit = null;
        $oldFile_ficheTechniqueDocument = null;
        $demande_auditNumerique = null;
        $devis = null;
        $ficheTechnique = null;

        switch ($demande->getType()) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                $webPath_justificatifPropriete = $demande->getDemandeAuditEnergie()->justificatifPropriete_getWebPath();
                $webPath_pieceComplement = $demande->getDemandeAuditEnergie()->pieceComplement_getWebPath();

                $oldFile_justificatifPropriete = $uploadDir . $webPath_justificatifPropriete;
                $oldFile_pieceComplement = $uploadDir . $webPath_pieceComplement;

                if (Demande_::DEMANDE_AUDIT_ENERGIE_TYPE == $demande->getType()) {
                    /* /////////////////////////////////////////////////////////////////
                                            AUDIT NUMERIQUE
                    ///////////////////////////////////////////////////////////////// */
                    $demande_auditNumerique = $repo_demande->findOneBy(
                        array(
                            'type'        => Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
                            'logement_id' => $demande->getLogementId()
                        ), array('id' => 'DESC')
                    );
                }

                break;
            case Demande_::DEMANDE_TRAVAUX_TYPE:
                $webPath_justificatifPropriete = $demande->getDemandeTravaux()->justificatifPropriete_getWebPath();
                $webPath_pieceComplement = $demande->getDemandeTravaux()->pieceComplement_getWebPath();
                $webPath_avisImposition = $demande->getDemandeTravaux()->avisImposition_getWebPath();

                $oldFile_justificatifPropriete = $uploadDir . $webPath_justificatifPropriete;
                $oldFile_pieceComplement = $uploadDir . $webPath_pieceComplement;
                $oldFile_avisImposition = $uploadDir . $webPath_avisImposition;

                /* /////////////////////////////////////////////////////////////////
                                        GET DEVIS
                ///////////////////////////////////////////////////////////////// */
                if (null != $demande->getDemandeTravaux()->getTravauxDevisId())  {
                    $repo_devis = $this->em->getRepository(Demande_travaux_devis::class);
                    $devis = $repo_devis->find($demande->getDemandeTravaux()->getTravauxDevisId());

                    $webPath_audit = $devis->audit_getWebPath();
                    $oldFile_audit = $uploadDir . $webPath_audit;

                    $oldFile_devisUpload = array();
                    $devisUpload = $devis->getDemandeTravauxDevisUpload();
                    foreach ($devisUpload as $item) {
                        $webPath_devisUpload = $item->devisDocument_getWebPath();

                        $oldFile_devisUpload[] = $uploadDir . $webPath_devisUpload;
                    }
                }

                /* /////////////////////////////////////////////////////////////////
                                        GET FICHE TECHNIQUE
                ///////////////////////////////////////////////////////////////// */
                if (null != $demande->getDemandeTravaux()->getFicheTechniqueId())  {
                    $repo_ficheTechnique = $this->em->getRepository(FicheTechnique::class);
                    $ficheTechnique = $repo_ficheTechnique->find($demande->getDemandeTravaux()->getFicheTechniqueId());

                    $webPath_ficheTechniqueDocument = $ficheTechnique->getFicheTechniquePrescription()->ficheTechniqueDocument_getWebPath();
                    $oldFile_ficheTechniqueDocument = $uploadDir . $webPath_ficheTechniqueDocument;
                }
                break;
            default:
                break;
        }

        /* /////////////////////////////////////////////////////////////////
                                GET INSTRUCTION
        ///////////////////////////////////////////////////////////////// */
        $repo_instruction = $this->em->getRepository(Instruction_::class);
        $instruction = $repo_instruction->findBy(array('demande_id' => $demandeId));

        /* /////////////////////////////////////////////////////////////////
                                GET DELETE FORM
        ///////////////////////////////////////////////////////////////// */
        $form = $this->createFormBuilder()
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                if ($oldFile_justificatifPropriete && file_exists($oldFile_justificatifPropriete)) unlink($oldFile_justificatifPropriete);
                if ($oldFile_pieceComplement && file_exists($oldFile_pieceComplement)) unlink($oldFile_pieceComplement);
                if ($oldFile_avisImposition && file_exists($oldFile_avisImposition)) unlink($oldFile_avisImposition);
                if ($oldFile_audit && file_exists($oldFile_audit)) unlink($oldFile_audit);
                if ($oldFile_ficheTechniqueDocument && file_exists($oldFile_ficheTechniqueDocument)) unlink($oldFile_ficheTechniqueDocument);

                if (!empty($oldFile_devisUpload)) {
                    foreach ($oldFile_devisUpload as $item) {
                        if (file_exists($item)) unlink($item);
                    }
                }

                $this->em->remove($demande);
                if ($demande_auditNumerique) $this->em->remove($demande_auditNumerique);
                if ($instruction) $this->em->remove($instruction[0]);
                if ($devis) $this->em->remove($devis);
                if ($ficheTechnique) $this->em->remove($ficheTechnique);
                $this->em->flush();
            }

            $request->getSession()->getFlashBag()->add(
                'success',
                'La Demande n°' . $demandeId . ' a bien été supprimée.'
            );

            return $this->redirectToRoute('demande_list_all', array());
        }

        return $this->redirectToRoute('demande_list_all', array());
    }


     #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function reactive(Request $request, string $demandeId): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $demandeRepository = $this->em->getRepository(Demande_::class);
        /**
         * @var Demande_ $demande
         */
        $demande = $demandeRepository->find($demandeId);

        /* /////////////////////////////////////////////////////////////////
                    CHECK DEMANDE ACCESS CONTROLE BY DEMANDE STATUT
        ///////////////////////////////////////////////////////////////// */
        if ($demande->getStatutId() != Demande_statut::STATUS_15) {
            throw new AccessDeniedHttpException();
        }

        /* /////////////////////////////////////////////////////////////////
                                GET REACTIVE FORM
        ///////////////////////////////////////////////////////////////// */
        $form = $this->formFactory->createNamed('form_reactive_demande');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->demandeServiceBO->savePreviousStatutAndHistoriqueAfterReactivation(
                $demande,
                $this->getUser()->getRoles()
            )) {
                // after changed demande statut => update demande statut description
                $demandeStatutDescription = $this->demandeServiceFO->findStatutDescriptionByDemande($demande->getId());
                $demande->setStatutDescription(!empty($demandeStatutDescription) ? $demandeStatutDescription : null);
                $this->em->flush();

                $request->getSession()->getFlashBag()->add(
                    'success',
                    'La Demande n°' . $demandeId . ' a bien été réactivée.'
                );
            } else {
                $request->getSession()->getFlashBag()->add(
                    'warning',
                    'La Demande n°' . $demandeId . ' n\'a pas pu être réactivée'
                );
            }

        }

        return $this->redirectToRoute('demande_list_all', array());
    }


     #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_EPCI') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function export(Request $request): Response
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        /* /////////////////////////////////////////////////////////////////
                                GET FORM EXPORT
        ///////////////////////////////////////////////////////////////// */
        $form_export = $this->formFactory->createNamed('form_export');
        $form_export->handleRequest($request);

        if ($form_export->isSubmitted() && $form_export->isValid()) {

            /* /////////////////////////////////////////////////////////////////
                                OBJECT - EXPORT DEMANDE
            ///////////////////////////////////////////////////////////////// */
            $exportDemande = new ExportDemande();
            $exportDemande->setDestinataireUserId($this->getUser()->getId());

            $fields = [
                'demandeId'               => $request->request->get('demandeId'),
                'demandeType'             => $request->request->get('demandeType'),
                'beneficiaireIdentifiant' => $request->request->get('beneficiaireIdentifiant'),
                'logement'                => $request->request->get('logement'),
                'demandeDateCreation'     => $request->request->get('demandeDateCreation'),
                'demandeStatutSlug'       => $request->request->get('demandeStatutSlug'),
                'structureConseiller'     => $request->request->get('structureConseiller'),
                'partenaire'              => $request->request->get('partenaire'),
                'commissionDate'          => $request->request->get('commissionDate'),
                'remboursementDate'       => $request->request->get('remboursementDate')
            ];

            $whereFormFilter = $this->demandeServiceBO->getWhereFormFilter($fields);

            $exportDemande->setWhereQuery($whereFormFilter);
            $this->em->persist($exportDemande);
            $this->em->flush();
            $exportId = $exportDemande->getId();
            $this->em->clear();

            // Asynchronous command
            $process = new Process([
                'php',
                'bin/console',
                'normandie:exportDemande',
                '--exportId=' . $exportId,
                '--env=' . $this->getParameter('kernel.environment')
            ]);

            $process->setWorkingDirectory($this->getParameter('kernel.project_dir'));
            $process->run();

            $request->getSession()->getFlashBag()->add(
                'success',
                'La demande d\'export a été enregistrée avec succès.'
            );
        }

        return $this->redirectToRoute('demande_list_all', array());
    }
}

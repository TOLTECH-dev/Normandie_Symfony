<?php

namespace App\Controller;

use App\Entity\Partenaire_;
use App\Entity\User;
use App\Entity\Rating;
use App\Form\Partenaire_Type;
use App\Service\AdminCoordonneeService;
use App\Service\AdminService;
use App\Service\PartenaireService;
use Doctrine\ORM\EntityManagerInterface;
use IBAN\Validation\IBANValidator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartenaireController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }
    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function list(string $type): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET PARTENAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo = $this->em->getRepository(Partenaire_::class);

        $title = '';
        $list = [];
        if ('0' === $type) {
            $title = 'auditeur';
            $list = $repo->findByType($type . ' | auditeur');
        } elseif ('1' === $type) {
            $title = 'rénovateur';
            $list = $repo->findByType($type . ' | renovateur');
        }

        return $this->render('BackOffice/Partenaire/list.html.twig', [
            'list_partenaire' => $list,
            'title' => $title,
            'type' => $type
        ]);
    }

    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function view(int $partenaireId, string $type): Response
    {
        $repo = $this->em->getRepository(Partenaire_::class);
        $partenaire = $repo->find($partenaireId);

        // Format dates for display (don't modify entity)
        $dateInactif = $partenaire->getPartenaireStatut()->getDateInactif()?->format('d/m/Y');
        $dateRattachement = $partenaire->getPartenaireStatut()->getDateRattachement()?->format('d/m/Y');

        $title = '';
        if ('0' === $type) {
            $title = 'auditeur';
        } elseif ('1' === $type) {
            $title = 'rénovateur';
        }

        return $this->render('BackOffice/Partenaire/view.html.twig', [
            'partenaire' => $partenaire,
            'title' => $title,
            'type' => $type,
            'dateInactif' => $dateInactif,
            'dateRattachement' => $dateRattachement,
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function viewRating(int $partenaireId, int $partenaireType): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                    GET AUDITEUR
        ///////////////////////////////////////////////////////////////// */
        $repo_partenaire = $this->em->getRepository(Partenaire_::class);
        if (0 !== $partenaireId) {
            $auditeur = $repo_partenaire->find($partenaireId);
        } else {
            throw new \Exception("Id non valide");
        }

        if (-1 !== $partenaireType) {
            if (0 === $partenaireType) {
                $prefixUser = 'A';
                $typeRating = $this->getParameter('rating_from_conseiller_to_auditeur');
            } elseif (1 === $partenaireType) {
                $prefixUser = 'R';
                $typeRating = $this->getParameter('rating_from_region_to_renovateur');
            } else {
                throw new \Exception('Le Partenaire type est invalide.');
            }
        } else {
            throw new \Exception('Le Partenaire type est invalide.');
        }

        /* /////////////////////////////////////////////////////////////////
                                    GET USER
        ///////////////////////////////////////////////////////////////// */
        $repo_user = $this->em->getRepository(User::class);
        $user = $repo_user->findOneBy([
            'username' => $prefixUser . str_pad((string)$partenaireId, 5, '0', STR_PAD_LEFT)
        ]);

        /* /////////////////////////////////////////////////////////////////
                                    GET LIST COMMENTING
        ///////////////////////////////////////////////////////////////// */
        $score = null;
        $listCommentaire = [];
        if ($user) {
            $option = [
                'fromCtoA' => $this->getParameter('rating_from_conseiller_to_auditeur'),
                'fromRtoR' => $this->getParameter('rating_from_region_to_renovateur')
            ];
            $repo_rating = $this->em->getRepository(Rating::class);

            $score = $repo_rating->findScore($user->getId(), $typeRating);
            $listCommentaire = $repo_rating->findCommentaire($user->getId(), $typeRating, $option);
        }

        $viewBloc = $this->renderView('BackOffice/Rating/inc/view/_view.html.twig', [
            'score'             => $score,
            'listCommentaire'   => $listCommentaire,
            'partenaireId'      => $partenaireId,
            'partenaireType'    => $partenaireType
        ]);
        return new Response($viewBloc, 200);
    }

    /**
     * @throws \Exception
     */
    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function add(Request $request, string $type, AdminService $adminService, AdminCoordonneeService $adminCoordonneeService): RedirectResponse|Response
    {
        $partenaire = new Partenaire_();


        $form = $this->createForm(Partenaire_Type::class, $partenaire);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $reference_date = $this->getParameter('date_reference');

            /* /////////////////////////////////////////////////////////////////
                                SET THEMATIQUE PARTENAIRE
            ///////////////////////////////////////////////////////////////// */
            if ('0' === $type) {
                $partenaire->getPartenaireIdentification()->setThematique('0 | auditeur');
                $partenaire->setType('0 | auditeur');
            } elseif ('1' === $type) {
                $partenaire->getPartenaireIdentification()->setThematique('1 | renovateur');
                $partenaire->setType('1 | renovateur');
            }
            // Format link date to persist
            $post_dateLink = $partenaire->getPartenaireStatut()->getDateRattachement() ?? new \DateTime($reference_date);
            $partenaire->getPartenaireStatut()->setDateRattachement($post_dateLink);

            // Format activity date to persist
            $post_dateActivity = $partenaire->getPartenaireStatut()->getDateInactif();

            $post_dateActivity = $post_dateActivity ?? new \DateTime($reference_date);
            $partenaire->getPartenaireStatut()->setDateInactif($post_dateActivity);

            /* /////////////////////////////////////////////////////////////////
                                        IBAN CHECK
            ///////////////////////////////////////////////////////////////// */
            $IBANValidator = new IBANValidator();
            $post_iban = $partenaire->getPartenaireOptionAuditeur()->getIban();

            if (isset($post_iban) && false === $IBANValidator->validate($post_iban)) {
                $request->getSession()->getFlashBag()->add(
                    'danger',
                    'Les coordonnées bancaires sont erronées.'
                );

                return $this->redirectToRoute('partenaire_add', [
                    'type' => $type,
                ]);
            } else {
                if (isset($post_iban) && true === $IBANValidator->validate($post_iban)) {
                    $this->em->persist($partenaire);
                    $this->em->flush();

                    $request->getSession()->getFlashBag()->add(
                        'success',
                        'L\'auditeur a été créé avec succès.'
                    );

                    // Create new auditeur in USER table
                    $adminService->createUser(
                        0,
                        $partenaire->getId(),
                        '',
                        $partenaire->getPartenaireIdentification()->getRaisonSociale(),
                        $partenaire->getPartenaireAdresse()->getEmail()
                    );
                } else {
                    $this->em->persist($partenaire);
                    $this->em->flush();

                    $request->getSession()->getFlashBag()->add(
                        'success',
                        'Le rénovateur a été créé avec succès.'
                    );

                    // Create new rénovateur in USER table
                    $adminService->createUser(
                        1,
                        $partenaire->getId(),
                        '',
                        $partenaire->getPartenaireIdentification()->getRaisonSociale(),
                        $partenaire->getPartenaireAdresse()->getEmail()
                    );
                }

                /* /////////////////////////////////////////////////////////////////
                                        SET AGENCE COORDONNEE
                ///////////////////////////////////////////////////////////////// */
                $adminCoordonneeService->createCoordonnee($partenaire->getId(), 'AGENCE_CODE');

                return $this->redirectToRoute('partenaire_list', [
                    'type' => $type
                ]);
            }
        }

        $title = '';
        if ('0' === $type) {
            $title = 'auditeur';
        } elseif ('1' === $type) {
            $title = 'rénovateur';
        }

        return $this->render('BackOffice/Partenaire/add.html.twig', [
            'form_partenaire' => $form->createView(),
            'partenaire' => $partenaire,
            'title' => $title,
            'type' => $type,
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function edit(Request $request, int $partenaireId, string $type, AdminService $adminService, AdminCoordonneeService $adminCoordonneeService): RedirectResponse|Response
    {
        $repo = $this->em->getRepository(Partenaire_::class);
        $partenaire = $repo->find($partenaireId);

        if (!$partenaire) {
            throw $this->createNotFoundException('Partenaire not found');
        }

        /* /////////////////////////////////////////////////////////////////
                                GET PARTENAIRE AGENCE
        ///////////////////////////////////////////////////////////////// */
        $arrayAgence = [];
        foreach ($partenaire->getPartenaireAgence() as $row) {
            $arrayAgence[$row->getId()] = AdminCoordonneeService::TYPE_PARTENAIRE_AGENCE_SLUG;
        }

        // Format dates for display (don't modify entity)
        $dateInactif = $partenaire->getPartenaireStatut()->getDateInactif()?->format('d/m/Y');
        $dateRattachement = $partenaire->getPartenaireStatut()->getDateRattachement()?->format('d/m/Y');

        $form = $this->createForm(Partenaire_Type::class, $partenaire);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $reference_date = $this->getParameter('date_reference');

            $partenaire->setDateModif(new \DateTime());
            $partenaire->setAuteurModif($_SESSION['login']->getUsername());

            /* /////////////////////////////////////////////////////////////////
                                SET THEMATIQUE PARTENAIRE
            ///////////////////////////////////////////////////////////////// */
            if ('0' === $type) {
                $partenaire->getPartenaireIdentification()->setThematique('0 | auditeur');
                $partenaire->setType('0 | auditeur');
            } elseif ('1' === $type) {
                $partenaire->getPartenaireIdentification()->setThematique('1 | renovateur');
                $partenaire->setType('1 | renovateur');
            }

            // Format link date to persist
            $post_dateLink = $partenaire->getPartenaireStatut()->getDateRattachement() ?? new \DateTime($reference_date);
            $partenaire->getPartenaireStatut()->setDateRattachement($post_dateLink);

            // Format activity date to persist
            $post_dateActivity = $partenaire->getPartenaireStatut()->getDateInactif();

            $post_dateActivity = $post_dateActivity ?? new \DateTime($reference_date);
            $partenaire->getPartenaireStatut()->setDateInactif($post_dateActivity);


            /* /////////////////////////////////////////////////////////////////
                                        IBAN CHECK
            ///////////////////////////////////////////////////////////////// */
            $IBANValidator = new IBANValidator();
            $post_iban = $partenaire->getPartenaireOptionAuditeur()->getIban();

            if (isset($post_iban) && false === $IBANValidator->validate($post_iban)) {
                $request->getSession()->getFlashBag()->add(
                    'danger',
                    'Les coordonnées bancaires sont erronées.'
                );

                return $this->redirectToRoute('partenaire_edit', [
                    'partenaireId' => $partenaireId,
                    'type' => $type,
                ]);
            }

            $this->em->persist($partenaire);
            $this->em->flush();

            if (isset($post_iban) && true === $IBANValidator->validate($post_iban)) {
                $request->getSession()->getFlashBag()->add(
                    'success',
                    'L\'auditeur a été modifié avec succès.'
                );

                // Update auditeur in USER table
                $adminService->updateUser(
                    0,
                    $partenaire->getId(),
                    '',
                    $partenaire->getPartenaireIdentification()->getRaisonSociale(),
                    $partenaire->getPartenaireAdresse()->getEmail(),
                    $partenaire->getPartenaireStatut()->getEnabled(),
                    $partenaire->getPartenaireStatut()->getDateInactif()
                );
            } else {
                $request->getSession()->getFlashBag()->add(
                    'success',
                    'Le rénovateur a été modifié avec succès.'
                );

                // Update rénovateur in USER table
                $adminService->updateUser(
                    1,
                    $partenaire->getId(),
                    '',
                    $partenaire->getPartenaireIdentification()->getRaisonSociale(),
                    $partenaire->getPartenaireAdresse()->getEmail(),
                    $partenaire->getPartenaireStatut()->getEnabled(),
                    $partenaire->getPartenaireStatut()->getDateInactif()
                );
            }

            /* /////////////////////////////////////////////////////////////////
                                        SET AGENCE COORDONNEE
            ///////////////////////////////////////////////////////////////// */
            $adminCoordonneeService->createCoordonnee($partenaireId, AdminCoordonneeService::TYPE_PARTENAIRE_AGENCE_CODE, $arrayAgence);

            return $this->redirectToRoute('partenaire_list', [
                'type' => $type,
            ]);
        }

        $title = '';
        if ('0' === $type) {
            $title = 'auditeur';
        } elseif ('1' === $type) {
            $title = 'rénovateur';
        }

        return $this->render('BackOffice/Partenaire/edit.html.twig', [
            'form_partenaire' => $form->createView(),
            'partenaire' => $partenaire,
            'title' => $title,
            'type' => $type,
            'dateInactif' => $dateInactif,
            'dateRattachement' => $dateRattachement,
        ]);
    }

    /**
     * @throws Html2PdfException
     */
    #[Security("is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function exportRating(PartenaireService $partenaireService,int $partenaireId = 0, int $partenaireType = -1 ): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                    GET PARTENAIRE
        ///////////////////////////////////////////////////////////////// */
        $repo = $this->em->getRepository(Partenaire_::class);
        if (0 !== $partenaireId) {
            $partenaire = $repo->find($partenaireId);
        } else {
            throw new \Exception("Id Partenaire non valide");
        }

        if (!$partenaire) {
            throw $this->createNotFoundException('Partenaire not found');
        }

        $partenaireRaisonSociale = $partenaire->getPartenaireIdentification()->getRaisonSociale();

        if (-1 !== $partenaireType) {
            if (0 === $partenaireType) {
                $prefixUser = 'A';
                $typeRating = $this->getParameter('rating_from_conseiller_to_auditeur');
            } elseif (1 === $partenaireType) {
                $prefixUser = 'R';
                $typeRating = $this->getParameter('rating_from_region_to_renovateur');
            } else {
                throw new \Exception('Le Partenaire type est invalide.');
            }
        } else {
            throw new \Exception('Le Partenaire type est invalide.');
        }

        /* /////////////////////////////////////////////////////////////////
                                    GET USER
        ///////////////////////////////////////////////////////////////// */
        $repo_user = $this->em->getRepository(User::class);
        $user = $repo_user->findOneBy([
            'username' => $prefixUser . str_pad((string)$partenaireId, 5, '0', STR_PAD_LEFT)
        ]);

        /* /////////////////////////////////////////////////////////////////
                                    GET LIST COMMENTING
        ///////////////////////////////////////////////////////////////// */
        $score = null;
        $listCommentaire = [];
        if ($user) {
            $option = [
                'fromCtoA' => $this->getParameter('rating_from_conseiller_to_auditeur'),
                'fromRtoR' => $this->getParameter('rating_from_region_to_renovateur')
            ];
            $repo_rating = $this->em->getRepository(Rating::class);
            $score = $repo_rating->findScore($user->getId(), $typeRating);
            $listCommentaire = $repo_rating->findCommentaire($user->getId(), $typeRating, $option);
        }

        /* /////////////////////////////////////////////////////////////////
                                EXPORT PDF
        ///////////////////////////////////////////////////////////////// */
        $html2pdf = $partenaireService->createPdfRating(
            $partenaireId,
            $partenaireType,
            $partenaireRaisonSociale,
            $score,
            $listCommentaire
        );

        return new Response(
            $html2pdf->output(),
            200,
            ['Content-Type' => 'application/pdf']
        );
    }

    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function export(int $type, PartenaireService $partenaireService): StreamedResponse
    {
        return $partenaireService->export($type);
    }
}

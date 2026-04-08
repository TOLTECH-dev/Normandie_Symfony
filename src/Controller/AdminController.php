<?php

namespace App\Controller;

use App\Entity\Admin_import;
use App\Entity\Beneficiaire;
use App\Entity\User;
use App\Form\Admin_importType;
use App\Form\UserType;
use App\Service\AdminService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;


class AdminController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserService $userService;

    private AdminService $adminService;

    public function __construct(EntityManagerInterface $em, UserService $userService, AdminService $adminService)
    {
        $this->em = $em;
        $this->userService = $userService;
        $this->adminService = $adminService;
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function list(): ?Response
    {
        /* /////////////////////////////////////////////////////////////////
                        GET COUNT DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->em->getRepository(User::class);
        $recordsTotal = $repo_demande->countForList(null, null);

        /* /////////////////////////////////////////////////////////////////
                        GET SELECT OPTION
        ///////////////////////////////////////////////////////////////// */
        $arraySelectOptions = [
            "userStatutSlug" => [1 => "Actif", 0 => "Inactif"],
            "userRolesLabel" => User::ROLES_LABEL,
        ];
        return $this->render('BackOffice/Admin/list.html.twig', [
            'recordsTotal'       => $recordsTotal,
            'arraySelectOptions' => $arraySelectOptions,
            'user'               => new User()
        ]);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_EPCI') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN') or is_granted('ROLE_TECHNIQUE')")]
    public function listAjax(Request $request): JsonResponse
    {
        $userRepository = $this->em->getRepository(User::class);
        $dataForListDispositif = $this->userService->getDataForListAjax();
        $post = $request->request->all();
        if (!empty($post)) {
            $recordsTotal = $userRepository->countForList(null, null)['countId'] ?? 0;

            if (!empty($post['search']['value'])) {
                $data = $userRepository->findForListAjax(
                    $dataForListDispositif['dataSQL'],
                    $dataForListDispositif['orderBy'],
                    $dataForListDispositif['orderType'],
                    $dataForListDispositif['start'],
                    $dataForListDispositif['length'],
                    $dataForListDispositif['columnWhere']
                );
                $recordsFiltered = $userRepository->countForList(
                    $dataForListDispositif['dataSQL'],
                    $dataForListDispositif['columnWhere']
                )['countId'] ?? 0;

            } elseif (!empty($dataForListDispositif['columnWhereTmp'])) {
                $data = $userRepository->findForListAjax(
                    $dataForListDispositif['dataSQL'],
                    $dataForListDispositif['orderBy'],
                    $dataForListDispositif['orderType'],
                    $dataForListDispositif['start'],
                    $dataForListDispositif['length'],
                    $dataForListDispositif['columnWhere']
                );
                $recordsFiltered = $userRepository->countForList(
                    $dataForListDispositif['dataSQL'],
                    $dataForListDispositif['columnWhere']
                )['countId'] ?? 0;

            } else {
                $data = $userRepository->findForListAjax(
                    null,
                    $dataForListDispositif['orderBy'],
                    $dataForListDispositif['orderType'],
                    $dataForListDispositif['start'],
                    $dataForListDispositif['length'],
                    null
                );

                $recordsFiltered = $recordsTotal;
            }
            $response = [
                "draw"            => intval($dataForListDispositif['draw']),
                "recordsTotal"    => $recordsTotal,
                "recordsFiltered" => $recordsFiltered,
                "data"            => $data
            ];
            return new JsonResponse($response);
        }
        return new JsonResponse([], 400);
    }

    #[Security("is_granted('ROLE_ADMIN')")]
    public function add(Request $request): Response
    {
        $formOption = [];
        $formOption[] = $this->getUser()?->getRoles() ?? [];
        $formOption[] = [0 => ''];
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'trait_choices' => $formOption
        ]);
        $form->remove('plainPassword');
        $form->remove('password');
        $form->remove('enabled');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->adminService->createUser(
                -1,
                $user->getId(),
                $user->getFirstname(),
                $user->getLastname(),
                $user->getEmail(),
                $user->getUsername(),
                [$request->request->get('whitelabel_mainbundle_user')['roles'] ?? []]
            );
            $this->addFlash('success', 'L\'utilisateur ' . $user->getFirstname() . ' ' . $user->getLastname() . ' a été crée avec succès.');
            return $this->redirectToRoute('admin_list', []);
        }
        return $this->render('BackOffice/Admin/add.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
    public function view(int $userId): Response
    {
        /* /////////////////////////////////////////////////////////////////
                            GET USER
        ///////////////////////////////////////////////////////////////// */
        $userRepository = $this->em->getRepository(User::class);
        $user = $userRepository->find($userId);

        $beneficiaireRepository = $this->em->getRepository(Beneficiaire::class);
        $beneficiaire = $beneficiaireRepository->findOneBy(['user_id' => $userId]);

        return $this->render('BackOffice/Admin/view.html.twig', [
            'user'                         => $user,
            'is_fiche_beneficiaire_exists' => !empty($beneficiaire)
        ]);
    }

    #[Security("is_granted('ROLE_ADMIN')")]
    public function edit(Request $request, int $userId): Response
    {
        $repo = $this->em->getRepository(User::class);
        $user = $repo->find($userId);
        $formOption = [];
        $formOption[] = [];
        $formOption[] = $user?->getRoles() ?? [];
        $form = $this->createForm(UserType::class, $user, [
            'trait_choices' => $formOption
        ]);
        $form->remove('plainPassword');
        $form->remove('password');
        $form->remove('username');
        $form->remove('enabled');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->adminService->updateUser(
                -1,
                $user->getId(),
                $user->getFirstname(),
                $user->getLastname(),
                $user->getEmail(),
                $user->isEnabled(),
                $user->getDateInactif(),
                [$request->request->get('whitelabel_mainbundle_user')['roles'] ?? []]
            );
            $this->addFlash('success', 'L\'utilisateur ' . $user->getFirstname() . ' ' . $user->getLastname() . ' a été modifié avec succès.');
            return $this->redirectToRoute('admin_list', []);
        }
        return $this->render('BackOffice/Admin/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[ParamConverter('user', options: ['id' => 'userId'])]
    #[Security("is_granted('ROLE_ADMIN')")]
    public function delete(Request $request, User $user): Response
    {
        $beneficiaireRepository = $this->em->getRepository(Beneficiaire::class);
        $beneficiaire = $beneficiaireRepository->findOneBy(['user_id' => $user->getId()]);

        if (!empty($beneficiaire)) {
            throw new AccessDeniedHttpException();
        }

        /* /////////////////////////////////////////////////////////////////
                        GET DELETE FORM
        ///////////////////////////////////////////////////////////////// */
        $form = $this->createFormBuilder()->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->remove($user);
            $this->em->flush();
            $this->em->clear();
            $this->addFlash('success', 'L\'Utilisateur a bien été supprimé.');

            return $this->redirectToRoute('admin_list', []);
        }

        return $this->render('BackOffice/Admin/inc/view_admin/__form_modal_delete_user.html.twig', [
            'form_delete' => $form->createView(),
            'user' => $user,
        ]);

    }

    #[Security("is_granted('ROLE_ADMIN')")]
    public function enabled(int $userId, int $flag): Response
    {
        $repo = $this->em->getRepository(User::class);
        $user = $repo->find($userId);
        if ($flag === 0) {
            $user->setEnabled(false);
            $user->setDateInactif(new \DateTime());
        } elseif ($flag === 1) {
            $user->setEnabled(true);
        }
        $this->em->persist($user);
        $this->em->flush();
        return $this->redirectToRoute('admin_list', []);
    }

    #[Security("is_granted('ROLE_ADMIN')")]
    public function import(Request $request): Response
    {
        $formOption = [];
        $import = new Admin_import();
        $form = $this->createForm(Admin_importType::class, $import, [
            'trait_choices' => $formOption
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($import);
            $this->em->flush();
            $this->adminService->persistImport(
                $import->getId(),
                $import->getType()
            );
            $this->addFlash('success', 'L\'import s\'est déroulé avec succès.');
            return $this->redirectToRoute('demande_list_all', []);
        }
        return $this->render('BackOffice/Admin/import.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}

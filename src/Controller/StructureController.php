<?php

namespace App\Controller;

use App\Entity\Structure_;
use App\Entity\Structure_conseiller;
use App\Form\Structure_Type;
use App\Service\AdminCoordonneeService;
use App\Service\AdminService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class StructureController extends AbstractController
{
    private EntityManagerInterface $em;
    private AdminCoordonneeService $adminCoordonneeService;
    private AdminService $adminService;
    private ParameterBagInterface $params;

    public function __construct(
        EntityManagerInterface $em,
        AdminCoordonneeService $adminCoordonneeService,
        AdminService $adminService,
        ParameterBagInterface $params
    ) {
        $this->em = $em;
        $this->adminCoordonneeService = $adminCoordonneeService;
        $this->adminService = $adminService;
        $this->params = $params;
    }

    public function list(): Response
    {
        $repo = $this->em->getRepository(Structure_::class);
        $list = $repo->findBy([], ['id' => 'DESC']);

        return $this->render('BackOffice/Structure/list.html.twig', [
            'list_structure' => $list
        ]);
    }

    public function add(Request $request): Response
    {
        $structure = new Structure_();
        $form = $this->createForm(Structure_Type::class, $structure);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $reference_date = $this->params->get('date_reference');
            $post_dateActivity = $structure->getStructure_statut()->getDateInactif();
            if (null !== $post_dateActivity) {
                $convert_dateActivity = \DateTime::createFromFormat('d/m/Y', $post_dateActivity);
            } else {
                $convert_dateActivity = new \DateTime($reference_date);
            }
            $structure->getStructure_statut()->setDateInactif($convert_dateActivity);

            $post_conseiller = $structure->getStructureConseiller();
            foreach ($post_conseiller as $row) {
                $post_enabled = $row->getEnabled();
                if ('1' == $post_enabled) {
                    $row->setDateInactif(new \DateTime($reference_date));
                } elseif ('0' == $post_enabled) {
                    $row->setDateInactif(new \DateTime());
                }
            }

            $this->em->persist($structure);
            $this->em->flush();

            $list_conseiller = $structure->getStructureConseiller();
            foreach ($list_conseiller as $row) {
                $this->adminService->createUser(
                    2,
                    $row->getId(),
                    $row->getPrenom(),
                    $row->getNom(),
                    $row->getEmail()
                );
            }

            $this->adminCoordonneeService->createCoordonnee($structure->getId(), AdminCoordonneeService::TYPE_STRUCTURE_PERMANENCE_CODE);

            $this->addFlash('success', 'La structure H&E a été créée avec succès.');

            return $this->redirectToRoute('structure_list', []);
        }

        return $this->render('BackOffice/Structure/add.html.twig', [
            'form_structure' => $form->createView(),
            'structure' => $structure,
        ]);
    }

    public function view(int $structureId): Response
    {
        $repo = $this->em->getRepository(Structure_::class);
        $structure = $repo->find($structureId);

        return $this->render('BackOffice/Structure/view.html.twig', [
            'structure' => $structure
        ]);
    }

    public function edit(Request $request, int $structureId): Response
    {
        $repo = $this->em->getRepository(Structure_::class);
        $structure = $repo->find($structureId);
        $arrayConseillerInitial = [];
        foreach ($structure->getStructureConseiller() as $row) {
            $arrayConseillerInitial[$row->getId()] = $row->getId();
        }
        $arrayPermanence = [];
        foreach ($structure->getStructurePermanence() as $row) {
            $arrayPermanence[$row->getId()] = AdminCoordonneeService::TYPE_STRUCTURE_PERMANENCE_SLUG;
        }

        $form = $this->createForm(Structure_Type::class, $structure);
        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $structure->setDateModif(new \DateTime());
            $structure->setAuteurModif($this->getUser() ? $this->getUser()->getUsername() : '');
            $reference_date = $this->params->get('date_reference');
            $post_dateActivity = $structure->getStructure_statut()->getDateInactif();
            if (null == $post_dateActivity){
                $convert_dateActivity = new \DateTime($reference_date);
                $structure->getStructure_statut()->setDateInactif($convert_dateActivity);
            }
            $post_conseiller = $structure->getStructureConseiller();
            foreach ($post_conseiller as $row) {
                $post_enabled = $row->getEnabled();
                if ('1' == $post_enabled) {
                    $row->setDateInactif(new \DateTime($reference_date));
                } elseif ('0' == $post_enabled) {
                    $row->setDateInactif(new \DateTime());
                }
            }
            $this->em->persist($structure);
            $this->em->flush();
            $list_conseiller = $structure->getStructureConseiller();
            foreach ($list_conseiller as $row) {
                $this->adminService->updateUser(
                    2,
                    $row->getId(),
                    $row->getPrenom(),
                    $row->getNom(),
                    $row->getEmail(),
                    $row->getEnabled(),
                    $row->getDateInactif()
                );
                unset($arrayConseillerInitial[$row->getId()]);
            }
            $repo_structureConseiller = $this->em->getRepository(Structure_conseiller::class);
            foreach ($arrayConseillerInitial as $row) {
                $conseiller = $repo_structureConseiller->find($row);
                $this->adminService->updateUser(
                    2,
                    $conseiller->getId(),
                    $conseiller->getPrenom(),
                    $conseiller->getNom(),
                    $conseiller->getEmail(),
                    false,
                    $conseiller->getDateInactif()
                );
            }
            $this->adminCoordonneeService->createCoordonnee($structureId, AdminCoordonneeService::TYPE_STRUCTURE_PERMANENCE_CODE, $arrayPermanence);
            $this->addFlash('success', 'La structure H&E ' . $structure->getStructureIdentification()->getNom() . ' a été modifiée avec succès.');
            return $this->redirectToRoute('structure_list', []);
        }
        return $this->render('BackOffice/Structure/edit.html.twig', [
            'form_structure' => $form->createView(),
            'structure' => $structure,
        ]);
    }
}

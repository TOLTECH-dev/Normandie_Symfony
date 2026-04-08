<?php

namespace App\Controller;

use App\Entity\EPCI_;
use App\Form\EPCI_Type;
use App\Repository\EPCI_Repository;
use App\Service\AdminCoordonneeService;
use App\Service\AdminService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

#[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class EPCIController extends AbstractController
{
    public function list(EPCI_Repository $epciRepository): Response
    {
        $list = $epciRepository->findBy([], ['id' => 'DESC']);

        return $this->render('BackOffice/EPCI/list.html.twig', [
            'list_EPCI' => $list
        ]);
    }

    public function view(int $EPCIId, EPCI_Repository $epciRepository): Response
    {
        $EPCI = $epciRepository->find($EPCIId);

        if (!$EPCI) {
            throw $this->createNotFoundException('EPCI non trouvée.');
        }

        return $this->render('BackOffice/EPCI/view.html.twig', [
            'EPCI' => $EPCI
        ]);
    }

    public function edit(
        Request $request,
        int $EPCIId,
        AdminService $adminService,
        EntityManagerInterface $entityManager,
        AdminCoordonneeService $adminCoordonneeService
    ): RedirectResponse|Response {
        $EPCI = $entityManager->getRepository(EPCI_::class)->find($EPCIId);

        if (!$EPCI) {
            throw $this->createNotFoundException('EPCI non trouvée.');
        }

        // Get EPCI permanence array
        $arrayPermanence = [];
        foreach ($EPCI->getEPCIPermanence() as $row) {
            $arrayPermanence[$row->getId()] = AdminCoordonneeService::TYPE_EPCI_PERMANENCE_SLUG;
        }

        // Format activity date to display without modifying entity

        $form = $this->createForm(EPCI_Type::class, $EPCI);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $referenceDate = $this->getParameter('date_reference');

            $EPCI->setDateModif(new \DateTime());
            $EPCI->setAuteurModif($_SESSION['login']->getUsername());

            $EPCI->setDateInactif(
                $form->get('dateInactif')->getData() ?? new \DateTime($referenceDate)
            );

            $entityManager->persist($EPCI);
            $entityManager->flush();

            // Update contacts in USER table
            $listContact = $EPCI->getEPCIContact();
            foreach ($listContact as $row) {
                $adminService->updateUser(
                    3,
                    $row->getId(),
                    $row->getPrenom(),
                    $row->getNom(),
                    $row->getEmail(),
                    $EPCI->getEnabled(),
                    $EPCI->getDateInactif()
                );
            }

            $adminCoordonneeService->createCoordonnee($EPCIId, AdminCoordonneeService::TYPE_EPCI_PERMANENCE_CODE, $arrayPermanence);
            $this->addFlash(
                'success',
                'L\'EPCI ' . $EPCI->getNom() . ' a été modifiée avec succès.'
            );

            return $this->redirectToRoute('EPCI_list', []);
        }

        return $this->render('BackOffice/EPCI/edit.html.twig', [
            'form_EPCI' => $form->createView(),
            'EPCI'      => $EPCI,
        ]);
    }

    public function add(Request $request, AdminService $adminService, AdminCoordonneeService $adminCoordonneeService, EntityManagerInterface $entityManager): RedirectResponse|Response
    {
        $EPCI = new EPCI_();
        $form = $this->createForm(EPCI_Type::class, $EPCI);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $referenceDate = $this->getParameter('date_reference');

            $EPCI->setDateInactif(
                $form->get('dateInactif')->getData() ?? new \DateTime($referenceDate)
            );

            $entityManager->persist($EPCI);
            $entityManager->flush();

            // Create new contact in USER table
            $listContact = $EPCI->getEPCIContact();
            foreach ($listContact as $row) {
                $adminService->createUser(
                    3,
                    $row->getId(),
                    $row->getPrenom(),
                    $row->getNom(),
                    $row->getEmail()
                );
            }

            // Set permanence coordonnee
            $adminCoordonneeService->createCoordonnee($EPCI->getId(), AdminCoordonneeService::TYPE_EPCI_PERMANENCE_CODE);

            $this->addFlash(
                'success',
                'L\'EPCI a été créée avec succès.'
            );

            return $this->redirectToRoute('EPCI_list', []);
        }

        return $this->render('BackOffice/EPCI/add.html.twig', [
            'form_EPCI' => $form->createView(),
            'EPCI'      => $EPCI,
        ]);
    }
}

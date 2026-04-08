<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Orientation;
use App\Entity\Logement;
use App\Repository\OrientationRepository;
use App\Form\OrientationType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;

#[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class OrientationController extends AbstractController
{
    public function list(OrientationRepository $orientationRepository): Response
    {
        $orientationResult = $orientationRepository->searchByVille();
        return $this->render('BackOffice/Orientation/list.html.twig', [
            'list_orientation' => $orientationResult,
        ]);
    }

    public function add(Request $request, int $villeId, EntityManagerInterface $entityManager): RedirectResponse|Response
    {
        $logementRepository = $entityManager->getRepository(Logement::class);
        $ville = $logementRepository->searchByVilleId($villeId);
        $orientation = new Orientation();
        $form = $this->createForm(OrientationType::class, $orientation, []);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $orientation->setVilleId($villeId);
            $entityManager->persist($orientation);
            $entityManager->flush();
            $this->addFlash('success', "L'Orientation a été créée avec succès.");
            return $this->redirectToRoute('orientation_list');
        }

        return $this->render('BackOffice/Orientation/add.html.twig', [
            'form_orientation' => $form->createView(),
            'ville' => $ville,
        ]);
    }

    public function view(int $orientationId, OrientationRepository $orientationRepository): Response
    {
        $orientationResult = $orientationRepository->findByIdCustom($orientationId);

        return $this->render('BackOffice/Orientation/view.html.twig', [
            'orientationResult' => $orientationResult
        ]);
    }

    public function edit(Request $request, int $orientationId, EntityManagerInterface $entityManager): RedirectResponse|Response
    {
        $orientationRepository = $entityManager->getRepository(Orientation::class);
        $orientation = $orientationRepository->find($orientationId);
        if (!$orientation) {
            throw $this->createNotFoundException('Orientation non trouvée.');
        }
        $villeId = $orientation->getVilleId();

        $logementRepository = $entityManager->getRepository(Logement::class);
        $ville = $logementRepository->searchByVilleId($villeId);

        $formOption['EPCIId'] = [$orientation->getEPCIId()];
        $form = $this->createForm(OrientationType::class, $orientation, [
            'trait_choices' => $formOption
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $orientation->setDateModif(new \DateTime());
            // TODO: Remplacer par l'utilisateur courant (Security), ici placeholder
            $orientation->setAuteurModif($this->getUser()?->getUserIdentifier() ?? '');
            $entityManager->persist($orientation);
            $entityManager->flush();
            $this->addFlash('success', "L'Orientation a été modifiée avec succès.");
            return $this->redirectToRoute('orientation_list');
        }
        return $this->render('BackOffice/Orientation/edit.html.twig', [
            'form_orientation' => $form->createView(),
            'ville' => $ville,
        ]);
    }
}

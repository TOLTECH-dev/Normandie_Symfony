<?php

namespace App\Controller;

use App\Entity\Banque_;
use App\Form\Banque_Type;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class BanqueController extends AbstractController
{
    /**
     * @return Response
     */
    public function list(EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Banque_::class);
        $list = $repo->findBy([], ['id' => 'DESC']);

        return $this->render('BackOffice/Banque/list.html.twig', [
            'list_banque' => $list
        ]);
    }

    public function add(Request $request, EntityManagerInterface $em): Response
    {
        $banque = new Banque_();
        $form = $this->createForm(Banque_Type::class, $banque);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reference_date = $this->getParameter('date_reference');
            $post_dateActivity = $banque->getBanqueStatut()->getDateInactif();

            if (null == $post_dateActivity) {
                $convert_dateActivity = new \DateTime($reference_date);
                $banque->getBanqueStatut()->setDateInactif($convert_dateActivity);
            }

            $em->persist($banque);
            $em->flush();

            $this->addFlash('success', 'La Banque ' . $banque->getNom() . ' a été créée avec succès.');
            return $this->redirectToRoute('banque_list');
        }

        return $this->render('BackOffice/Banque/add.html.twig', [
            'form_banque' => $form->createView(),
            'banque'      => $banque,
        ]);
    }

    public function view(int $banqueId, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Banque_::class);
        $banque = $repo->find($banqueId);

        return $this->render('BackOffice/Banque/view.html.twig', [
            'banque'    => $banque
        ]);
    }

    public function edit(Request $request, int $banqueId, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Banque_::class);
        $banque = $repo->find($banqueId);

        $form = $this->createForm(Banque_Type::class, $banque);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $banque->setDateModif(new \DateTime());
            $user = $this->getUser();
            $banque->setAuteurModif($user ? $user->getUsername() : '');

            $reference_date = $this->getParameter('date_reference');
            $post_dateActivity = $banque->getBanqueStatut()->getDateInactif();
            if (null === $post_dateActivity) {
                $convert_dateActivity = new \DateTime($reference_date);
                $banque->getBanqueStatut()->setDateInactif($convert_dateActivity);
            }

            $em->persist($banque);
            $em->flush();

            $this->addFlash('success', 'La Banque ' . $banque->getNom() . ' a été modifiée avec succès.');
            return $this->redirectToRoute('banque_list');
        }

        return $this->render('BackOffice/Banque/edit.html.twig', [
            'form_banque' => $form->createView(),
            'banque'      => $banque,
        ]);
    }

    public function enabled(int $banqueId, int $flag, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Banque_::class);
        $banque = $repo->find($banqueId);

        if (0 === $flag) {
            $today_date = new \DateTime();
            $banque->getBanqueStatut()->setDateInactif($today_date);
            $banque->getBanqueStatut()->setEnabled(0);
            $em->persist($banque);
            $em->flush();
        } elseif (1 === $flag) {
            $reference_date = $this->getParameter('date_reference');
            $convert_date = new \DateTime($reference_date);
            $banque->getBanqueStatut()->setDateInactif($convert_date);
            $banque->getBanqueStatut()->setEnabled(1);
            $em->persist($banque);
            $em->flush();
        }

        return $this->redirectToRoute('banque_list');
    }
}

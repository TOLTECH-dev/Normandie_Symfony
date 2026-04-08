<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Contact;
use App\Form\ContactType;
use App\Entity\Newsletter;

#[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class ContactController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function list(): Response
    {

        $repo = $this->em->getRepository(Contact::class);
        $repo_newsletter = $this->em->getRepository(Newsletter::class);
        $listNewsletter = $repo_newsletter->findAll();
        $list = $repo->findBy([], ['id' => 'DESC']);
        $form_delete = $this->createFormBuilder()->getForm();
        return $this->render('BackOffice/Contact/list.html.twig', [
            'form_delete'       => $form_delete->createView(),
            'list_contact'      => $list,
            'list_newsletter'   => $listNewsletter
        ]);
    }

    public function addEdit(Request $request, int $contactId = 0): JsonResponse|Response
    {
        $repo = $this->em->getRepository(Contact::class);
        if ($contactId !== 0) {
            $contact = $repo->find($contactId);
            $successMessage = 'Le Contact %s a été modifié avec succès.';
        } else {
            $contact = new Contact();
            $successMessage = 'Le Contact %s a été créé avec succès.';
        }
        $type = $repo->searchType();
        $array_type = [];
        foreach ($type as $item) {
            $array_type[$item['slug']] = $item['id'] . ' | ' . $item['typologie'];
        }
        $formOption = [
            'optionType' => $array_type
        ];
        $form = $this->createForm(ContactType::class, $contact, [
            'trait_choices' => $formOption
        ]);
        $form->handleRequest($request);
        if ($request->isMethod('POST') && $form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contact);
            $this->em->flush();
            $this->em->clear();
            $this->addFlash(
                'success',
                sprintf($successMessage, ucfirst($contact->getPrenom()) . ' ' . strtoupper($contact->getNom()))
            );
            $response = new JsonResponse([], 200);
        } else {
            $viewForm = $this->renderView('BackOffice/Contact/inc/form_contact/form.html.twig', [
                'form' => $form->createView()
            ]);
            $response = ($request->isMethod('POST')) ? new JsonResponse(['form' => $viewForm], 400) : new Response($viewForm, 200);
        }
        return $response;
    }

    public function delete(Request $request, int $contactId = 0): JsonResponse|Response
    {
        $repo = $this->em->getRepository(Contact::class);
        if ($contactId !== 0) {
            $contact = $repo->find($contactId);
        } else {
            throw new \Exception("Id inconnu");
        }
        $form = $this->createFormBuilder()->getForm();
        $form->handleRequest($request);
        if ($request->isMethod('POST') && $form->isSubmitted() && $form->isValid()) {
            if ($this->isGranted('ROLE_CLIENT') || $this->isGranted('ROLE_ADMIN')) {
                $this->em->remove($contact);
                $this->em->flush();
                $this->em->clear();
            }
            $this->addFlash('success', 'Le Contact a bien été supprimé.');
            $response = new JsonResponse([], 200);
        } else {
            $viewForm = $this->renderView('BackOffice/Contact/inc/form_contact/form_delete.html.twig', [
                'form'      => $form->createView(),
                'contact'   => $contact
            ]);
            $response = ($request->isMethod('POST')) ? new JsonResponse(['form' => $viewForm], 400) : new Response($viewForm, 200);
        }

        return $response;
    }
}

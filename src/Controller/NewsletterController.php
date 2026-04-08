<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Newsletter;
use App\Form\NewsletterType;
use App\Service\NewsletterService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class NewsletterController extends AbstractController
{
    private EntityManagerInterface $em;
    private NewsletterService $newsletterService;

    public function __construct(EntityManagerInterface $em, NewsletterService $newsletterService)
    {
        $this->em = $em;
        $this->newsletterService = $newsletterService;
    }

    public function add(Request $request): Response
    {
        $repo = $this->em->getRepository(Newsletter::class);

        /* /////////////////////////////////////////////////////////////////
                                    GET NEWSLETTER
        ///////////////////////////////////////////////////////////////// */
        $newsletter = new Newsletter();

        /* /////////////////////////////////////////////////////////////////
                                    GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $repo_contact = $this->em->getRepository(Contact::class);
        $type = $repo_contact->searchType();
        $array_type = [];
        foreach ($type as $item) {
            $array_type[$item['slug']] = $item['id'] . ' | ' . $item['typologie'];
        }

        /* /////////////////////////////////////////////////////////////////
                                    BUILD FORM
        ///////////////////////////////////////////////////////////////// */
        $formOption = [
            'optionType' => $array_type
        ];

        $form = $this->createForm(NewsletterType::class, $newsletter, [
            'trait_choices' => $formOption
        ]);

        $form->handleRequest($request);
        if ($request->isMethod('POST') && $form->isSubmitted() && $form->isValid()) {
            $arrayDestinataire = [
                'isSentToClient'            => null,
                'isSentToAuditeur'          => null,
                'isSentToRenovateur'        => null,
                'isSentToConseiller'        => null,
                'isSentToEPCI'              => null,
                'isSentToBeneficiaire'      => null,
                'isSentToAdministrateur'    => null,
                'isSentToInstructeur'       => null,
                'isSentToTechnique'         => null
            ];
            if ($newsletter->getIsSentToClient()) $arrayDestinataire['isSentToClient'] = true;
            if ($newsletter->getIsSentToAuditeur()) $arrayDestinataire['isSentToAuditeur'] = true;
            if ($newsletter->getIsSentToRenovateur()) $arrayDestinataire['isSentToRenovateur'] = true;
            if ($newsletter->getIsSentToConseiller()) $arrayDestinataire['isSentToConseiller'] = true;
            if ($newsletter->getIsSentToEPCI()) $arrayDestinataire['isSentToEPCI'] = true;
            if ($newsletter->getIsSentToBeneficiaire()) $arrayDestinataire['isSentToBeneficiaire'] = true;
            if ($newsletter->getIsSentToAdministrateur()) $arrayDestinataire['isSentToAdministrateur'] = true;
            if ($newsletter->getIsSentToInstructeur()) $arrayDestinataire['isSentToInstructeur'] = true;
            if ($newsletter->getIsSentToTechnique()) $arrayDestinataire['isSentToTechnique'] = true;
            $listDestinataire = $repo->findContact($arrayDestinataire);
            foreach ($newsletter->getPartenaireType() as $row) {
                $arrayContact = $repo_contact->findBy([
                    'type' => $row
                ]);
                foreach ($arrayContact as $item) {
                    $listDestinataire[] = $item->getEmail();
                }
            }
            if (count($listDestinataire) > 0) {
                if ($newsletter->getFile()) {
                    $body = file_get_contents($newsletter->getFile());
                    $contentType = 'text/html';
                } else {
                    $body = $newsletter->getEmail();
                    $contentType = 'text/plain';
                }
                $isSent = $this->newsletterService->sendNewsletter(
                    $newsletter->getSubject(),
                    $body,
                    $contentType,
                    $listDestinataire
                );
                if ($isSent > 0) {
                    $this->addFlash('success', 'La Newsletter a été envoyée avec succès.');
                } else {
                    $this->addFlash('danger', "L'envoi de la Newsletter a échoué.");
                }
                $this->em->persist($newsletter);
                $this->em->flush();
                $this->em->clear();
            } else {
                $this->addFlash('danger', "Aucun destinataire n'a été sélectionné.");
            }
            return $this->redirectToRoute('contact_list', []);
        }

        return $this->render('BackOffice/Newsletter/add.html.twig', [
            'form'  => $form->createView()
        ]);
    }

    public function view(int $newsletterId): Response
    {
        $repo = $this->em->getRepository(Newsletter::class);
        $newsletter = $repo->find($newsletterId);
        if (!$newsletter) {
            throw new NotFoundHttpException('Newsletter non trouvée.');
        }
        $message = $newsletter->getEmail();
        if ($message) {
            return $this->render('BackOffice/Newsletter/view.html.twig', [
                'message' => $message
            ]);
        } else {
            $path = $this->getParameter('app_root_dossier_data_symfony') . $newsletter->fileGetWebPath();
            $content = file_get_contents($path);
            return new Response($content);
        }
    }
}

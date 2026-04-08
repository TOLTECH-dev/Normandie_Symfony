<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use App\Entity\ANAHCritere;
use App\Form\ANAHCritereType;

#[Security("is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class ANAHCritereController extends AbstractController
{
    /**
     * @param Request $request
     * @param EntityManagerInterface $EM
     * @param FormFactoryInterface $formFactory
     * @return RedirectResponse|Response
     */
    public function list(
        Request $request,
        EntityManagerInterface $EM,
        FormFactoryInterface $formFactory,
    )
    {
        /* /////////////////////////////////////////////////////////////////
                                GET LIST ANAH
        ///////////////////////////////////////////////////////////////// */
        $repo = $EM->getRepository(ANAHCritere::class);
        $list_ANAHCritere = $repo->findAll(
            array(),
            array('id' => 'DESC')
        );

        /* /////////////////////////////////////////////////////////////////
                                GET FORM ADD
        ///////////////////////////////////////////////////////////////// */
        $ANAHCritere = new ANAHCritere();
        $ANAHCritere->setAuteurCreation($this->getUser()->getUsername());
        $ANAHCritere->setAuteurModif($this->getUser()->getUsername());

        $nbPersonneMax = $repo->findNbPersonneMax();
        $ANAHCritere->setNbPersonne($nbPersonneMax + 1);
        $formOption = [
            'isSupplementsReadOnly' => ($ANAHCritere->getNbPersonne() != ANAHCritere::NOMBRE_PERSONNE_PARAMETRE_SUPPLEMENT)
        ];

        $form_add = $this->createForm(ANAHCritereType::class, $ANAHCritere, [
            'trait_choices' => $formOption
        ]);

        if ($request->isMethod('POST') && $form_add->handleRequest($request)->isSubmitted() && $form_add->isValid())
        {
            $EM->persist($ANAHCritere);
            $EM->flush();

            $this->addFlash(
                'success',
                'Le Critère ANAH a été créé avec succès.'
            );

            return $this->redirectToRoute('ANAHCritere_list', array());
        }

        /* /////////////////////////////////////////////////////////////////
                                GET ARRAY FORM EDIT
        ///////////////////////////////////////////////////////////////// */
        $arrayForm_edit = array();

        foreach ($list_ANAHCritere as $item) {
            if (!$request->request->has('whitelabel_backofficebundle_anahcritere')) {
                /* /////////////////////////////////////////////////////////////////
                                        GET FORM EDIT
                ///////////////////////////////////////////////////////////////// */
                $formOption = [
                    'isSupplementsReadOnly' => ($item->getNbPersonne() != ANAHCritere::NOMBRE_PERSONNE_PARAMETRE_SUPPLEMENT)
                ];
                $form_edit = $formFactory->createNamed(
                    'formANAHCritere_edit_' . $item->getId(),
                    ANAHCritereType::class,
                    $item,
                    [
                        'trait_choices' => $formOption
                    ]
                );

                $arrayForm_edit[$item->getId()] = $form_edit->createView();

                if ($request->isMethod('POST') && $form_edit->handleRequest($request)->isSubmitted() && $form_edit->isValid())
                {
                    $item->setDateModif(new \Datetime());
                    $item->setAuteurModif($this->getUser()->getUsername());

                    $EM->persist($item);
                    $EM->flush();

                    $this->addFlash(
                        'success',
                        'Le critère ANAH a été modifié avec succès.'
                    );

                    return $this->redirectToRoute('ANAHCritere_list', array());
                }
            }
        }

        return $this->render('BackOffice/ANAHCritere/list.html.twig', array(
            'formAdd'           => $form_add->createView(),
            'formEdit'          => $arrayForm_edit,
            'list_ANAHCritere'  => $list_ANAHCritere
        ));
    }

}
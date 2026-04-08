<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Demande_;
use App\Entity\Production_;
use App\Entity\User;
use App\Form\Production_Type;
use App\Service\ProductionService;
use App\Service\UserService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

#[Security("is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN')")]
class ProductionController extends AbstractController
{
    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param ProductionService $productionService
     * @param UserService $userService
     * @param FormFactoryInterface $formFactory
     * @return RedirectResponse|Response
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \Exception
     */
    public function list(
        Request $request,
        EntityManagerInterface $em,
        ProductionService $productionService,
        UserService $userService,
        FormFactoryInterface $formFactory,
    ): RedirectResponse|Response {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $repo = $em->getRepository(Production_::class);
        $list_production = $repo->findAllCustom();

        $list_production_form = $repo->findBy([], ['id' => 'DESC']);

        $arrayForm = [];
        foreach ($list_production_form as $item) {
            if (!$request->request->has('whitelabel_backofficebundle_production_')) {
                if (('POST' !== $request->getMethod()) && ($item->getDateExpedition() !== null)) {
                    $dateExpedition = $item->getDateExpedition();
                    if (is_string($dateExpedition)) {
                        $dt = \DateTime::createFromFormat('Y-m-d', $dateExpedition) ?: \DateTime::createFromFormat('d/m/Y', $dateExpedition);
                        $item->setDateExpedition($dt ?: null);
                    }
                }
                /* /////////////////////////////////////////////////////////////////
                                                       GET FORM EDIT
                ///////////////////////////////////////////////////////////////// */
                $form_edit = $formFactory->createNamed('formProduction_edit_' . $item->getId(), Production_Type::class, $item);
                $form_edit->remove('type');
                $form_edit->remove('niveau');

                $arrayForm[$item->getId()] = $form_edit->createView();

                if ($request->isMethod('POST') && $form_edit->handleRequest($request)->isSubmitted() && $form_edit->isValid()) {
                    $item->setDateModif(new \DateTime());
                    $item->setAuteurModif($_SESSION['login']->getUsername());

                    $date_expedition = $item->getDateExpedition();
                    if (is_string($date_expedition)) {
                        $date_format_expedition = \DateTime::createFromFormat('d/m/Y', $date_expedition);
                        if ($date_format_expedition) {
                            $item->setDateExpedition($date_format_expedition);
                        }
                    }

                    $em->persist($item);
                    $em->flush();

                    $request->getSession()->getFlashBag()->add('success', 'La date d\'expedition a été modifiée avec succès.');

                    return $this->redirectToRoute('production_list', []);
                }
            }
        }

        $production = new Production_();
        $form = $this->createForm(Production_Type::class, $production, ['trait_choices' => null]);
        $form->remove('dateExpedition');

        $repo_demande = $em->getRepository(Demande_::class);
        $list_demande_typeE = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_AUDIT_ENERGIE_TYPE, null);
        $list_demande_typeERegion = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE, null);
        $list_demande_typeN = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE, null);
        $list_demande_typeMiseAJourAuditE = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE, null);
        $list_demande_niveau1 = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_TRAVAUX_TYPE, '0');
        $list_demande_niveau2 = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_TRAVAUX_TYPE, '1');
        $list_demande_renovateur = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_TRAVAUX_TYPE, '2');
        $list_demande_bbc = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_TRAVAUX_TYPE, '3');
        $list_demande_bbc_biosource = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_TRAVAUX_TYPE, '4');
        $list_demande_sortie_passoire = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_TRAVAUX_TYPE, '6');
        $list_demande_premiere_etape_BBC_RGE = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_TRAVAUX_TYPE, '7');
        $list_demande_premiere_etape_BBC_renovateur = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_TRAVAUX_TYPE, '8');
        $list_demande_renovation_gobale_BBC = $repo_demande->findProductionByTypeNiveau(Demande_::DEMANDE_TRAVAUX_TYPE, '9');

        // Attention : ordre est important, doit être le même que les affichage du form Type
        $count_data = [
            count($list_demande_typeE),
            count($list_demande_typeN),
            count($list_demande_typeERegion),
            count($list_demande_typeMiseAJourAuditE),
            count($list_demande_niveau1),
            count($list_demande_niveau2),
            count($list_demande_renovateur),
            count($list_demande_bbc),
            count($list_demande_bbc_biosource),
            count($list_demande_sortie_passoire),
            count($list_demande_premiere_etape_BBC_RGE),
            count($list_demande_premiere_etape_BBC_renovateur),
            count($list_demande_renovation_gobale_BBC)
        ];
        if ($request->isMethod('POST') && $form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $now = new \DateTime();
            $production->setDateLancement($now);
            $em->persist($production);
            $em->flush();

            /* /////////////////////////////////////////////////////////////////
             RECUPERATION DES DONNEES DU FORM : TABLEAU TYPE ET TABLEAU NIVEAU
            ///////////////////////////////////////////////////////////////// */
            $audit = $production->getType();
            $travaux = $production->getNiveau();

            $listData = [];

            // Audit Energie
            $list_demande_for_auditE_OPE = [];
            $param_auditEnergie = $this->getParameter('production_auditEnergie');
            if (in_array(Production_::TYPE_AUDIT_ENERGETIQUE_ET_SCENARIO_KEY, $audit)) {
                $list_demande_for_auditE_OPE = array_merge($list_demande_for_auditE_OPE, $list_demande_typeE);
            }
            if (in_array(Production_::TYPE_AUDIT_ENERGIE_REGION_KEY, $audit)) {
                $list_demande_for_auditE_OPE = array_merge($list_demande_for_auditE_OPE, $list_demande_typeERegion);
            }
            $listData[$param_auditEnergie] = $list_demande_for_auditE_OPE;

            // Audit Numérique
            $param_auditNumerique = $this->getParameter('production_auditNumerique');
            $list_demande_for_auditN_OPE = [];
            if (in_array(Production_::TYPE_AUDIT_NUMERIQUE_KEY, $audit)) {
                $list_demande_for_auditN_OPE = array_merge($list_demande_for_auditN_OPE, $list_demande_typeN);
            }
            if (in_array(Production_::TYPE_MISE_A_JOUR_AUDIT_ENERGIE_KEY, $audit)) {
                $list_demande_for_auditN_OPE = array_merge($list_demande_for_auditN_OPE, $list_demande_typeMiseAJourAuditE);
            }
            $listData[$param_auditNumerique] = $list_demande_for_auditN_OPE;

            $listeDemandeNiveau1_2 = [];
            if (in_array(Production_::NIVEAU_CHEQUE_TRAVAUX_NIVEAU_1_KEY, $travaux)) {
                $listeDemandeNiveau1_2 = array_merge($listeDemandeNiveau1_2, $list_demande_niveau1);
            }
            if (in_array(Production_::NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_KEY, $travaux)) {
                $listeDemandeNiveau1_2 = array_merge($listeDemandeNiveau1_2, $list_demande_niveau2);
            }
            if (in_array(Production_::NIVEAU_CHEQUE_TRAVAUX_NIVEAU_2_RENOVATEUR_KEY, $travaux)) {
                $listeDemandeNiveau1_2 = array_merge($listeDemandeNiveau1_2, $list_demande_renovateur);
            }
            if (in_array(Production_::NIVEAU_CHEQUE_TRAVAUX_SORTIE_PASSOIRE_KEY, $travaux)) {
                $listeDemandeNiveau1_2 = array_merge($listeDemandeNiveau1_2, $list_demande_sortie_passoire);
            }
            if (in_array(Production_::NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RGE_KEY, $travaux)) {
                $listeDemandeNiveau1_2 = array_merge($listeDemandeNiveau1_2, $list_demande_premiere_etape_BBC_RGE);
            }
            if (!empty($listeDemandeNiveau1_2)) {
                $listData[$this->getParameter('production_travauxNiveau1_2')] = $listeDemandeNiveau1_2;
            }

            // Travaux niveau BCC et BBC Biosourcé
            $arrayDemandeBBCAndBBCbiosource = [];
            if (in_array(Production_::NIVEAU_CHEQUE_TRAVAUX_BBC_KEY, $travaux)) {
                $arrayDemandeBBCAndBBCbiosource = array_merge($arrayDemandeBBCAndBBCbiosource, $list_demande_bbc);
            }
            if (in_array(Production_::NIVEAU_CHEQUE_TRAVAUX_BBC_BIOSOURCE_KEY, $travaux)) {
                $arrayDemandeBBCAndBBCbiosource = array_merge($arrayDemandeBBCAndBBCbiosource, $list_demande_bbc_biosource);
            }
            if (in_array(Production_::NIVEAU_CHEQUE_TRAVAUX_ETAPE1_BBC_RENOVATEUR_KEY, $travaux)) {
                $arrayDemandeBBCAndBBCbiosource = array_merge($arrayDemandeBBCAndBBCbiosource, $list_demande_premiere_etape_BBC_renovateur);
            }
            if (in_array(Production_::NIVEAU_CHEQUE_TRAVAUX_RENOVATION_GLOBALE_BBC_KEY, $travaux)) {
                $arrayDemandeBBCAndBBCbiosource = array_merge($arrayDemandeBBCAndBBCbiosource, $list_demande_renovation_gobale_BBC);
            }

            if (!empty($arrayDemandeBBCAndBBCbiosource)) {
                $listData[$this->getParameter('production_travauxNiveau_BBC1')] = $arrayDemandeBBCAndBBCbiosource;
                $listData[$this->getParameter('production_travauxNiveau_BBC2')] = $arrayDemandeBBCAndBBCbiosource;
            }

            $writeDataReturn = $productionService->writeData($listData, $production);

            $repo_user = $em->getRepository(User::class);
            $listEmailBcc = $userService->getEmailBcc($repo_user, null, [User::PARAM_ROLE_ADMIN]);
            $emailTo = trim($this->getUser()->getEmail());

            $templateReportPath = 'BackOffice/Production/email/reportLancement.html.twig';
            if (!$writeDataReturn['success']) {
                $request->getSession()->getFlashBag()->add('danger', 'Error lors du lancement de la production (écriture des fichiers concernés).');

                /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                                            GENERATE REPORT
                +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
                $productionService->sendEmailReport('Lancement de Production', $templateReportPath, ['reportContent' => $writeDataReturn['reportContent']], $emailTo, $listEmailBcc, null);
            } else {
                $request->getSession()->getFlashBag()->add('success', 'La production est en cours.');

                /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                                            GENERATE REPORT
                +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
                $productionService->sendEmailReport('Lancement de Production', $templateReportPath, ['reportContent' => $writeDataReturn['reportContent']], $emailTo, $listEmailBcc, null);
            }

            return $this->redirectToRoute('production_list', []);
        }

        return $this->render('BackOffice/Production/list.html.twig', [
            'list_production' => $list_production,
            'form' => $form->createView(),
            'arrayForm' => $arrayForm,
            'count_data_json' => json_encode($count_data),
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\Demande_;
use App\Entity\Historique_;
use App\Entity\User;
use App\Service\DemandeServiceBO;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Security("is_granted('ROLE_BO_DOSSIER_CONSULTATION')")]
class HistoriqueController extends AbstractController
{
    /**
     * La taille d'une tranche de chronologie générale.
     *
     * 411 000 lignes au total : la chronologie se charge à la demande, et par
     * tranches. Rien n'est lu tant que l'onglet n'est pas ouvert.
     */
    public const ACTIONS_PAR_TRANCHE = 50;

    private EntityManagerInterface $entityManager;
    private DemandeServiceBO $demandeService;

    public function __construct(EntityManagerInterface $entityManager, DemandeServiceBO $demandeService)
    {
        $this->entityManager = $entityManager;
        $this->demandeService = $demandeService;
    }

    /**
     * @param string $demandeId
     * @param string $redirectRoute
     * @return Response
     */
    public function list(string $demandeId, string $redirectRoute): Response
    {
        $option = array(
            'roles' => $this->getUser()->getRoles(),
            'username' => $this->getUser()->getUsername()
        );

        /* /////////////////////////////////////////////////////////////////
                                    GET DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $repo_demande = $this->entityManager->getRepository(Demande_::class);
        $demande = $repo_demande->find($demandeId);

        /* /////////////////////////////////////////////////////////////////
                            CHECK DEMANDE ACCESS CONTROLE
        ///////////////////////////////////////////////////////////////// */
        $this->demandeService->checkAccesByRole($demande, $option);

        /* /////////////////////////////////////////////////////////////////
                                    GET LIST HISTORIQUE
        ///////////////////////////////////////////////////////////////// */
        $repo = $this->entityManager->getRepository(Historique_::class);
        $list = $repo->findBy(
            array('demande_id' => $demandeId),
            array('dateCreation' => 'DESC')
        );

        /* /////////////////////////////////////////////////////////////////
                                    GET LIST AUTEUR
        ///////////////////////////////////////////////////////////////// */
        $repo_user = $this->entityManager->getRepository(User::class);

        $list_allUser = array();
        foreach ($list as $item) {
            $list_allUser[] = $repo_user->findOneBy(array(
                'username' => $item->getAuteurCreation()
            ));
        }

        $list_user = array();
        foreach ($list_allUser as $item) {
            if ($item) {
                $list_user[strtolower($item->getUsername())] = $item->getFirstname() . ' ' . $item->getLastname();
            }
        }

        return $this->render('BackOffice/Historique/list.html.twig', [
            'list' => $list,
            'list_user' => $list_user,
            'demande' => $demande,
            'redirectRoute' => $redirectRoute
        ]);
    }

    /**
     * Une tranche de la chronologie générale, servie en Ajax (D26013).
     *
     * Réservée aux administrateurs, et volontairement détachée des deux écrans
     * qui l'affichent : la liste des utilisateurs et celle des dossiers y
     * montrent la même matière — toutes les actions enregistrées — l'une du
     * point de vue des personnes, l'autre des dossiers.
     *
     * La pagination est un CURSEUR (`avant`), pas un numéro de page : voir
     * Historique_Repository::lesDernieresActions().
     */
    #[Security("is_granted('ROLE_BO_SUIVI_COMPTES')")]
    public function timeline(Request $request): Response
    {
        $avant = $request->query->getInt('avant');

        $depot = $this->entityManager->getRepository(Historique_::class);

        // Une ligne de plus que la tranche : sa présence dit qu'il reste
        // quelque chose à charger, sans avoir à compter 411 000 lignes.
        $lignes = $depot->lesDernieresActions(
            $avant > 0 ? $avant : null,
            self::ACTIONS_PAR_TRANCHE + 1
        );

        $reste = count($lignes) > self::ACTIONS_PAR_TRANCHE;
        $lignes = array_slice($lignes, 0, self::ACTIONS_PAR_TRANCHE);
        $derniere = end($lignes);

        return new JsonResponse([
            'html' => $this->renderView('BackOffice/Historique/inc/timeline_tranche.html.twig', [
                'lignes' => $lignes,
            ]),
            'suivant' => $reste && false !== $derniere ? (int) $derniere['id'] : null,
            'tranche' => count($lignes),
        ]);
    }
}

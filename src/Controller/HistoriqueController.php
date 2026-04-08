<?php

namespace App\Controller;

use App\Entity\Demande_;
use App\Entity\Historique_;
use App\Entity\User;
use App\Service\DemandeServiceBO;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_INSTRUCTEUR') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_EPCI') or is_granted('ROLE_CLIENT') or is_granted('ROLE_ADMIN') or is_granted('ROLE_TECHNIQUE')")]
class HistoriqueController extends AbstractController
{
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

}
<?php

namespace App\Controller;

use App\Entity\Demande_;
use App\Entity\Historique_;
use App\Entity\User;
use App\Service\CommentaireService;
use App\Service\DemandeServiceBO;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[Security("is_granted('ROLE_BO_DOSSIER_CONSULTATION')")]
class HistoriqueController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private DemandeServiceBO $demandeService;
    private CommentaireService $commentaireService;

    public function __construct(
        EntityManagerInterface $entityManager,
        DemandeServiceBO $demandeService,
        CommentaireService $commentaireService
    ) {
        $this->entityManager = $entityManager;
        $this->demandeService = $demandeService;
        $this->commentaireService = $commentaireService;
    }

    /**
     * @param string $demandeId
     * @param string $redirectRoute
     * @return Response
     */
    public function list(string $demandeId, string $redirectRoute): Response
    {
        $donnees = $this->donneesHistorique($demandeId);

        return $this->render('BackOffice/Historique/list.html.twig', $donnees + [
            'redirectRoute' => $redirectRoute
        ]);
    }

    /**
     * Historique d'un dossier pour le panneau ouvert depuis la liste : mêmes étapes, présentées en
     * frise, et renvoyées en fragment HTML. La vue complète (list) reste accessible par ailleurs.
     */
    public function panneau(string $demandeId): Response
    {
        return $this->render('BackOffice/Historique/inc/_frise.html.twig', $this->donneesHistorique($demandeId));
    }

    /**
     * Regroupe les envois d'une étape : un même message parti vers plusieurs adresses est
     * enregistré une fois par adresse. Les envois de même sujet et de même contenu sont réunis, et
     * leurs destinataires rassemblés — inutile de répéter le message autant de fois. Deux messages
     * différents (l'auditeur et le rénovateur n'en reçoivent pas le même) restent distincts.
     *
     * @param list<Historique_> $list
     *
     * @return array<int, list<array{sujet: string, contenu: string, destinataires: list<string>}>>
     */
    private static function regrouperEnvois(array $list): array
    {
        $envoisParEtape = [];

        foreach ($list as $etape) {
            $groupes = [];

            foreach ($etape->getHistoriqueEmail() as $email) {
                $cle = md5($email->getSubject() . '|' . $email->getContent());

                $groupes[$cle] ??= [
                    'sujet' => $email->getSubject(),
                    'contenu' => $email->getContent(),
                    'destinataires' => [],
                ];

                foreach ((array) $email->getRecipient() as $adresse) {
                    if (!in_array($adresse, $groupes[$cle]['destinataires'], true)) {
                        $groupes[$cle]['destinataires'][] = $adresse;
                    }
                }
            }

            $envoisParEtape[$etape->getId()] = array_values($groupes);
        }

        return $envoisParEtape;
    }

    /**
     * Étapes du dossier et noms de leurs auteurs. Le périmètre est vérifié ici : un dossier
     * inexistant ou hors périmètre donne un 404.
     *
     * @return array{list: array, list_user: array<string, string>, demande: Demande_}
     */
    private function donneesHistorique(string $demandeId): array
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

        /* /////////////////////////////////////////////////////////////////
                        PROFIL DES DESTINATAIRES (Bénéficiaire, Auditeur…)
        ///////////////////////////////////////////////////////////////// */
        // Seules les adresses sont enregistrées avec un envoi. Le profil est retrouvé à partir des
        // intervenants du dossier (bénéficiaire, conseiller, auditeur, rénovateur) et des adresses
        // de la Région et du prestataire. Une adresse inconnue s'affiche seule, sans étiquette.
        $profilsParEmail = array_flip(array_merge(
            $repo->findAdressesIntervenants((int) $demandeId),
            $this->commentaireService->searchRecipientFormList((int) $demandeId, (int) $demande->getType())
        ));

        return [
            'list' => $list,
            'list_user' => $list_user,
            'demande' => $demande,
            'profilsParEmail' => $profilsParEmail,
            'envoisParEtape' => self::regrouperEnvois($list),
        ];
    }

}
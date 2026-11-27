<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Historique_;


class Historique_Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Historique_::class);
    }

    /**
     * @param $demandeId
     * @return array
     * @throws Exception
     */
    public function findCommentaire($demandeId)
    {
        $query = "
            SELECT  h.id AS historiqueId,
                    DATE_FORMAT(h.date_creation, '%d/%m/%Y à %H:%i:%s') AS commentaireDate,
                    u.email AS commentaireAuteur,
                    he.content AS commentaireContent,
                    he.recipient AS commentaireRecipient
            FROM historique_ h
                INNER JOIN historique_email he ON he.historique_id = h.id
                INNER JOIN user u ON u.username = h.auteur_creation
            WHERE h.demande_id = " . $demandeId . "
                AND h.action = 'commentaire'
            ORDER BY h.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $demandeId
     * @return array
     * @throws Exception
     */
    public function countCommentaireByDemande($demandeId)
    {
        $query = "
            SELECT COUNT(h.id)
            FROM historique_ h
                INNER JOIN historique_email he ON he.historique_id = h.id
                INNER JOIN user u ON u.username = h.auteur_creation
            WHERE h.demande_id = " . $demandeId . "
                AND h.action = 'commentaire'
            GROUP BY h.id
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
    /**
     * Le nombre d'actions d'un compte (D26013, point 4).
     *
     * L'historique porte le nom de l'auteur, pas son identifiant : c'est une
     * trace, elle survit à la suppression du compte. Le rapprochement se fait
     * donc sur le login, et l'index auteur_date_idx est là pour cela.
     */
    public function compterLesActionsDe(string $auteur): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.auteurCreation = :auteur')
            ->setParameter('auteur', $auteur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Une page d'actions, de la plus récente à la plus ancienne.
     *
     * La pagination n'est pas un confort : un seul compte porte 57 000 lignes,
     * et la page entière ne tiendrait ni en mémoire ni à l'écran.
     *
     * Le tri secondaire sur l'identifiant départage les actions enregistrées
     * dans la même seconde — sans quoi deux pages voisines pourraient répéter
     * ou omettre une ligne.
     *
     * @return list<Historique_>
     */
    public function lesActionsDe(string $auteur, int $limite, int $decalage): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.auteurCreation = :auteur')
            ->setParameter('auteur', $auteur)
            ->orderBy('h.dateCreation', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->setMaxResults($limite)
            ->setFirstResult($decalage)
            ->getQuery()
            ->getResult();
    }
    /**
     * L'historique d'un dossier, du plus récent au plus ancien (D26013).
     *
     * Pas de pagination : le dossier le plus chargé porte 87 lignes.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function lesActionsDuDossier(int $demandeId): array
    {
        $lignes = $this->_em->getConnection()->fetchAllAssociative(
            <<<'SQL'
            SELECT id, date_creation, action, auteur_creation, auteur_role,
                   demande_id, statut_slug, remboursement_id
            FROM historique_
            WHERE demande_id = :demande
            ORDER BY date_creation DESC, id DESC
            SQL,
            ['demande' => $demandeId]
        );

        return $this->avecLesAuteurs($lignes);
    }

    /**
     * La chronologie générale, par tranches, de la plus récente à la plus
     * ancienne (D26013).
     *
     * Le tri se fait sur l'IDENTIFIANT et non sur la date : la table ne porte
     * aucun index sur `date_creation` seule, et trier 411 000 lignes par date
     * imposerait un tri sur disque à chaque consultation. L'identifiant est
     * auto-incrémenté, donc déjà dans l'ordre chronologique des
     * enregistrements : le parcours se fait à rebours de la clé primaire, sans
     * aucun tri.
     *
     * La pagination est par CURSEUR et non par décalage : un `OFFSET 100000`
     * ferait lire cent mille lignes pour en rendre cinquante, là où `id <
     * :avant` part directement au bon endroit. Le coût d'une tranche ne dépend
     * donc pas de sa profondeur — mesuré identique sur la première et sur la
     * dernière.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function lesDernieresActions(?int $avant, int $limite): array
    {
        $parametres = ['limite' => $limite];
        $curseur = '';

        if (null !== $avant) {
            $curseur = ' WHERE id < :avant';
            $parametres['avant'] = $avant;
        }

        $lignes = $this->_em->getConnection()->fetchAllAssociative(
            'SELECT id, date_creation, action, auteur_creation, auteur_role,'
            . ' demande_id, statut_slug, remboursement_id'
            . ' FROM historique_' . $curseur
            . ' ORDER BY id DESC LIMIT :limite',
            $parametres,
            ['limite' => \PDO::PARAM_INT, 'avant' => \PDO::PARAM_INT]
        );

        return $this->avecLesAuteurs($lignes);
    }

    /**
     * Ajoute le nom des auteurs, en une requête pour toute la tranche.
     *
     * Surtout pas par une jointure SQL : `user.username` n'est pas indexé —
     * seul `username_canonical` l'est —, la jointure se fait donc par parcours
     * complet de la table des comptes. Sur la chronologie générale,
     * l'optimiseur y ajoutait un tri temporaire sur 403 000 lignes et la
     * requête ne rendait plus la main.
     *
     * Surtout pas non plus compte par compte : l'écran d'historique existant
     * interroge `user` une fois par ligne, jusqu'à 87 fois pour un dossier.
     *
     * Un auteur introuvable — compte supprimé depuis, ou « Automate » — laisse
     * les deux champs à null : le gabarit retombe alors sur le login.
     *
     * @param list<array<string, mixed>> $lignes
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    private function avecLesAuteurs(array $lignes): array
    {
        if ([] === $lignes) {
            return [];
        }

        $logins = array_values(array_unique(array_column($lignes, 'auteur_creation')));

        $comptes = [];

        foreach ($this->_em->getConnection()->fetchAllAssociative(
            'SELECT username, firstname, lastname FROM user WHERE username IN (:logins)',
            ['logins' => $logins],
            ['logins' => \Doctrine\DBAL\ArrayParameterType::STRING]
        ) as $compte) {
            $comptes[$compte['username']] = $compte;
        }

        return array_map(
            static fn (array $ligne): array => $ligne + [
                'firstname' => $comptes[$ligne['auteur_creation']]['firstname'] ?? null,
                'lastname' => $comptes[$ligne['auteur_creation']]['lastname'] ?? null,
            ],
            $lignes
        );
    }
}

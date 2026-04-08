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
}

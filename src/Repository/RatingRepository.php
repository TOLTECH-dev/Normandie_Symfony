<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Rating;


class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    /**
     * @param $toUserId
     * @param $type
     * @return false | array
     * @throws Exception
     */
    public function findScore($toUserId, $type): bool|array
    {
        $query = "
            SELECT  SUM(CASE WHEN r.score = '1' THEN 1 ELSE 0 END) AS countMauvais,
                    SUM(CASE WHEN r.score = '2' THEN 1 ELSE 0 END) AS countMoyen,
                    SUM(CASE WHEN r.score = '3' THEN 1 ELSE 0 END) AS countParfait,
                    ROUND(SUM(CASE WHEN r.score = '1' THEN 1 ELSE 0 END)/SUM(CASE WHEN r.score <> '0' THEN 1 ELSE 0 END)*100) AS percentMauvais,
                    ROUND(SUM(CASE WHEN r.score = '2' THEN 1 ELSE 0 END)/SUM(CASE WHEN r.score <> '0' THEN 1 ELSE 0 END)*100) AS percentMoyen,
                    ROUND(SUM(CASE WHEN r.score = '3' THEN 1 ELSE 0 END)/SUM(CASE WHEN r.score <> '0' THEN 1 ELSE 0 END)*100) AS percentParfait
            FROM rating r
            WHERE r.to_user_id = ?
                AND r.type = ?
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery([$toUserId, $type]);

        return $result->fetchAssociative();
    }

    /**
     * @param $toUserId
     * @param $type
     * @param array $option
     * @return array
     * @throws Exception
     */
    public function findCommentaire($toUserId, $type, array $option = []): array
    {
        $queryJoin = '';
        if ($option['fromCtoA'] == $type) {
            $queryJoin ="
                LEFT JOIN remboursement_audit_energie_validation raev ON raev.rating_id = r.id
                LEFT JOIN remboursement_audit_energie rae ON rae.validation_id = raev.id
                LEFT JOIN remboursement_ re ON re.remboursement_audit_energie_id = rae.id
            ";
        } elseif ($option['fromRtoR'] == $type) {
            $queryJoin ="
                LEFT JOIN remboursement_travaux rt ON rt.rating_id = r.id
                LEFT JOIN remboursement_ re ON re.remboursement_travaux_id = rt.id
            ";
        }

        $query = "
            SELECT  r.id AS ratingId,
                    re.demande_id AS demandeId,
                    r.score AS ratingScore,
                    r.commentaire AS ratingCommentaire,
                    CONCAT(CONCAT(UCASE(LEFT(u.firstname, 1)), LCASE(SUBSTRING(u.firstname, 2))), ' ', UCASE(u.lastname)) AS ratingAuteur,
                    DATE_FORMAT(r.date_creation, '%d/%m/%Y à %h:%i') AS ratingDate
            FROM rating r
                INNER JOIN user u ON u.id = r.from_user_id
                " . $queryJoin . "
            WHERE r.to_user_id = ?
                AND r.type = ?
                AND r.commentaire IS NOT NULL
            ORDER BY r.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery([$toUserId, $type]);

        return $result->fetchAllAssociative();
    }
}

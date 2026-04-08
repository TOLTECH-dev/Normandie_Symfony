<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Structure_conseiller;


class Structure_conseillerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Structure_conseiller::class);
    }

    /**
     * @param $id
     * @return array|false
     * @throws Exception
     */
    public function findSlugById($id)
    {
        if ('' != $id) {
            $query = "
                SELECT  id,
                        nom,
                        prenom
                FROM structure_conseiller
                WHERE id = " . $id . "
            ";

            $statement = $this->_em
                ->getConnection()
                ->prepare($query);
            $result = $statement->executeQuery();

            return $result->fetchAssociative();
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function findAllCustom()
    {
        $query = "
            SELECT CONCAT(LOWER(sc.nom),LOWER(sc.prenom)) AS conseillerNom
            FROM structure_conseiller sc
            ORDER BY sc.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

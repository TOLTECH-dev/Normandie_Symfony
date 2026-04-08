<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Structure_permanence;


class Structure_permanenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Structure_permanence::class);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function findAllCustom()
    {
        $query = "
            SELECT LOWER(sp.nom) AS conseillerPermanence
            FROM structure_permanence sp
            ORDER BY sp.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

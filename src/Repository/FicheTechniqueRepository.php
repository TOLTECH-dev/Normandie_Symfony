<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\FicheTechnique;


class FicheTechniqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheTechnique::class);
    }

    /**
     * @param $type
     * @return array
     * @throws Exception
     */
    public function search($type)
    {
        $query = "
             SELECT  id AS id,
                    libelle AS libelle,
                    slug AS slug
            FROM up_ficheTechnique
            WHERE filtre = '" . $type . "'
            ORDER BY id ASC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

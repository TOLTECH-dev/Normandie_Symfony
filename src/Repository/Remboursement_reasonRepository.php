<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Remboursement_reason;


class Remboursement_reasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Remboursement_reason::class);
    }

    /**
     * @param $filtre
     * @return QueryBuilder
     */
    public function findByFiltre($filtre)
    {
        $query = $this
            ->createQueryBuilder('a');

        $query = $query
            ->where('a.filtre LIKE :filtre')
            ->setParameters(array(
                'filtre' => $filtre
            ))
            ->orderBy('a.positionLast', 'ASC');
        ;

        return $query;
    }
}

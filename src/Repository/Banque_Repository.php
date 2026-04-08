<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Banque_;


class Banque_Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Banque_::class);
    }

    /**
     * @param $enabled
     * @return QueryBuilder
     */
    public function findByEnabled($enabled)
    {
        $query = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->join('a.banque_statut', 'banque_statut')
            ->addSelect('banque_statut');

        $query = $query
            ->where('banque_statut.enabled LIKE :enabled')
            ->setParameters(array(
                'enabled'   => $enabled
            ))
            ->orderBy('a.nom', 'ASC')
        ;

        return $query;
    }
}

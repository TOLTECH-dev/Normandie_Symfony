<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Remboursement_statut;


class Remboursement_statutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Remboursement_statut::class);
    }

    /**
     * @param $statut
     * @return string
     */
    public function findSlugByStatut($statut)
    {
        $slug = '';
        $queryBuilder = $this
            ->createQueryBuilder('s');
        $queryBuilder
            ->select('s.slug')
            ->where($queryBuilder->expr()->eq('s.statut', ':statut'))
            ->setParameter('statut', $statut);
        $results = $queryBuilder->getQuery()->getResult();

        if ($results && $results[0]) {
            $slug = $results[0]['slug'];
        }

        return $slug;
    }
}

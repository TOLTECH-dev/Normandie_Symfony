<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\ANAHCritere;


class ANAHCritereRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ANAHCritere::class);
    }

    /**
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findNbPersonneMax()
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select($qb->expr()->max('a.nbPersonne'));

        $res = (integer)($qb->getQuery()->getSingleScalarResult());

        return $res;
    }
}

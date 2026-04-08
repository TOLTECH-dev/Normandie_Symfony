<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\PlanFinancementType;


class PlanFinancementTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanFinancementType::class);
    }

    /**
     * @param $categoryId
     * @return QueryBuilder
     */
    public function findCustomQbByCategoryId($categoryId)
    {
        $qb = $this
            ->createQueryBuilder('t')
            ->select('t')
            ->where('t.categoryId = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('t.id', 'ASC')
        ;
        return $qb;
    }
}

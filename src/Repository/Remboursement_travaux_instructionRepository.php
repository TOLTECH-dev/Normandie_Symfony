<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Remboursement_travaux_instruction;


class Remboursement_travaux_instructionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Remboursement_travaux_instruction::class);
    }


    /**
     *
     * @param int $conformiteId
     * @return Remboursement_travaux_instruction|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByConformiteId(int $conformiteId): ?Remboursement_travaux_instruction
    {
        return $this->createQueryBuilder('rti')
            ->join('rti.remboursement_travaux_instruction_conformite', 'rtic')
            ->where('rtic.id = :conformiteId')
            ->setParameter('conformiteId', $conformiteId)
            ->getQuery()
            ->getOneOrNullResult();
    }

}

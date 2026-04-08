<?php

namespace App\Repository;

use App\Entity\Remboursement_auditEnergie_validation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Remboursement_auditEnergie_validationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Remboursement_auditEnergie_validation::class);
    }

}
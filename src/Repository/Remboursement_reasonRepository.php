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
     * Les motifs proposables pour un filtre donné.
     *
     * Seuls les motifs ACTIFS sont proposés (D26013). Les identifiants
     * passés en second argument sont conservés même s'ils ne le sont
     * plus : ce sont ceux déjà choisis sur le dossier en cours
     * d'édition. Sans cette précaution, rouvrir un dossier instruit avec
     * un motif depuis désactivé ferait échouer le formulaire, la valeur
     * liée ne figurant plus parmi les choix.
     *
     * @param string    $filtre
     * @param list<int> $idsDejaChoisis
     */
    public function findByFiltre($filtre, array $idsDejaChoisis = []): QueryBuilder
    {
        $query = $this
            ->createQueryBuilder('a')
            ->orderBy('a.positionLast', 'ASC')
            ->setParameter('filtre', $filtre);

        if ([] === $idsDejaChoisis) {
            return $query->where('a.filtre LIKE :filtre')
                ->andWhere('a.actif = true');
        }

        return $query
            ->where('a.filtre LIKE :filtre')
            ->andWhere('a.actif = true OR a.id IN (:dejaChoisis)')
            ->setParameter('dejaChoisis', $idsDejaChoisis);
    }
}

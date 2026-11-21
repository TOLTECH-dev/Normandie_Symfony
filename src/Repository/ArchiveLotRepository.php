<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArchiveLot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArchiveLot>
 */
class ArchiveLotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArchiveLot::class);
    }

    public function findParReference(string $reference): ?ArchiveLot
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    /**
     * La campagne en cours, s'il y en a une.
     *
     * Une seule campagne peut être ouverte à la fois : deux lots simultanés
     * pourraient retenir le même compte, et le second détruirait des données
     * dont le premier a la charge.
     */
    public function findLotOuvert(): ?ArchiveLot
    {
        return $this->createQueryBuilder('l')
            ->where('l.etat IN (:etats)')
            ->setParameter('etats', ArchiveLot::ETATS_OUVERTS)
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<ArchiveLot>
     */
    public function findTousDuPlusRecent(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function enregistrer(ArchiveLot $lot, bool $vider = true): void
    {
        $this->getEntityManager()->persist($lot);

        if ($vider) {
            $this->getEntityManager()->flush();
        }
    }
}

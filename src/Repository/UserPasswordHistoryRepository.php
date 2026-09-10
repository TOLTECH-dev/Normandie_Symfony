<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPasswordHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPasswordHistory>
 */
class UserPasswordHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPasswordHistory::class);
    }

    /**
     * @return string[] empreintes des derniers mots de passe du compte, de la plus récente à la plus ancienne
     */
    public function findLatestPasswords(User $user, int $limit): array
    {
        if ($limit <= 0 || null === $user->getId()) {
            return [];
        }

        $rows = $this->createQueryBuilder('h')
            ->select('h.password')
            ->where('h.user = :user')
            ->setParameter('user', $user)
            ->orderBy('h.createdAt', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'password');
    }

    /**
     * Ne conserve que les $keep entrées les plus récentes du compte.
     */
    public function purgeOlderEntries(User $user, int $keep): void
    {
        $keptIds = [];

        if ($keep > 0) {
            $rows = $this->createQueryBuilder('h')
                ->select('h.id')
                ->where('h.user = :user')
                ->setParameter('user', $user)
                ->orderBy('h.createdAt', 'DESC')
                ->addOrderBy('h.id', 'DESC')
                ->setMaxResults($keep)
                ->getQuery()
                ->getArrayResult();

            $keptIds = array_column($rows, 'id');
        }

        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->delete(UserPasswordHistory::class, 'h')
            ->where('h.user = :user')
            ->setParameter('user', $user);

        if ([] !== $keptIds) {
            $queryBuilder->andWhere('h.id NOT IN (:keptIds)')->setParameter('keptIds', $keptIds);
        }

        $queryBuilder->getQuery()->execute();
    }
}

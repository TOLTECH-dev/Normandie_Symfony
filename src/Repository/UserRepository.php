<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newEncodedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newEncodedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find an active user by username
     */
    public function findActiveByUsername(string $username): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.username = :username')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('username', $username)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find an active user by email
     */
    public function findActiveByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('email', $email)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find users by role
     *
     * @return User[]
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('JSON_CONTAINS(u.roles, :role) = true')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('role', sprintf('"%s"', $role))
            ->setParameter('isActive', true)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active users
     *
     * @return User[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :isActive')
            ->setParameter('isActive', true)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param $email
     * @return array|false
     * @throws Exception
     */
    public function search($email)
    {
        $query = "
            SELECT  u.id AS userId,
                    u.email AS userEmail,
                    u.is_france_connect as userIsFranceConnect,
                    CASE u.is_france_connect
                        WHEN '1'
                            THEN 'existe_via_fc'
                        ELSE 'existe_hors_fc'
                    END AS choice
            FROM user u
            WHERE u.email = '" . $email . "'
        ";

        $stmt = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $stmt->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function findDataDeleteAccountCommand()
    {
        $dateJour = new \DateTime();
        $twoYearsAgoDate = date('Y-m-d', mktime(0,0,0, (integer)($dateJour->format('m')),(integer)($dateJour->format('d')), $dateJour->format('Y')-2));

        $query = $this->createQueryBuilder('u')
            ->select('u')
            ->addSelect('b', 'l')
            ->leftJoin('App\Entity\Beneficiaire', 'b', Join::WITH, 'b.user_id = u.id')
            ->leftJoin('App\Entity\Logement', 'l', Join::WITH, 'l.beneficiaire_id = b.id')
            ->leftJoin('App\Entity\Demande_', 'd', Join::WITH, 'd.beneficiaire_id = b.id')
            ->where('d.id IS NULL')
            ->andWhere('u.dateCreation <= :dateCreation')
            ->andWhere('u.roles LIKE :role')
            ->setParameters(
                array(
                    'dateCreation'  => $twoYearsAgoDate,
                    'role'          => '%ROLE_MEMBER%'
                )
            );
        return $query->getQuery()->getResult();
    }

    /**
     * @param array $roles
     * @param bool $hasRole
     * @param bool $enabled
     * @param false $isSelectOnlyEmail
     * @param false $isSelectOnlyUsername
     * @return array|int|string
     */
    public function getList(
        $roles = [],
        $hasRole = true,
        $enabled = true,
        $isSelectOnlyEmail = false,
        $isSelectOnlyUsername = false
    ) {
        $hasRole = $hasRole ? '' : 'NOT';

        $qb = $this->createQueryBuilder('u');

        if ($isSelectOnlyEmail) {
            $qb->select('u.email');
        } else if ($isSelectOnlyUsername) {
            $qb->select('u.username');
        }

        foreach ($roles as $key => $item) {
            $qb->orWhere('u.roles ' . $hasRole . ' LIKE :_' . $key)
                ->setParameter('_' . $key, '%' . $item . '%');
        }

        if (isset($enabled)) {
            $qb->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', $enabled);
        }

        return ($isSelectOnlyEmail || $isSelectOnlyUsername) ? $qb->getQuery()->getResult("ColumnHydrator") : $qb->getQuery()->getResult();
    }

    /**
     * @param array $data
     * @param string $where
     * @return int|mixed|string|null
     * @throws NonUniqueResultException
     */
    public function countForList($data = [], $where = '')
    {
        $qb = $this->createQueryBuilder("u");
        $qb->select("COUNT(u.id) AS countId")
            ->setMaxResults(1);

        if(!empty($where)) {
            $qb->andWhere($where);
        }
        if (!empty($data['param'])) {
            $qb->setParameters($data['param']);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param $data
     * @param $orderBy
     * @param $orderType
     * @param $start
     * @param $length
     * @param null $where
     * @return array|int|string
     */
    public function findForListAjax(
        $data,
        $orderBy,
        $orderType,
        $start,
        $length,
        $where = null
    ) {

        $qb = $this->createQueryBuilder("u");
        $qb->select("u.id AS id")
            ->addSelect("u.username AS username")
            ->addSelect("u.firstname AS firstname")
            ->addSelect("u.lastname AS lastname")
            ->addSelect("u.email AS email")
            ->addSelect("u.enabled AS enabled")
            ->addSelect("u.roles AS roles")
            ->addSelect("IF(u.lastLogin IS NOT NULL, DATE_FORMAT(u.lastLogin, '%d/%m/%Y'), '-') AS lastLogin")
            ->addSelect("IF(u.dateInactif IS NOT NULL, DATE_FORMAT(u.dateInactif, '%d/%m/%Y'), '-') AS dateInactif")
            ->addSelect("'' AS action");

        if(!empty($where)) {
            $qb->andWhere($where);
        }
        if (!empty($data['param'])) {
            $qb->setParameters($data['param']);
        }

        $qb->orderBy($orderBy, $orderType)
            ->setFirstResult($start)
            ->setMaxResults($length);
//var_dump($qb->getQuery()->getResult()); die();
        return $qb->getQuery()->getResult();
    }
}


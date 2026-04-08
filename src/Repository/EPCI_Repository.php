<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\EPCI_;


class EPCI_Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EPCI_::class);
    }

    /**
     * @return QueryBuilder
     */
    public function findEnabled()
    {
        $query = $this
            ->createQueryBuilder('a');

        return $query;
    }

    /**
     * @param $contactId
     * @return false | array
     * @throws Exception
     */
    public function findByContactId($contactId)
    {
        $query = "
            SELECT e.id AS id
            FROM EPCI_ e
                INNER JOIN EPCI__EPCI_contact eec ON e.id = eec.epci__id
                INNER JOIN EPCI_contact ec ON eec.epci_contact_id = ec.id
            WHERE ec.id = " . $contactId . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @return array
     * @throws Exception
     */
    function findContact()
    {
        $query = "
            SELECT e.email 
            FROM EPCI e
            WHERE e.email IS NOT NULL
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }


    /**
     * Vérifie si un EPCI donné est rattaché à un logement via son code INSEE.
     *
     * @param int    $epciId
     * @param string $inseeCode
     *
     * @return bool
     */
    public function checkEpciAccessByInsee(int $epciId, string $inseeCode): bool
    {
        $qb = $this->_em->getConnection()->createQueryBuilder();

        $qb->select('1')
            ->from('up_ville', 'uv')
            ->leftJoin('uv', 'orientation', 'o', 'uv.id = o.ville_id')
            ->where('o.EPCI_id = :epciId')
            ->andWhere('uv.code_insee = :inseeCode')
            ->setParameter('epciId', $epciId)
            ->setParameter('inseeCode', $inseeCode)
            ->setMaxResults(1);

        $result = $qb->executeQuery()->fetchOne();

        return (bool) $result;
    }

    /**
     * Récupère l'ID de l’EPCI à partir du contact (utilisateur BO).
     *
     * @param int $contactId
     *
     * @return int|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findEpciIdByContactId(int $contactId): ?int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.id')
            ->where('e.contactId = :contactId')
            ->setParameter('contactId', $contactId)
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result ? (int) $result['id'] : null;
    }
}

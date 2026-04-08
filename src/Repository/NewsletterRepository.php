<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Newsletter;


class NewsletterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Newsletter::class);
    }

    /**
     * @param $arrayDestinataire
     * @return array
     * @throws Exception
     */
    public function findContact($arrayDestinataire)
    {
        $arrayQueryWhere = array();

        if (true == $arrayDestinataire['isSentToClient']) $arrayQueryWhere[] = "u.roles LIKE '%ROLE_CLIENT%'";
        if (true == $arrayDestinataire['isSentToAuditeur']) $arrayQueryWhere[] = "u.roles LIKE '%ROLE_AUDITEUR%'";
        if (true == $arrayDestinataire['isSentToRenovateur']) $arrayQueryWhere[] = "u.roles LIKE '%ROLE_RENOVATEUR%'";
        if (true == $arrayDestinataire['isSentToConseiller']) $arrayQueryWhere[] = "u.roles LIKE '%ROLE_CONSEILLER%'";
        if (true == $arrayDestinataire['isSentToEPCI']) $arrayQueryWhere[] = "u.roles LIKE '%ROLE_EPCI%'";
        if (true == $arrayDestinataire['isSentToBeneficiaire']) $arrayQueryWhere[] = "u.roles LIKE '%ROLE_MEMBER%'";
        if (true == $arrayDestinataire['isSentToAdministrateur']) $arrayQueryWhere[] = "u.roles LIKE '%ROLE_ADMIN%'";
        if (true == $arrayDestinataire['isSentToInstructeur']) $arrayQueryWhere[] = "u.roles LIKE '%ROLE_INSTRUCTEUR%'";
        if (true == $arrayDestinataire['isSentToTechnique']) $arrayQueryWhere[] = "u.roles LIKE '%ROLE_TECHNIQUE%'";

        if (!empty($arrayQueryWhere)) {
            $queryWhere = "AND (" . implode(' OR ' , $arrayQueryWhere) . ")";

            $query = "
                SELECT u.email
                FROM user u
                WHERE u.enabled = 1 AND u.email IS NOT NULL " . $queryWhere . "
            ";

            $statement = $this->_em
                ->getConnection()
                ->prepare($query);
            $result = $statement->executeQuery();

            return $result->fetchFirstColumn();
        }

        return array();
    }
}

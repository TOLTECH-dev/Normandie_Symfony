<?php

namespace App\Repository;

use App\Entity\Orientation;
use App\Utils\DefaultServiceUtils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;


class OrientationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Orientation::class);
    }

    /**
     * @param $orientationId
     * @return array
     * @throws Exception
     */
    public function findByIdCustom($orientationId)
    {
        $query = "
            SELECT  o.id AS orientationId,
                    v.code_postal AS orientationCodePostal,
                    v.nom AS orientationVille,
                    e.nom AS EPCInom,
                    GROUP_CONCAT(DISTINCT siinf.nom ORDER BY siinf.nom ASC SEPARATOR '" . DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS . "') AS structureInferieurNomConcat,
                    GROUP_CONCAT(DISTINCT sisup.nom ORDER BY sisup.nom ASC SEPARATOR '" . DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS . "') AS structureSuperieurNomConcat
            FROM orientation o
                LEFT JOIN EPCI_ e ON o.EPCI_id = e.id
                INNER JOIN up_ville v ON o.ville_id = v.id
                LEFT JOIN orientation_structure_inferieur osinf ON o.id = osinf.orientation_id
                LEFT JOIN structure_ sinf ON osinf.structure_id = sinf.id
                LEFT JOIN structure_identification siinf ON sinf.structure_identification_id = siinf.id
                LEFT JOIN orientation_structure_superieur ossup ON o.id = ossup.orientation_id
                LEFT JOIN structure_ ssup ON ossup.structure_id = ssup.id
                LEFT JOIN structure_identification sisup ON ssup.structure_identification_id = sisup.id
            WHERE o.id = " . $orientationId
        ;

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
    public function searchByVille()
    {
        $query = "
            SELECT  v.id AS ville_id,
                    v.code_postal AS ville_codePostal,
                    v.nom AS ville_nom,
                    o.id AS orientation_id,
                    GROUP_CONCAT(DISTINCT siinf.nom ORDER BY siinf.nom ASC SEPARATOR '" . DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS . "') AS structureInferieurNomConcat,
                    GROUP_CONCAT(DISTINCT sisup.nom ORDER BY sisup.nom ASC SEPARATOR '" . DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS . "') AS structureSuperieurNomConcat,
                    E.nom AS EPCI_nom
            FROM up_ville v
            LEFT JOIN orientation o ON o.ville_id = v.id
            LEFT JOIN EPCI_ E ON o.EPCI_id = E.id
            LEFT JOIN orientation_structure_inferieur osinf ON o.id = osinf.orientation_id
            LEFT JOIN structure_ sinf ON osinf.structure_id = sinf.id
            LEFT JOIN structure_identification siinf ON sinf.structure_identification_id = siinf.id
            LEFT JOIN orientation_structure_superieur ossup ON o.id = ossup.orientation_id
            LEFT JOIN structure_ ssup ON ossup.structure_id = ssup.id
            LEFT JOIN structure_identification sisup ON ssup.structure_identification_id = sisup.id
            WHERE SUBSTRING(v.code_postal,1,2) IN ('14','27','50','61','76')
            GROUP BY v.id
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $logementId
     * @return array
     * @throws Exception
     */
    public function searchEPCIIdByLogement($logementId)
    {
        $params = array($logementId);

        $query = "
            SELECT  o.EPCI_id AS EPCI_id
            FROM orientation o
            INNER JOIN up_ville uv ON uv.id = o.ville_id
            INNER JOIN logement l ON l.INSEE = uv.code_insee
            WHERE l.id = ?
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchFirstColumn();
    }
}

<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use App\Service\TitreService;
use App\Entity\Demande_;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\DateCP;


class DateCPRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DateCP::class);
    }

    /**
     * @param $datePassageMontantAuditEnergie
     * @param $datePassageMontantAuditRegion
     * @return array
     * @throws Exception
     */
    public function findDemande($datePassageMontantAuditEnergie, $datePassageMontantAuditRegion)
    {
        $query = "
            SELECT  dCP.id AS dateId,
                    COUNT(d.id) AS countDemande,
                    SUM(CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(dCP.date_CP < '" . $datePassageMontantAuditEnergie . "', '" . TitreService::MONTANT_HUIT_CENT_AVEC_VIRGULE . "', '" . TitreService::MONTANT_CINQ_CENT_AVEC_VIRGULE . "')
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN '" . TitreService::MONTANT_QUATRE_CENT_AVEC_VIRGULE . "'
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dtd.aide_region
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(dCP.date_CP < '" . $datePassageMontantAuditRegion . "', '" . TitreService::MONTANT_HUIT_CENT_AVEC_VIRGULE . "', '" . TitreService::MONTANT_SIX_CENT_AVEC_VIRGULE . "')
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN '" . TitreService::MONTANT_DEUX_CENT_AVEC_VIRGULE . "'
                    END) AS montant
            FROM date_CP dCP
                LEFT JOIN demande_ d ON dCP.id = d.dateCP_id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
            GROUP BY dCP.id
            ORDER BY dCP.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

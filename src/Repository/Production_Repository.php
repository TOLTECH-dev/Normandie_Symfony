<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Production_;


class Production_Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Production_::class);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function findAllCustom()
    {
        $query = "
            SELECT  COUNT(CASE WHEN d.type = " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN 1 ELSE NULL END) AS countEnergie,
                    COUNT(CASE WHEN d.type = " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN 1 ELSE NULL END) AS countNumerique,
                    COUNT(CASE WHEN d.type = " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN 1 ELSE NULL END) AS countEnergieRegion,
                    COUNT(CASE WHEN d.type = " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN 1 ELSE NULL END) AS countMiseAJourAuditE,
                    COUNT(CASE WHEN dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_1_VALUE . "' THEN 1 ELSE NULL END) AS countNiveau1,
                    COUNT(CASE WHEN dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_VALUE . "' THEN 1 ELSE NULL END) AS countNiveau2,
                    COUNT(CASE WHEN dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . "' THEN 1 ELSE NULL END) AS countNiveau2Renovateur,
                    COUNT(CASE WHEN dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN 1 ELSE NULL END) AS countNiveauBBCrenovateur,
                    COUNT(CASE WHEN dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . "' THEN 1 ELSE NULL END) AS countNiveauBBCbiosource,
                    COUNT(CASE WHEN dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE . "' THEN 1 ELSE NULL END) AS countNiveauSortiePassoire,
                    COUNT(CASE WHEN dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE . "' THEN 1 ELSE NULL END) AS countNiveauPremiereEtapeBBCRGE,
                    COUNT(CASE WHEN dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . "' THEN 1 ELSE NULL END) AS countNiveauPremiereEtapeBBCRenovateur,
                    COUNT(CASE WHEN dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . "' THEN 1 ELSE NULL END) AS countNiveauRenovationGobaleBBC,
                    p.id AS productionId,
                    p.date_lancement AS dateLancement,
                    p.date_production AS dateProduction,
                    p.date_expedition AS dateExpedition
            FROM production_ p
                INNER JOIN production__demande_ pd ON pd.production__id = p.id
                INNER JOIN demande_ d ON d.id = pd.demande__id
                LEFT JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
                LEFT JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
            GROUP BY p.id
            ORDER BY p.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

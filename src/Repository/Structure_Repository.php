<?php

namespace App\Repository;

use App\Entity\Structure_;
use App\Utils\DefaultServiceUtils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;


class Structure_Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Structure_::class);
    }

    /**
     * @param $enabled
     * @return QueryBuilder
     */
    public function findByStructureEnabled($enabled)
    {
        $query = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->join('a.structure_statut', 'structure_statut')
            ->addSelect('structure_statut');

        $query = $query
            ->where('structure_statut.enabled LIKE :enabled')
            ->setParameters(array(
                'enabled'       => $enabled
            ))
        ;

        return $query;
    }

    /**
     * @param $enabled
     * @return QueryBuilder
     */
    public function findByConseillerEnabled($enabled)
    {
        $query = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->join('a.structure_conseiller', 'structure_conseiller')
            ->addSelect('structure_conseiller')
            ->join('a.structure_statut', 'structure_statut')
            ->addSelect('structure_statut')
        ;

        $query = $query
            ->where('structure_conseiller.nom IS NOT NULL')
            ->andWhere('structure_conseiller.enabled LIKE :enabled')
            ->andWhere('structure_statut.enabled LIKE :enabled')
            ->setParameters(array(
                'enabled'       => $enabled
            ))
        ;

        return $query;
    }

    /**
     * @param $id
     * @return bool|array
     * @throws Exception
     */
    public function findSlugById($id)
    {
        if ('' != $id) {
            $query = "
                SELECT  a.id,
                        b.nom
                FROM structure_ a, structure_identification b
                WHERE a.structure_identification_id = b.id
                    AND a.id = " . $id . "
            ";

            $statement = $this->_em
                ->getConnection()
                ->prepare($query);
            $result = $statement->executeQuery();

            return $result->fetchAssociative();
        }

        return false;
    }

    /**
     * @param $conseillerId
     * @return array|false
     * @throws Exception
     */
    public function findByConseillerId($conseillerId)
    {
        $query = "
            SELECT  s.id AS id
            FROM structure_ s
                INNER JOIN structure__structure_conseiller ssc ON s.id = ssc.structure__id
                INNER JOIN structure_conseiller sc ON ssc.structure_conseiller_id = sc.id 
            WHERE sc.id = " . $conseillerId . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param array $advisedStructure
     * @return array
     * @throws Exception
     */
    public function findOther($advisedStructure = array())
    {
        $where = '';
        if (count($advisedStructure) > 0) {
            $where = " WHERE s.id NOT IN (" . implode(', ', $advisedStructure) . ") ";
        }

        $query = "
            SELECT  s.id AS s_id,
                    si.nom AS si_nom
            FROM structure_ AS s
                INNER JOIN structure_identification AS si ON s.structure_identification_id = si.id
                INNER JOIN structure_statut AS ss ON s.structure_statut_id = ss.id 
                    AND ss.enabled = '1'
                " . $where . "
            ORDER BY si.nom ASC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $INSEE
     * @return array|false
     * @throws Exception
     */
    public function findByOrientation($INSEE)
    {
        $query = "
            SELECT  GROUP_CONCAT(DISTINCT CONCAT_WS('" . DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS_ID_LABEL . "', sinf.id, siinf.nom) ORDER BY siinf.nom ASC SEPARATOR '" . DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS . "') AS structureInferieurIdNomConcat,
                    GROUP_CONCAT(DISTINCT CONCAT_WS('" . DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS_ID_LABEL . "', ssup.id, sisup.nom) ORDER BY sisup.nom ASC SEPARATOR '" . DefaultServiceUtils::GROUPCONCAT_SEPARATOR_ITEMS . "') AS structureSuperieurIdNomConcat
            FROM up_ville v
                INNER JOIN orientation o ON o.ville_id = v.id
                LEFT JOIN orientation_structure_inferieur osinf ON o.id = osinf.orientation_id
                LEFT JOIN structure_ sinf ON osinf.structure_id = sinf.id
                LEFT JOIN structure_statut AS ssinf ON sinf.structure_statut_id = ssinf.id AND ssinf.enabled = '1'
                LEFT JOIN structure_identification siinf ON sinf.structure_identification_id = siinf.id
                LEFT JOIN orientation_structure_superieur ossup ON o.id = ossup.orientation_id
                LEFT JOIN structure_ ssup ON ossup.structure_id = ssup.id
                LEFT JOIN structure_statut AS sssup ON ssup.structure_statut_id = sssup.id AND sssup.enabled = '1'
                LEFT JOIN structure_identification sisup ON ssup.structure_identification_id = sisup.id
            WHERE v.code_insee = " . $INSEE . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * Retourne le Revenu fiscal de référence du foyer, le nombre de personnes constituant le foyer de la fiche Bénéficiaire et le Code Insee du Logement
     *
     * @param $beneficiaireId
     * @param $logementId
     * @return array|false
     * @throws Exception
     */
    public function findCriteria($beneficiaireId, $logementId)
    {
        $query = "
                SELECT  b.nb_pers_foyer AS nombrePersonneFoyer,
                        b.revenu_fiscal_ref AS revenuFiscalRef,
                        l.INSEE AS insee
                FROM beneficiaire AS b
                INNER JOIN logement AS l ON l.beneficiaire_id = b.id
                    AND l.id = " . $logementId . "
                WHERE b.id = " . $beneficiaireId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $structureId
     * @return array
     * @throws Exception
     */
    public function search($structureId)
    {
        $query = "
            SELECT  DISTINCT sc.id, 
                    sc.nom, 
                    sc.prenom 
            FROM structure_ s
                LEFT JOIN structure__structure_conseiller s_sc ON s.id = s_sc.structure__id
                LEFT JOIN structure_conseiller sc ON sc.id = s_sc.structure_conseiller_id
            WHERE s.id = " . $structureId . "
                AND sc.enabled = '1'
            ORDER BY sc.nom ASC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

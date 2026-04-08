<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Partenaire_;


class Partenaire_Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partenaire_::class);
    }

    /**
     * @param $type
     * @return array
     * @throws Exception
     */
    public function findByType($type)
    {
        $querySelect = '';
        $queryJoin = '';
        $queryGroupBy = '';

        $prefixUser = '';
        if ('0 | auditeur' == $type) $prefixUser = 'A';
        elseif ('1 | renovateur' == $type) $prefixUser = 'R';

        $querySelect .= "
            , SUM(CASE WHEN r.score <> '0' THEN 1 ELSE 0 END) AS countRating
        ";
        $queryJoin .= "
            LEFT JOIN user u ON u.username = CONCAT('". $prefixUser ."', LPAD(p.id,5,'0'))
            LEFT JOIN rating r ON r.to_user_id = u.id
        ";
        $queryGroupBy .= "
            GROUP BY p.id
        ";

        $query = "
            SELECT  p.id AS partenaireId,
                    pi.raison_sociale AS partenaireRaisonSociale,
                    pi.thematique AS partenaireThematique,
                    pa.code_postal AS partenaireCodePostal,
                    pa.ville AS partenaireVille,
                    ps.enabled AS partenaireEnabled,
                    DATE_FORMAT(ps.date_inactif, '%d/%m/%Y') AS partenaireDateInactif,
                    (
                        SELECT 
                            CONCAT_WS('__|__', IF(pco.civilite != '', pco.civilite, ''), IF(pco.prenom != '', pco.prenom, ''), IF(pco.nom != '', pco.nom, ''), IF(pco.telephone != '', pco.telephone, ''), IF(pco.email != '', pco.email, ''))
                        FROM 
                            partenaire_contact pco
                        INNER JOIN partenaire__partenaire_contact ppc ON pco.id = ppc.partenaire_contact_id
                        WHERE ppc.partenaire__id = p.id
                        ORDER BY pco.id ASC
                        LIMIT 1
                    ) AS strContact               
                    " . $querySelect . "
            FROM partenaire_ p
                INNER JOIN partenaire_identification pi ON pi.id = p.partenaire_identification_id
                INNER JOIN partenaire_adresse pa ON pa.id = p.partenaire_adresse_id
                INNER JOIN partenaire_statut ps ON ps.id = p.partenaire_statut_id
                    " . $queryJoin . "
            WHERE pi.thematique = '" . $type . "'
            " . $queryGroupBy . "
            ORDER BY pi.raison_sociale DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $thematique
     * @param $enabled
     * @return QueryBuilder
     */
    public function findByThematiqueEnabled($thematique, $enabled)
    {
        $query = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->join('a.partenaire_identification', 'partenaire_identification')
            ->addSelect('partenaire_identification')
            ->join('a.partenaire_statut', 'partenaire_statut')
            ->addSelect('partenaire_statut');

        $query = $query
            ->where('partenaire_identification.thematique LIKE :thematique')
            ->andWhere('partenaire_statut.enabled LIKE :enabled')
            ->setParameters(array(
                'thematique'    => $thematique,
                'enabled'       => $enabled
            ))
            ->orderBy('partenaire_identification.raisonSociale', 'ASC')
        ;

        return $query;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function findAllCustom()
    {
        $query = "
            SELECT LOWER(pi.raison_sociale) AS raisonSociale
            FROM partenaire_ p
                INNER JOIN partenaire_identification pi ON p.partenaire_identification_id = pi.id
            ORDER BY pi.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $key
     * @return array
     * @throws Exception
     */
    public function search($key)
    {
        if ('0' == $key) $type = '0 | auditeur';
        elseif ('1' == $key) $type = '1 | renovateur';

        $query = "
            SELECT  p.id AS id,
                    pi.raison_sociale AS raison_sociale
            FROM partenaire_ p
            LEFT JOIN partenaire_identification pi ON p.partenaire_identification_id = pi.id
            LEFT JOIN partenaire_statut ps ON p.partenaire_statut_id = ps.id
            WHERE p.type = '" . $type . "'
                AND ps.enabled = '1'
            ORDER BY pi.raison_sociale ASC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

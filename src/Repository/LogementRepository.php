<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Logement;


class LogementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Logement::class);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function searchAnneeConstruction()
    {
        $query = "
            SELECT  id AS id,
                    annee AS annee,
                    slug AS slug
            FROM up_anneeConstructionLogement
            ORDER BY id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $data
     * @return array
     * @throws Exception
     */
    public function searchByCodePostal($data)
    {
        $query = "
            SELECT  up_ville.id AS id,
                    up_ville.nom AS nom,
                    up_ville.code_postal AS code_postal, 
                    up_ville.code_insee AS code_insee,  
                    up_ville.cedex AS cedex,
                    up_departement.departement_id AS dep_id,
                    up_departement.departement_code AS dep_code,
                    up_departement.departement_nom AS dep_name
            FROM up_ville, up_departement
            WHERE up_ville.code_postal LIKE '" . $data . "%'
                AND up_departement.departement_code = SUBSTRING(" . $data . ",1,2)
            ORDER BY up_ville.nom, up_ville.code_postal ASC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $villeId
     * @return array|false
     * @throws Exception
     */
    public function searchByVilleId($villeId)
    {
        $query = "
            SELECT  id AS id,
                    nom AS nom,
                    code_postal AS code_postal
            FROM up_ville
            WHERE up_ville.id = " . $villeId . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $data
     * @return array
     * @throws Exception
     */
    public function searchByINSEE($data)
    {
        $query = "
            SELECT * 
            FROM up_adresse
            WHERE code_insee = " . $data . "
            ORDER BY libelle_voie ASC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $duplicateKey
     * @param null $logementId
     * @return array
     * @throws Exception
     */
    public function searchDuplicate($duplicateKey, $logementId = null)
    {
        $statutRefuse = '2 | refuse';
        $condWhere = $logementId ? " l.id != ".$logementId." AND " : '';
        $query = "
            SELECT  l.id AS logementId,
                    b.id AS beneficiaireId
            FROM logement l
                INNER JOIN beneficiaire b ON l.beneficiaire_id = b.id
            WHERE (
                " . $condWhere . "
                l.duplicate_key = '".$duplicateKey."' AND
                l.statut != '" . $statutRefuse . "'
            )
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

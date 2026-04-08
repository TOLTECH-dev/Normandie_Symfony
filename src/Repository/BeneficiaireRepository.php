<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Beneficiaire;


class BeneficiaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Beneficiaire::class);
    }

    /**
     * @param $beneficiaireId
     * @return array|false
     * @throws Exception
     */
    public function findAllCustomById($beneficiaireId)
    {
        $query = "
            SELECT  b.id AS beneficiaireId,
                    b.type AS beneficiaireType,
                    b.nom_SCI AS beneficiaireNomSCI,
                    b.civilite AS beneficiaireCivilite,
                    b.nom AS beneficiaireNom,
                    b.prenom AS beneficiairePrenom,
                    b.code_postal AS beneficiaireCodePostal,
                    b.ville AS beneficiaireVille,
                    b.numero_rue AS beneficiaireNumeroRue,
                    b.complement_numero_rue AS beneficiaireComplementNumeroRue,
                    b.nom_rue AS beneficiaireNomRue,
                    b.complement_1 AS beneficiaireComplement1,
                    b.complement_2 AS beneficiaireComplement2,
                    b.email AS beneficiaireEmail,
                    b.tel_1 AS beneficiaireTel1,
                    b.tel_2 AS beneficiaireTel2,
                    b.situation_famille AS beneficiaireSituationFamille,
                    b.nom_conjoint AS beneficiaireNomConjoint,
                    b.prenom_conjoint AS beneficiairePrenomConjoint,
                    b.nb_pers_foyer AS beneficiaireNbPersonneFoyer,
                    b.revenu_fiscal_ref AS beneficiaireRevenuFiscal,
                    b.known_by_media AS beneficiaireKnownByMedia,
                    b.known_by_other AS beneficiaireKnownByOther,
                    bq.id AS banqueId,
                    bq.nom AS banqueNom,
                    p.id AS partenaireIdentificationRenovateurId,
                    pi.raison_sociale AS partenaireIdentificationRenovateur,
                    p_.id AS partenaireIdentificationAuditeurId,
                    pi_.raison_sociale AS partenaireIdentificationAuditeur,
                    s.id AS structureId,
                    si.nom AS structureIdentificationNom,
                    sr.id AS structureRattachementId,
                    sir.nom AS structureIdentificationRattachementNom,
                    scr.id AS structureConseillerRattachementId,
                    scr.nom AS structureConseillerRattachementNom,
                    scr.prenom AS structureConseillerRattachementPrenom
            FROM beneficiaire b
                LEFT JOIN structure_ s ON s.id = b.structure_id
                LEFT JOIN structure_identification si ON si.id = s.structure_identification_id
                LEFT JOIN structure_ sr ON sr.id = b.structure_rattachement_id
                LEFT JOIN structure_identification sir ON sir.id = sr.structure_identification_id
                LEFT JOIN structure_conseiller scr ON scr.id = b.conseiller_rattachement_id
                LEFT JOIN partenaire_ p ON p.id = b.renovateur_id
                LEFT JOIN partenaire_identification pi ON pi.id = p.partenaire_identification_id
                LEFT JOIN partenaire_ p_ ON p_.id = b.auditeur_id
                LEFT JOIN partenaire_identification pi_ ON pi_.id = p_.partenaire_identification_id
                LEFT JOIN banque_ bq ON bq.id = b.financeur_id
            WHERE b.id = ". $beneficiaireId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param string $andWhere
     * @return int
     * @throws Exception
     */
    public function countAll($andWhere='')
    {
        $query = "
            SELECT  b.id
            FROM beneficiaire b
                LEFT JOIN structure_ sr ON sr.id = b.structure_rattachement_id
                LEFT JOIN structure_identification sir ON sir.id = sr.structure_identification_id
                LEFT JOIN structure_conseiller scr ON scr.id = b.conseiller_rattachement_id
                LEFT JOIN logement l ON l.beneficiaire_id = b.id 
            WHERE 1 " . $andWhere . "
            GROUP BY b.id
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->rowCount();
    }

    /**
     * @param $orderBy
     * @param $orderType
     * @param $start
     * @param $length
     * @param string $andWhere
     * @return array
     * @throws Exception
     */
    public function findAllAjax($orderBy, $orderType, $start, $length, $andWhere='')
    {
        $query = "
            SELECT  b.id AS beneficiaireId,
                    b.nom_SCI AS beneficiaireNomSCI,
                    b.civilite AS beneficiaireCivilite,
                    CASE WHEN ('1 | sci' != b.type)
                        THEN CONCAT(UCASE(b.nom), ' ', CONCAT(UCASE(LEFT(b.prenom, 1)), LCASE(SUBSTRING(b.prenom, 2))))
                        ELSE CONCAT(UCASE(LEFT(b.nom_SCI, 1)), LCASE(SUBSTRING(b.nom_SCI, 2)))
                    END AS beneficiaireNomPrenom,
                    CASE WHEN ('1 | sci' != b.type)
                        THEN 'Particulier'
                        ELSE 'SCI'
                    END AS beneficiaireType,
                    CONCAT(b.code_postal, ' ', CONCAT(UCASE(LEFT(b.ville, 1)), LCASE(SUBSTRING(b.ville, 2)))) AS beneficiaireCodePostalVille,
                    b.email AS beneficiaireEmail,
                    b.nb_pers_foyer AS beneficiaireNbPersFoyer,
                    b.revenu_fiscal_ref AS beneficiaireRevenuFiscalRef,
                    b.INSEE AS beneficiaireINSEE,
                    sr.id AS structureRattachementId,
                    sir.nom AS structureIdentificationRattachementNom,
                    scr.id AS structureConseillerRattachementId,
                    scr.nom AS structureConseillerRattachementNom,
                    scr.prenom AS structureConseillerRattachementPrenom,
                    COUNT(l.id) AS nombreLogement
            FROM beneficiaire b
                LEFT JOIN structure_ sr ON sr.id = b.structure_rattachement_id
                LEFT JOIN structure_identification sir ON sir.id = sr.structure_identification_id
                LEFT JOIN structure_conseiller scr ON scr.id = b.conseiller_rattachement_id
                LEFT JOIN logement l ON l.beneficiaire_id = b.id 
                WHERE 1 " . $andWhere ."
            GROUP BY b.id
            ORDER BY " . $orderBy . " " . $orderType . "
            LIMIT " . $start . "," . $length . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $duplicateKey
     * @param null $beneficiaireId
     * @return array
     * @throws Exception
     */
    public function searchDuplicate($duplicateKey, $beneficiaireId = null)
    {
        $condWhere = $beneficiaireId ? " b.id != ".$beneficiaireId." AND " : '';
        $query = "
            SELECT b.id AS beneficiaireId
            FROM beneficiaire b
            WHERE (
                " . $condWhere . "
                b.duplicate_key = '".$duplicateKey."'
            )
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $codePostal
     * @return array
     * @throws Exception
     */
    public function searchByCodePostal($codePostal)
    {
        $query = "
            SELECT  uv.code_insee AS codeINSEE,
                    uv.code_postal AS codePostal,
                    uv.nom AS nomVille
            FROM up_ville uv
            WHERE uv.code_postal = '" . $codePostal . "' 
        ";

        $stmt = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $codeINSEE
     * @param $nomRue
     * @return array|false
     * @throws Exception
     */
    public function searchByCodeINSEENomRue($codeINSEE, $nomRue)
    {
        $query = "
            SELECT ua.libelle_voie AS nomRue
            FROM up_adresse ua
            WHERE ua.libelle_voie = '" . $nomRue . "'
                AND ua.code_insee = '" . $codeINSEE . "'
        ";

        $stmt = $this->_em
            ->getConnection()
            ->prepare($query)
        ;
        $result = $stmt->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param string $andWhere
     * @return int
     * @throws Exception
     */
    public function countAllForAssistanceBeneficiaire($andWhere='')
    {
        $query = "
            SELECT  b.id
            FROM beneficiaire b
            WHERE 1 " . $andWhere . "
        ";
        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->rowCount();
    }

    /**
     * @param $orderBy
     * @param $orderType
     * @param $start
     * @param $length
     * @param string $andWhere
     * @return array
     * @throws Exception
     */
    public function findAllAjaxForAssistanceBeneficiaire($orderBy, $orderType, $start, $length, $andWhere='')
    {
        $query = "
            SELECT  b.id AS beneficiaireId,
                    CASE WHEN ('1 | sci' != b.type)
                        THEN CONCAT(UCASE(b.nom), ' ', CONCAT(UCASE(LEFT(b.prenom, 1)), LCASE(SUBSTRING(b.prenom, 2))))
                        ELSE CONCAT(UCASE(LEFT(b.nom_SCI, 1)), LCASE(SUBSTRING(b.nom_SCI, 2)))
                    END AS beneficiaireNomPrenom,
                    b.email AS beneficiaireEmail,
                    b.auteur_creation AS beneficiaireAuteurCreation,
                    '' AS action
            FROM beneficiaire b
                WHERE 1 " . $andWhere ."
            GROUP BY b.id
            ORDER BY " . $orderBy . " " . $orderType . "
            LIMIT " . $start . "," . $length . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

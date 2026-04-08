<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use App\Trait\GenerateQueryBuilderTrait;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Titre;


class TitreRepository extends ServiceEntityRepository
{

    use GenerateQueryBuilderTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Titre::class);
    }


    /**
     * @param array $arrayWhere
     * @return int|mixed|string|null
     * @throws NonUniqueResultException
     */
    public function countForList($arrayWhere = [])
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select('COUNT(t.id) AS countId')
            ->innerJoin(Demande_::class, 'd', Join::WITH, 'd.id = t.demandeId')
            ->innerJoin(Beneficiaire::class, 'b', Join::WITH, 'b.id = d.beneficiaire_id')
            ->leftJoin('d.demande_travaux', 'dt')
            ->leftJoin(Demande_travaux_devis::class, 'dtd', Join::WITH, 'dtd.id = dt.travauxDevis_id');

        if (!empty($arrayWhere)) {
            $this->generateWhereQueryBuilder($qb, $arrayWhere);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param $productionTravauxNiveauBBC1
     * @param $productionTravauxNiveauBBC2
     * @param $start
     * @param $length
     * @param $orderBy
     * @param $orderType
     * @param $arrayWhere
     * @return array|float|int|string
     */
    public function findForListAjax(
        $productionTravauxNiveauBBC1,
        $productionTravauxNiveauBBC2,
        $start,
        $length,
        $orderBy = null,
        $orderType = null,
        $arrayWhere = []
    ) {
        $qb = $this->createQueryBuilder('t');
        $qb->select('t.id AS titreId');

        $querySelectDemandeTypeCase =
            "(CASE " .
                "WHEN d.type = " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " " .
                "WHEN d.type = " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " " .
                "WHEN d.type = " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN  " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " " .
                "WHEN d.type = " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " " .
                "WHEN d.type = " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN " .
                    "(CASE " .
                        "WHEN SUBSTRING(dtd.niveau, 1, 1) LIKE '0' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE . " " .
                        "WHEN SUBSTRING(dtd.niveau, 1, 1) LIKE '1' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE . " " .
                        "WHEN SUBSTRING(dtd.niveau, 1, 1) LIKE '2' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE . " " .
                        "WHEN SUBSTRING(dtd.niveau, 1, 1) LIKE '3' THEN " .
                            "(CASE " .
                                "WHEN t.numeroOperation = " . $productionTravauxNiveauBBC1 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE . " " .
                                "WHEN t.numeroOperation = " . $productionTravauxNiveauBBC2 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE . " " .
                                "ELSE '' " .
                             "END) " .
                        "WHEN SUBSTRING(dtd.niveau, 1, 1) LIKE '4' THEN " .
                             "(CASE " .
                                 "WHEN t.numeroOperation = " . $productionTravauxNiveauBBC1 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE . " " .
                                 "WHEN t.numeroOperation = " . $productionTravauxNiveauBBC2 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE . " " .
                                 "ELSE '' " .
                             "END) " .
                        "WHEN SUBSTRING(dtd.niveau, 1, 1) LIKE '6' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE . " " .
                        "WHEN SUBSTRING(dtd.niveau, 1, 1) LIKE '7' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE . " " .
                        "WHEN SUBSTRING(dtd.niveau, 1, 1) LIKE '8' THEN " .
                             "(CASE " .
                                 "WHEN t.numeroOperation = " . $productionTravauxNiveauBBC1 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE . " " .
                                 "WHEN t.numeroOperation = " . $productionTravauxNiveauBBC2 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE . " " .
                                 "ELSE '' " .
                             "END) " .
                        "WHEN SUBSTRING(dtd.niveau, 1, 1) LIKE '9' THEN " .
                             "(CASE " .
                                 "WHEN t.numeroOperation = " . $productionTravauxNiveauBBC1 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE . " " .
                                 "WHEN t.numeroOperation = " . $productionTravauxNiveauBBC2 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE . " " .
                                 "ELSE '' " .
                             "END) " .
                        "ELSE " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_A_DEFINIR_CODE . " " .
                    "END) " .
                "ELSE '' " .
            "END) AS demandeType" ;

        $qb->addSelect($querySelectDemandeTypeCase)
            ->addSelect("b.nom beneficiaireNom")
            ->addSelect("b.prenom beneficiairePrenom")
            ->addSelect("CONCAT(b.nom, ' ', b.prenom) AS beneficiaire")
            ->addSelect('t.demandeId AS demandeId')
            ->addSelect('t.productionId AS productionId')
            ->addSelect('t.numeroCheque AS numeroCheque')
            ->addSelect('t.valeurTitre AS valeurTitre')
            ->addSelect('DATE_FORMAT(t.dateEmission, \'%d/%m/%Y\') AS dateEmission')
            ->addSelect('DATE_FORMAT(t.dateValidite, \'%d/%m/%Y\') AS dateValidite')
            ->addSelect("'-' AS action")
            ->innerJoin(Demande_::class, 'd', Join::WITH, 'd.id = t.demandeId')
            ->innerJoin(Beneficiaire::class, 'b', Join::WITH, 'b.id = d.beneficiaire_id')
            ->leftJoin('d.demande_travaux', 'dt')
            ->leftJoin(Demande_travaux_devis::class, 'dtd', Join::WITH, 'dtd.id = dt.travauxDevis_id');

        if (!empty($arrayWhere)) {
            $this->generateWhereQueryBuilder($qb, $arrayWhere);
        }

        if (!empty($orderBy) && !empty($orderType)) {
            $qb->orderBy($orderBy, $orderType);
        }

        $qb->setFirstResult($start)
            ->setMaxResults($length);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param $titreId
     * @return array|false
     * @throws Exception
     */
    public function findByDemandeIdAndNumeroOperation($titreId)
    {
        $query = "SELECT    t.id AS titreId,
                            t.demande_id AS demandeId,
                            t.numero_cheque AS titreNumeroCheque,
                            t.numero_operation AS titreNumeroOperation,
                            DATE_FORMAT(t.date_emission, '%Y-%m-%d') AS titreDateEmission,
                            dtd.total_devis AS totalDevis,
                            dtd.niveau AS demandeTravauxNiveau
                  FROM  titre t
                    INNER JOIN demande_ d ON t.demande_id = d.id
                    LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                    LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                  WHERE t.id = " .$titreId . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $titreId
     * @param $productionTravauxNiveauBBC1
     * @param $productionTravauxNiveauBBC2
     * @return array|false
     * @throws Exception
     */
    public function findDataAttestationNonReception($titreId, $productionTravauxNiveauBBC1, $productionTravauxNiveauBBC2)
    {
        $query = "
            SELECT  t.demande_id AS demandeId,
                    t.numero_operation AS numeroOperation,
                    t.production_id AS productionId,
                    t.numero_chequier AS numeroChequier,
                    t.numero_cheque AS numeroCheque,
                    t.valeur_titre AS valeurTitre,
                    t.date_emission AS dateEmission,
                    t.date_validite AS dateValidite,
                    b.civilite AS beneficiaireCivilite,
                    b.nom AS beneficiaireNom,
                    b.prenom AS beneficiairePrenom,
                    b.code_postal AS beneficiaireCodePostal,
                    b.ville AS beneficiaireVille,
                    b.numero_rue AS beneficiaireNumeroRue,
                    b.complement_numero_rue AS beneficiaireComplementNumeroRue,
                    b.nom_rue AS beneficiaireNomRue,
                    d.type AS typeAide,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . "
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . "
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . "
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . "
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE dtd.niveau
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_1_VALUE . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE . "
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_VALUE . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE . "
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE . "
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE . "
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE . "
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '".$productionTravauxNiveauBBC1."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE . "
                                        WHEN '".$productionTravauxNiveauBBC2."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE . "
                                        ELSE " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_AUTRE_CODE . "
                                   END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '".$productionTravauxNiveauBBC1."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE . "
                                        WHEN '".$productionTravauxNiveauBBC2."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE . "
                                        ELSE " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_AUTRE_CODE . "
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '".$productionTravauxNiveauBBC1."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE . "
                                        WHEN '".$productionTravauxNiveauBBC2."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE . "
                                        ELSE " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_AUTRE_CODE . "
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '".$productionTravauxNiveauBBC1."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE . "
                                        WHEN '".$productionTravauxNiveauBBC2."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE . "
                                        ELSE " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_AUTRE_CODE . "
                                    END                                                           
                                ELSE NULL
                            END
                        ELSE NULL
                    END AS labelDemandeType,
                    pi_dae.raison_sociale AS auditeurNomAuditE,
                    pi_dan.raison_sociale AS auditeurNomAuditN,
                    dt.audit as isChequeAudit
            FROM titre t
                INNER JOIN demande_ d ON t.demande_id = d.id
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
            WHERE t.id = ".$titreId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }
}

<?php

namespace App\Repository;

use App\Entity\Structure_;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use App\Entity\DateRMH;
use App\Entity\Remboursement_;
use App\Entity\Remboursement_statut;
use App\Entity\Titre;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_travaux;
use App\Entity\Demande_travaux_devis;
use Doctrine\Persistence\ManagerRegistry;


class Remboursement_Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Remboursement_::class);
    }

    /**
     * @param $options
     * @param string $where
     * @return int
     * @throws Exception
     */
    public function countForList($options, $where = '')
    {
        $queryJoin = "";
        $queryWhere = "";
        $queryCase = "";

        $roles = $options['roles'];
        $username = $options['username'];
        $productionTravauxNiveauBBC1 = $options['production_travauxNiveau_BBC1'];
        $productionTravauxNiveauBBC2 = $options['production_travauxNiveau_BBC2'];
        $dateUsNouvelInstructeur = $options['app_date_us_nouvel_instructeur'];

        $conditionWhereBBC2 = "
            t.numero_operation != " .$productionTravauxNiveauBBC2. " 
            OR (
                t.numero_operation = " .$productionTravauxNiveauBBC2. " 
                AND r2.statut_id = " .Remboursement_statut::STATUS_22. "
            )
        ";

        if ($username) {
            $adminId = (int)substr($username, 1);

            if (is_int($adminId)) {
                if (in_array('ROLE_CONSEILLER', $roles)) {
                    $repo_structure = $this->_em->getRepository(Structure_::class);
                    $structure_id = $repo_structure->findByConseillerId($adminId);
                    $user_id_current = $structure_id['id'];

                    // La structure qui doit voir ses dossiers doit être la structure lié à la fiche du bénéficiaire
                    $queryWhere = "
                        WHERE (
                            b.structure_rattachement_id =" . (int)$user_id_current . "
                        ) AND d.type IN(" . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . "," . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . ")
                    ";
                } elseif (in_array('ROLE_AUDITEUR', $roles)) {
                    $queryWhere = "
                        WHERE (
                            p_dae.id = '" .$adminId."'
                            OR p_dan.id = '" .$adminId."'
                            OR p_dtd_aud.id = '" .$adminId."'
                            AND dt.audit = 1
                        ) AND (d.type IN(" . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . ",
                                " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . ",
                                " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . ",
                                " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . "
                        ))";

                } elseif (in_array('ROLE_RENOVATEUR', $roles)) {
                    $queryWhere = "
                        WHERE (
                            dtd.renovateur_id = '" .$adminId."'
                            AND (
                                 dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE. "'
                                 OR (dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE. "' AND t.numero_operation = '" . $productionTravauxNiveauBBC2."')
                                 OR (dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE. "' AND t.numero_operation = '" . $productionTravauxNiveauBBC2."')
                                 OR (dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE. "' AND t.numero_operation = '" . $productionTravauxNiveauBBC2."')
                                 OR (dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE. "' AND t.numero_operation = '" . $productionTravauxNiveauBBC2."')
                            ) AND (d.type = " . Demande_::DEMANDE_TRAVAUX_TYPE . ")
                        )
                    ";
                } else {
                    $queryWhere = "
                        WHERE 1=1 
                    ";
                }

                $queryWhere .= " AND ( ".$conditionWhereBBC2.")";
            } else {
                $queryWhere = "
                    WHERE ( ".$conditionWhereBBC2.")
                ";
            }

            if (!empty($dateUsNouvelInstructeur)) {
                if (in_array('ROLE_INSTRUCTEUR', $roles)) {
                    $queryWhere .= " AND (t.date_emission >= '" . $dateUsNouvelInstructeur . "') ";
                } else if (in_array('ROLE_INSTRUCTEUR_UP', $roles)) {
                    $queryWhere .= " AND (t.date_emission < '" . $dateUsNouvelInstructeur . "') ";
                }
            }
        }

        $queryWhere .= " AND (r.statut_id IS NULL OR (r.statut_id NOT IN (" . Remboursement_statut::STATUS_22 . ") AND r.rgpd != 1))";
        $queryWhere .= " AND (
            r.statut_id IS NULl
            OR (r.statut_id IN (" . Remboursement_statut::STATUS_20 . ") AND (d.type NOT IN (" . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . ", " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . ", " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . ", " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . ")))
            OR (r.statut_id NOT IN (" . Remboursement_statut::STATUS_20 . "))
        )";

        $queryJoin = "
            LEFT JOIN fiche_technique ft_rt ON rt.fiche_technique_id = ft_rt.id
            LEFT JOIN remboursement_audit_energie_validation rae_v ON rae.validation_id = rae_v.id
            LEFT JOIN remboursement_audit_energie_instruction rae_i ON rae.instruction_id = rae_i.id
            LEFT JOIN remboursement_audit_numerique_instruction ran_i ON ran.instruction_id = ran_i.id
            LEFT JOIN remboursement_travaux_instruction rt_i ON rt.instruction_id = rt_i.id
        ";

        $query = " 
            SELECT  DISTINCT d.id AS demandeId,
                    r.id AS remboursementId,
                    t.id AS titreId
            FROM demande_ d 
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id 
                INNER JOIN logement l ON l.id = d.logement_id 
                INNER JOIN demande_statut ds ON ds.id = d.statut_id 
                INNER JOIN titre t ON t.demande_id = d.id 
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id 
                LEFT JOIN remboursement_ r2 ON r2.demande_id = d.id AND r2.titre_id = t2.id 
                LEFT JOIN remboursement_ r ON r.demande_id = d.id AND r.titre_id = t.id 
                LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id 
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id 
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id 
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id 
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id 
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id 
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id 
                LEFT JOIN structure_identification si_dae ON s_dae.structure_identification_id = si_dae.id 
                LEFT JOIN structure_conseiller sc_dae ON dae.conseiller_id = sc_dae.id 
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id 
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN partenaire_option_auditeur poa_dae ON poa_dae.id = p_dae.partenaire_option_auditeur_id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id 
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id 
                LEFT JOIN structure_identification si_dan ON s_dan.structure_identification_id = si_dan.id 
                LEFT JOIN structure_conseiller sc_dan ON dan.conseiller_id = sc_dan.id 
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id 
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN partenaire_option_auditeur poa_dan ON poa_dan.id = p_dan.partenaire_option_auditeur_id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id 
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id 
                LEFT JOIN structure_identification si_dt ON s_dt.structure_identification_id = si_dt.id 
                LEFT JOIN structure_conseiller sc_dt ON dt.conseiller_id = sc_dt.id 
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id 
                LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id 
                LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id
                LEFT JOIN partenaire_option_auditeur poa_dtd ON poa_dtd.id = p_dtd.partenaire_option_auditeur_id 
                LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id 
                LEFT JOIN partenaire_statut ps_dtd_aud ON p_dtd_aud.partenaire_statut_id = ps_dtd_aud.id 
                    AND ps_dtd_aud.enabled = 1
                LEFT JOIN partenaire_identification pi_dtd_aud ON p_dtd_aud.partenaire_identification_id = pi_dtd_aud.id
                LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id
                LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
                LEFT JOIN remboursement_travaux_instruction__conformite rti_c ON rti.id = rti_c.remboursement_travaux_instruction_id
                LEFT JOIN remboursement_travaux_instruction_conformite rtic ON rti_c.remboursement_travaux_instruction_conformite_id = rtic.id
                " .
            $queryJoin .
            $queryWhere .
            $where . "
            GROUP BY d.id, r.id, t.id
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->rowCount();
    }

    /**
     * @param $options
     * @param null $orderBy
     * @param null $orderType
     * @param null $start
     * @param null $length
     * @param string $where
     * @return array
     * @throws Exception
     */
    public function findForListAjax(
        $options,
        $orderBy = null,
        $orderType = null,
        $start = null,
        $length = null,
        $where = ''
    ) {

        $queryJoin = "";
        $queryWhere = "";
        $queryCase = "";

        $roles = $options['roles'];
        $username = $options['username'];
        $productionTravauxNiveauBBC1 = $options['production_travauxNiveau_BBC1'];
        $productionTravauxNiveauBBC2 = $options['production_travauxNiveau_BBC2'];
        $dateUsNouvelInstructeur = $options['app_date_us_nouvel_instructeur'];


        $conditionWhereBBC2 = "
            t.numero_operation != " .$productionTravauxNiveauBBC2. " 
            OR (
                t.numero_operation = " .$productionTravauxNiveauBBC2. " 
                AND r2.statut_id = " .Remboursement_statut::STATUS_22. "
            )
        ";

        if ($username) {
            $adminId = substr($username, 1);

            if (is_numeric($adminId)) {
                $adminId = (int)$adminId;

                if (in_array('ROLE_CONSEILLER', $roles)) {
                    $repo_structure = $this->_em->getRepository(Structure_::class);
                    $structure_id = $repo_structure->findByConseillerId($adminId);
                    $user_id_current = $structure_id['id'];

                    // La structure qui doit voir ses dossiers doit être la structure lié à la fiche du bénéficiaire
                    $queryWhere = "
                        WHERE (
                            b.structure_rattachement_id =" . (int)$user_id_current . "
                        ) AND d.type IN(" . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . "," . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . ")
                    ";
                } elseif (in_array('ROLE_AUDITEUR', $roles)) {
                    $queryWhere = "
                        WHERE (
                            p_dae.id = '" .$adminId."'
                            OR p_dan.id = '" .$adminId."'
                            OR p_dtd_aud.id = '" .$adminId."'
                            AND dt.audit = 1
                        ) AND (d.type IN(" . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . ",
                                " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . ",
                                " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . ",
                                " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . "
                        ))"
                    ;
                } elseif (in_array('ROLE_RENOVATEUR', $roles)) {
                    $queryWhere = "
                        WHERE (
                            dtd.renovateur_id = '" .$adminId."'
                            AND (
                                 dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE. "'
                                 OR (dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE. "' AND t.numero_operation = '" . $productionTravauxNiveauBBC2."')
                                 OR (dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE. "' AND t.numero_operation = '" . $productionTravauxNiveauBBC2."')
                                 OR (dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE. "' AND t.numero_operation = '" . $productionTravauxNiveauBBC2."')
                                 OR (dtd.niveau = '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE. "' AND t.numero_operation = '" . $productionTravauxNiveauBBC2."')
                            ) AND (d.type = " . Demande_::DEMANDE_TRAVAUX_TYPE . ")
                        )
                    ";
                } else {
                    $queryWhere = "
                        WHERE 1=1 
                    ";
                }

                $queryWhere .= " AND ( ".$conditionWhereBBC2.")";
            } else {
                $queryWhere = "
                    WHERE ( ".$conditionWhereBBC2.")
                ";
            }

            if (!empty($dateUsNouvelInstructeur)) {
                if (in_array('ROLE_INSTRUCTEUR', $roles)) {
                    $queryWhere .= " AND (t.date_emission >= '" . $dateUsNouvelInstructeur . "') ";
                } else if (in_array('ROLE_INSTRUCTEUR_UP', $roles)) {
                    $queryWhere .= " AND (t.date_emission < '" . $dateUsNouvelInstructeur . "') ";
                }
            }
        }

        $queryWhere .= " AND (r.statut_id IS NULL OR (r.statut_id NOT IN (" . Remboursement_statut::STATUS_22 . ") AND r.rgpd != 1))";
        $queryWhere .= " AND (
            r.statut_id IS NULl
            OR (r.statut_id IN (" . Remboursement_statut::STATUS_20 . ") AND (d.type NOT IN (" . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . ", " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . ", " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . ", " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . ")))
            OR (r.statut_id NOT IN (" . Remboursement_statut::STATUS_20 . "))
        )";

        $queryCase = ",
            d.type AS demandeType,
            CASE d.type
                WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN rae.instruction_id
                WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN rae.instruction_id
                WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN ran.instruction_id
                WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN ran.instruction_id
                WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rt.instruction_id
                ELSE NULL
            END AS instructionId,
            CASE d.type
                WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN rae.depot_id
                WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN rae.depot_id
                WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN ran.depot_id
                WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN ran.depot_id
                ELSE NULL
            END AS depotId,
            CASE d.type
                WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rt.fiche_technique_id
                ELSE NULL
            END AS ficheTechniqueId,
            CASE d.type
                WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN ft_rt.statut_ficheTechnique
                ELSE NULL
            END AS statutFicheTechnique, ";

        $queryCase .=
            "(CASE d.type " .
                "WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " " .
                "WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " " .
                "WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN  " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " " .
                "WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " " .
                "WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN " .
                    "(CASE SUBSTRING(dtd.niveau, 1, 1) " .
                        "WHEN '0' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE . " " .
                        "WHEN '1' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE . " " .
                        "WHEN '2' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE . " " .
                        "WHEN '3' THEN " .
                            "(CASE t.numero_operation " .
                                "WHEN " . $productionTravauxNiveauBBC1 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE . " " .
                                "WHEN " . $productionTravauxNiveauBBC2 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE . " " .
                                "ELSE '' " .
                             "END) " .
                        "WHEN '4' THEN " .
                             "(CASE t.numero_operation " .
                                 "WHEN " . $productionTravauxNiveauBBC1 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE . " " .
                                 "WHEN " . $productionTravauxNiveauBBC2 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE . " " .
                                 "ELSE '' " .
                             "END) " .
                        "WHEN '6' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE . " " .
                        "WHEN '7' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE . " " .
                        "WHEN '8' THEN " .
                             "(CASE t.numero_operation " .
                                 "WHEN " . $productionTravauxNiveauBBC1 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE . " " .
                                 "WHEN " . $productionTravauxNiveauBBC2 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE . " " .
                                 "ELSE '' " .
                             "END) " .
                        "WHEN '9' THEN " .
                             "(CASE t.numero_operation " .
                                 "WHEN " . $productionTravauxNiveauBBC1 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE . " " .
                                 "WHEN " . $productionTravauxNiveauBBC2 . " THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE . " " .
                                 "ELSE '' " .
                             "END) " .
                        "ELSE " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_A_DEFINIR_CODE . " " .
                    "END) " .
                "ELSE '' " .
            "END) AS demandeTypeDetaille, " ;

        $queryCase .= "
            CASE dtd.niveau
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . "' THEN 1
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC2 . "' THEN 1
                        ELSE 0
                    END
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE. "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC2 . "' THEN 1
                        ELSE 0
                    END
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE. "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC2 . "' THEN 1
                        ELSE 0
                    END
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE. "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC2 . "' THEN 1
                        ELSE 0
                    END
                ELSE 0
            END AS isTechnicalAccess,
            CASE dtd.niveau
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                        ELSE '0'
                    END
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE. "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                        ELSE '0'
                    END
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE. "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                        ELSE '0'
                    END
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE. "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                        ELSE '0'
                    END
                ELSE '0'
            END AS isBBC1
        ";

        $queryJoin = "
            LEFT JOIN fiche_technique ft_rt ON rt.fiche_technique_id = ft_rt.id
            LEFT JOIN remboursement_audit_energie_validation rae_v ON rae.validation_id = rae_v.id
            LEFT JOIN remboursement_audit_energie_instruction rae_i ON rae.instruction_id = rae_i.id
            LEFT JOIN remboursement_audit_numerique_instruction ran_i ON ran.instruction_id = ran_i.id
            LEFT JOIN remboursement_travaux_instruction rt_i ON rt.instruction_id = rt_i.id
        ";

        $query = "
            SELECT  DISTINCT d.id AS demandeId,
                    d.statut_id AS demandeStatutId,
                    ds.slug AS demandeStatutSlug,
                    b.id AS beneficiaireId, 
                    CASE WHEN ('1 | sci' != b.type)
                        THEN CONCAT(' ', UPPER(b.nom), ' ', CONCAT(UCASE(LEFT(b.prenom, 1)), LCASE(SUBSTRING(b.prenom, 2))))
                        ELSE CONCAT_WS(' ', CONCAT(UCASE(LEFT(b.nom_SCI, 1)), LCASE(SUBSTRING(b.nom_SCI, 2))), '<br>', CONCAT(UPPER(b.nom), ' ', CONCAT(UCASE(LEFT(b.prenom, 1)), LCASE(SUBSTRING(b.prenom, 2)))))
                    END AS beneficiaire,
                    l.id AS logementId,
                    CONCAT(l.code_postal, ' ', l.ville) AS logement,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN
                            CASE WHEN ('' != sc_dae.nom)
                                THEN CONCAT(' ', si_dae.nom, ' - ', sc_dae.nom)
                                ELSE si_dae.nom
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN
                            CASE WHEN ('' != sc_dae.nom)
                                THEN CONCAT(' ', si_dae.nom, ' - ', sc_dae.nom)
                                ELSE si_dae.nom
                            END                            
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN
                            CASE WHEN ('' != sc_dan.nom)
                                THEN CONCAT(' ', si_dan.nom, ' - ', sc_dan.nom)
                                ELSE si_dan.nom
                            END
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN
                            CASE WHEN ('' != sc_dan.nom)
                                THEN CONCAT(' ', si_dan.nom, ' - ', sc_dan.nom)
                                ELSE si_dan.nom
                            END                            
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE WHEN ('' != sc_dt.nom)
                                THEN CONCAT(' ', si_dt.nom, ' - ', sc_dt.nom)
                                ELSE si_dt.nom
                            END
                        ELSE NULL
                    END AS structureConseiller,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pi_dae.raison_sociale
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pi_dae.raison_sociale
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pi_dan.raison_sociale
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pi_dan.raison_sociale
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN  pi_dtd.raison_sociale
                        ELSE NULL
                    END AS partenaire,
                    t.id AS titreId,
                    t.numero_cheque AS numeroCheque,
                    r.id AS remboursementId,
                    r.statut_id AS remboursementStatutId,
                    r.statut_description AS remboursementStatutDescriptionFormatted,
                    rs.slug AS remboursementStatutSlug,
                    IF(r.id, rs.slug, ds.slug) AS statut,
                    rs.description AS remboursementStatutDescription,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(raei.destinataire = '0 | auditeur',poa_dae.rib_alt,raei.rib_alt)
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(raei.destinataire = '0 | auditeur',poa_dae.rib_alt,raei.rib_alt)
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN IF(rani.destinataire = '0 | auditeur',poa_dan.rib_alt,rani.rib_alt)
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN IF(rani.destinataire = '0 | auditeur',poa_dan.rib_alt,rani.rib_alt)
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN IF(rti.destinataire = '0 | auditeur',poa_dtd.rib_alt,rti.rib_alt)
                        ELSE NULL
                    END AS ribAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.recto_cheque_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.recto_cheque_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.recto_cheque_alt
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.recto_cheque_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.recto_cheque_alt
                        ELSE NULL
                    END AS rectoChequeAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.verso_cheque_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.verso_cheque_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.verso_cheque_alt
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.verso_cheque_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.verso_cheque_alt
                        ELSE NULL
                    END AS versoChequeAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.facture_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.facture_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.facture_alt
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.facture_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN GROUP_CONCAT(rtic.document_alt)
                        ELSE NULL
                    END AS factureAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.fiche_travaux_alt
                        ELSE NULL
                    END AS ficheTravauxAlt,
                    dRMH.date_RMH AS RMHDate,
                    COUNT(h.id) AS countCommentaire,
                    '' AS action " .
            $queryCase." 
            FROM demande_ d 
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id 
                INNER JOIN logement l ON l.id = d.logement_id 
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                LEFT JOIN historique_ h ON h.demande_id = d.id
                    AND LOWER(h.action) = 'commentaire'
                INNER JOIN titre t ON t.demande_id = d.id 
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id 
                LEFT JOIN remboursement_ r2 ON r2.demande_id = d.id AND r2.titre_id = t2.id 
                LEFT JOIN remboursement_ r ON r.demande_id = d.id AND r.titre_id = t.id 
                LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id 
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id 
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id 
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id 
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id 
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id 
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id 
                LEFT JOIN structure_identification si_dae ON s_dae.structure_identification_id = si_dae.id 
                LEFT JOIN structure_conseiller sc_dae ON dae.conseiller_id = sc_dae.id 
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id 
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN partenaire_option_auditeur poa_dae ON poa_dae.id = p_dae.partenaire_option_auditeur_id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id 
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id 
                LEFT JOIN structure_identification si_dan ON s_dan.structure_identification_id = si_dan.id 
                LEFT JOIN structure_conseiller sc_dan ON dan.conseiller_id = sc_dan.id 
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id 
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN partenaire_option_auditeur poa_dan ON poa_dan.id = p_dan.partenaire_option_auditeur_id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id 
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id 
                LEFT JOIN structure_identification si_dt ON s_dt.structure_identification_id = si_dt.id 
                LEFT JOIN structure_conseiller sc_dt ON dt.conseiller_id = sc_dt.id 
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id 
                LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id 
                LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id
                LEFT JOIN partenaire_option_auditeur poa_dtd ON poa_dtd.id = p_dtd.partenaire_option_auditeur_id 
                LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id 
                LEFT JOIN partenaire_statut ps_dtd_aud ON p_dtd_aud.partenaire_statut_id = ps_dtd_aud.id 
                    AND ps_dtd_aud.enabled = 1
                LEFT JOIN partenaire_identification pi_dtd_aud ON p_dtd_aud.partenaire_identification_id = pi_dtd_aud.id
                LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id
                LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
                LEFT JOIN remboursement_travaux_instruction__conformite rti_c ON rti.id = rti_c.remboursement_travaux_instruction_id
                LEFT JOIN remboursement_travaux_instruction_conformite rtic ON rti_c.remboursement_travaux_instruction_conformite_id = rtic.id
                " .
            $queryJoin .
            $queryWhere .
            $where . "
            GROUP BY d.id, r.id, t.id
        ";

        if (!empty($orderBy) && !empty($orderType) ) {
            $query .= "
                ORDER BY " . $orderBy . " " . $orderType;
        }

        if (isset($start) && isset($length)) {
            $query .= "
                LIMIT " . $start . "," . $length;
        }

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function findForUpdateStatutDescriptionCommand()
    {
        $query = "
            SELECT r.id AS remboursementId
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id 
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                INNER JOIN titre t ON t.demande_id = d.id
                INNER JOIN remboursement_ r ON r.demande_id = d.id AND r.titre_id = t.id
            WHERE r.statut_description IS NULL
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $remboursementId
     * @param $productionTravauxNiveauBBC1
     * @return array | false
     * @throws Exception
     */
    public function findCustomForStatutDescriptionByRemboursement(
        $remboursementId,
        $productionTravauxNiveauBBC1
    ) {
        $queryCase = '';
        $queryWhere = " WHERE r.id = " . $remboursementId;

        $queryCase .= ",
            CASE dtd.niveau
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                        ELSE '0'
                    END
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE. "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                        ELSE '0'
                    END
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE. "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                        ELSE '0'
                    END
                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE. "' THEN
                    CASE t.numero_operation
                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                        ELSE '0'
                    END
                ELSE '0'
            END AS isBBC1
        ";

        $query = "
            SELECT  DISTINCT d.id AS demandeId,
                    r.id AS remboursementId,
                    d.type AS demandeType,
                    rs.description AS remboursementStatutDescription,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(raei.destinataire = '0 | auditeur',poa_dae.rib_alt,raei.rib_alt)
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(raei.destinataire = '0 | auditeur',poa_dae.rib_alt,raei.rib_alt)
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN IF(rani.destinataire = '0 | auditeur',poa_dan.rib_alt,rani.rib_alt)
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN IF(rani.destinataire = '0 | auditeur',poa_dan.rib_alt,rani.rib_alt)
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN IF(rti.destinataire = '0 | auditeur',poa_dtd.rib_alt,rti.rib_alt)
                        ELSE NULL
                    END AS ribAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.recto_cheque_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.recto_cheque_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.recto_cheque_alt
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.recto_cheque_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.recto_cheque_alt
                        ELSE NULL
                    END AS rectoChequeAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.verso_cheque_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.verso_cheque_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.verso_cheque_alt
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.verso_cheque_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.verso_cheque_alt
                        ELSE NULL
                    END AS versoChequeAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.facture_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.facture_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.facture_alt
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.facture_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN GROUP_CONCAT(rtic.document_alt)
                        ELSE NULL
                    END AS factureAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.fiche_travaux_alt
                        ELSE NULL
                    END AS ficheTravauxAlt "
                . $queryCase . " 
            FROM demande_ d 
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id 
                INNER JOIN logement l ON l.id = d.logement_id 
                INNER JOIN demande_statut ds ON ds.id = d.statut_id 
                INNER JOIN titre t ON t.demande_id = d.id 
                LEFT JOIN remboursement_ r ON r.demande_id = d.id AND r.titre_id = t.id 
                LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id 
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id 
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id 
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id 
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id 
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id 
                LEFT JOIN structure_identification si_dae ON s_dae.structure_identification_id = si_dae.id 
                LEFT JOIN structure_conseiller sc_dae ON dae.conseiller_id = sc_dae.id 
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id 
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN partenaire_option_auditeur poa_dae ON poa_dae.id = p_dae.partenaire_option_auditeur_id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id 
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id 
                LEFT JOIN structure_identification si_dan ON s_dan.structure_identification_id = si_dan.id 
                LEFT JOIN structure_conseiller sc_dan ON dan.conseiller_id = sc_dan.id 
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id 
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN partenaire_option_auditeur poa_dan ON poa_dan.id = p_dan.partenaire_option_auditeur_id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id 
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id 
                LEFT JOIN structure_identification si_dt ON s_dt.structure_identification_id = si_dt.id 
                LEFT JOIN structure_conseiller sc_dt ON dt.conseiller_id = sc_dt.id 
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id 
                LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id 
                LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id
                LEFT JOIN partenaire_option_auditeur poa_dtd ON poa_dtd.id = p_dtd.partenaire_option_auditeur_id 
                LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id 
                LEFT JOIN partenaire_statut ps_dtd_aud ON p_dtd_aud.partenaire_statut_id = ps_dtd_aud.id 
                    AND ps_dtd_aud.enabled = 1
                LEFT JOIN partenaire_identification pi_dtd_aud ON p_dtd_aud.partenaire_identification_id = pi_dtd_aud.id
                LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id
                LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
                LEFT JOIN remboursement_travaux_instruction__conformite rti_c ON rti.id = rti_c.remboursement_travaux_instruction_id
                LEFT JOIN remboursement_travaux_instruction_conformite rtic ON rti_c.remboursement_travaux_instruction_conformite_id = rtic.id
                "
            . $queryWhere
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $dateRMHId
     * @return array
     * @throws Exception
     */
    public function findByDateRMH($dateRMHId)
    {
        $query = "
            SELECT  r.id AS remboursementId,
                    d.type AS demandeType,
                    b.email AS beneficiaireEmail
            FROM remboursement_ r
                INNER JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id AND dRMH.enabled = 1
                INNER JOIN demande_ d ON d.id = r.demande_id
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
            WHERE r.dateRMH_id = " .$dateRMHId . "
                AND r.statut_id = " . Remboursement_statut::STATUS_21 . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $productionTravauxNiveauBBC1
     * @param array $list_demandeId
     * @return array
     * @throws Exception
     */
    public function findAllCustomByDemande($productionTravauxNiveauBBC1, $list_demandeId = array())
    {
        $results = array();

        if (!empty($list_demandeId)) {
            $query = "
                SELECT  DISTINCT d.id AS demandeId,
                        d.type AS demandeType,
                        r.id AS remboursementId,
                        rs.description AS remboursementStatutDescription,
                        CASE d.type
                            WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(raei.destinataire = '0 | auditeur',poa_dae.rib_alt,raei.rib_alt)
                            WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(raei.destinataire = '0 | auditeur',poa_dae.rib_alt,raei.rib_alt)
                            WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN IF(rani.destinataire = '0 | auditeur',poa_dan.rib_alt,rani.rib_alt)
                            WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN IF(rani.destinataire = '0 | auditeur',poa_dan.rib_alt,rani.rib_alt)
                            WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN IF(rti.destinataire = '0 | auditeur',poa_dtd.rib_alt,rti.rib_alt)
                            ELSE NULL
                        END AS ribAlt,
                        CASE d.type
                            WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.recto_cheque_alt
                            WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.recto_cheque_alt
                            WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.recto_cheque_alt
                            WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.recto_cheque_alt
                            WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.recto_cheque_alt
                            ELSE NULL
                        END AS rectoChequeAlt,
                        CASE d.type
                            WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.verso_cheque_alt
                            WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.verso_cheque_alt
                            WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.verso_cheque_alt
                            WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.verso_cheque_alt
                            WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.verso_cheque_alt
                            ELSE NULL
                        END AS versoChequeAlt,   
                        CASE d.type
                            WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.facture_alt
                            WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.facture_alt
                            WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.facture_alt
                            WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.facture_alt
                            WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rtic.document_alt
                            ELSE NULL
                        END AS factureAlt,
                        CASE d.type
                            WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.fiche_travaux_alt
                            ELSE NULL
                        END AS ficheTravauxAlt,
                        CASE dtd.niveau
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                                CASE t.numero_operation
                                    WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                                    ELSE '0'
                                END
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . "' THEN
                                CASE t.numero_operation
                                    WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                                    ELSE '0'
                                END
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . "' THEN
                                CASE t.numero_operation
                                    WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                                    ELSE '0'
                                END
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . "' THEN
                                CASE t.numero_operation
                                    WHEN '" . $productionTravauxNiveauBBC1 . "' THEN '1'
                                    ELSE '0'
                                END
                            ELSE '0'
                        END AS isBBC1
                FROM demande_ d
                    INNER JOIN titre t ON t.demande_id = d.id
                    LEFT JOIN remboursement_ r ON r.demande_id = d.id AND r.titre_id = t.id
                    LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id
                    LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id 
                    LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id
                    LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id                
                    LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id
                    LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                    LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
                    LEFT JOIN remboursement_travaux_instruction__conformite rti_c ON rti.id = rti_c.remboursement_travaux_instruction_id
                    LEFT JOIN remboursement_travaux_instruction_conformite rtic ON rti_c.remboursement_travaux_instruction_conformite_id = rtic.id
                    LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                    LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                    LEFT JOIN partenaire_option_auditeur poa_dae ON poa_dae.id = p_dae.partenaire_option_auditeur_id
                    LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                    LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id 
                    LEFT JOIN partenaire_option_auditeur poa_dan ON poa_dan.id = p_dan.partenaire_option_auditeur_id
                    LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                    LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id 
                    LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id
                    LEFT JOIN partenaire_option_auditeur poa_dtd ON poa_dtd.id = p_dtd.partenaire_option_auditeur_id
                WHERE d.id IN (" . implode(', ', $list_demandeId) . ")";

            $statement = $this->_em
                ->getConnection()
                ->prepare($query);
            $result = $statement->executeQuery();
            $results = $result->fetchAllAssociative();
        }

        return $results;
    }

    /**
     * @param $option
     * @return array
     * @throws Exception
     */
    public function findDataExport($option)
    {
        $productionTravauxNiveauBBC1 = $option['production_travauxNiveau_BBC1'];
        $productionTravauxNiveauBBC2 = $option['production_travauxNiveau_BBC2'];

        $query = " 
            SELECT  DISTINCT d.id AS demandeId,
                    r.id AS remboursementId,
                    d.type AS demandeType,
                    CASE d.type
                        WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN 'Audit énergétique et scénarios'
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN 'Audit numérique'
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE dtd.niveau
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_1_VALUE . "' THEN 'Travaux niveau 1'
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_VALUE . "' THEN 'Travaux niveau 2'
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . "' THEN 'Travaux niveau 2 - Rénovateur BBC'
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE . "' THEN 'Travaux - Sortie de passoire'
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE . "' THEN 'Travaux - Première étape BBC avec RGE'
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE. "' THEN
                                    CASE t.numero_operation
                                        WHEN '".$productionTravauxNiveauBBC1."' THEN 'Travaux niveau 3 - Rénovation BBC (1/2)'
                                        WHEN '".$productionTravauxNiveauBBC2."' THEN 'Travaux niveau 3 - Rénovation BBC (2/2)'
                                        ELSE 'autre'
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE. "' THEN
                                    CASE t.numero_operation
                                        WHEN '".$productionTravauxNiveauBBC1."' THEN 'Travaux niveau 3 - Biosourcé (1/2)'
                                        WHEN '".$productionTravauxNiveauBBC2."' THEN 'Travaux niveau 3 - Biosourcé (2/2)'
                                        ELSE 'autre'
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE. "' THEN
                                    CASE t.numero_operation
                                        WHEN '".$productionTravauxNiveauBBC1."' THEN 'Travaux - Première étape BBC avec Rénovateur (1/2)'
                                        WHEN '".$productionTravauxNiveauBBC2."' THEN 'Travaux - Première étape BBC avec Rénovateur (2/2)'
                                        ELSE 'autre'
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE. "' THEN
                                    CASE t.numero_operation
                                        WHEN '".$productionTravauxNiveauBBC1."' THEN 'Travaux - Rénovation globale BBC (1/2)'
                                        WHEN '".$productionTravauxNiveauBBC2."' THEN 'Travaux - Rénovation globale BBC (2/2)'
                                        ELSE 'autre'
                                    END
                                ELSE NULL
                            END
                        ELSE NULL
                    END AS typeCheque,
                    b.nom AS beneficiaireNom, 
                    b.prenom AS beneficiairePrenom,
                    l.code_postal AS logementCodePostal,
                    l.ville AS logementVille,
                    r.date_creation AS dateDemandeRemboursement,
                    rs.slug AS remboursementStatutSlug,
                    CASE d.type
                        WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN sc_dae.nom
                        WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN sc_dae.nom
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN sc_dan.nom
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN sc_dan.nom
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN sc_dt.nom
                        ELSE NULL
                    END AS conseillerNom,
                    CASE d.type
                        WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pi_dae.raison_sociale
                        WHEN  " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pi_dae.raison_sociale
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pi_dan.raison_sociale
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pi_dan.raison_sociale
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN  pi_dtd.raison_sociale
                        ELSE NULL
                    END AS raisonSociale,
                    dRMH.date_RMH AS RMHDate
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                INNER JOIN titre t ON t.demande_id = d.id
                INNER JOIN remboursement_ r ON r.demande_id = d.id AND r.titre_id = t.id
                INNER JOIN remboursement_statut rs ON r.statut_id = rs.id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id
                LEFT JOIN structure_conseiller sc_dae ON dae.conseiller_id = sc_dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN partenaire_option_auditeur poa_dae ON poa_dae.id = p_dae.partenaire_option_auditeur_id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id
                LEFT JOIN structure_conseiller sc_dan ON dan.conseiller_id = sc_dan.id
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN partenaire_option_auditeur poa_dan ON poa_dan.id = p_dan.partenaire_option_auditeur_id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id
                LEFT JOIN structure_conseiller sc_dt ON dt.conseiller_id = sc_dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id
                LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id
                LEFT JOIN partenaire_option_auditeur poa_dtd ON poa_dtd.id = p_dtd.partenaire_option_auditeur_id
                LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id
                LEFT JOIN partenaire_statut ps_dtd_aud ON p_dtd_aud.partenaire_statut_id = ps_dtd_aud.id
                    AND ps_dtd_aud.enabled = 1
                LEFT JOIN partenaire_identification pi_dtd_aud ON p_dtd_aud.partenaire_identification_id = pi_dtd_aud.id
            ORDER BY d.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $production_travauxNiveau_BBC1
     * @param $production_travauxNiveau_BBC2
     *
     * @return array|float|int|string
     */
    public function findWithRMHConditionForDeleteDocumentProcess($production_travauxNiveau_BBC1, $production_travauxNiveau_BBC2)
    {
        $dateJour = new \DateTime();
        $dateLastYear = date('Y-m-d', mktime(0,0,0, (integer)($dateJour->format('m')),(integer)($dateJour->format('d')), $dateJour->format('Y')-1));

        $qb = $this->createQueryBuilder('r');
        $qb
            ->select('r')
            ->innerJoin(Remboursement_statut::class, 'rs', Join::WITH, 'r.statut_id = rs.id')
            ->innerJoin(Demande_::class, 'd', Join::WITH, 'r.demande_id = d.id')
            ->leftJoin(Titre::class, 'tbbc1', Join::WITH, 'tbbc1.demandeId = d.id AND r.titre_id = tbbc1.id AND tbbc1.numeroOperation = ' . $production_travauxNiveau_BBC1)
            ->leftJoin(Titre::class, 'tbbc2', Join::WITH, 'tbbc2.demandeId = tbbc1.demandeId AND tbbc2.id != tbbc1.id AND tbbc2.numeroOperation = ' . $production_travauxNiveau_BBC2)
            ->leftJoin(Remboursement_::class, 'r2', Join::WITH, 'r2.demande_id = d.id AND r2.titre_id = tbbc2.id')
            ->leftJoin(DateRMH::class, 'dRMHr2', Join::WITH, 'dRMHr2.id = r2.dateRMH_id')
            ->innerJoin(DateRMH::class, 'dRMH', Join::WITH, 'dRMH.id = r.dateRMH_id')
            ->innerJoin(Beneficiaire::class, 'b', Join::WITH, 'b.id = d.beneficiaire_id')
            ->where('dRMH.dateRMH <= :dateRMH')
            ->andWhere('dRMH.rgpd != :rgpd')
            ->andWhere('r.statut_id = :statutId')
            ->andWhere($qb->expr()->orX('tbbc1.id IS NULL', $qb->expr()->andX('tbbc1.id IS NOT NULL', 'r2.statut_id = :statutId2', 'dRMHr2.dateRMH <= :dateRMH')))
            ->setParameters(
                [
                    'dateRMH'   => $dateLastYear,
                    'statutId'  => Remboursement_statut::STATUS_22,
                    'statutId2' => Remboursement_statut::STATUS_22,
                    'rgpd'      => 1
                ]
            );

        return $qb->getQuery()->getResult();
    }

    /**
     * @param $demandeId
     * @param $productionTravauxNiveauBBC1
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findBBC1RembourseByDemandeAndBBC1Parameter($demandeId, $productionTravauxNiveauBBC1)
    {
        $dateJour = new \DateTime();
        $dateLastYear = date('Y-m-d', mktime(0,0,0, (integer)($dateJour->format('m')),(integer)($dateJour->format('d')), $dateJour->format('Y')-1));

        $query = $this->createQueryBuilder('r')
            ->select('r')
            ->innerJoin('App\Entity\Remboursement_statut', 'rs', Join::WITH, 'r.statut_id = rs.id')
            ->innerJoin('App\Entity\Demande_', 'd', Join::WITH, 'r.demande_id = d.id')
            ->innerJoin('App\Entity\DateRMH', 'dRMH', Join::WITH, 'dRMH.id = r.dateRMH_id')
            ->innerJoin('App\Entity\Titre', 't', Join::WITH, 't.id = r.titre_id')
            ->where('r.demande_id = :demandeId')
            ->andWhere('r.statut_id = :statutId')
            ->andWhere('t.numeroOperation = :numeroOperation')
            ->andWhere('dRMH.dateRMH <= :dateRMH')
            ->setParameters(
                array(
                    'demandeId'         => $demandeId,
                    'statutId'          => Remboursement_statut::STATUS_22,
                    'numeroOperation'   => $productionTravauxNiveauBBC1,
                    'dateRMH'           => $dateLastYear,
                )
            );

        return $query->getQuery()->getOneOrNullResult();
    }

    /**
     * @param $demandeTypeCustom
     * @param null $titreId
     * @param null $remboursementId
     * @return array | false
     * @throws Exception
     */
    public function findOneForExamineReexamine(
        $demandeTypeCustom,
        $titreId = null,
        $remboursementId = null
    ) {

        $andWhere = " AND (r.statut_id IS NULL OR r.statut_id NOT IN (" . Remboursement_statut::STATUS_22 . ")) ";
        $andWhereInstructionIs = "";

        if (!empty($titreId) && empty($remboursementId)) {
            // cas IS EXAMINE
            $andWhereInstructionIs = " NULL ";
            $andWhere .= " AND t.id = " . $titreId;
        } else if(empty($titreId) && !empty($remboursementId)) {
            // cas IS REEXAMINE
            $andWhereInstructionIs = " NOT NULL ";
            $andWhere .= " AND r.id = " . $remboursementId;
        }

        switch ($demandeTypeCustom) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:  // Audit Energetique
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:  // Audit Energetique Région
                $andWhere .= " AND rae.instruction_id IS " . $andWhereInstructionIs;
                break;
            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:  // Audit Numerique
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:  // Mise à jour Audit Energetique
                $andWhere .= " AND ran.instruction_id IS " . $andWhereInstructionIs;
                break;
            case Demande_::DEMANDE_TRAVAUX_TYPE:  // Travaux
                $andWhere .= " AND rt.instruction_id IS " . $andWhereInstructionIs;
                break;
        }

        $query = " 
            SELECT  d.id AS demandeId,
                    t.id AS titreId,
                    r.id AS remboursementId
            FROM demande_ d
                INNER JOIN titre t ON t.demande_id = d.id
                LEFT JOIN remboursement_ r ON r.demande_id = d.id AND r.titre_id = t.id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id 
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id 
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id 
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id
                WHERE dRMH.date_RMH IS NULL "
            . $andWhere ."
            LIMIT 1 
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $demandeTypeCustom
     * @param null $demandeId
     * @param null $remboursementId
     * @return bool|array
     * @throws Exception
     */
    public function findOneForDepot(
        $demandeTypeCustom,
        $demandeId = null,
        $remboursementId = null
    ) {

        $andWhereDepotIdIs = "";
        $andWhere = " AND (r.statut_id IS NULL OR r.statut_id NOT IN (" . Remboursement_statut::STATUS_20 . ", " . Remboursement_statut::STATUS_22 . ")) ";

        if (!empty($demandeId) && empty($remboursementId)) {
            // ADD DEPOT
            $andWhereDepotIdIs = " NULL ";
            $andWhere .= " AND d.id = " . $demandeId;
        } else if(empty($demandeId) && !empty($remboursementId)) {
            // EDIT DEPOT
            $andWhereDepotIdIs = " NOT NULL ";
            $andWhere .= " AND r.id = " . $remboursementId;
        }

        if (in_array($demandeTypeCustom, [
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
        ])) { // Audit Energetique, Audit Région
            $andWhere .= " AND rae.depot_id IS " . $andWhereDepotIdIs;
        } else if (in_array($demandeTypeCustom, [
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE
        ])) { // Audit Numerique, Mise à jour Audit
            $andWhere .= " AND ran.depot_id IS " . $andWhereDepotIdIs;
        }

        $query = " 
            SELECT  d.id AS demandeId,
                    r.id AS remboursementId
            FROM demande_ d
                LEFT JOIN remboursement_ r ON r.demande_id = d.id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id 
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id
                WHERE dRMH.date_RMH IS NULL "
            . $andWhere ."
            LIMIT 1 
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $productionTravauxNiveauBBC2
     * @param null $titreId
     * @param null $remboursementId
     * @return false|array
     * @throws Exception
     */
    public function findOneForFicheTechnique(
        $productionTravauxNiveauBBC2,
        $titreId = null,
        $remboursementId = null
    ) {

        $andWhere = " AND (r.statut_id IS NULL OR r.statut_id NOT IN (" . Remboursement_statut::STATUS_20 . ", " . Remboursement_statut::STATUS_22 . ")) ";
        $andWhereFicheTechniqueIdIs = "";

        if (!empty($titreId) && empty($remboursementId)) {
            // cas IS EXAMINE
            $andWhereFicheTechniqueIdIs = " NULL ";
            $andWhere .= " AND t.id = " . $titreId;
        } else if(empty($titreId) && !empty($remboursementId)) {
            // cas IS REEXAMINE
            $andWhereFicheTechniqueIdIs = " NOT NULL ";
            $andWhere .= " AND r.id = " . $remboursementId;
            $andWhere .= " AND (ft_rt.statut_ficheTechnique = '0' OR ft_rt.statut_ficheTechnique = '1') ";
        }

        $andWhere .= " AND rt.fiche_technique_id IS " . $andWhereFicheTechniqueIdIs;

        $arrayTravauxDoubleCheque = [
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE
        ];

        $addSelect = "";
        foreach ($arrayTravauxDoubleCheque as $travauxNiveau) {
            $addSelect .=   "WHEN '" . $travauxNiveau . "' THEN
                                CASE t.numero_operation
                                    WHEN '" . $productionTravauxNiveauBBC2 . "' THEN 1
                                    ELSE 0
                                END ";
        }

        $query = "
            SELECT  d.id AS demandeId,
                    t.id AS titreId,
                    r.id AS remboursementId,
                    CASE dtd.niveau
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . "' THEN 1 "
                    . $addSelect
                    . " ELSE 0
                    END AS isTechnicalAccess
            FROM demande_ d
                INNER JOIN titre t ON t.demande_id = d.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN remboursement_ r ON r.demande_id = d.id AND r.titre_id = t.id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id 
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id 
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id
                LEFT JOIN fiche_technique ft_rt ON rt.fiche_technique_id = ft_rt.id
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id
                WHERE dRMH.date_RMH IS NULL "
            . $andWhere ."
            LIMIT 1 
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $demandeId
     * @param $productionTravauxNiveauBBC2
     * @return array|false
     * @throws Exception
     */
    public function findByDemandeAndRemboursementTermine($demandeId, $productionTravauxNiveauBBC2)
    {
        $query = "
            SELECT  d.id AS demandeId,
                    r.id AS remboursementId,
                    r.statut_id AS  remboursementStatutId
            FROM demande_ d
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                LEFT JOIN titre t ON t.demande_id = d.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN remboursement_ r ON r.titre_id = t.id 
                LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id
                WHERE d.id = " . $demandeId . "
                AND (
                    (dtd.niveau IN ('" . implode("', '", Demande_travaux_devis::$arrayTravauxDoubleCheque) . "') AND t.numero_operation = " .  $productionTravauxNiveauBBC2 . ")
                    OR dtd.niveau IN ('" . implode("', '", Demande_travaux_devis::$arrayTravauxSimpleCheque) . "')
                )
                AND r.statut_id = " .Remboursement_statut::STATUS_22."
            ORDER BY r.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function findRefusForDeleteDocumentProcess()
    {
        $dateJour = new \DateTime();
        $dateLimit = (new \DateTime('-1 year' . $dateJour->format('Y-m-d')))->format('Y-m-d');

        $query = "
            SELECT  r.id AS remboursementId,
                    d.id AS demandeId,
                    d.type AS demandeType
            FROM remboursement_ r
                INNER JOIN remboursement_statut rs ON r.statut_id = rs.id
                INNER JOIN demande_ d ON d.id = r.demande_id
            WHERE rs.statut = :statut
                AND DATE_FORMAT(r.date_modif, '%Y-%m-%d') <= :dateLimit
                AND r.rgpd != :rgpd
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery([
            ':dateLimit' => $dateLimit,
            ':rgpd'      => 1,
            ':statut'    => Remboursement_statut::STATUS_20,
        ]);

        return $result->fetchAllAssociative();
    }

    /**
     * @return array|float|int|string
     * @throws \Exception
     */
    public function findForDeleteAuditDocumentRmbTermineProcess()
    {
        $dateJour = new \DateTime();
        $dateLimit = (new \DateTime('-1 year' . $dateJour->format('Y-m-d')))->format('Y-m-d');

        $demandeTypes = [
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE,
            Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE
        ];

        $qb = $this->createQueryBuilder('r');
        $qb->select('r.id AS remboursementId')
            ->addSelect('d.id AS demandeId')
            ->addSelect('d.type AS demandeType')
            ->innerJoin(Remboursement_statut::class, 'rs', Join::WITH, 'r.statut_id = rs.id')
            ->innerJoin(Demande_::class, 'd', Join::WITH, 'r.demande_id = d.id')
            ->where('rs.statut = :statut')
            ->andWhere('DATE_FORMAT(r.dateModif, \'%Y-%m-%d\') <= :dateLimit')
            ->andWhere('r.isAuditRmbTermineDocDeleted != :isAuditRmbTermineDocDeleted')
            ->andWhere($qb->expr()->in('d.type', ':demandeTypes'))
            ->setParameters([
                'dateLimit'                   => $dateLimit,
                'isAuditRmbTermineDocDeleted' => 1,
                'statut'                      => Remboursement_statut::STATUS_22,
                'demandeTypes'                => $demandeTypes
            ]);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param $production_travauxNiveau_BBC1
     * @param $production_travauxNiveau_BBC2
     *
     * @return array|float|int|string
     * @throws \Exception
     */
    public function findForDeleteTravauxDocumentRmbTermineProcess($production_travauxNiveau_BBC1, $production_travauxNiveau_BBC2)
    {
        $dateJour = new \DateTime();
        $dateLimit = (new \DateTime('-1 year' . $dateJour->format('Y-m-d')))->format('Y-m-d');

        $qb = $this->createQueryBuilder('r');
        $qb->select('r.id AS remboursementId')
            ->addSelect('r2.id AS remboursementId2')
            ->addSelect('d.id AS demandeId')
            ->addSelect('d.type AS demandeType')
            ->addSelect('dt AS demandeTravaux')
            ->innerJoin(Demande_::class, 'd', Join::WITH, 'r.demande_id = d.id')
            ->innerJoin(Demande_travaux::class, 'dt', Join::WITH, 'dt.id = d.demande_travaux')
            ->innerJoin(Demande_travaux_devis::class, 'dtd', Join::WITH, 'dt.travauxDevis_id = dtd.id')
            ->leftJoin(Titre::class, 'tbbc1', Join::WITH, 'tbbc1.demandeId = d.id AND r.titre_id = tbbc1.id AND tbbc1.numeroOperation = ' . $production_travauxNiveau_BBC1)
            ->leftJoin(Titre::class, 'tbbc2', Join::WITH, 'tbbc2.demandeId = tbbc1.demandeId AND tbbc2.id != tbbc1.id AND tbbc2.numeroOperation = ' . $production_travauxNiveau_BBC2)
            ->leftJoin(Remboursement_::class, 'r2', Join::WITH, 'r2.demande_id = d.id AND r2.titre_id = tbbc2.id')
            ->where('r.statut_id = :statutId')
            ->andWhere('DATE_FORMAT(r.dateModif, \'%Y-%m-%d\') <= :dateLimit')
            ->andWhere('r.isTravauxRmbTermineDocDeleted != :isTravauxRmbTermineDocDeleted')
            ->andWhere('d.type = :demandeType')
            ->andWhere($qb->expr()->orX('dtd.niveau NOT IN (\'' . implode("', '", Demande_travaux_devis::$arrayTravauxDoubleCheque) . '\')', $qb->expr()->andX('dtd.niveau IN (\'' . implode("', '", Demande_travaux_devis::$arrayTravauxDoubleCheque) . '\')', 'r2.statut_id = :statutId2', 'DATE_FORMAT(r2.dateModif, \'%Y-%m-%d\') <= :dateLimit')))
            ->setParameters([
                'dateLimit'                     => $dateLimit,
                'isTravauxRmbTermineDocDeleted' => 1,
                'statutId'                      => Remboursement_statut::STATUS_22,
                'statutId2'                     => Remboursement_statut::STATUS_22,
                'demandeType'                   => Demande_::DEMANDE_TRAVAUX_TYPE
            ]);
        $qb->orderBy('d.id', 'ASC');
        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche remboursement travaux cheque 1 terminés dont le chèque 2 pas encore terminés
     *
     * @param $production_travauxNiveau_BBC1
     * @param $production_travauxNiveau_BBC2
     *
     * @return array|float|int|string
     */
    public function findForRollbackDeleteTravauxDocumentRmbTermineProcess($production_travauxNiveau_BBC1, $production_travauxNiveau_BBC2)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('r.id AS remboursementId')
            ->addSelect('r2.id AS remboursementId2')
            ->addSelect('d.id AS demandeId')
            ->addSelect('d.type AS demandeType')
            ->addSelect('dt AS demandeTravaux')
            ->innerJoin(Remboursement_statut::class, 'rs', Join::WITH, 'r.statut_id = rs.id')
            ->innerJoin(Demande_::class, 'd', Join::WITH, 'r.demande_id = d.id')
            ->innerJoin(Demande_travaux::class, 'dt', Join::WITH, 'dt.id = d.demande_travaux')
            ->innerJoin(Demande_travaux_devis::class, 'dtd', Join::WITH, 'dt.travauxDevis_id = dtd.id')
            ->innerJoin(Titre::class, 't', Join::WITH, 't.demandeId = d.id AND r.titre_id = t.id AND t.numeroOperation = ' . $production_travauxNiveau_BBC1)
            ->leftJoin(Titre::class, 't2', Join::WITH, 't2.demandeId = t.demandeId AND t2.id != t.id AND t2.numeroOperation = ' . $production_travauxNiveau_BBC2)
            ->leftJoin(Remboursement_::class, 'r2', Join::WITH, 'r2.demande_id = d.id AND r2.titre_id = t2.id')
            ->where('r.statut_id = :statutId')
            ->andWhere('r.isTravauxRmbTermineDocDeleted = :isTravauxRmbTermineDocDeleted')
            ->andWhere('d.type = :demandeType')
            ->andWhere('dtd.niveau IN (\'' . implode("', '", Demande_travaux_devis::$arrayTravauxDoubleCheque) . '\')')
            ->andWhere('r2.id IS NULL OR r2.statut_id != :statutId2')
            ->setParameters([
                'isTravauxRmbTermineDocDeleted' => 1,
                'statutId'                      => Remboursement_statut::STATUS_22,
                'statutId2'                     => Remboursement_statut::STATUS_22,
                'demandeType'                   => Demande_::DEMANDE_TRAVAUX_TYPE
            ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param $statutsToUpdate
     * @return array|float|int|string
     */
    public function findForUpdateAfterRetrieveRemboursementValidationConseiller($statutsToUpdate)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('r.id AS remboursementId')
            ->addSelect('d.id AS demandeId')
            ->addSelect('d.type AS demandeType')
            ->innerJoin(Remboursement_statut::class, 'rs', Join::WITH, 'r.statut_id = rs.id')
            ->innerJoin(Demande_::class, 'd', Join::WITH, 'r.demande_id = d.id')
            ->innerJoin(Beneficiaire::class, 'b', Join::WITH, 'b.id = d.beneficiaire_id')
            ->innerJoin(Titre::class, 't', Join::WITH, 't.id = r.titre_id')
            ->andWhere($qb->expr()->in('rs.statut', ':arrayStatutsIds'))
            ->setParameter('arrayStatutsIds', $statutsToUpdate);

        return $qb->getQuery()->getResult();
    }
}

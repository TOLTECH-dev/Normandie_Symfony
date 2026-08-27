<?php

namespace App\Repository;

use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux_devis;
use App\Entity\EPCI_;
use App\Entity\FicheTechnique;
use App\Entity\Historique_;
use App\Entity\Remboursement_;
use App\Entity\Remboursement_statut;
use App\Entity\Structure_;
use App\Entity\Titre;
use App\Service\TitreService;
use App\Utils\DefaultServiceUtils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;


class Demande_Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande_::class);
    }

    /**
     * @param $option
     * @return array
     * @throws ORMException
     * @throws Exception
     */
    public function findAllCustom($option)
    {
        $roles = $option['roles'];
        $username = $option['username'];
        $production_travauxNiveau_BBC1 = $option['production_travauxNiveau_BBC1'];
        $production_travauxNiveau_BBC2 = $option['production_travauxNiveau_BBC2'];

        $queryJoin = "";
        $queryWhere = "";

        $adminId = (int)substr($username, 1);

        if (is_int($adminId)) {
            if (in_array('ROLE_CONSEILLER', $roles)) {
                $repo_structure = $this->_em->getRepository(Structure_::class);
                $structure_id = $repo_structure->findByConseillerId($adminId);
                $user_id_current = $structure_id['id'];

                // La structure qui doit voir ses dossiers doit être la structure lié à la fiche du bénéficiaire
                $queryJoin = "";
                $queryWhere = " AND (
                                    b.structure_rattachement_id =" . (int)$user_id_current . "
                                )";
            } elseif (in_array('ROLE_AUDITEUR', $roles)) {
                $queryJoin = "
                    LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id
                    LEFT JOIN partenaire_statut ps_dtd_aud ON (p_dtd_aud.partenaire_statut_id = ps_dtd_aud.id AND ps_dtd_aud.enabled = '1')
                    LEFT JOIN partenaire_identification pi_dtd_aud ON p_dtd_aud.partenaire_identification_id = pi_dtd_aud.id
                ";

                $queryWhere = " AND ( " .
                    "p_dae.id = '" .$adminId."' OR
                     p_dan.id = '" .$adminId."' OR
                     p_dtd_aud.id = '" .$adminId."' AND dt.audit = '1' ".
                    ")";
            } elseif (in_array('ROLE_RENOVATEUR', $roles)) {
                $queryJoin = " LEFT JOIN partenaire_statut ps_dtd ON (p_dtd.partenaire_statut_id = ps_dtd.id AND ps_dtd.enabled = '1') ";

                $queryWhere = " AND ( " .
                    "dtd.renovateur_id = '" .$adminId."'".
                    ")";
            }  elseif (in_array('ROLE_EPCI', $roles)) {
                $repo_EPCI = $this->_em->getRepository(EPCI_::class);
                $EPCI_id = $repo_EPCI->findByContactId($adminId);
                $user_id_current = $EPCI_id['id'];

                $queryJoin = "
                    LEFT JOIN up_ville uv ON l.INSEE = uv.code_insee
                    LEFT JOIN orientation o ON uv.id = o.ville_id
                ";

                $queryWhere = " AND (
                                    o.EPCI_id = " . (int)$user_id_current . "
                                )
                ";
            } elseif (in_array('ROLE_TECHNIQUE', $roles)) {
                $queryWhere = " AND ( " .
                    "d.statut_id <> 15
                    AND (dt.fiche_technique_id IS NOT NULL OR dt.travaux_devis_id IS NOT NULL)" .
                    ")";
            }
        }

        $query = "
            SELECT  DISTINCT d.id AS demandeId,
                    d.date_creation AS demandeDateCreation,
                    d.type AS demandeType,
                    d.statut_id AS demandeStatutId,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.justificatif_propriete_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.justificatif_propriete_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.justificatif_propriete_alt
                        ELSE NULL
                    END AS documentJPAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.piece_complement_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.piece_complement_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.piece_complement_alt
                        ELSE NULL
                    END AS documentKBISAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.avis_imposition_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.avis_imposition_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.avis_imposition_alt
                        ELSE NULL
                    END AS documentAIAlt,
                    dCP.date_CP AS commissionDate,
                    dCP.numero_deliberation AS commissionNumero,
                    ds.slug AS statutSlug,
                    ds.description AS demandeStatutDescription,
                    b.id AS beneficiaireId,
                    b.nom AS beneficiaireNom,
                    b.prenom AS beneficiairePrenom,
                    b.nom_SCI AS beneficiaireNomSCI,
                    b.type AS beneficiaireType,
                    l.id AS logementId,
                    l.code_postal AS logementCodePostal,
                    l.ville AS logementVille,
                    l.situation as logementSituation,
                    i.id AS instructionId,
                    ( SELECT demande_.id 
                      FROM demande_ 
                      WHERE demande_.logement_id = l.id 
                          AND demande_.id <> d.id 
                          AND demande_.type = " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . "
                          AND demande_.statut_id <> 15
                    ) AS auditEId,
                    si_dt.nom AS structureNomTravaux,
                    sc_dt.nom AS conseillerNomTravaux,
                    pi_dtd.raison_sociale AS renovateurNomTravaux,
                    dtd.niveau AS travauxDevisNivCheque,
                    si_dae.nom AS structureNomAuditE,
                    sc_dae.nom AS conseillerNomAuditE,
                    pi_dae.raison_sociale AS auditeurNomAuditE,
                    si_dan.nom AS structureNomAuditN,
                    sc_dan.nom AS conseillerNomAuditN,
                    pi_dan.raison_sociale AS auditeurNomAuditN,
                    dt.audit AS audit,
                    dRMH.date_RMH AS remboursementDate,
                    rs.description As remboursementStatutDescription,
                    rs.slug AS remboursementStatutSlug,
                    r.id AS remboursementId
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                LEFT JOIN date_CP dCP ON dCP.id = d.dateCP_id
                LEFT JOIN instruction_ i ON i.demande_id = d.id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id
                LEFT JOIN structure_identification si_dae ON s_dae.structure_identification_id = si_dae.id
                LEFT JOIN structure_conseiller sc_dae ON (dae.conseiller_id = sc_dae.id)
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id
                LEFT JOIN structure_identification si_dan ON s_dan.structure_identification_id = si_dan.id
                LEFT JOIN structure_conseiller sc_dan ON (dan.conseiller_id = sc_dan.id)
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id    
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id
                LEFT JOIN structure_identification si_dt ON s_dt.structure_identification_id = si_dt.id
                LEFT JOIN structure_conseiller sc_dt ON (dt.conseiller_id = sc_dt.id)
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id
                LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id
                LEFT JOIN titre t ON t.demande_id = d.id
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id 
                LEFT JOIN remboursement_ r2 ON r2.demande_id = d.id AND r2.titre_id = t2.id
                LEFT JOIN remboursement_ r ON r.titre_id = t.id 
                LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id " .
            $queryJoin .
            " WHERE (
                        t.id IS NULL
                        OR t.numero_operation NOT IN (".$production_travauxNiveau_BBC1.",".$production_travauxNiveau_BBC2.")
                        OR t.numero_operation = ".$production_travauxNiveau_BBC2." AND r2.statut_id = " .Remboursement_statut::STATUS_22."
                        OR t.numero_operation = ".$production_travauxNiveau_BBC1." AND r.statut_id != " .Remboursement_statut::STATUS_22."
                        OR t.numero_operation = ".$production_travauxNiveau_BBC1." AND r.id IS NULL
            )" .
            $queryWhere . "
            ORDER BY d.id DESC
        ";

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
            SELECT  DISTINCT d.id AS demandeId
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
            WHERE statut_description IS NULL
            ORDER BY d.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $demandeId
     * @return array|false
     * @throws Exception
     */
    public function findCustomForStatutDescriptionByDemande($demandeId)
    {
        $query = "
            SELECT  d.id AS demandeId,
                    d.type AS demandeType,
                    ds.description AS demandeStatutDescription,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.justificatif_propriete_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.justificatif_propriete_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.justificatif_propriete_alt
                        ELSE NULL
                    END AS documentJPAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.piece_complement_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.piece_complement_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.piece_complement_alt
                        ELSE NULL
                    END AS documentKBISAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.avis_imposition_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.avis_imposition_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.avis_imposition_alt
                        ELSE NULL
                    END AS documentAIAlt,
                    b.type AS beneficiaireType,
                    l.situation as logementSituation
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                WHERE d.id = " . $demandeId . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $option
     * @param string $where
     * @return int
     * @throws Exception
     */
    public function countAll($option, $where='')
    {
        $queryJoin = "";
        $queryWhere = "";

        $roles = $option['roles'];
        $username = $option['username'];
        $production_travauxNiveau_BBC1 = $option['production_travauxNiveau_BBC1'];
        $production_travauxNiveau_BBC2 = $option['production_travauxNiveau_BBC2'];

        $adminId = (int)substr($username, 1);

        if (is_int($adminId)) {
            if (in_array('ROLE_CONSEILLER', $roles)) {
                $repo_structure = $this->_em->getRepository(Structure_::class);
                $structure_id = $repo_structure->findByConseillerId($adminId);
                $user_id_current = $structure_id['id'];

                // La structure qui doit voir ses dossiers doit être la structure lié à la fiche du bénéficiaire
                $queryJoin = "";
                $queryWhere = " AND (
                                    b.structure_rattachement_id =" . (int)$user_id_current . "
                                )";
            } elseif (in_array('ROLE_AUDITEUR', $roles)) {
                $queryJoin = "
                        LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id
                        LEFT JOIN partenaire_statut ps_dtd_aud ON (p_dtd_aud.partenaire_statut_id = ps_dtd_aud.id AND ps_dtd_aud.enabled = '1')
                        LEFT JOIN partenaire_identification pi_dtd_aud ON p_dtd_aud.partenaire_identification_id = pi_dtd_aud.id
                    ";

                $queryWhere = " AND ( " .
                    "p_dae.id = '" .$adminId."' OR
                         p_dan.id = '" .$adminId."' OR
                         p_dtd_aud.id = '" .$adminId."' AND dt.audit = '1' ".
                    ")";
            } elseif (in_array('ROLE_RENOVATEUR', $roles)) {
                $queryJoin = " LEFT JOIN partenaire_statut ps_dtd ON (p_dtd.partenaire_statut_id = ps_dtd.id AND ps_dtd.enabled = '1') ";

                $queryWhere = " AND ( " .
                    "dtd.renovateur_id = '" .$adminId."'".
                    ")";
            }  elseif (in_array('ROLE_EPCI', $roles)) {
                $repo_EPCI = $this->_em->getRepository(EPCI_::class);
                $EPCI_id = $repo_EPCI->findByContactId($adminId);
                $user_id_current = $EPCI_id['id'];

                $queryJoin = "
                    LEFT JOIN up_ville uv ON l.INSEE = uv.code_insee
                    LEFT JOIN orientation o ON uv.id = o.ville_id
                ";

                $queryWhere = " AND (
                                    o.EPCI_id = " . (int)$user_id_current . "
                                )
                ";
            } elseif (in_array('ROLE_TECHNIQUE', $roles)) {
                $queryWhere = " AND ( " .
                    "d.statut_id <> 15
                        AND (dt.fiche_technique_id IS NOT NULL OR dt.travaux_devis_id IS NOT NULL)" .
                    ")";
            }
        }

        // Inner query : on selectionne juste d.id, r.id avec GROUP BY pour
        // dedupliquer les combinaisons (titre / remboursement multiples).
        // On wrap dans un SELECT COUNT(*) afin que MariaDB ne materialise
        // pas toutes les lignes cote serveur+PHP : ~23k lignes -> 1 ligne.
        // Le LEFT JOIN instruction_ i a ete retire : il n'est reference
        // ni dans le WHERE ni dans le SELECT, c'etait du travail pur perdu.
        $innerQuery = "
            SELECT 1
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                LEFT JOIN date_CP dCP ON dCP.id = d.dateCP_id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id
                LEFT JOIN structure_identification si_dae ON s_dae.structure_identification_id = si_dae.id
                LEFT JOIN structure_conseiller sc_dae ON (dae.conseiller_id = sc_dae.id)
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id
                LEFT JOIN structure_identification si_dan ON s_dan.structure_identification_id = si_dan.id
                LEFT JOIN structure_conseiller sc_dan ON (dan.conseiller_id = sc_dan.id)
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id
                LEFT JOIN structure_identification si_dt ON s_dt.structure_identification_id = si_dt.id
                LEFT JOIN structure_conseiller sc_dt ON (dt.conseiller_id = sc_dt.id)
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id
                LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id
                LEFT JOIN titre t ON t.demande_id = d.id
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id
                LEFT JOIN remboursement_ r2 ON r2.demande_id = d.id AND r2.titre_id = t2.id
                LEFT JOIN remboursement_ r ON r.titre_id = t.id
                LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id " .
            $queryJoin .
            " WHERE (
                        t.id IS NULL
                        OR t.numero_operation NOT IN (" . $production_travauxNiveau_BBC1 . "," . $production_travauxNiveau_BBC2 . ")
                        OR t.numero_operation = " . $production_travauxNiveau_BBC2 . " AND r2.statut_id = " . Remboursement_statut::STATUS_22 . "
                        OR t.numero_operation = " . $production_travauxNiveau_BBC1 . " AND r.statut_id != " . Remboursement_statut::STATUS_22 . "
                        OR t.numero_operation = " . $production_travauxNiveau_BBC1 . " AND r.id IS NULL
            )" .
            $queryWhere . $where . "
            GROUP BY d.id, r.id
        ";

        $query = "SELECT COUNT(*) FROM (" . $innerQuery . ") AS sub";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return (int) $result->fetchOne();
    }


    /**
     *
     * @param array<string, mixed> $option
     * @param string               $orderBy
     * @param string               $orderType
     * @param int                  $start
     * @param int                  $length
     * @param string               $where
     *
     * @return array<int, mixed>
     * @throws \Doctrine\DBAL\Exception
     */
    public function findAllAjax(array $option, string $orderBy, string $orderType, int $start, int $length, string $where = ''): array
    {
        $queryJoin = '';
        $queryWhere = '';

        $roles = $option['roles'];
        $username = $option['username'];
        $production_travauxNiveau_BBC1 = (int)$option['production_travauxNiveau_BBC1'];
        $production_travauxNiveau_BBC2 = (int)$option['production_travauxNiveau_BBC2'];

        $adminId = (int)substr($username, 1);

        if (is_int($adminId)) {
            if (in_array('ROLE_CONSEILLER', $roles)) {
                $repo_structure = $this->_em->getRepository(Structure_::class);
                $structure_id = $repo_structure->findByConseillerId($adminId);
                $user_id_current = $structure_id['id'];

                // La structure qui doit voir ses dossiers doit être la structure lié à la fiche du bénéficiaire
                $queryJoin = '';
                $queryWhere = ' AND (b.structure_rattachement_id = ' . (int)$user_id_current . ')';
            } elseif (in_array('ROLE_AUDITEUR', $roles)) {
                $queryJoin = '
                        LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id
                        LEFT JOIN partenaire_statut ps_dtd_aud ON (p_dtd_aud.partenaire_statut_id = ps_dtd_aud.id AND ps_dtd_aud.enabled = \'1\')
                        LEFT JOIN partenaire_identification pi_dtd_aud ON p_dtd_aud.partenaire_identification_id = pi_dtd_aud.id
                    ';

                $queryWhere = ' AND (p_dae.id = ' . $adminId . ' OR p_dan.id = ' . $adminId . ' OR (p_dtd_aud.id = ' . $adminId . ' AND dt.audit = \'1\'))';
            } elseif (in_array('ROLE_RENOVATEUR', $roles)) {
                $queryJoin = ' LEFT JOIN partenaire_statut ps_dtd ON (p_dtd.partenaire_statut_id = ps_dtd.id AND ps_dtd.enabled = \'1\') ';

                $queryWhere = ' AND (dtd.renovateur_id = ' . $adminId . ')';
            } elseif (in_array('ROLE_EPCI', $roles)) {
                $repo_EPCI = $this->_em->getRepository(EPCI_::class);
                $EPCI_id = $repo_EPCI->findByContactId($adminId);
                $user_id_current = $EPCI_id['id'];

                $queryJoin = '
                    LEFT JOIN up_ville uv ON l.INSEE = uv.code_insee
                    LEFT JOIN orientation o ON uv.id = o.ville_id
                ';

                $queryWhere = ' AND (o.EPCI_id = ' . (int)$user_id_current . ')';
            } elseif (in_array('ROLE_TECHNIQUE', $roles)) {
                $queryWhere = ' AND (d.statut_id <> 15 AND (dt.fiche_technique_id IS NOT NULL OR dt.travaux_devis_id IS NOT NULL))';
            }
        }

        $query = "
            SELECT  d.id AS demandeId,
                    d.statut_description AS demandeStatutDescriptionFormatted,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . "
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . "
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . "
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN CASE SUBSTRING(dtd.niveau, 1, 1)
                                          WHEN '0' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE . "
                                          WHEN '1' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE . "
                                          WHEN '2' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE . "
                                          WHEN '3' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_CODE . "
                                          WHEN '4' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_CODE . "
                                          WHEN '6' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE . "
                                          WHEN '7' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE . "
                                          WHEN '8' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_CODE . "
                                          WHEN '9' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_CODE . "
                                          ELSE " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_A_DEFINIR_CODE . "
                                      END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . "
                    END AS demandeType,
                    dt.audit AS audit,
                    ( SELECT dAuditE.id 
                      FROM demande_ dAuditE
                      LEFT JOIN remboursement_ rAuditE
                          ON rAuditE.demande_id = dAuditE.id
                          AND rAuditE.statut_id = " . Remboursement_statut::STATUS_20 . "
                      WHERE dAuditE.logement_id = l.id 
                          AND dAuditE.id <> d.id 
                          AND (dAuditE.type = " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " OR dAuditE.type = " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . ")
                          AND dAuditE.statut_id <> " . Demande_statut::STATUS_15 . "
                          AND rAuditE.demande_id IS NULL
                      LIMIT 1
                    ) AS auditEId,
                    dtd.niveau AS travauxNiveauCheque,
                    dtd.is_bonification_aide AS demandeTravauxDevisIsBonificationAide,
                    CASE WHEN ('1 | sci' != b.type)
                        THEN CONCAT(' ', UPPER(b.nom), ' ', CONCAT(UCASE(LEFT(b.prenom, 1)), LCASE(SUBSTRING(b.prenom, 2))))
                        ELSE CONCAT_WS(' ', CONCAT(UCASE(LEFT(b.nom_SCI, 1)), LCASE(SUBSTRING(b.nom_SCI, 2))), '<br>', CONCAT(UPPER(b.nom), ' ', CONCAT(UCASE(LEFT(b.prenom, 1)), LCASE(SUBSTRING(b.prenom, 2)))))
                    END AS beneficiaireIdentifiant,
                    CONCAT(l.code_postal, ' ', l.ville) AS logement,
                    DATE_FORMAT(d.date_creation, '%Y/%m/%d') AS demandeDateCreation,
                    ds.slug AS demandeStatutSlug,
                    ds.description AS demandeStatutDescription,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN
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
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN
                            CASE WHEN ('' != sc_dae.nom)
                                THEN CONCAT(' ', si_dae.nom, ' - ', sc_dae.nom)
                                ELSE si_dae.nom
                            END                            
                        ELSE NULL
                    END AS structureConseiller,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pi_dae.raison_sociale
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pi_dan.raison_sociale
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pi_dan.raison_sociale
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN  pi_dtd.raison_sociale
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pi_dae.raison_sociale
                        ELSE NULL
                    END AS partenaire,
                    DATE_FORMAT(dCP.date_CP, '%Y/%m/%d') AS commissionDate,
                    d.statut_id AS demandeStatutId,
                    b.id AS beneficiaireId,
                    b.type AS beneficiaireType,
                    l.id AS logementId,
                    DATE_FORMAT(dRMH.date_RMH, '%Y/%m/%d') AS remboursementDate,
                    '' AS action,
                    0 AS countCommentaire,
                    rs.description As remboursementStatutDescription,
                    rs.slug AS remboursementStatutSlug,
                    r.id AS remboursementId,
                    r.statut_description AS remboursementStatutDescriptionFormatted
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                LEFT JOIN date_CP dCP ON dCP.id = d.dateCP_id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id
                LEFT JOIN structure_identification si_dae ON s_dae.structure_identification_id = si_dae.id
                LEFT JOIN structure_conseiller sc_dae ON dae.conseiller_id = sc_dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id
                LEFT JOIN structure_identification si_dan ON s_dan.structure_identification_id = si_dan.id
                LEFT JOIN structure_conseiller sc_dan ON dan.conseiller_id = sc_dan.id
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id    
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id
                LEFT JOIN structure_identification si_dt ON s_dt.structure_identification_id = si_dt.id
                LEFT JOIN structure_conseiller sc_dt ON dt.conseiller_id = sc_dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id
                LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id
                LEFT JOIN titre t ON t.demande_id = d.id
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id 
                LEFT JOIN remboursement_ r2 ON r2.demande_id = d.id AND r2.titre_id = t2.id
                LEFT JOIN remboursement_ r ON r.titre_id = t.id
                LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id " .
            $queryJoin .
            " WHERE (
                        t.id IS NULL
                        OR t.numero_operation NOT IN (".$production_travauxNiveau_BBC1.",".$production_travauxNiveau_BBC2.")
                        OR t.numero_operation = ".$production_travauxNiveau_BBC2." AND r2.statut_id = " .Remboursement_statut::STATUS_22."
                        OR t.numero_operation = ".$production_travauxNiveau_BBC1." AND r.statut_id != " .Remboursement_statut::STATUS_22."
                        OR t.numero_operation = ".$production_travauxNiveau_BBC1." AND r.id IS NULL
            )" .
            $queryWhere . $where . "
            GROUP BY d.id, r.id
            ORDER BY " . $orderBy . ' ' . $orderType . '
            LIMIT ' . $start . ',' . $length . '
        ';

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Compte les commentaires (historique_.action = 'commentaire') pour un
     * ensemble de demandes donne. Appele apres findAllAjax() pour eviter
     * d'exploser la requete principale via un LEFT JOIN historique_ +
     * COUNT(h.id) qui forcait un GROUP BY couteux sur ~23k lignes.
     *
     * @param int[] $demandeIds
     * @return array<int, int> Map [demandeId => countCommentaire]
     * @throws Exception
     */
    public function countCommentairesByDemandeIds(array $demandeIds): array
    {
        if (empty($demandeIds)) {
            return [];
        }

        $ids = array_map('intval', $demandeIds);
        $placeholders = implode(',', $ids);

        $query = "
            SELECT h.demande_id AS demande_id, COUNT(h.id) AS cnt
            FROM historique_ h
            WHERE h.demande_id IN (" . $placeholders . ")
              AND LOWER(h.action) = 'commentaire'
            GROUP BY h.demande_id
        ";

        $statement = $this->_em->getConnection()->prepare($query);
        $rows = $statement->executeQuery()->fetchAllAssociative();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['demande_id']] = (int) $row['cnt'];
        }
        return $map;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function findByType()
    {
        $query = "
            SELECT  d.id AS id,
                    d.type AS type,
                    d.statut_id AS statut,
                    d.dateCP_id AS dateCP,
                    ds.slug AS statutSlug,
                    b.nom AS nom,
                    b.prenom AS prenom,
                    l.code_postal AS codePostal,
                    l.ville AS ville,
                    i.id AS instructionId
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                LEFT JOIN instruction_ i ON d.id = i.demande_id
            WHERE d.type IN (" . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . "," . Demande_::DEMANDE_TRAVAUX_TYPE . "," . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . ")
                AND d.statut_id <> 15
                AND d.dateCP_id IS NULL
            ORDER BY d.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $beneficiaireId
     * @param $demandeType
     * @return array
     * @throws Exception
     */
    public function findByBeneficiaireAndType($beneficiaireId, $demandeType)
    {
        $andWhere = ' AND d.type = ' . $demandeType;
        $join = '';
        $andSelect = '';
        $andGroupBy = '';

        switch ($demandeType) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                $andSelect .= ', r.statut_id AS rStatutId';
                $join .= ' LEFT JOIN remboursement_ r ON r.demande_id = d.id';
                $andGroupBy .= ", r.id";
                break;
            case Demande_::DEMANDE_TRAVAUX_TYPE:
                $andSelect = ', r.id AS rId, r.statut_id AS rStatutId';
                $andSelect .= ', dtd.id AS dtdId, dtd.niveau AS dtdNiveau';

                $join = ' LEFT JOIN remboursement_ r ON r.demande_id = d.id ';
                $join .= ' LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                    LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id';

                $andGroupBy = ", r.id, dtd.id";
                break;
        }

        if (in_array($demandeType, [
            Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
            Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
        ])) {
            $andSelect .= ', ps_dae.enabled AS partenaireStatutEnabled';
            $join .= '
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_statut ps_dae ON p_dae.partenaire_statut_id = ps_dae.id
            ';
            $andGroupBy .= ", ps_dae.id";
        }

        $query = "
            SELECT  d.beneficiaire_id,
                    d.logement_id,
                    GROUP_CONCAT(d.id SEPARATOR '|') AS concat_demande_id,
                    d.dateCP_id,
                    d.statut_id ".
            $andSelect ."
            FROM demande_ d " .
            $join . "
            WHERE d.beneficiaire_id = " . $beneficiaireId . $andWhere . "
                AND d.statut_id <> 15
            GROUP BY d.logement_id, d.dateCP_id, d.statut_id ".
            $andGroupBy
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $beneficiaireId
     * @param $logementId
     * @param $andWhere
     * @return bool|mixed|string
     * @throws Exception
     */
    public function findCountByBeneficiaireAndLogement($beneficiaireId, $logementId = null, $andWhere = '')
    {
        if ($logementId) {
            $andWhere .= ' AND d.logement_id = ' . $logementId. ' ';
        }

        $query = "
            SELECT COUNT(d.id) AS nombreDemandes
            FROM demande_ d
            WHERE d.beneficiaire_id = " . $beneficiaireId
                . $andWhere;
        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchOne();
    }

    /**
     * @param $beneficiaireId
     * @param $logementId
     * @return bool|mixed|string|Demande_[]
     * @throws Exception
     */
    public function findCountByBeneficiaireAndLogementForEditDenied($beneficiaireId, $logementId = null)
    {
        $andWhere = " AND d.dateCP_id IS NOT NULL";

        return self::findCountByBeneficiaireAndLogement($beneficiaireId, $logementId, $andWhere);
    }

    /**
     * @param $beneficiaireId
     * @param $logementId
     * @return bool|mixed|string|Demande_[]
     * @throws Exception
     */
    public function findCountByBeneficiaireAndLogementForDeleteDenied($beneficiaireId, $logementId = null)
    {
        return self::findCountByBeneficiaireAndLogement($beneficiaireId, $logementId, '');
    }

    /**
     * @param $beneficiaireId
     * @param $productionTravauxNiveauBBC1
     * @param $productionTravauxNiveauBBC2
     * @return array
     * @throws Exception
     */
    public function findByBeneficiaire(
        $beneficiaireId,
        $productionTravauxNiveauBBC1,
        $productionTravauxNiveauBBC2
    ) {
        $query = "
            SELECT  d.date_creation AS dateCreation,
                    d.id AS demandeId,
                    d.type AS demandeType,
                    d.statut_id AS demandeStatut,
                    b.id AS beneficiaireId,
                    b.nom AS beneficiaireNom,
                    b.prenom AS beneficiairePrenom,
                    b.type AS beneficiaireType,
                    l.id AS logementId,
                    l.nom AS logementNom,
                    l.situation as logementSituation,
                    ds.description AS demandeStatutDescription,
                    ds.slug AS demandeStatutSlug,
                    ds.color AS statutColor,
                    dtd.id AS devisId,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.justificatif_propriete_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.justificatif_propriete_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.justificatif_propriete_alt
                        ELSE NULL
                    END AS documentJPAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.piece_complement_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.piece_complement_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.piece_complement_alt
                        ELSE NULL
                    END AS documentKBISAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.avis_imposition_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.avis_imposition_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.avis_imposition_alt
                        ELSE NULL
                    END AS documentAIAlt,
                    CASE
                        WHEN r.statut_id IS NULL THEN ds.slug
                        WHEN r.statut_id IN(" . Remboursement_statut::STATUS_20 . ", " . Remboursement_statut::STATUS_22 . ") THEN rs.slug
                        ELSE 'Remboursement en cours'
                    END AS statutSlug,
                    CASE
                        WHEN r.statut_id IS NULL THEN d.statut_description
                        WHEN r.statut_id IN(" . Remboursement_statut::STATUS_20 . ", " . Remboursement_statut::STATUS_22 . ") THEN r.statut_description
                        ELSE 'Votre remboursement est actuellement en cours de traitement'
                    END AS statutDescriptionFormatted
            FROM demande_ d                
                INNER JOIN beneficiaire b ON d.beneficiaire_id = b.id
                    AND b.id = " . $beneficiaireId . "
                INNER JOIN demande_statut ds ON d.statut_id = ds.id
                INNER JOIN logement l ON d.logement_id = l.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_energie_id = dan.id
                LEFT JOIN titre t ON t.demande_id = d.id
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id
                LEFT JOIN remboursement_ r2 ON r2.demande_id = d.id AND r2.titre_id = t2.id
                LEFT JOIN remboursement_ r ON r.titre_id = t.id
                LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id
            WHERE (
                    t.id IS NULL
                    OR t.numero_operation NOT IN (" . $productionTravauxNiveauBBC1 . "," . $productionTravauxNiveauBBC2 . ")
                    OR t.numero_operation = " . $productionTravauxNiveauBBC2 . " AND r2.statut_id = " . Remboursement_statut::STATUS_22 . "
                    OR t.numero_operation = " . $productionTravauxNiveauBBC1 . " AND r.statut_id != " . Remboursement_statut::STATUS_22 . "
                    OR t.numero_operation = " . $productionTravauxNiveauBBC1 . " AND r.id IS NULL
            )
            GROUP BY d.id, r.id
            ORDER BY d.date_creation DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $roles
     * @param null $username
     * @param array $arrayDemandeStatutToExclude
     * @return array
     * @throws Exception
     */
    public function findAllDevis(
        $roles,
        $username = null,
        $arrayDemandeStatutToExclude = array(Demande_statut::STATUS_15)
    ) {
        $queryJoin = "";

        $userId = (int)substr($username, 1);

        if (is_int($userId)) {
            if (in_array('ROLE_CONSEILLER', $roles)) {
                $repo_structure = $this->_em->getRepository(Structure_::class);
                $structure_id = $repo_structure->findByConseillerId($userId);
                $user_id_current = $structure_id['id'];

                $queryJoin = "
                    INNER JOIN structure_ s ON b.structure_rattachement_id = s.id
                    INNER JOIN structure_statut ss ON (s.structure_statut_id = ss.id AND ss.enabled = '1')
                    WHERE b.structure_rattachement_id = '" .$user_id_current."'
                ";
            } elseif (in_array('ROLE_AUDITEUR', $roles)) {
                $queryJoin = "
                    INNER JOIN partenaire_ p ON dtd.auditeur_id = p.id
                    INNER JOIN partenaire_statut ps ON (p.partenaire_statut_id = ps.id AND ps.enabled = '1')
                    WHERE p.id = '" .$userId."' 
                        AND dt.audit = '1'
                ";
            }
        }

        $query = "
            SELECT  d.id,
                    d.logement_id AS logementId,
                    d.dateCP_id AS dateCPId,
                    d.statut_id AS statutId,
                    b.nom,
                    b.prenom,
                    l.code_postal AS codePostal,
                    l.ville,
                    ds.slug AS statutSlug,
                    dt.travaux_devis_id AS devisId,
                    dt.fiche_technique_id AS ficheTechniqueId,
                    dtd.statut_instruction AS statutInstruction,
                    ft.statut_ficheTechnique AS statutFicheTechnique,
                    ft.is_validation_conseiller AS isValidationConseiller
            FROM demande_ d
                INNER JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id 
                    AND d.statut_id NOT IN (" .implode(',', $arrayDemandeStatutToExclude). ")
                INNER JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
                LEFT JOIN fiche_technique ft ON dt.fiche_technique_id = ft.id " .
                $queryJoin . "
            ORDER BY d.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $demandeId
     * @return array|false
     * @throws Exception
     */
    public function findDataFicheLiaison($demandeId)
    {
        $query = "
            SELECT  sc.nom AS nomStructureC,
                    sc.prenom AS prenomStructureC,
                    si.nom AS nomStructureI,
                    d.id AS numeroDossierD,
                    d.date_creation AS dateInscription,
                    dcp.date_CP AS dateCp,
                    b.nom AS nomBenef,
                    b.prenom AS prenomBenef,
                    b.numero_rue AS numRueBenef,
                    b.nom_rue AS nomRueBenef,
                    b.code_postal AS codePBenef,
                    b.ville AS villeBenef,
                    bptz.nom AS ecoPTZBanque,
                    bautre.nom AS autrePretBanque, 
                    dt.nb_pers_foyer AS nbPersFoyerBenef,
                    dt.audit as statutAudit,
                    dtd.aide_anah AS aideAnah, 
                    dtd.credit_impot AS creditImpot,
                    dtd.type_ma_prime_renov_nom AS typeMaPrimeRenov,
                    dtd.aide_region AS aideRegion,
                    dtd.aide_departement AS aideDepartement,
                    dtd.aide_departement_origine AS aideDepartementOrigine,
                    dtd.aide_intercommunalite AS aideIntercommunalite,
                    dtd.aide_intercommunalite_origine AS aideIntercommunaliteOrigine,
                    dtd.CEE AS CEE,
                    dtd.ecoPTZ AS ecoPTZ,
                    dtd.fonds_propres AS fondPropres,
                    dtd.autre_aide AS autreAide,
                    dtd.autre_pret AS autrePret,
                    dtd.autre_aide_origine AS autreAideOrigine,
                    dtd.total_devis AS totalDevis,
                    dtd.aide_habiter_mieux AS aideHabiterMieux,
                    dtd.type_ma_prime_renov_serenite_nom AS typeMaPrimeRenovSerenite,
                    dtd.total_plan AS financement,
                    SUBSTRING(dtd.niveau,5) AS typeDemande,
                    ftfi.surface_habitable AS surfaceHabitable,
                    ftfi.CEP_ AS cepDepart,
                    ftfp.CEP_ AS cepArrivee,
                    l.description_projet AS travauxSouhaite,
                    l.code_postal AS cpAdresseTravaux,
                    l.ville AS villeAdresseTravaux,
                    l.numero_rue AS numRueAdresseTravaux,
                    l.adresse AS adresseTravaux,
                    l.complement_rue AS complementRueTravaux,
                    l.complement_1 AS complement1Travaux,
                    l.complement_2 AS complement2Travaux,
                    pi.raison_sociale as renovateur,
                    pa.code_postal AS CP,
                    pa.ville AS ville                    
            FROM demande_ d
                INNER JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                LEFT JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
                LEFT JOIN date_CP dcp ON dcp.id = d.dateCP_id
                LEFT JOIN partenaire_ p ON p.id = dtd.renovateur_id
                LEFT JOIN partenaire_identification pi ON pi.id = p.partenaire_identification_id
                LEFT JOIN partenaire_adresse pa ON pa.id = p.partenaire_adresse_id
                LEFT JOIN banque_ bptz ON bptz.id = dtd.EcoPTZ_banque
                LEFT JOIN banque_ bautre ON bautre.id = dtd.autre_pret_banque
                LEFT JOIN fiche_technique ft ON ft.id = dt.fiche_technique_id
                LEFT JOIN fiche_technique_field ftfi ON ftfi.id = ft.fiche_technique_initial_id
                LEFT JOIN fiche_technique_field ftfp ON ftfp.id = ft.fiche_technique_prescription_id
                LEFT JOIN structure_ s ON dt.structure_id = s.id
                LEFT JOIN structure_identification si ON s.structure_identification_id = si.id
                LEFT JOIN structure_conseiller sc ON dt.conseiller_id = sc.id
                LEFT JOIN structure__structure_conseiller ssc ON ssc.structure__id = s.id 
                    AND ssc.structure_conseiller_id = sc.id
            WHERE d.id = " . $demandeId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $demandeType
     * @param $niveau
     * @return array
     * @throws Exception
     */
    public function findProductionByTypeNiveau($demandeType, $niveau = null): array
    {
        $demande_statut = Demande_statut::STATUS_12;

        $queryNiveau ='';

        if ('0' == $niveau) $queryNiveau = ' AND dtd.niveau = \'' . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_1_VALUE . '\'';
        elseif ('1' == $niveau) $queryNiveau = ' AND dtd.niveau = \'' . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_VALUE . '\'';
        elseif ('2' == $niveau) $queryNiveau = ' AND dtd.niveau = \'' . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . '\'';
        elseif ('3' == $niveau) $queryNiveau = ' AND dtd.niveau = \'' . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . '\'';
        elseif ('4' == $niveau) $queryNiveau = ' AND dtd.niveau = \'' . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . '\'';
        elseif ('6' == $niveau) $queryNiveau = ' AND dtd.niveau = \'' . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE . '\'';
        elseif ('7' == $niveau) $queryNiveau = ' AND dtd.niveau = \'' . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE . '\'';
        elseif ('8' == $niveau) $queryNiveau = ' AND dtd.niveau = \'' . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . '\'';
        elseif ('9' == $niveau) $queryNiveau = ' AND dtd.niveau = \'' . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . '\'';

        $query = "
            SELECT  d.id AS demandeId,
                    d.type AS demandeType,
                    dtd.niveau AS typeTravauxNiveau,
                    dtd.logement_id AS travauxDevisLogementId,
                    dtd.is_bonification_aide AS demandeTravauxDevisIsBonificationAide,
                    b.id AS beneficiaireId,
                    CASE WHEN ('1 | sci' != b.type)
                        THEN b.nom
                        ELSE b.nom_SCI
                    END AS beneficiaireNom,
                    CASE WHEN ('1 | sci' != b.type)
                        THEN b.prenom
                        ELSE CONCAT(b.nom, ' ', b.prenom)
                    END AS beneficiairePrenom,
                    b.civilite AS beneficiaireCivilite,
                    b.numero_rue AS beneficiaireNumeroRue,
                    b.nom_rue AS beneficiaireNomRue,
                    b.code_postal AS beneficiaireCodePostal,
                    b.ville AS beneficiaireVille,
                    b.complement_numero_rue AS beneficiaireComplementNumeroRue,
                    b.complement_1 AS beneficiaireComplement1,
                    b.complement_2 AS beneficiaireComplement2,
                    l.code_postal AS logementCodePostal,
                    l.ville AS logementVille,
                    l.numero_rue AS logementNumeroRue,
                    l.adresse AS logementAdresse,
                    l.complement_rue AS logementComplementRue,
                    l.complement_1 AS logementComplement1,
                    l.complement_2 AS logementComplement2
            FROM demande_ d
            INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
            INNER JOIN logement l ON l.id = d.logement_id
            LEFT JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
            LEFT JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
            WHERE d.statut_id = " . $demande_statut
            . " AND d.type = " . $demandeType
            . $queryNiveau
        ;

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
    public function findForAttenteProduction()
    {
        $query = "
            SELECT  d.id AS demandeId,
                    d.type AS demandeType,
                    d.statut_id AS demandeStatut,
                    b.type AS beneficiaireType,
                    b.email AS beneficiaireEmail,
                    l.situation AS logementSituation,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.justificatif_propriete_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.justificatif_propriete_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.justificatif_propriete_alt
                        ELSE NULL
                    END AS documentJPAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.piece_complement_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.piece_complement_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.piece_complement_alt
                        ELSE NULL
                    END AS documentKBISAlt,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.avis_imposition_alt
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dt.avis_imposition_alt
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.avis_imposition_alt
                        ELSE NULL
                    END AS documentAIAlt
            FROM demande_ AS d
                INNER JOIN  date_CP AS dateCP ON d.dateCP_id = dateCP.id 
                    AND dateCP.enabled = 1 
                    AND dateCP.date_CP <= CURDATE()
                INNER JOIN  demande_statut ds ON d.statut_id = ds.id 
                    AND ds.statut = " . Demande_statut::STATUS_11 ."
                INNER JOIN  beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN  logement l ON l.id = d.logement_id
                LEFT JOIN  demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN  demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN  demande_travaux dt ON d.demande_travaux_id = dt.id
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $dateCPId
     * @param $datePassageMontantAuditEnergie
     * @param $datePassageMontantAuditRegion
     * @return array
     * @throws Exception
     */
    public function findByDateCp($dateCPId, $datePassageMontantAuditEnergie, $datePassageMontantAuditRegion)
    {
        $query = "
            SELECT  DISTINCT d.id AS demandeId,
                    d.date_creation AS demandeDateCreation,
                    b.nom AS beneficiaireNom,
                    b.prenom AS beneficiairePrenom,
                    b.nom_SCI AS beneficiaireNomSCI,
                    l.code_postal AS logementCodePostal,
                    l.ville AS logementVille,
                    dtd.is_bonification_aide AS demandeTravauxDevisIsBonificationAide,
                    CASE    d.type
                            WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::$demandeType[Demande_::DEMANDE_AUDIT_ENERGIE_TYPE]  . "'
                            WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN '" . Demande_::$demandeType[Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE] . "'
                            WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::$demandeType[Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE] . "'
                            WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN CASE substr(dtd.niveau,1,1)
                                WHEN 0 THEN 'Chèque travaux niveau I'
                                WHEN 1 THEN 'Chèque travaux niveau II'
                                WHEN 2 THEN 'Chèque travaux niveau II option rénovateur'
                                WHEN 3 THEN 'Chèque travaux BBC'
                                WHEN 4 THEN 'Chèque travaux BBC biosourcé'
                                WHEN 6 THEN 'Sortie de passoire'
                                WHEN 7 THEN 'Première étape BBC avec RGE'
                                WHEN 8 THEN 'Première étape BBC avec Rénovateur'
                                WHEN 9 THEN 'Rénovation globale BBC'
                            END
                            WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN '" . Demande_::$demandeType[Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE]  . "'
                    END AS demandeType,
                    CASE    d.type
                            WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(dCP.date_CP < '" . $datePassageMontantAuditEnergie . "', '" . TitreService::MONTANT_HUIT_CENT_AVEC_VIRGULE . "', '" . TitreService::MONTANT_CINQ_CENT_AVEC_VIRGULE . "')
                            WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN '" . TitreService::MONTANT_QUATRE_CENT_AVEC_VIRGULE . "'
                            WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN '" . TitreService::MONTANT_DEUX_CENT_AVEC_VIRGULE . "'
                            WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dtd.aide_region
                            WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(dCP.date_CP < '" . $datePassageMontantAuditRegion . "', '" . TitreService::MONTANT_HUIT_CENT_AVEC_VIRGULE . "', '" . TitreService::MONTANT_SIX_CENT_AVEC_VIRGULE . "')
                    END AS montant,
                    CASE    d.type
                            WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(pi_dae.raison_sociale IS NULL,'* En cours de choix',pi_dae.raison_sociale)
                            WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN IF(pi_dan.raison_sociale IS NULL,'* En cours de choix',pi_dan.raison_sociale)
                            WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN IF(pi_dan.raison_sociale IS NULL,'* En cours de choix',pi_dan.raison_sociale)
                            WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN IF(pi_dtd.raison_sociale IS NULL,'* En cours de choix',pi_dtd.raison_sociale)
                            WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(pi_dae.raison_sociale IS NULL,'* En cours de choix',pi_dae.raison_sociale)
                    END AS professionnel
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                INNER JOIN date_CP dCP ON dCP.id = d.dateCP_id
                LEFT JOIN instruction_ i ON i.demande_id = d.id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id
                LEFT JOIN structure_identification si_dae ON s_dae.structure_identification_id = si_dae.id
                LEFT JOIN structure_conseiller sc_dae ON (dae.conseiller_id = sc_dae.id)
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id
                LEFT JOIN structure_identification si_dan ON s_dan.structure_identification_id = si_dan.id
                LEFT JOIN structure_conseiller sc_dan ON (dan.conseiller_id = sc_dan.id)
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id
                LEFT JOIN structure_identification si_dt ON s_dt.structure_identification_id = si_dt.id
                LEFT JOIN structure_conseiller sc_dt ON (dt.conseiller_id = sc_dt.id)
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id
                LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id
                WHERE d.dateCP_id=" . $dateCPId . "
            ORDER BY d.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $option
     * @param string $whereFormFilter
     * @return \Doctrine\DBAL\Driver\Statement|\Doctrine\DBAL\Statement
     * @throws Exception
     */
    public function findDataExportStatement(
        $option,
        $whereFormFilter = ''
    ) {
        $roles = $option['roles'];
        $username = $option['username'];
        $production_travauxNiveau_BBC1 = $option['production_travauxNiveau_BBC1'];
        $production_travauxNiveau_BBC2 = $option['production_travauxNiveau_BBC2'];

        $queryJoin = "";
        $queryWhere = "";

        $adminId = substr($username, 1);

        if (is_numeric($adminId)) {
            $adminId = (int)$adminId;

            if (in_array('ROLE_CONSEILLER', $roles)) {
                $repo_structure = $this->_em->getRepository(Structure_::class);
                $structure_id = $repo_structure->findByConseillerId($adminId);
                $user_id_current = $structure_id['id'];

                // La structure qui doit voir ses dossiers doit être la structure lié à la fiche du bénéficiaire
                $queryJoin = "";
                $queryWhere = " 
                    AND (
                        b.structure_rattachement_id =" . (int)$user_id_current . "
                    )
                ";
            } elseif (in_array('ROLE_AUDITEUR', $roles)) {
                $queryJoin = "
                    LEFT JOIN partenaire_ p_dtd_aud ON dtd.auditeur_id = p_dtd_aud.id
                    LEFT JOIN partenaire_statut ps_dtd_aud ON (p_dtd_aud.partenaire_statut_id = ps_dtd_aud.id AND ps_dtd_aud.enabled = '1')
                    LEFT JOIN partenaire_identification pi_dtd_aud ON p_dtd_aud.partenaire_identification_id = pi_dtd_aud.id
                ";

                $queryWhere = "
                    AND (
                        p_dae.id = '" . $adminId . "' 
                        OR p_dan.id = '" . $adminId . "' 
                        OR p_dtd_aud.id = '" . $adminId . "' 
                        AND dt.audit = '1'
                    )
                ";
            } elseif (in_array('ROLE_RENOVATEUR', $roles)) {
                $queryJoin = " LEFT JOIN partenaire_statut ps_dtd ON (p_dtd.partenaire_statut_id = ps_dtd.id AND ps_dtd.enabled = '1') ";

                $queryWhere = " AND (
                                    dtd.renovateur_id = '" .$adminId."'
                                )
                ";
            } elseif (in_array('ROLE_EPCI', $roles)) {
                $repo_EPCI = $this->_em->getRepository(EPCI_::class);
                $EPCI_id = $repo_EPCI->findByContactId($adminId);
                $user_id_current = $EPCI_id['id'];

                $queryWhere = " AND (
                                    o.EPCI_id = " . (int)$user_id_current . "
                                )
                ";
            }
        }

        $query = "
            SELECT  DISTINCT d.id AS demandeId,
                    r.id AS remboursementId,
                    r2.id AS remboursementIdSuppl,
                    dRMH.date_RMH as remboursementDateRMH,
                    rs.slug AS remboursementStatutSlug,
                    r.date_instruction_instructeur AS remboursementDateInstruction,
                    r2.date_instruction_instructeur AS remboursementDateInstructionSuppl,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::$demandeType[Demande_::DEMANDE_AUDIT_ENERGIE_TYPE] . "'
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN '" . Demande_::$demandeType[Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE] . "'
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::$demandeType[Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE] . "'
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN CASE dtd.niveau
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_1_VALUE . "' THEN 'Chèque travaux niveau I'
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_VALUE . "' THEN 'Chèque travaux niveau II'
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE. "' THEN 'Chèque travaux BBC'
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE . "' THEN 'Chèque Travaux - Sortie de passoire'
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE . "' THEN 'Chèque Travaux - Première étape BBC avec RGE'
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . "' THEN 'Chèque travaux niveau II option rénovateur'
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE. "' THEN 'Chèque travaux BBC biosourcé'
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE. "' THEN 'Chèque Travaux - Première étape BBC avec Rénovateur'
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE. "' THEN 'Chèque Travaux - Rénovation globale BBC'
                            ELSE 'Travaux'
                        END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN '" . Demande_::$demandeType[Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE] . "'
                    END AS demandeType,
                    b.nom AS beneficiaireNom,
                    b.prenom AS beneficiairePrenom,
                    l.ville AS logementVille,
                    l.code_postal AS logementCodePostal,
                    d.date_creation AS demandeDate,
                    d.type_menage AS typeMenage,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(sc_dae.nom IS NULL,'',sc_dae.nom)
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN IF(sc_dan.nom IS NULL,'',sc_dan.nom)
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN IF(sc_dan.nom IS NULL,'',sc_dan.nom)
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN IF(sc_dt.nom IS NULL,'',sc_dt.nom)
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(sc_dae.nom IS NULL,'',sc_dae.nom)
                    END  AS conseiller,
                    ds.slug AS demandeStatutSlug,
                    i.date_creation AS instructionDateCreation,
                    dCP.date_CP AS commissionDate,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN ''
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN 'oui'
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN 'oui'
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN IF(dt.audit=1,'oui','non')
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN ''
                    END AS audit,
                    CASE dae.carnet_numerique
                        WHEN 0 THEN 'non'
                        WHEN 1 THEN 'oui'
                    END AS carnet_numerique,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(si_dae.nom IS NULL,'',si_dae.nom)			
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN IF(si_dan.nom IS NULL,'',si_dan.nom)
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN IF(si_dan.nom IS NULL,'',si_dan.nom)
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN IF(si_dt.nom IS NULL,'',si_dt.nom)
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(si_dae.nom IS NULL,'',si_dae.nom)		
                    END  AS structure,                 
                    l.situation AS situation,
                    b.code_postal AS code_postal,
                    b.ville AS ville,
                    IF(dt.revenu_foyer, dt.revenu_foyer, IF(dae.revenu_foyer, dae.revenu_foyer, b.revenu_fiscal_ref)) AS revenu_fiscal_ref,
                    b.situation_famille AS situation_famille,
                    IF(dt.nb_pers_foyer, dt.nb_pers_foyer, IF(dae.nb_pers_foyer, dae.nb_pers_foyer, b.nb_pers_foyer)) AS nb_pers_foyer,
                    E.nom AS epci,
                    substr(l.code_postal,1,2) AS departement,
                    'tranche' AS tranche,
                    dtd.total_devis AS total_devis,
                    dtd.aide_anah AS aide_anah,
                    dtd.aide_habiter_mieux AS aide_habiter_mieux,
                    dtd.type_ma_prime_renov_serenite_nom AS type_ma_prime_renov_serenite,
                    dtd.credit_impot AS credit_impot,
                    dtd.type_ma_prime_renov_nom AS type_ma_prime_renov,
                    t.id,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN t.valeur_titre
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN t.valeur_titre
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN t.valeur_titre
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dtd.aide_region
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN t.valeur_titre
                    END AS aide_region,
                    dtd.CEE AS CEE,
                    dtd.EcoPTZ AS EcoPTZ,
                    dtd.fonds_propres AS fonds_propres,
                    dtd.aide_departement AS aide_departement,
                    dtd.aide_departement_origine AS aide_departement_origine,
                    dtd.aide_intercommunalite AS aide_intercommunalite,
                    dtd.aide_intercommunalite_origine AS aide_intercommunalite_origine,
                    dtd.autre_aide AS autre_aide,
                    dtd.autre_pret AS autre_pret,
                    b_ecoPTZ.nom AS EcoPTZ_banque,
                    b_autrePret.nom AS autre_pret_banque,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(pi_dae.raison_sociale IS NULL,'* En cours de choix',pi_dae.raison_sociale)
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN IF(pi_dan.raison_sociale IS NULL,'* En cours de choix',pi_dan.raison_sociale)
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN IF(pi_dan.raison_sociale IS NULL,'* En cours de choix',pi_dan.raison_sociale)
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN IF(pi_dtd.raison_sociale IS NULL,'* En cours de choix',pi_dtd.raison_sociale)
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(pi_dae.raison_sociale IS NULL,'* En cours de choix',pi_dae.raison_sociale)
                    END   AS professionnel,
                    b.email AS email,
                    b.tel_1 AS tel_1,
                    b.tel_2 AS tel_2,
                    l.annee_construction AS annee_construction,
                    l.type_habitation AS typeHabitation,
                    l.description_projet AS description_projet,
                    ft_field_initial.surface_habitable AS surface_habitable,
                    ft_field_initial.CEP_ AS CEP_depart,
                    ft_field_bbc.CEP_ AS CEP_bbc,
                    ft_field_prescription.CEP_ AS CEP_prescription,
                    ft_field_prescription.CEP_gain AS CEP_gain,
                    ftfr.CEP_ AS CEP_fin_de_chantier,
                    ft_field_initial.CEP_GES AS GES_depart,
                    ft_field_prescription.CEP_GES AS GES_prescription,
                    ftfr.CEP_GES AS GES_fin_de_chantier,
                    ft_field_initial.CEP_etiquette_energetique AS etiquetteEnergetique_depart,
                    ft_field_prescription.CEP_etiquette_energetique AS etiquetteEnergetique_prescription,
                    ftfr.CEP_etiquette_energetique AS etiquetteEnergetique_fin_de_chantier,
                    ft_field_initial.CEP_ubat AS CEP_ubatdepart,
                    ft_field_bbc.CEP_ubat AS CEP_ubatbbc,
                    ft_field_prescription.CEP_ubat AS CEP_ubatprescription,
                    ftfr.CEP_ubat AS ubat_fin_de_chantier,
                    ftfr.CEP_Q4Pa_surf AS Q4_fin_de_chantier,
                    ftfr.information_controleur_chantier AS information_controleur_chantier,
                    CASE ftfr.is_valoriser_renovation
                        WHEN 0 THEN 'non'
                        WHEN 1 THEN 'oui'
                    END AS is_valoriser_renovation,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN IF(raei.montant_facture IS NULL,'',raei.montant_facture)
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN IF(rani.montant_facture IS NULL,'',rani.montant_facture)
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN IF(rani.montant_facture IS NULL,'',rani.montant_facture)
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN IF(rti.montant_facture IS NULL,'',rti.montant_facture)
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN IF(raei.montant_facture IS NULL,'',raei.montant_facture)
                    END   AS montantFacture
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                LEFT JOIN structure_ ss ON b.structure_id = ss.id
                LEFT JOIN structure_identification ssi_ ON ss.structure_identification_id = ssi_.id
                LEFT JOIN partenaire_ p_a ON b.auditeur_id = p_a.id
                LEFT JOIN partenaire_identification pi_a ON p_a.partenaire_identification_id = pi_a.id
                LEFT JOIN partenaire_ p_r ON b.renovateur_id = p_r.id
                LEFT JOIN partenaire_identification pi_r ON p_r.partenaire_identification_id = pi_r.id
                LEFT JOIN up_ville v ON v.id = l.ville_id
                LEFT JOIN orientation o ON o.ville_id = v.id
                LEFT JOIN EPCI_ E ON E.id = o.EPCI_id
                LEFT JOIN date_CP dCP ON dCP.id = d.dateCP_id
                LEFT JOIN instruction_ i ON i.demande_id = d.id
                LEFT JOIN instruction_audit_energie iae ON iae.id = i.instruction_audit_energie_id
                LEFT JOIN instruction_travaux it ON it.id = i.instruction_travaux_id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id
                LEFT JOIN structure_identification si_dae ON s_dae.structure_identification_id = si_dae.id
                LEFT JOIN structure_conseiller sc_dae ON dae.conseiller_id = sc_dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id
                LEFT JOIN structure_identification si_dan ON s_dan.structure_identification_id = si_dan.id
                LEFT JOIN structure_conseiller sc_dan ON dan.conseiller_id = sc_dan.id
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id    
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN fiche_technique ft ON ft.id = dt.fiche_technique_id
                LEFT JOIN fiche_technique_field ft_field_initial ON ft_field_initial.id = ft.fiche_technique_initial_id
                LEFT JOIN fiche_technique_field ft_field_bbc ON ft_field_bbc.id = ft.fiche_technique_bbc_id
                LEFT JOIN fiche_technique_field ft_field_prescription ON ft_field_prescription.id = ft.fiche_technique_prescription_id
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id
                LEFT JOIN structure_identification si_dt ON s_dt.structure_identification_id = si_dt.id
                LEFT JOIN structure_conseiller sc_dt ON dt.conseiller_id = sc_dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN banque_ ba ON b.financeur_id = ba.id
                LEFT JOIN banque_ b_ecoPTZ ON b_ecoPTZ.id = dtd.EcoPTZ_banque
                LEFT JOIN banque_ b_autrePret ON b_autrePret.id = dtd.autre_pret_banque
                LEFT JOIN partenaire_ p_dtd ON dtd.renovateur_id = p_dtd.id
                LEFT JOIN partenaire_identification pi_dtd ON p_dtd.partenaire_identification_id = pi_dtd.id
                LEFT JOIN titre t ON t.demande_id = d.id
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id
                LEFT JOIN remboursement_ r2 ON r2.demande_id = d.id AND r2.titre_id = t2.id
                LEFT JOIN remboursement_ r ON r.titre_id = t.id
                LEFT JOIN remboursement_statut rs ON r.statut_id = rs.id
                LEFT JOIN remboursement_travaux rt ON rt.id = r.remboursement_travaux_id
                LEFT JOIN remboursement_travaux_instruction rti ON rti.id = rt.instruction_id
                LEFT JOIN remboursement_audit_energie rae ON rae.id = r.remboursement_audit_energie_id
                LEFT JOIN remboursement_audit_energie_instruction raei ON raei.id = rae.instruction_id
                LEFT JOIN remboursement_audit_numerique ran ON ran.id = r.remboursement_audit_numerique_id
                LEFT JOIN remboursement_audit_numerique_instruction rani ON rani.id = ran.instruction_id                
                LEFT JOIN fiche_technique ftr ON ftr.id = rt.fiche_technique_id
                LEFT JOIN fiche_technique_field ftfr ON ftfr.id = ftr.fiche_technique_fin_chantier_id
                LEFT JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id " .
            $queryJoin .
            " WHERE (
                        t.id IS NULL
                        OR t.numero_operation NOT IN (".$production_travauxNiveau_BBC1.",".$production_travauxNiveau_BBC2.")
                        OR t.numero_operation = ".$production_travauxNiveau_BBC2." AND r2.statut_id = " .Remboursement_statut::STATUS_22."
                        OR t.numero_operation = ".$production_travauxNiveau_BBC1." AND r.statut_id != " .Remboursement_statut::STATUS_22."
                        OR t.numero_operation = ".$production_travauxNiveau_BBC1." AND r.id IS NULL
            )" .
            $queryWhere .  $whereFormFilter . "
            GROUP BY d.id, r.id, r2.id, t.id
            ORDER BY d.id DESC, r.id, r2.id, t.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $statement->executeQuery();

        return $statement;
    }

    /**
     * @param $dateUSBegin
     * @param $dateUSEnd
     * @return array
     * @throws Exception
     */
    public function findDataExportADEMEA03($dateUSBegin, $dateUSEnd)
    {

        $query = "
            SELECT  DISTINCT d.id AS numeroDossier,
                    DATE_FORMAT(dRMH.date_RMH, '%Y-%m-%d') AS dateActe,
                    CASE SUBSTRING(l.situation, 1, 1)
                        WHEN 0 THEN 'PO'
                        WHEN 1 THEN 'PB'
                        ELSE ''
                    END AS typePublic,
                    l.code_postal AS logementCodePostal,
                    l.ville AS logementCommune,
                    SUBSTR(CONCAT_WS(' ', IF(l.numero_rue, l.numero_rue , null), UPPER(SUBSTRING(l.complement_rue,1,1)), l.adresse, l.complement_1, l.complement_2), 1, 60) AS logementAdresse,
                    IF(uv.code_insee_parent IS NULL, uv.code_insee, uv.code_insee_parent) AS codeInsee,
                    b.nom AS beneficiaireNom,
                    b.prenom AS beneficiairePrenom,
                    IF(b.tel_1 IS NULL, b.tel_2, b.tel_1) AS beneficiaireTel,
                    IF(b.email IS NULL AND sc.email IS NOT NULL, sc.email, b.email) AS email,
                    b.revenu_fiscal_ref AS beneficiaireRevenuFiscalRef,
                    b.nb_pers_foyer AS beneficiaireNbPersFoyer,
                    dae.nb_pers_foyer AS demandeNbPersFoyer,
                    dae.revenu_foyer AS demandeRevenuFiscalRef
            FROM demande_ d
                INNER JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN remboursement_ r ON r.demande_id = d.id
                INNER JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id
                INNER JOIN up_ville uv ON uv.code_insee = l.INSEE AND UCASE(TRIM(uv.nom)) = UCASE(TRIM(l.ville))
                LEFT JOIN structure_conseiller sc ON sc.id = dae.conseiller_id
            WHERE dRMH.date_RMH BETWEEN '" . $dateUSBegin . "' AND '" . $dateUSEnd . "'
            ORDER BY d.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $dateUSBegin
     * @param $dateUSEnd
     * @param $production_travauxNiveau1_2
     * @param $production_travauxNiveau_BBC2
     * @return array
     * @throws Exception
     */
    public function findDataExportADEMEA05(
        $dateUSBegin,
        $dateUSEnd,
        $production_travauxNiveau1_2,
        $production_travauxNiveau_BBC2
    ) {
        $arrayDemandeTravauxNiveaux = [
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE,
            Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE
        ];

        $query = "
            SELECT  DISTINCT d.id AS numeroDossier,
                    DATE_FORMAT(dRMH.date_RMH, '%Y-%m-%d') AS dateActe,
                    CASE SUBSTRING(l.situation, 1, 1)
                        WHEN 0 THEN 'PO'
                        WHEN 1 THEN 'PB'
                        ELSE ''
                    END AS typePublic,
                    l.code_postal AS logementCodePostal,
                    l.ville AS logementCommune,
                    SUBSTR(CONCAT_WS(' ', IF(l.numero_rue, l.numero_rue , null), UPPER(SUBSTRING(l.complement_rue,1,1)), l.adresse, l.complement_1, l.complement_2), 1, 60) AS logementAdresse,
                    IF(uv.code_insee_parent IS NULL, uv.code_insee, uv.code_insee_parent) AS codeInsee,
                    b.nom AS beneficiaireNom,
                    b.prenom AS beneficiairePrenom,
                    IF(b.tel_1 IS NULL, b.tel_2, b.tel_1) AS beneficiaireTel,
                    IF(b.email IS NULL AND sc.email IS NOT NULL, sc.email, b.email) AS email,
                    b.revenu_fiscal_ref AS beneficiaireRevenuFiscalRef,
                    b.nb_pers_foyer AS beneficiaireNbPersFoyer,
                    dt.nb_pers_foyer AS demandeNbPersFoyer,
                    dt.revenu_foyer AS demandeRevenuFiscalRef
            FROM demande_ d
                INNER JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                INNER JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN up_ville uv ON uv.code_insee = l.INSEE AND UCASE(TRIM(uv.nom)) = UCASE(TRIM(l.ville))
                INNER JOIN structure_conseiller sc ON sc.id = dt.conseiller_id
                INNER JOIN titre t ON d.id = t.demande_id
                INNER JOIN remboursement_ r ON r.titre_id = t.id
                INNER JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id
                INNER JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id
            WHERE (dRMH.date_RMH BETWEEN '" . $dateUSBegin . "' AND '" . $dateUSEnd . "')
                AND dtd.niveau IN ('" . implode("','", $arrayDemandeTravauxNiveaux). "')
                AND t.numero_operation IN (".$production_travauxNiveau1_2 . "," . $production_travauxNiveau_BBC2 . ")
            ORDER BY d.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $demandeId
     * @return array|false
     * @throws Exception
     */
    public function findDataForEmail($demandeId)
    {
        $query = "
            SELECT  pa_dae.email AS auditeurEmailAE,
                    pa_dan.email AS auditeurEmailAN,
                    pa_dtd.email AS auditeurEmailTravaux,
                    r_pa_dtd.email AS renovateurEmailTravaux,
                    r_pi_dtd.raison_sociale AS renovateurRaisonSocialeTravaux,
                    sc_dae.email AS conseillerEmailAE,
                    sc_dan.email AS conseillerEmailAN,
                    sc_dt.email AS conseillerEmailTravaux,
                    b.id AS beneficiaireId,
                    b.nom AS beneficiaireNom,
                    b.prenom AS beneficiairePrenom,
                    CASE b.civilite
                        WHEN '0 | madame' THEN 'Madame'
                        WHEN '1 | monsieur' THEN 'Monsieur'
                        ELSE NULL
                    END AS beneficiaireCivilite,
                    b.numero_rue AS beneficiaireNumeroRue,
                    b.nom_rue AS beneficiaireNomRue,
                    b.code_postal AS beneficiaireCodePostal,
                    b.ville AS beneficiaireVille,
                    l.code_postal AS logementCodePostal,
                    l.ville AS logementVille,
                    l.numero_rue AS logementNumeroRue,
                    l.adresse AS logementAdresse,                                                 
                    CASE l.complement_rue
                        WHEN '0 | bis' THEN 'BIS'
                        WHEN '1 | ter' THEN 'TER'
                        ELSE NULL
                    END AS logementComplementRue,
                    dtd.niveau as demandeTravauxDevisNiveau
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_statut ps_dae ON (p_dae.partenaire_statut_id = ps_dae.id AND ps_dae.enabled = '1')
                LEFT JOIN partenaire_adresse pa_dae ON p_dae.partenaire_adresse_id = pa_dae.id  
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_statut ps_dan ON (p_dan.partenaire_statut_id = ps_dan.id AND ps_dan.enabled = '1')
                LEFT JOIN partenaire_adresse pa_dan ON p_dan.partenaire_adresse_id = pa_dan.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN partenaire_ p_dtd ON dtd.auditeur_id = p_dtd.id
                LEFT JOIN partenaire_statut ps_dtd ON (p_dtd.partenaire_statut_id = ps_dtd.id AND ps_dtd.enabled = '1')
                LEFT JOIN partenaire_adresse pa_dtd ON p_dtd.partenaire_adresse_id = pa_dtd.id
                LEFT JOIN partenaire_ r_p_dtd ON dtd.renovateur_id = r_p_dtd.id
                LEFT JOIN partenaire_statut r_ps_dtd ON (r_p_dtd.partenaire_statut_id = r_ps_dtd.id AND r_ps_dtd.enabled = '1')
                LEFT JOIN partenaire_adresse r_pa_dtd ON r_p_dtd.partenaire_adresse_id = r_pa_dtd.id
                LEFT JOIN partenaire_identification r_pi_dtd ON r_p_dtd.partenaire_identification_id = r_pi_dtd.id
                LEFT JOIN structure_ s_dae ON dae.structure_id = s_dae.id
                LEFT JOIN structure_statut ss_dae ON (s_dae.structure_statut_id = ss_dae.id AND ss_dae.enabled = '1')
                LEFT JOIN structure_conseiller sc_dae ON (dae.conseiller_id = sc_dae.id AND sc_dae.enabled = '1')
                LEFT JOIN structure__structure_conseiller s_sc_dae ON (s_sc_dae.structure__id = s_dae.id AND s_sc_dae.structure_conseiller_id = sc_dae.id)
                LEFT JOIN structure_ s_dan ON dan.structure_id = s_dan.id
                LEFT JOIN structure_statut ss_dan ON (s_dan.structure_statut_id = ss_dan.id AND ss_dan.enabled = '1')
                LEFT JOIN structure_conseiller sc_dan ON (dan.conseiller_id = sc_dan.id AND sc_dan.enabled = '1')
                LEFT JOIN structure__structure_conseiller s_sc_dan ON (s_sc_dan.structure__id = s_dan.id AND s_sc_dan.structure_conseiller_id = sc_dan.id)
                LEFT JOIN structure_ s_dt ON dt.structure_id = s_dt.id
                LEFT JOIN structure_statut ss_dt ON (s_dt.structure_statut_id = ss_dt.id AND ss_dt.enabled = '1')
                LEFT JOIN structure_conseiller sc_dt ON (dt.conseiller_id = sc_dt.id AND sc_dt.enabled = '1')
                LEFT JOIN structure__structure_conseiller s_sc_dt ON (s_sc_dt.structure__id = s_dt.id AND s_sc_dt.structure_conseiller_id = sc_dt.id)
            WHERE d.id = " . $demandeId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $demandeId
     * @param $demandeType
     * @return array|false
     * @throws Exception
     */
    public function findRecipientForAudit($demandeId, $demandeType)
    {
        switch ($demandeType) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                $table = 'demande_audit_energie';
                break;
            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                $table = 'demande_audit_numerique';
                break;
            default:
                $table = null;
        }

        $query = "
            SELECT  d.id AS demandeId,
                    sc.id AS conseillerId,
                    sc.email AS conseillerEmail,
                    pa.id AS auditeurId,
                    IF(psa.enabled = 0, NULL, paa.email) AS auditeurEmail
            FROM demande_ d
                INNER JOIN " .$table. " da ON d." .$table. "_id = da.id
                LEFT JOIN structure_conseiller sc ON da.conseiller_id = sc.id
                    AND sc.enabled = 1
                    AND sc.email IS NOT NULL
                LEFT JOIN partenaire_ pa ON da.auditeur_id = pa.id
                LEFT JOIN partenaire_adresse paa ON pa.partenaire_adresse_id = paa.id
                    AND paa.email IS NOT NULL
                LEFT JOIN partenaire_statut psa ON pa.partenaire_statut_id = psa.id
            WHERE d.id = " . $demandeId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $demandeId
     * @return array|false
     * @throws Exception
     */
    public function findRecipientForTravaux($demandeId)
    {
        $query = "
            SELECT  d.id AS demandeId,
                    sc.id AS conseillerId,
                    sc.email AS conseillerEmail,
                    pa.id AS auditeurId,
                    IF(psa.enabled = 0, NULL, paa.email) AS auditeurEmail,
                    pr.id AS renovateurId,
                    IF(psr.enabled = 0, NULL, par.email) AS renovateurEmail
            FROM demande_ AS d
                INNER JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN structure_conseiller sc ON dt.conseiller_id = sc.id
                    AND sc.enabled = 1
                    AND sc.email IS NOT NULL
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN partenaire_ pa ON dtd.auditeur_id = pa.id
                LEFT JOIN partenaire_adresse paa ON pa.partenaire_adresse_id = paa.id
                    AND paa.email IS NOT NULL
                LEFT JOIN partenaire_statut psa ON pa.partenaire_statut_id = psa.id
                LEFT JOIN partenaire_ pr ON dtd.renovateur_id = pr.id
                LEFT JOIN partenaire_adresse par ON pr.partenaire_adresse_id = par.id
                    AND par.email IS NOT NULL
                LEFT JOIN partenaire_statut psr ON pr.partenaire_statut_id = psr.id                    
            WHERE d.id = " . $demandeId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $demandeId
     * @return array
     * @throws Exception
     */
    public function findByDemande($demandeId)
    {
        $query = "
            SELECT  d.id AS demandeId,
                    he.content AS content,
                    u.email AS auteurEmail,
                    h.date_creation AS dateCreation,
                    he.recipient AS listRecipient
            FROM demande_ d
                INNER JOIN historique_ h ON d.id = h.demande_id
                    AND LCASE(h.action) = 'commentaire'
                INNER JOIN historique_email he ON h.id = he.historique_id
                LEFT JOIN user u ON h.auteur_creation = u.username
            WHERE d.id = " . $demandeId . "
            ORDER BY d.date_creation DESC, he.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $demandeId
     * @param $production_travauxNiveau_BBC2
     * @return array|false
     * @throws Exception
     */
    public function findEtatAvancement($demandeId,$production_travauxNiveau_BBC2)
    {
        $query = "
            SELECT  SUBSTR(dtd.niveau,1,1) AS demandeTravauxDevisNiveau,  
                    d.statut_id AS demandeStatut,
                    ds.color_step AS demandeStatutColorStep,
                    r.statut_id AS remboursementStatut,
                    rs.color_step AS remboursementColorStep,
                    t.numero_operation AS titreNumeroOperation,
                    r2.statut_id AS remboursementStatut2,
                    rs2.color_step AS remboursementColorStep2,
                    t2.numero_operation AS titreNumeroOperation2
            FROM demande_ d
                INNER JOIN demande_statut ds ON d.statut_id = ds.id
                LEFT JOIN titre t ON d.id = t.demande_id AND t.numero_operation != '" . $production_travauxNiveau_BBC2 . "'
                LEFT JOIN remboursement_ r ON r.titre_id = t.id                
                LEFT JOIN remboursement_statut rs ON rs.id = r.statut_id  
                LEFT JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
                LEFT JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id AND t2.numero_operation = '" . $production_travauxNiveau_BBC2 . "'
                LEFT JOIN remboursement_ r2 ON r2.titre_id = t2.id AND r2.id != r.id
                LEFT JOIN remboursement_statut rs2 ON rs2.id = r2.statut_id                
            WHERE d.id = " . $demandeId ."
            ORDER BY t.numero_operation ASC, t2.numero_operation ASC
        ";

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
    public function findForTitre()
    {
        $query = "
            SELECT d.id AS demandeId,
                   p.id AS productionId
            FROM demande_ d
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                INNER JOIN production__demande_ pd ON pd.demande__id = d.id
                INNER JOIN production_ p ON p.id = pd.production__id
            WHERE ds.statut = " . Demande_statut::STATUS_13 . "
            GROUP BY productionId, demandeId
            ORDER BY p.id, d.id
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $option
     * @return array|false
     * @throws Exception
     */
    public function findStatutByDemande($option)
    {
        $demandeId = $option['demandeId'];
        $production_travauxNiveau_BBC1 = $option['production_travauxNiveau_BBC1'];
        $production_travauxNiveau_BBC2 = $option['production_travauxNiveau_BBC2'];

        $query = "
            SELECT IF(r.statut_id IS NOT NULL, r.statut_id, d.statut_id) AS statutId,
                   r.id AS remboursementId
            FROM demande_ d
                INNER JOIN demande_statut ds ON ds.id = d.statut_id
                LEFT JOIN titre t ON t.demande_id = d.id
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id 
                LEFT JOIN remboursement_ r2 ON r2.demande_id = d.id AND r2.titre_id = t2.id
                LEFT JOIN remboursement_ r ON r.titre_id = t.id
                LEFT JOIN remboursement_statut rs ON rs.id = r.statut_id
            WHERE (
                t.id IS NULL
                OR t.numero_operation NOT IN (" . $production_travauxNiveau_BBC1 . "," . $production_travauxNiveau_BBC2 . ")
                OR t.numero_operation = " . $production_travauxNiveau_BBC2 . " AND r2.statut_id = " . Remboursement_statut::STATUS_22 . "
                OR t.numero_operation = " . $production_travauxNiveau_BBC1 . " AND r.statut_id != " . Remboursement_statut::STATUS_22 . "
                OR t.numero_operation = " . $production_travauxNiveau_BBC1 . " AND r.id IS NULL
            ) AND d.id = " . $demandeId . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $beneficiaireId
     * @param $logementId
     * @param $demandeType
     * @return bool
     * @throws Exception
     */
    public function findIsCreated($beneficiaireId, $logementId, $demandeType)
    {
        $andWhere = '';
        $join = '';

        if (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType) {
            $join = ' LEFT JOIN remboursement_ r ON r.demande_id = d.id AND r.statut_id IN (' . Remboursement_statut::STATUS_20 . ', ' . Remboursement_statut::STATUS_22 . ')';
            $andWhere .= ' AND r.id IS NULL';
        }

        $query = "
            SELECT  d.id AS demandeId
            FROM demande_ d " .
            $join . "
            WHERE d.beneficiaire_id = :beneficiaireId
                AND d.statut_id <> 15
                AND d.logement_id = :logementId
                AND d.type = :type " .
            $andWhere . "
            LIMIT 1
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery(
            array(
                'beneficiaireId' => $beneficiaireId,
                'logementId'     => $logementId,
                'type'           => $demandeType
            )
        );

        return (bool)$result->fetchAssociative();
    }

    /**
     * @param $demandeId
     * @param $demandeType
     * @param null $instructionId
     * @return array|false
     * @throws Exception
     */
    public function findOneForInstructionExamineReexamine(
        $demandeId,
        $demandeType,
        $instructionId = null
    ) {

        // 15 => REFUSÉ
        $arrayExamineStatutsExclude = array(
            Demande_statut::STATUS_1,
            Demande_statut::STATUS_3,
            Demande_statut::STATUS_15,
            Demande_statut::STATUS_18,
            Demande_statut::STATUS_20,
            Demande_statut::STATUS_23,
            Demande_statut::STATUS_25,
            Demande_statut::STATUS_38,
            Demande_statut::STATUS_43
        );

        if ($instructionId) {
            $andWhere = " AND i.id = " . $instructionId . " ";
        } else {
            $andWhere = " AND i.id IS NULL ";
        }

        $query = "
            SELECT  d.id AS id
            FROM demande_ d
                LEFT JOIN instruction_ i ON d.id = i.demande_id
            WHERE d.type = '" . $demandeType . "'
                AND d.statut_id NOT IN (" . implode(',', $arrayExamineStatutsExclude) . ")
                AND d.dateCP_id IS NULL " .
            $andWhere . "
                AND d.id = " . $demandeId . "
            LIMIT 1
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $type
     * @param $devisId
     * @param null $logementId
     * @param null $ficheTechniqueId
     * @return bool|array
     * @throws Exception
     */
    public function findOneForInstructionTechniqueExamineReexamine(
        $type,
        $devisId,
        $logementId = null,
        $ficheTechniqueId = null
    ) {
        $arrayDemandeStatutToExclude = array(
            Demande_statut::STATUS_11,
            Demande_statut::STATUS_12,
            Demande_statut::STATUS_13,
            Demande_statut::STATUS_14,
            Demande_statut::STATUS_15
        );

        $queryJoin = "";

        if ('devis' == $type) {
            // DEMANDE TRAVAUX DEVIS

            $andWhere = " WHERE d.logement_id = " . $logementId
                . " AND dtd.id = " . $devisId
                . " AND (dtd.statut_instruction = '0' OR dtd.statut_instruction = '1') ";
        } else {
            // FICHE TECHNIQUE
            $andWhere = " WHERE dtd.id = " . $devisId;
            if ($ficheTechniqueId) { // REEXAMINE
                $andWhere .= " AND (ft.statut_ficheTechnique = '0' OR ft.statut_ficheTechnique = '1')
                               AND dt.fiche_technique_id IS NOT NULL ";
            } else { // EXAMINE
                $andWhere .= " AND dt.fiche_technique_id IS NULL ";
            }
            $queryJoin .= " LEFT JOIN fiche_technique ft ON dt.fiche_technique_id = ft.id ";
        }

        $query = "
            SELECT  d.id
            FROM demande_ d
                INNER JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                INNER JOIN logement l ON l.id = d.logement_id
                INNER JOIN demande_statut ds ON ds.id = d.statut_id 
                    AND d.statut_id NOT IN (" . implode(',', $arrayDemandeStatutToExclude) . ")
                INNER JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id "
            . $queryJoin
            . $andWhere
            . " LIMIT 1
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $demandeId
     * @return mixed
     * @throws Exception
     */
    public function findDemandeTravauxDevisNiveau($demandeId)
    {
        $query = "
            SELECT dtd.niveau AS demandeTravauxDevisNiveau
            FROM demande_ d
                INNER JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
                INNER JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
            WHERE d.id = " . $demandeId . "
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchOne();
    }

    /**
     * @param $logementId
     * @return mixed
     * @throws Exception
     */
    public function findParticipationSAREByLogementId($logementId)
    {
        $query = "
            SELECT 
                e.participation_sare
            FROM EPCI_ e
            INNER JOIN orientation o ON o.EPCI_id = e.id
            INNER JOIN up_ville uv ON uv.id = o.ville_id    
            INNER JOIN logement l ON l.INSEE = uv.code_insee     
            WHERE l.id = :logementId
        ";

        $stmt = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $stmt->executeQuery([
            ':logementId' => $logementId
        ]);

        return $result->fetchOne();
    }

    /**
     * @return array
     * @throws Exception|\DateMalformedStringException
     */
    public function findForProcessRefuser()
    {
        $arrayDemandeStatutNotIn = [
            Demande_statut::STATUS_2,
            Demande_statut::STATUS_4,
            Demande_statut::STATUS_8,
            Demande_statut::STATUS_11,
            Demande_statut::STATUS_12,
            Demande_statut::STATUS_13,
            Demande_statut::STATUS_14,
            Demande_statut::STATUS_15,
            Demande_statut::STATUS_19,
            Demande_statut::STATUS_21,
            Demande_statut::STATUS_22,
            Demande_statut::STATUS_24,
            Demande_statut::STATUS_39,
            Demande_statut::STATUS_44
        ];

        $dateJour = new \DateTime();
        $dateLimit = (new \DateTime('-2 years' . $dateJour->format('Y-m-d')))->format('Y-m-d');

        $query = "
            SELECT  d.id AS demandeId
            FROM demande_ AS d
                INNER JOIN demande_statut ds ON d.statut_id = ds.id
            WHERE ds.statut NOT IN (" . implode(",", $arrayDemandeStatutNotIn) . ")
            AND DATE_FORMAT(d.date_creation, '%Y-%m-%d') <= :dateLimit
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery([
            ':dateLimit' => $dateLimit
        ]);

        return $result->fetchFirstColumn();
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
            SELECT  d.id AS demandeId
            FROM demande_ AS d
                INNER JOIN demande_statut ds ON d.statut_id = ds.id
            WHERE ds.statut = :statut
            AND DATE_FORMAT(d.date_modif, '%Y-%m-%d') <= :dateLimit
            AND d.rgpd != :rgpd
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery([
            ':dateLimit' => $dateLimit,
            ':rgpd'      => 1,
            ':statut'    => Demande_statut::STATUS_15
        ]);

        return $result->fetchFirstColumn();
    }

    /**
     * @param $beneficiaireId
     * @param $logementId
     * @return array
     * @throws Exception
     */
    public function findByBeneficiaireAndLogementForEditActionSubmitted($beneficiaireId, $logementId = null)
    {
        $andWhere = ' AND d.statut_id != ' . Demande_statut::STATUS_15;  // 15 => demande statut refusée
        if ($logementId) {
            $andWhere .= ' AND d.logement_id = ' . $logementId . ' ';
        }

        $query = "
            SELECT d.id AS demandeId
            FROM demande_ d
            WHERE d.beneficiaire_id = " . $beneficiaireId
            . $andWhere;
        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchFirstColumn();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function findCreatedForProcessUpdateTypeMenage($year)
    {
        $query = "
            SELECT DISTINCT d.id AS demandeId
            FROM demande_ AS d
                INNER JOIN demande_statut ds ON d.statut_id = ds.id
            WHERE DATE_FORMAT(d.date_creation, '%Y') = :year
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery([
            ':year' => $year
        ]);

        return $result->fetchFirstColumn();
    }

    /**
     * @param $year
     * @return array
     * @throws Exception
     */
    public function findUpdatedForProcessUpdateTypeMenage($year)
    {
        $query = "
            SELECT DISTINCT d.id AS demandeId
            FROM demande_ AS d
                INNER JOIN demande_statut ds ON d.statut_id = ds.id
                INNER JOIN historique_ h ON d.id = h.demande_id
            WHERE DATE_FORMAT(h.date_creation, '%Y') = :year
                AND h.action IN ('" . implode("','", Historique_::$actionModificationDemande) . "')
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery([
            ':year' => $year
        ]);

        return $result->fetchFirstColumn();
    }

    /**
     * @param $beneficiaireId
     * @param $logementId
     * @return float|int|mixed|string|null
     * @throws NonUniqueResultException
     */
    public function findLastDemandeTravauxDevisRemboursementTermine($beneficiaireId, $logementId)
    {
        $qb = $this->createQueryBuilder("d");
        $qb->select("dtd.id AS demandeTravauxDevisId")
            ->addSelect("dtd.niveau AS demandeTravauxDevisNiveau")
            ->innerJoin("d.demande_travaux", "dt")
            ->innerJoin(Demande_travaux_devis::class, "dtd", Join::WITH, "dt.travauxDevis_id = dtd.id")
            ->innerJoin(Remboursement_::class, "r", Join::WITH, "d.id = r.demande_id AND r.statut_id = :remboursementStatut")
            ->where("d.beneficiaire_id = :beneficiaireId")
            ->andWhere("d.logement_id = :logementId")
            ->andWhere("d.type = :demandeType")
            ->setParameters([
                "remboursementStatut" => Remboursement_statut::STATUS_22,
                "beneficiaireId"      => $beneficiaireId,
                "logementId"          => $logementId,
                "demandeType"         => Demande_::DEMANDE_TRAVAUX_TYPE
            ])
            ->orderBy("d.id", "DESC")
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return array|float|int|string
     */
    public function findForUpdateFicheTechniqueSurfaceHabitableCommand()
    {
        $qb = $this->createQueryBuilder('d');
        $qb->select('d.id as demandeId')
            ->addSelect('r.id AS remboursementId')
            ->addSelect('r.titre_id AS titreId')
            ->addSelect('dtft.id AS dtftId')
            ->addSelect('dtftInitial.id AS dtftInitialId')
            ->addSelect('dtftInitial.surfaceHabitable AS  dtftSurfaceHabitableInitial')
            ->addSelect('REGEXP(dtftInitial.surfaceHabitable,:regexp) AS dtftIsNumericInitial')
            ->addSelect('dtftBBC.id AS dtftBBCId')
            ->addSelect('dtftBBC.surfaceHabitable AS dtftSurfaceHabitableBBC')
            ->addSelect('REGEXP(dtftBBC.surfaceHabitable,:regexp) AS dtftIsNumericBBC')
            ->addSelect('dtftPrescription.id AS dtftPrescriptionId')
            ->addSelect('dtftPrescription.surfaceHabitable AS dtftSurfaceHabitablePrescription')
            ->addSelect('REGEXP(dtftPrescription.surfaceHabitable,:regexp) AS dtftIsNumericPrescription')
            ->addSelect('rtft.id AS rtftId')
            ->addSelect('rtftInitial.id AS rtftInitialId')
            ->addSelect('rtftInitial.surfaceHabitable AS  rtftSurfaceHabitableInitial')
            ->addSelect('REGEXP(rtftInitial.surfaceHabitable,:regexp) AS rtftIsNumericInitial')
            ->addSelect('rtftBBC.id AS rtftBBCId')
            ->addSelect('rtftBBC.surfaceHabitable AS rtftSurfaceHabitableBBC')
            ->addSelect('REGEXP(rtftBBC.surfaceHabitable,:regexp) AS rtftIsNumericBBC')
            ->addSelect('rtftPrescription.id AS rtftPrescriptionId')
            ->addSelect('rtftPrescription.surfaceHabitable AS rtftSurfaceHabitablePrescription')
            ->addSelect('REGEXP(rtftPrescription.surfaceHabitable,:regexp) AS rtftIsNumericPrescription')
            ->addSelect('rtftFinChantier.id AS rtftFinChantierId')
            ->addSelect('rtftFinChantier.surfaceHabitable AS rtftSurfaceHabitableFinChantier')
            ->addSelect('REGEXP(rtftFinChantier.surfaceHabitable,:regexp) AS rtftIsNumericFinChantier')
            ->innerJoin('d.demande_travaux', 'dt')
            ->innerJoin(FicheTechnique::class, 'dtft', Join::WITH, 'dt.ficheTechnique_id = dtft.id')
            ->leftJoin('dtft.ficheTechnique_initial', 'dtftInitial')
            ->leftJoin('dtft.ficheTechnique_BBC', 'dtftBBC')
            ->leftJoin('dtft.ficheTechnique_prescription', 'dtftPrescription')
            ->leftJoin(Titre::class, 't', Join::WITH, 't.demandeId = d.id')
            ->leftJoin(Remboursement_::class, 'r', Join::WITH, 'r.titre_id = t.id AND d.id = r.demande_id')
            ->leftJoin('r.remboursement_travaux', 'rt')
            ->leftJoin('rt.ficheTechnique', 'rtft')
            ->leftJoin('rtft.ficheTechnique_initial', 'rtftInitial')
            ->leftJoin('rtft.ficheTechnique_BBC', 'rtftBBC')
            ->leftJoin('rtft.ficheTechnique_prescription', 'rtftPrescription')
            ->leftJoin('rtft.ficheTechnique_finChantier', 'rtftFinChantier')
            ->where('d.type = :demandeType')
            ->andWhere($qb->expr()->orX(
                'REGEXP(dtftPrescription.surfaceHabitable,:regexp) = 0 AND dtftPrescription.id IS NOT NULL AND rtft.id IS NULL',
                'REGEXP(rtftFinChantier.surfaceHabitable,:regexp) = 0 AND rtftFinChantier.id IS NOT NULL'
            ))
            ->setParameters([
                'demandeType' => Demande_::DEMANDE_TRAVAUX_TYPE,
                'regexp'      => DefaultServiceUtils::DECIMAL_FIELD_FORM_PATTERN
            ])
            ->orderBy("d.id", "DESC")
            ->groupBy('d.id, r.id, t.id, dtft.id, rtft.id');

        return $qb->getQuery()->getArrayResult();
    }
}

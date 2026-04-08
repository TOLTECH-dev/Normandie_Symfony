<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use App\Entity\Remboursement_statut;
use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\DateRMH;


class DateRMHRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DateRMH::class);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function findRemboursement(): array
    {
        $query = "
            SELECT  dRMH.id AS dateRMHId,
                    COUNT(DISTINCT r.demande_id) AS countRemboursement
            FROM date_RMH dRMH
                LEFT JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
            GROUP BY dRMH.id
            ORDER BY dRMH.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $dateRMHId
     * @return array
     * @throws Exception
     */
    public function findDataRecapitulatifPreRMH($dateRMHId): array
    {
        $query = "
            SELECT  YEAR(dCP.date_CP) AS annee,
                    SUM(t.valeur_titre) AS montantTitre
            FROM date_RMH dRMH
                INNER JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
                INNER JOIN titre t ON r.titre_id = t.id 
                INNER JOIN demande_ d ON t.demande_id = d.id
                INNER JOIN date_CP dCP ON d.dateCP_id = dCP.id
            WHERE dRMH.id = ".$dateRMHId. " 
                AND r.statut_id = " .Remboursement_statut::STATUS_21. "
            GROUP BY YEAR(dCP.date_CP)
            ORDER BY YEAR(dCP.date_CP) ASC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $dateRMHId
     * @return array
     * @throws Exception
     */
    public function findDataFileRMH($dateRMHId): array
    {
        $query = "
            (SELECT
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN p_dae.id
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN p_dae.id
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN p_dan.id
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN p_dan.id
                END AS matricule,            
                SUM(t.valeur_titre) AS VNTotal,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN UPPER(poa_dae.domicile_bancaire)
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN UPPER(poa_dae.domicile_bancaire)
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN UPPER(poa_dan.domicile_bancaire)
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN UPPER(poa_dan.domicile_bancaire)
                END AS domiciliationBancaire,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN UPPER(poa_dae.bic)
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN UPPER(poa_dae.bic)
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN UPPER(poa_dan.bic)
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN UPPER(poa_dan.bic)
                END AS BIC,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN UPPER(poa_dae.iban)
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN UPPER(poa_dae.iban)
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN UPPER(poa_dan.iban)
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN UPPER(poa_dan.iban)
                END AS IBAN,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN UPPER(pi_dae.raison_sociale)
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN UPPER(pi_dae.raison_sociale)
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN UPPER(pi_dan.raison_sociale)
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN UPPER(pi_dan.raison_sociale)
                END AS nom,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.destinataire
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.destinataire
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.destinataire
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.destinataire
                END AS destinataire     
            FROM date_RMH dRMH
                INNER JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
                INNER JOIN titre t ON r.titre_id = t.id
                INNER JOIN demande_ d ON t.demande_id = d.id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id
                LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id
                LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN partenaire_option_auditeur poa_dae ON p_dae.partenaire_option_auditeur_id = poa_dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN partenaire_option_auditeur poa_dan ON p_dan.partenaire_option_auditeur_id = poa_dan.id
            WHERE dRMH.id = ".$dateRMHId. "
                AND (raei.destinataire = '0 | auditeur' OR rani.destinataire = '0 | auditeur')
            GROUP BY matricule, destinataire, nom, domiciliationBancaire, BIC, IBAN)
            UNION
            (SELECT
                p.id AS matricule,            
                SUM(t.valeur_titre) AS VNTotal,
                UPPER(rti.domiciliation_bancaire) AS domiciliationBancaire,
                UPPER(rti.bic) AS BIC,
                UPPER(rti.iban) AS IBAN,
                UPPER(pi.raison_sociale) AS nom,
                rti.destinataire AS destinataire
            FROM date_RMH dRMH
                INNER JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
                INNER JOIN titre t ON r.titre_id = t.id
                INNER JOIN demande_ d ON t.demande_id = d.id
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id
                LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN partenaire_ p ON dtd.renovateur_id = p.id
                LEFT JOIN partenaire_identification pi ON p.partenaire_identification_id = pi.id
            WHERE dRMH.id = ".$dateRMHId. "
                AND rti.destinataire = '2 | renovateur'
            GROUP BY matricule, destinataire, nom, domiciliationBancaire, BIC, IBAN)
            UNION
            (SELECT
                b.id AS matricule,            
                SUM(t.valeur_titre) AS VNTotal,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN UPPER(raei.domiciliation_bancaire)
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN UPPER(raei.domiciliation_bancaire)
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN UPPER(rani.domiciliation_bancaire)
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN UPPER(rani.domiciliation_bancaire)
                    WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN UPPER(rti.domiciliation_bancaire)
                END AS domiciliationBancaire,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN UPPER(raei.bic)
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN UPPER(raei.bic)
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN UPPER(rani.bic)
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN UPPER(rani.bic)
                    WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN UPPER(rti.bic)
                END AS BIC,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN UPPER(raei.iban)
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN UPPER(raei.iban)
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN UPPER(rani.iban)
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN UPPER(rani.iban)
                    WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN UPPER(rti.iban)
                END AS IBAN,
            	CASE WHEN ('1 | sci' != b.type)
            		THEN UPPER(CONCAT(b.prenom, ' ', UCASE(b.nom)))
            		ELSE UPPER(b.nom_SCI)
                END AS nom,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.destinataire
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.destinataire
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.destinataire
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.destinataire
                    WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.destinataire
                END AS destinataire        
            FROM date_RMH dRMH
                INNER JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
                INNER JOIN titre t ON r.titre_id = t.id
                INNER JOIN demande_ d ON t.demande_id = d.id
                INNER JOIN beneficiaire b ON d.beneficiaire_id = b.id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id
                LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id
                LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id
                LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
            WHERE dRMH.id = ".$dateRMHId. "
                AND (raei.destinataire = '1 | beneficiaire' OR rani.destinataire = '1 | beneficiaire' OR rti.destinataire = '1 | beneficiaire')
            GROUP BY matricule, destinataire, nom, domiciliationBancaire, BIC, IBAN)
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $dateRMHId
     * @param $productionTravauxNiveauBBC1
     * @param $productionTravauxNiveauBBC2
     * @param $destinataire
     * @return array
     * @throws Exception
     */
    public function findDataFileSyntheseForTotalReleve($dateRMHId, $productionTravauxNiveauBBC1, $productionTravauxNiveauBBC2, $destinataire): array
    {
        $whereDestinataire = '';
        if (0 == $destinataire) {
            $whereDestinataire = " AND (raei.destinataire = '1 | beneficiaire' OR rani.destinataire = '1 | beneficiaire' OR rti.destinataire = '1 | beneficiaire')";
        } elseif (1 == $destinataire) {
            $whereDestinataire = " AND (raei.destinataire = '0 | auditeur' OR rani.destinataire = '0 | auditeur' OR rti.destinataire = '2 | renovateur')";
        }

        $query = "
            SELECT
                SUM(t.valeur_titre) AS montantTitre,
                COUNT(t.id) as nbCheque,
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
                                    WHEN '" . $productionTravauxNiveauBBC1."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_CODE . "
                                    WHEN '" . $productionTravauxNiveauBBC2."' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_CODE . "
                                    ELSE NULL
                               END
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . "' THEN
                                CASE t.numero_operation
                                    WHEN '" . $productionTravauxNiveauBBC1 . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_CODE . "
                                    WHEN '" . $productionTravauxNiveauBBC2 . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_CODE . "
                                    ELSE NULL
                                END
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . "' THEN
                                CASE t.numero_operation
                                    WHEN '" . $productionTravauxNiveauBBC1 . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_CODE . "
                                    WHEN '" . $productionTravauxNiveauBBC2 . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_CODE . "
                                    ELSE NULL
                                END
                            WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . "' THEN
                                CASE t.numero_operation
                                    WHEN '" . $productionTravauxNiveauBBC1 . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_CODE . "
                                    WHEN '" . $productionTravauxNiveauBBC2 . "' THEN " . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_CODE . "
                                    ELSE NULL
                                END                                                           
                            ELSE NULL
                        END
                    ELSE NULL
                END AS niveauDemande
            FROM date_RMH dRMH
                INNER JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
                INNER JOIN titre t ON r.titre_id = t.id
                INNER JOIN demande_ d ON t.demande_id = d.id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id
                LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id
                LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id
                LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN partenaire_option_auditeur poa_dae ON p_dae.partenaire_option_auditeur_id = poa_dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN partenaire_option_auditeur poa_dan ON p_dan.partenaire_option_auditeur_id = poa_dan.id
            WHERE dRMH.id = " . $dateRMHId . "
                AND (r.statut_id = " . Remboursement_statut::STATUS_22 . ")
                " . $whereDestinataire . "
            GROUP BY niveauDemande
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $dateRMHId
     * @return array
     * @throws Exception
     */
    public function findDataFileSyntheseForDateCP($dateRMHId): array
    {
        $query = "
            SELECT  DISTINCT(dCP.date_CP) AS dateCP,
                    SUM(t.valeur_titre) AS totalTitre
            FROM date_RMH dRMH
                INNER JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
                INNER JOIN titre t ON r.titre_id = t.id 
                INNER JOIN demande_ d ON t.demande_id = d.id
                INNER JOIN date_CP dCP ON d.dateCP_id = dCP.id
            WHERE dRMH.id = ".$dateRMHId. "
            GROUP BY dateCP
            ORDER BY dateCP ASC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $dateRMHId
     * @param $productionTravauxNiveauBBC1
     * @param $productionTravauxNiveauBBC2
     * @param $dateCP
     * @return array
     * @throws Exception
     */
    public function findDataFileSyntheseForDateCPDetail(
        $dateRMHId,
        $productionTravauxNiveauBBC1,
        $productionTravauxNiveauBBC2,
        $dateCP
    ): array
    {
        // Auditeur data UNION Renovateur data UNION Beneficiaire data
        $query = "
            SELECT
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::DEMANDE_AUDIT_ENERGIE_LABEL . "'
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN '" . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_LABEL . "'
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN '" . Demande_::DEMANDE_AUDIT_NUMERIQUE_LABEL . "'
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_LABEL . "'
                    WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN CASE dtd.niveau
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_1_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_LABEL . "'
                                ELSE NULL
                            END
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_LABEL . "'
                                ELSE NULL
                            END
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_LABEL . "'
                                ELSE NULL
                            END
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_LABEL . "'
                                ELSE NULL
                            END                   
                    END
                    ELSE NULL
                END AS niveauDemande,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN p_dae.id
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN p_dae.id
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN p_dan.id
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN p_dan.id
                END AS numPartenaire,
                t.valeur_titre AS montantTitre,
                t.numero_cheque AS numeroCheque,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pi_dae.raison_sociale
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pi_dae.raison_sociale
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pi_dan.raison_sociale
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pi_dan.raison_sociale
                END AS raisonSociale,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN poa_dae.iban
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN poa_dae.iban
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN poa_dan.iban
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN poa_dan.iban
                END AS IBAN,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN poa_dae.bic
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN poa_dae.bic
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN poa_dan.bic
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN poa_dan.bic
                END AS BIC,
                d.id AS numeroDossier,
                UCASE(b.nom) AS nomParticulier,
                b.prenom AS prenomParticulier
            FROM date_RMH dRMH
                INNER JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
                INNER JOIN titre t ON r.titre_id = t.id
                INNER JOIN demande_ d ON t.demande_id = d.id
                INNER JOIN beneficiaire b ON d.beneficiaire_id = b.id
                INNER JOIN date_CP dCP ON d.dateCP_id = dCP.id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id
                LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id
                LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_identification pi_dae ON p_dae.partenaire_identification_id = pi_dae.id
                LEFT JOIN partenaire_option_auditeur poa_dae ON p_dae.partenaire_option_auditeur_id = poa_dae.id
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN partenaire_ p_dan ON dan.auditeur_id = p_dan.id
                LEFT JOIN partenaire_identification pi_dan ON p_dan.partenaire_identification_id = pi_dan.id
                LEFT JOIN partenaire_option_auditeur poa_dan ON p_dan.partenaire_option_auditeur_id = poa_dan.id
            WHERE dRMH.id = " . $dateRMHId . "
                AND (dCP.date_CP " . $dateCP . ")
                AND (raei.destinataire = '0 | auditeur' OR rani.destinataire = '0 | auditeur')
            UNION
            SELECT
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::DEMANDE_AUDIT_ENERGIE_LABEL . "'
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN '" . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_LABEL . "'
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN '" . Demande_::DEMANDE_AUDIT_NUMERIQUE_LABEL . "'
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_LABEL . "'
                    WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN CASE dtd.niveau
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_1_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_LABEL . "'
                                ELSE NULL
                            END
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_LABEL . "'
                                ELSE NULL
                            END
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_LABEL . "'
                                ELSE NULL
                            END
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_LABEL . "'
                                ELSE NULL
                            END                   
                    END
                    ELSE NULL
                END AS niveauDemande,
                p.id AS numPartenaire,
                t.valeur_titre AS montantTitre,
                t.numero_cheque AS numeroCheque,
                pi.raison_sociale AS raisonSociale,
                rti.iban AS IBAN,
                rti.bic AS BIC,
                d.id AS numeroDossier,
                UCASE(b.nom) AS nomParticulier,
                b.prenom AS prenomParticulier
            FROM date_RMH dRMH
                INNER JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
                INNER JOIN titre t ON r.titre_id = t.id
                INNER JOIN demande_ d ON t.demande_id = d.id
                INNER JOIN beneficiaire b ON d.beneficiaire_id = b.id
                INNER JOIN date_CP dCP ON d.dateCP_id = dCP.id
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id
                LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN partenaire_ p ON dtd.renovateur_id = p.id
                LEFT JOIN partenaire_identification pi ON p.partenaire_identification_id = pi.id
            WHERE dRMH.id = " . $dateRMHId . "
                AND (dCP.date_CP " . $dateCP . ")
                AND (rti.destinataire = '2 | renovateur')
            UNION
            SELECT
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::DEMANDE_AUDIT_ENERGIE_LABEL . "'
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN '" . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_LABEL . "'
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN '" . Demande_::DEMANDE_AUDIT_NUMERIQUE_LABEL . "'
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN '" . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_LABEL . "'
                    WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN CASE dtd.niveau
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_1_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_2_RENOVATEUR_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_SORTIE_PASSOIRE_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RGE_VALUE . "' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_LABEL . "'
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_BBC2_LABEL . "'
                                ELSE NULL
                            END
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_BBC2_LABEL . "'
                                ELSE NULL
                            END
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_BBC2_LABEL . "'
                                ELSE NULL
                            END
                        WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . "' THEN
                            CASE t.numero_operation
                                WHEN '".$productionTravauxNiveauBBC1."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC1_LABEL . "'
                                WHEN '".$productionTravauxNiveauBBC2."' THEN '" . Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_BBC2_LABEL . "'
                                ELSE NULL
                            END                   
                    END
                    ELSE NULL
                END AS niveauDemande,
                d.id AS numPartenaire,
                t.valeur_titre AS montantTitre,
                t.numero_cheque AS numeroCheque,
                CASE WHEN ('1 | sci' != b.type)
                        THEN CONCAT(UCASE(b.nom), ' ',b.prenom)
                        ELSE b.nom_SCI
                END AS raisonSociale, 
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.iban
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.iban
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.iban
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.iban
                    WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.iban
                END AS IBAN,
                CASE d.type
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.bic
                    WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.bic
                    WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.bic
                    WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.bic
                    WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.bic
                END AS BIC,
                d.id AS numeroDossier,
				CASE WHEN ('1 | sci' != b.type)
					THEN UCASE(b.nom)
                	ELSE UCASE(b.nom_SCI)
                END AS nomParticulier,
				CASE WHEN ('1 | sci' != b.type)
					THEN b.prenom
                	ELSE ''
                END AS prenomParticulier 
            FROM date_RMH dRMH
                INNER JOIN remboursement_ r ON dRMH.id = r.dateRMH_id
                INNER JOIN titre t ON r.titre_id = t.id
                INNER JOIN demande_ d ON t.demande_id = d.id
                INNER JOIN beneficiaire b ON d.beneficiaire_id = b.id
                INNER JOIN date_CP dCP ON d.dateCP_id = dCP.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN demande_travaux_devis dtd ON dt.travaux_devis_id = dtd.id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id
                LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id
                LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id
                LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
            WHERE dRMH.id = " . $dateRMHId . "
                AND (dCP.date_CP " . $dateCP . ")
                AND (raei.destinataire = '1 | beneficiaire' OR rani.destinataire = '1 | beneficiaire' OR rti.destinataire = '1 | beneficiaire')
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $dateRMHId
     * @param $fileRMH
     * @param $productionTravauxNiveauBBC1
     * @return array
     * @throws Exception
     */
    public function findDataXemelios($dateRMHId, $fileRMH, $productionTravauxNiveauBBC1): array
    {
        $query = "
            SELECT  CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.destinataire
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.destinataire
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.destinataire
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.destinataire
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.destinataire
                        ELSE NULL
                    END AS TYPECHEQUE,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN p_dae.id
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN p_dae.id
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN p_dan.id
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN p_dan.id
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN p_dtd.id
                        ELSE NULL
                    END AS IdAuditeur,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN poa_dae.id
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN poa_dae.id
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN poa_dan.id
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN poa_dan.id
                        ELSE NULL
                    END AS IdAuditeurRib,
                    '' AS PROFIL,
                    r.id AS APP_UID,
                    r.demande_id AS IdDossier,
                    'O18C' AS Prestation,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN p_dae.id
                                ELSE b.id
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN p_dae.id
                                ELSE b.id
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN p_dan.id
                                ELSE b.id
                            END
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN p_dan.id
                                ELSE b.id
                            END                            
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE rti.destinataire
                                WHEN '2 | renovateur' THEN p_dtd.id
                                ELSE b.id
                            END
                        ELSE NULL
                    END AS IdTiers,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN p_dae.id
                                ELSE b.id
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN p_dae.id
                                ELSE b.id
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN p_dan.id
                                ELSE b.id
                            END
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN p_dan.id
                                ELSE b.id
                            END
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE rti.destinataire
                                WHEN '2 | renovateur' THEN p_dtd.id
                                ELSE b.id
                            END
                        ELSE NULL
                    END AS RefTiers,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.rib_url
                                ELSE raei.rib_url
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.rib_url
                                ELSE raei.rib_url
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.rib_url
                                ELSE rani.rib_url
                            END
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE. " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.rib_url
                                ELSE rani.rib_url
                            END             
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.rib_url
                        ELSE NULL
                    END AS PJRef0,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.facture_url
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.facture_url
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.facture_url
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.facture_url
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE dtd.niveau
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN rti.fiche_travaux_url
                                        ELSE GROUP_CONCAT(rtic.document_url ORDER BY rtic.id ASC SEPARATOR '##')
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN rti.fiche_travaux_url
                                        ELSE GROUP_CONCAT(rtic.document_url ORDER BY rtic.id ASC SEPARATOR '##')
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN rti.fiche_travaux_url
                                        ELSE GROUP_CONCAT(rtic.document_url ORDER BY rtic.id ASC SEPARATOR '##')
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN rti.fiche_travaux_url
                                        ELSE GROUP_CONCAT(rtic.document_url ORDER BY rtic.id ASC SEPARATOR '##')
                                    END
                                ELSE GROUP_CONCAT(rtic.document_url ORDER BY rtic.id ASC SEPARATOR '##')
                            END
                        ELSE NULL
                    END AS PJRef1,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.recto_cheque_url
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.recto_cheque_url
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.recto_cheque_url
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.recto_cheque_url
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.recto_cheque_url
                        ELSE NULL
                    END AS PJRef2,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.verso_cheque_url
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.verso_cheque_url
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.verso_cheque_url
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.verso_cheque_url
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.verso_cheque_url
                        ELSE NULL
                    END AS PJRef3,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN raei.id
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN raei.id
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN rani.id
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN rani.id
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN rti.id
                        ELSE NULL
                    END AS IdEntity,
                    d.type AS demandeType,
                    dCP.numero_deliberation AS IdDecision,
                    '01' AS CatTiers,
                    CASE WHEN ('1 | sci' != b.type)
            			THEN b.nom
            			ELSE b.nom_SCI
                	END AS Nom,
                    CASE WHEN ('1 | sci' != b.type)
            			THEN b.prenom
            			ELSE ''
                	END AS Prenom,
                    CONCAT_WS(' ', b.numero_rue, ' ', UPPER(SUBSTR(b.complement_numero_rue, 5)), ' ', upper(b.nom_rue)) AS Adr1,
                    '' AS Adr2,
                    b.code_postal AS CP,
                    b.ville AS Ville,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.iban
                                ELSE raei.iban
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.iban
                                ELSE raei.iban
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.iban
                                ELSE rani.iban
                            END
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.iban
                                ELSE rani.iban
                            END
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE rti.destinataire
                                WHEN '0 | auditeur' THEN poa_dtd.iban
                                ELSE rti.iban
                            END
                        ELSE NULL
                    END AS IBAN,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.bic
                                ELSE raei.bic
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.bic
                                ELSE raei.bic
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.bic
                                ELSE rani.bic
                            END
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.bic
                                ELSE rani.bic
                            END
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE rti.destinataire
                                WHEN '0 | auditeur' THEN poa_dtd.bic
                                ELSE rti.bic
                            END
                        ELSE NULL
                    END AS BIC,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.domicile_bancaire
                                ELSE raei.domiciliation_bancaire
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.domicile_bancaire
                                ELSE raei.domiciliation_bancaire
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.domicile_bancaire
                                ELSE rani.domiciliation_bancaire
                            END
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.domicile_bancaire
                                ELSE rani.domiciliation_bancaire
                            END
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE rti.destinataire
                                WHEN '0 | auditeur' THEN poa_dtd.domicile_bancaire
                                ELSE rti.domiciliation_bancaire
                            END
                        ELSE NULL
                    END AS LibBanc,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.titulaire
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN
                            CASE raei.destinataire
                                WHEN '0 | auditeur' THEN poa_dae.titulaire
                            END
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.titulaire
                            END
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN
                            CASE rani.destinataire
                                WHEN '0 | auditeur' THEN poa_dan.titulaire
                            END
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE rti.destinataire
                                WHEN '0 | auditeur' THEN poa_dtd.titulaire
                            END
                        ELSE CONCAT(' ', UPPER(b.nom), ' ', CONCAT(UCASE(LEFT(b.prenom, 1)), LCASE(SUBSTRING(b.prenom, 2))))
                    END AS TitCpte,
                    '" . $fileRMH . "' AS IdVerst,
                    DATE_FORMAT(t.date_emission, '%Y-%m-%d') AS Date,
                    DATE_FORMAT(dCP.date_CP, '%Y-%m-%d') AS DateEffet,
                    t.valeur_titre AS Mt,
                    t.id AS UID,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
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
                            END
                        ELSE NULL
                    END AS isBBC1,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN
                            CASE dtd.niveau
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_RENOVATEUR_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN NULL
                                        ELSE GROUP_CONCAT(rtic.id ORDER BY rtic.id ASC SEPARATOR '##')
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_BBC_BIOSOURCE_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN NULL
                                        ELSE GROUP_CONCAT(rtic.id ORDER BY rtic.id ASC SEPARATOR '##')
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN NULL
                                        ELSE GROUP_CONCAT(rtic.id ORDER BY rtic.id ASC SEPARATOR '##')
                                    END
                                WHEN '" . Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE . "' THEN
                                    CASE t.numero_operation
                                        WHEN '" . $productionTravauxNiveauBBC1 . "' THEN NULL
                                        ELSE GROUP_CONCAT(rtic.id ORDER BY rtic.id ASC SEPARATOR '##')
                                    END
                                ELSE GROUP_CONCAT(rtic.id ORDER BY rtic.id ASC SEPARATOR '##')
                            END
                        ELSE GROUP_CONCAT(rtic.id ORDER BY rtic.id ASC SEPARATOR '##')
                    END AS IdEntityTravauxConformite
            FROM titre t
                INNER JOIN demande_ d ON d.id = t.demande_id 
                    AND d.statut_id NOT IN(0,1,999) 
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id 
                INNER JOIN remboursement_ r ON r.titre_id = t.id 
                    AND r.statut_id NOT IN(0,1,999) 
                INNER JOIN date_RMH dRMH ON dRMH.id = r.dateRMH_id
                LEFT JOIN demande_audit_energie dae ON dae.id = d.demande_audit_energie_id
                LEFT JOIN partenaire_ p_dae ON p_dae.id = dae.auditeur_id
                LEFT JOIN partenaire_identification pi_dae ON pi_dae.id = p_dae.partenaire_identification_id
                LEFT JOIN partenaire_adresse pa_dae ON pa_dae.id = p_dae.partenaire_adresse_id
                LEFT JOIN partenaire_option_auditeur poa_dae ON poa_dae.id = p_dae.partenaire_option_auditeur_id
                LEFT JOIN demande_audit_numerique dan ON dan.id = d.demande_audit_numerique_id
                LEFT JOIN partenaire_ p_dan ON p_dan.id = dan.auditeur_id
                LEFT JOIN partenaire_identification pi_dan ON pi_dan.id = p_dan.partenaire_identification_id
                LEFT JOIN partenaire_adresse pa_dan ON pa_dan.id = p_dan.partenaire_adresse_id
                LEFT JOIN partenaire_option_auditeur poa_dan ON poa_dan.id = p_dan.partenaire_option_auditeur_id
                LEFT JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
                LEFT JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
                LEFT JOIN partenaire_ p_dtd ON p_dtd.id = dtd.auditeur_id
                LEFT JOIN partenaire_identification pi_dtd ON pi_dtd.id = p_dtd.partenaire_identification_id
                LEFT JOIN partenaire_adresse pa_dtd ON pa_dtd.id = p_dtd.partenaire_adresse_id
                LEFT JOIN partenaire_option_auditeur poa_dtd ON poa_dtd.id = p_dtd.partenaire_option_auditeur_id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id
                LEFT JOIN remboursement_audit_energie_instruction raei ON rae.instruction_id = raei.id 
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id 
                LEFT JOIN remboursement_audit_numerique_instruction rani ON ran.instruction_id = rani.id
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id 
                LEFT JOIN remboursement_travaux_instruction rti ON rt.instruction_id = rti.id
                LEFT JOIN remboursement_travaux_instruction__conformite rti_c ON rti.id = rti_c.remboursement_travaux_instruction_id
                LEFT JOIN remboursement_travaux_instruction_conformite rtic ON rti_c.remboursement_travaux_instruction_conformite_id = rtic.id
                LEFT JOIN date_CP dCP ON dCP.id = d.dateCP_id
            WHERE dRMH.id = " . $dateRMHId . "
            GROUP BY r.id"
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
    public function findDataXemeliosForPartenaire(): array
    {
        $query = "
            SELECT  CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN dae.auditeur_id
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN dae.auditeur_id
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN dan.auditeur_id
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN dan.auditeur_id
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN dtd.auditeur_id
                        ELSE NULL
                    END AS IdAuditeur,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN CONCAT('9', LPAD(dae.auditeur_id,14,'0'))
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN CONCAT('9', LPAD(dae.auditeur_id,14,'0'))
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN CONCAT('9', LPAD(dan.auditeur_id,14,'0'))
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN CONCAT('9', LPAD(dan.auditeur_id,14,'0'))
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN CONCAT('9', LPAD(dtd.auditeur_id,14,'0'))
                        ELSE NULL
                    END AS IdTiers,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pi_dae.siret
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pi_dae.siret
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pi_dan.siret
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pi_dan.siret
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN pi_dtd.siret
                        ELSE NULL
                    END AS RefTiers,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pi_dae.raison_sociale
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pi_dae.raison_sociale
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pi_dan.raison_sociale
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pi_dan.raison_sociale
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN pi_dtd.raison_sociale
                        ELSE NULL
                    END AS Nom,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pa_dae.adresse1
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pa_dae.adresse1
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pa_dan.adresse1
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pa_dan.adresse1
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN pa_dtd.adresse1
                        ELSE NULL
                    END AS Adr1,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pa_dae.adresse2
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pa_dae.adresse2
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pa_dan.adresse2
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pa_dan.adresse2
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN pa_dtd.adresse2
                        ELSE NULL
                    END AS Adr2,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pa_dae.code_postal
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pa_dae.code_postal
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pa_dan.code_postal
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pa_dan.code_postal
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN pa_dtd.code_postal
                        ELSE NULL
                    END AS CP,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN pa_dae.ville
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN pa_dae.ville
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN pa_dan.ville
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN pa_dan.ville
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN pa_dtd.ville
                        ELSE NULL
                    END AS Ville,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN poa_dae.iban
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN poa_dae.iban
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN poa_dan.iban
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN poa_dan.iban
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN poa_dtd.iban
                        ELSE NULL
                    END AS IBAN,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN poa_dae.bic
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN poa_dae.bic
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN poa_dan.bic
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN poa_dan.bic
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN poa_dtd.bic
                        ELSE NULL
                    END AS BIC,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN poa_dae.domicile_bancaire
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN poa_dae.domicile_bancaire
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN poa_dan.domicile_bancaire
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN poa_dan.domicile_bancaire
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN poa_dtd.domicile_bancaire
                        ELSE NULL
                    END AS LibBanc,
                    CASE d.type
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE . " THEN poa_dae.titulaire
                        WHEN " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE . " THEN poa_dae.titulaire
                        WHEN " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE . " THEN poa_dan.titulaire
                        WHEN " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE . " THEN poa_dan.titulaire
                        WHEN " . Demande_::DEMANDE_TRAVAUX_TYPE . " THEN poa_dtd.titulaire
                        ELSE NULL
                    END AS TitCpte,
                    '65' AS CatTiers
            FROM titre t
                INNER JOIN demande_ d ON d.id = t.demande_id 
                LEFT JOIN demande_audit_energie dae ON dae.id = d.demande_audit_energie_id
                LEFT JOIN partenaire_ p_dae ON p_dae.id = dae.auditeur_id
                LEFT JOIN partenaire_identification pi_dae ON pi_dae.id = p_dae.partenaire_identification_id
                LEFT JOIN partenaire_adresse pa_dae ON pa_dae.id = p_dae.partenaire_adresse_id
                LEFT JOIN partenaire_option_auditeur poa_dae ON poa_dae.id = p_dae.partenaire_option_auditeur_id
                LEFT JOIN demande_audit_numerique dan ON dan.id = d.demande_audit_numerique_id
                LEFT JOIN partenaire_ p_dan ON p_dan.id = dan.auditeur_id
                LEFT JOIN partenaire_identification pi_dan ON pi_dan.id = p_dan.partenaire_identification_id
                LEFT JOIN partenaire_adresse pa_dan ON pa_dan.id = p_dan.partenaire_adresse_id
                LEFT JOIN partenaire_option_auditeur poa_dan ON poa_dan.id = p_dan.partenaire_option_auditeur_id
                LEFT JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
                LEFT JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
                LEFT JOIN partenaire_ p_dtd ON p_dtd.id = dtd.auditeur_id
                LEFT JOIN partenaire_identification pi_dtd ON pi_dtd.id = p_dtd.partenaire_identification_id
                LEFT JOIN partenaire_adresse pa_dtd ON pa_dtd.id = p_dtd.partenaire_adresse_id
                LEFT JOIN partenaire_option_auditeur poa_dtd ON poa_dtd.id = p_dtd.partenaire_option_auditeur_id
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

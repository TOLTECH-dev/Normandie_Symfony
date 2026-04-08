<?php

namespace App\Repository;

use App\Entity\Demande_;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Demande_auditEnergie;


class Demande_auditEnergieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande_auditEnergie::class);
    }

    /**
     * @param $logementId
     * @param $demandeType
     * @return array|false
     * @throws Exception
     */
    public function findOneByLogementAndType($logementId, $demandeType)
    {
        $query = "
            SELECT  dae.structure_id AS structure_id,
                    dae.conseiller_id AS conseiller_id,
                    dae.auditeur_id AS auditeur_id,
                    d.dateCP_id AS dateCP_id,
                    d.statut_id AS statut_id,
                    ps_dae.enabled AS partenaireStatutEnabled
            FROM demande_ d
                INNER JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN partenaire_ p_dae ON dae.auditeur_id = p_dae.id
                LEFT JOIN partenaire_statut ps_dae ON p_dae.partenaire_statut_id = ps_dae.id
            WHERE d.logement_id = " . $logementId . "
                AND d.type = '" . $demandeType . "'
        ";

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
    public function findByIdCustom($demandeId)
    {
        $query = "
            SELECT  d.id AS demandeId,
                    d.dateCP_id AS dateCPId,
                    d.beneficiaire_id AS beneficiaireId,
                    d.type AS demandeType,
                    d.statut_id AS statutId,
                    b.user_id AS beneficiaireUserId,
                    b.type AS beneficiaireType,
                    l.situation AS logementSituation,
                    dae.id AS auditEId,
                    dae.justificatif_propriete_alt AS demandeJustificatifProprieteAlt,
                    dae.justificatif_propriete_url AS demandeJustificatifProprieteUrl,
                    dae.piece_complement_alt AS demandePieceComplementAlt,
                    dae.piece_complement_url AS demandePieceComplementUrl,
                    dae.avis_imposition_alt AS demandeAvisImpositionAlt,
                    dae.avis_imposition_url AS demandeAvisImpositionUrl,
                    dae.avis_imposition_conjoint_alt AS demandeAvisImpositionConjointAlt,
                    dae.avis_imposition_conjoint_url AS demandeAvisImpositionConjointUrl,
                    dae.cgv AS demandeCGV,
                    dae.nb_pers_foyer AS demandeNbPersFoyer,
                    dae.revenu_demandeur AS demandeRevenuDemandeur,
                    dae.revenu_conjoint AS demandeRevenuConjoint,
                    dae.revenu_foyer AS demandeRevenuFoyer,
                    dae.carnet_numerique AS demandeCarnetNumerique,
                    dae.signature AS demandeSignature,
                    dae.is_accompagne_structure AS demandeIsAccompagneStructure,
                    s.id AS structureId,
                    si.nom AS structureNom,
                    sc.id AS conseillerId,
                    sc.nom AS conseillerNom,
                    sc.prenom AS conseillerPrenom,
                    p.id AS auditeurId,
                    pi.raison_sociale AS auditeurNom,
                    poa.id AS optionAuditeurId,
                    poa.rib_url AS optionAuditeurRibUrl,
                    poa.rib_alt AS optionAuditeurRibAlt,
                    r.id AS remboursementId
            FROM demande_ d
                INNER JOIN beneficiaire b ON d.beneficiaire_id = b.id
                INNER JOIN logement l ON d.logement_id = l.id
                LEFT JOIN demande_audit_energie dae ON d.demande_audit_energie_id = dae.id
                LEFT JOIN structure_ s ON dae.structure_id = s.id
                LEFT JOIN structure_identification si ON s.structure_identification_id = si.id
                LEFT JOIN structure_conseiller sc ON dae.conseiller_id = sc.id
                LEFT JOIN partenaire_ p ON dae.auditeur_id = p.id
                LEFT JOIN partenaire_identification pi ON p.partenaire_identification_id = pi.id
                LEFT JOIN partenaire_option_auditeur poa ON p.partenaire_option_auditeur_id = poa.id
                LEFT JOIN remboursement_ r ON d.id = r.demande_id
                LEFT JOIN remboursement_audit_energie rae ON r.remboursement_audit_energie_id = rae.id
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
     * @param $duplicateKey
     * @return array
     * @throws Exception
     */
    public function searchDuplicate($demandeType, $duplicateKey)
    {
        $query = null;

        // Cas Audit Energie
        if (Demande_::DEMANDE_AUDIT_ENERGIE_TYPE == $demandeType) {
            $query = "
                SELECT old_ae.duplicate_key
                FROM up_old_demande_audit_energie AS old_ae
                WHERE old_ae.duplicate_key = '".$duplicateKey."'
            ";

            $statement = $this->_em
                ->getConnection()
                ->prepare($query);
            $result = $statement->executeQuery();

            return $result->fetchAllAssociative();
        } else {
            return array();
        }
    }
}

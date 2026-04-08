<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Demande_travaux;


class Demande_travauxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande_travaux::class);
    }

    /**
     * @param $demandeId
     * @param $production_travauxNiveau_BBC2
     * @return array|false
     * @throws Exception
     */
    public function findByIdCustom($demandeId, $production_travauxNiveau_BBC2)
    {
        $query = "
            SELECT  d.id AS demandeId,
                    d.beneficiaire_id AS beneficiaireId,
                    d.dateCP_id AS dateCPId,
                    d.type AS demandeType,
                    d.statut_id AS statutId,
                    b.user_id AS beneficiaireUserId,
                    b.type AS beneficiaireType,
                    l.situation AS logementSituation,
                    dt.id AS travauxId,
                    dt.travaux_devis_id AS devisId,
                    dt.fiche_technique_id AS ficheTechniqueId,
                    dt.audit AS demandeAudit,
                    dt.justificatif_propriete_alt AS demandeJustificatifProprieteAlt,
                    dt.justificatif_propriete_url AS demandeJustificatifProprieteUrl,
                    dt.piece_complement_alt AS demandePieceComplementAlt,
                    dt.piece_complement_url AS demandePieceComplementUrl,
                    dt.avis_imposition_alt AS demandeAvisImpositionAlt,
                    dt.avis_imposition_url AS demandeAvisImpositionUrl,
                    dt.avis_imposition_conjoint_alt AS demandeAvisImpositionConjointAlt,
                    dt.avis_imposition_conjoint_url AS demandeAvisImpositionConjointUrl,
                    dt.nb_pers_foyer AS demandeNbPersFoyer,
                    dt.revenu_demandeur AS demandeRevenuDemandeur,
                    dt.revenu_conjoint AS demandeRevenuConjoint,
                    dt.revenu_foyer AS demandeRevenuFoyer,
                    dt.signature AS demandeSignature,
                    dt.is_accompagne_structure AS demandeIsAccompagneStructure,
                    s.id AS structureId,
                    si.nom AS structureNom,
                    sc.id AS conseillerId,
                    sc.nom AS conseillerNom,
                    sc.prenom AS conseillerPrenom,
                    r.id AS remboursementId,
                    t.numero_operation AS titreNumeroOperation,
                    IFNULL(rt.fiche_technique_id, rt2.fiche_technique_id) AS remboursementFicheTechniqueId,
                    r2.id AS remboursementId2,
                    t2.numero_operation AS titreNumeroOperation2
            FROM demande_ d
                INNER JOIN beneficiaire b ON d.beneficiaire_id = b.id
                INNER JOIN logement l ON d.logement_id = l.id
                LEFT JOIN demande_travaux dt ON d.demande_travaux_id = dt.id
                LEFT JOIN structure_ s ON dt.structure_id = s.id
                LEFT JOIN structure_identification si ON s.structure_identification_id = si.id
                LEFT JOIN structure_conseiller sc ON dt.conseiller_id = sc.id
                LEFT JOIN titre t ON d.id = t.demande_id AND t.numero_operation != '" . $production_travauxNiveau_BBC2 . "'
                LEFT JOIN remboursement_ r ON r.titre_id = t.id
                LEFT JOIN remboursement_travaux rt ON r.remboursement_travaux_id = rt.id
                LEFT JOIN titre t2 ON t2.demande_id = t.demande_id AND t2.id != t.id AND t2.numero_operation = '" . $production_travauxNiveau_BBC2 . "'
                LEFT JOIN remboursement_ r2 ON r2.titre_id = t2.id AND r2.id != r.id
                LEFT JOIN remboursement_travaux rt2 ON r2.remboursement_travaux_id = rt2.id
            WHERE d.id = " . $demandeId . "
            ORDER BY t.numero_operation ASC, t2.numero_operation ASC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }
}

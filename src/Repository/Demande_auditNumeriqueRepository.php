<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Demande_auditNumerique;


class Demande_auditNumeriqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande_auditNumerique::class);
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
                    dan.commitment AS demandeCommitment,
                    dan.signature AS demandeSignature,
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
                LEFT JOIN demande_audit_numerique dan ON d.demande_audit_numerique_id = dan.id
                LEFT JOIN structure_ s ON dan.structure_id = s.id
                LEFT JOIN structure_identification si ON s.structure_identification_id = si.id
                LEFT JOIN structure_conseiller sc ON dan.conseiller_id = sc.id
                LEFT JOIN partenaire_ p ON dan.auditeur_id = p.id
                LEFT JOIN partenaire_identification pi ON p.partenaire_identification_id = pi.id
                LEFT JOIN partenaire_option_auditeur poa ON p.partenaire_option_auditeur_id = poa.id
                LEFT JOIN remboursement_ r ON d.id = r.demande_id
                LEFT JOIN remboursement_audit_numerique ran ON r.remboursement_audit_numerique_id = ran.id
            WHERE d.id = " . $demandeId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }
}

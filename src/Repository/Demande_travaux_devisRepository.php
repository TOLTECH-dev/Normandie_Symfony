<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Demande_travaux_devis;


class Demande_travaux_devisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande_travaux_devis::class);
    }

    /**
     * @param $devisId
     * @return array|false
     * @throws Exception
     */
    public function findByIdCustom($devisId)
    {
        $query = "
            SELECT  d.dateCP_id AS dateCPId,
                    d.id AS demandeId,
                    b.user_id AS beneficiaireUserId,
                    dtd.id AS devisId,
                    dtd.beneficiaire_id AS beneficiaireId,
                    dtd.total_devis AS devisTotalDevis,
                    dtd.is_bonification_aide AS demandeTravauxDevisIsBonificationAide,
                    dtd.niveau AS devisNiveau,
                    dtd.audit_alt AS devisAuditAlt,
                    dtd.audit_url AS devisAuditUrl,
                    dtd.aide_anah AS devisAideAnah,
                    dtd.aide_habiter_mieux AS devisAideHabiterMieux,
                    dtd.type_ma_prime_renov_serenite_nom AS devisTypeMaPrimeRenovSereniteNom,
                    dtd.credit_impot AS devisCreditImpot,
                    dtd.type_ma_prime_renov_nom AS devisTypeMaPrimeRenovNom,
                    dtd.aide_region AS devisAideRegion,
                    dtd.CEE AS devisCEE,
                    dtd.EcoPTZ AS devisEcoPTZ,
                    dtd.EcoPTZ_banque AS devisEcoPTZBanque,
                    dtd.is_banque_access AS isBanqueAccess,
                    dtd.fonds_propres AS devisFondPropre,
                    dtd.aide_departement AS devisAideDepartement,
                    dtd.aide_departement_origine AS devisAideDepartementOrigine,
                    dtd.aide_intercommunalite AS devisAideIntercommunalite,
                    dtd.aide_intercommunalite_origine AS devisAideIntercommunaliteOrigine,
                    dtd.autre_aide AS devisAutreAide,
                    dtd.autre_aide_origine AS devisAutreAideOrigine,
                    dtd.autre_pret AS devisAutrePret,
                    dtd.autre_pret_banque AS devisAutrePretBanque,
                    dtd.total_plan AS devisTotalPlan,
                    dtd.acte_engagement_alt AS acteEngagementAlt,
                    dtd.acte_engagement_url AS acteEngagementUrl,
                    dtd.instruction_dossier_conforme AS instructionDossierConforme,
                    dtdu.id AS demandeTravauxDevisUploadId,
                    dtdu.type AS demandeTravauxDevisUploadType,
                    dtdu.biosource AS demandeTravauxDevisUploadBiosource,
                    dtdu.montant AS demandeTravauxDevisUploadMontant,
                    dtdu.entreprise_RGE AS demandeTravauxDevisUploadEntrepriseRGE,
                    dtdu.bonification AS demandeTravauxDevisUploadBonification,
                    dtdu.devis_document_url AS demandeTravauxDevisUploadDevisDocumentUrl,
                    pa.id AS auditeurId,
                    pai.raison_sociale AS auditeurNom,
                    pr.id AS renovateurId,
                    pri.raison_sociale AS renovateurNom,
                    becoptz.nom AS devisEcoPTZBanqueNom,
                    bap.nom AS devisAutrePretBanqueNom
            FROM demande_travaux_devis dtd
                INNER JOIN beneficiaire b ON dtd.beneficiaire_id = b.id
                LEFT JOIN demande_travaux dt ON dtd.id = dt.travaux_devis_id
                LEFT JOIN demande_ d ON dt.id = d.demande_travaux_id
                LEFT JOIN partenaire_ pa ON dtd.auditeur_id = pa.id
                LEFT JOIN partenaire_identification pai ON pa.partenaire_identification_id = pai.id
                LEFT JOIN partenaire_ pr ON dtd.renovateur_id = pr.id
                LEFT JOIN partenaire_identification pri ON pr.partenaire_identification_id = pri.id
                LEFT JOIN demande_travaux_devis_demande_travaux_devis_upload dtddtdu ON dtd.id = dtddtdu.demande_travaux_devis_id
                LEFT JOIN demande_travaux_devis_upload dtdu ON dtddtdu.demande_travaux_devis_upload_id = dtdu.id
                LEFT JOIN banque_ becoptz ON dtd.EcoPTZ_banque = becoptz.id
                LEFT JOIN banque_ bap ON dtd.autre_pret_banque = bap.id
            WHERE dtd.id = " . $devisId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @param $devisId
     * @return array|false
     * @throws Exception
     */
    public function findDateCPStatutById($devisId)
    {
        $query = "
            SELECT  d.dateCP_id AS dateCPId,
                    d.statut_id AS statutId
            FROM demande_travaux_devis dtd
                LEFT JOIN demande_travaux dt ON dtd.id = dt.travaux_devis_id
                LEFT JOIN demande_ d ON dt.id = d.demande_travaux_id
            WHERE dtd.id = " . $devisId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }


    /**
     *
     * @param int $devisUploadId
     *
     * @return Demande_travaux_devis|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByIdTravauxDevisUploadId(int $devisUploadId): ?Demande_travaux_devis
    {
        $qb = $this->createQueryBuilder('dtd')
            ->innerJoin('dtd.demande_travaux_devis_upload', 'dtdu')
            ->where('dtdu.id = :devisUploadId')
            ->setParameter('devisUploadId', $devisUploadId);

        return $qb->getQuery()->getOneOrNullResult();
    }
}

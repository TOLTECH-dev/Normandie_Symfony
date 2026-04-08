<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Demande_travaux_devis_upload;


class Demande_travaux_devis_uploadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande_travaux_devis_upload::class);
    }

    /**
     * @param $devisId
     * @return array
     * @throws Exception
     */
    public function findAllCustomByDevisId($devisId)
    {
        $query = "
                SELECT  dtdu.id AS demandeTravauxDevisUploadId,
                        dtdu.type AS demandeTravauxDevisUploadType,
                        dtdu.biosource AS demandeTravauxDevisUploadBiosource,
                        dtdu.montant AS demandeTravauxDevisUploadMontant,
                        dtdu.entreprise_RGE AS demandeTravauxDevisUploadEntrepriseRGE,
                        dtdu.bonification AS demandeTravauxDevisUploadBonification,
                        dtdu.devis_document_url AS demandeTravauxDevisUploadDevisDocumentUrl,
                        dtdu.devis_document_alt AS demandeTravauxDevisUploadDevisDocumentAlt
                FROM demande_travaux_devis_upload dtdu
                    INNER JOIN demande_travaux_devis_demande_travaux_devis_upload dtddtdu ON dtddtdu.demande_travaux_devis_upload_id = dtdu.id
                    INNER JOIN demande_travaux_devis dtd ON dtd.id = dtddtdu.demande_travaux_devis_id
                WHERE dtd.id =".$devisId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $devisId
     * @return array|false
     * @throws Exception
     */
    public function findDemande($devisId)
    {
        $query = "
                SELECT  dtddtdu.demande_travaux_devis_id AS demandeTravauxDevisId,
                        dtddtdu.demande_travaux_devis_upload_id AS demandeTravauxDevisUploadId
                FROM demande_travaux_devis_demande_travaux_devis_upload dtddtdu
                WHERE dtddtdu.demande_travaux_devis_upload_id =".$devisId
        ;

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }
}

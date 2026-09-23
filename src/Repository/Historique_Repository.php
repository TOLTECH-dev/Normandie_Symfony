<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Historique_;


class Historique_Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Historique_::class);
    }

    /**
     * Adresses des intervenants d'un dossier, pour nommer les destinataires de son historique.
     *
     * Cette lecture ne sert qu'à l'affichage : contrairement à celle qui établit les destinataires
     * proposés à l'envoi (CommentaireService), elle ne demande pas que le compte partenaire soit
     * actif — un auditeur depuis désactivé a bien reçu les courriels de l'époque, et son profil
     * doit pouvoir être affiché.
     *
     * @return array<string, string> profil => adresse, les adresses absentes étant écartées
     *
     * @throws Exception
     */
    public function findAdressesIntervenants(int $demandeId): array
    {
        $query = "
            SELECT  b.email AS beneficiaire,
                    COALESCE(sc_dae.email, sc_dan.email, sc_dt.email) AS conseiller,
                    COALESCE(pa_dae.email, pa_dan.email, pa_dtd_aud.email) AS auditeur,
                    pa_dtd.email AS renovateur
            FROM demande_ d
                INNER JOIN beneficiaire b ON b.id = d.beneficiaire_id
                LEFT JOIN demande_audit_energie dae ON dae.id = d.demande_audit_energie_id
                LEFT JOIN structure_conseiller sc_dae ON sc_dae.id = dae.conseiller_id
                LEFT JOIN partenaire_ p_dae ON p_dae.id = dae.auditeur_id
                LEFT JOIN partenaire_adresse pa_dae ON pa_dae.id = p_dae.partenaire_adresse_id
                LEFT JOIN demande_audit_numerique dan ON dan.id = d.demande_audit_numerique_id
                LEFT JOIN structure_conseiller sc_dan ON sc_dan.id = dan.conseiller_id
                LEFT JOIN partenaire_ p_dan ON p_dan.id = dan.auditeur_id
                LEFT JOIN partenaire_adresse pa_dan ON pa_dan.id = p_dan.partenaire_adresse_id
                LEFT JOIN demande_travaux dt ON dt.id = d.demande_travaux_id
                LEFT JOIN structure_conseiller sc_dt ON sc_dt.id = dt.conseiller_id
                LEFT JOIN demande_travaux_devis dtd ON dtd.id = dt.travaux_devis_id
                LEFT JOIN partenaire_ p_dtd ON p_dtd.id = dtd.renovateur_id
                LEFT JOIN partenaire_adresse pa_dtd ON pa_dtd.id = p_dtd.partenaire_adresse_id
                LEFT JOIN partenaire_ p_dtd_aud ON p_dtd_aud.id = dtd.auditeur_id
                LEFT JOIN partenaire_adresse pa_dtd_aud ON pa_dtd_aud.id = p_dtd_aud.partenaire_adresse_id
            WHERE d.id = :demandeId
        ";

        $ligne = $this->_em
            ->getConnection()
            ->executeQuery($query, ['demandeId' => $demandeId])
            ->fetchAssociative();

        if (false === $ligne) {
            return [];
        }

        $profils = [
            'Bénéficiaire' => $ligne['beneficiaire'] ?? null,
            'Conseiller H&E' => $ligne['conseiller'] ?? null,
            'Auditeur' => $ligne['auditeur'] ?? null,
            'Rénovateur' => $ligne['renovateur'] ?? null,
        ];

        return array_filter($profils, static fn (?string $adresse): bool => null !== $adresse && '' !== $adresse);
    }

    /**
     * @param $demandeId
     * @return array
     * @throws Exception
     */
    public function findCommentaire($demandeId)
    {
        $query = "
            SELECT  h.id AS historiqueId,
                    DATE_FORMAT(h.date_creation, '%d/%m/%Y à %H:%i:%s') AS commentaireDate,
                    u.email AS commentaireAuteur,
                    he.content AS commentaireContent,
                    he.recipient AS commentaireRecipient
            FROM historique_ h
                INNER JOIN historique_email he ON he.historique_id = h.id
                INNER JOIN user u ON u.username = h.auteur_creation
            WHERE h.demande_id = " . $demandeId . "
                AND h.action = 'commentaire'
            ORDER BY h.id DESC
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @param $demandeId
     * @return array
     * @throws Exception
     */
    public function countCommentaireByDemande($demandeId)
    {
        $query = "
            SELECT COUNT(h.id)
            FROM historique_ h
                INNER JOIN historique_email he ON he.historique_id = h.id
                INNER JOIN user u ON u.username = h.auteur_creation
            WHERE h.demande_id = " . $demandeId . "
                AND h.action = 'commentaire'
            GROUP BY h.id
        ";

        $statement = $this->_em
            ->getConnection()
            ->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}

<?php

declare(strict_types=1);

namespace App\Repository\Archive;

use App\Archive\InventairePieces;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Purge des coordonnées bancaires des bénéficiaires (Reco-03).
 *
 * « Suppression de la rubrique coordonnées bancaires : 5 ans après la date de
 * paiement » (compte rendu du 17/11/2026). La Région a retenu la date de
 * paiement, et non la date de désactivation du compte, parce que cette
 * dernière n'existe pas dans la base : `user.date_inactif` porte en réalité la
 * date de CRÉATION du compte, et s'y fier aurait purgé des milliers de comptes
 * actifs.
 *
 * Cette règle est INDÉPENDANTE de l'archivage : un dossier payé il y a plus de
 * cinq ans voit ses coordonnées bancaires disparaître, qu'il soit archivé ou
 * non. La commande tourne donc sur toute la base, pas sur un lot.
 *
 * Trois tables portent l'IBAN d'un bénéficiaire, et chacune porte aussi un RIB
 * en PDF qu'il faut effacer du disque. `partenaire_option_auditeur.iban`
 * relève des partenaires : c'est la Reco-13, pas celle-ci.
 */
class ArchivagePurgeBancaireRepository
{
    /**
     * Les trois origines, avec le chemin qui mène de l'instruction à la date
     * de paiement, et le type de pièce du RIB.
     *
     * @var array<string, array{table: string, jointures: string, dossier: int}>
     */
    final public const ORIGINES = [
        'travaux' => [
            'table' => 'remboursement_travaux_instruction',
            'jointures' => 'JOIN remboursement_travaux rt ON rt.instruction_id = i.id
                            JOIN remboursement_ r ON r.remboursement_travaux_id = rt.id',
            'dossier' => 9,
        ],
        'audit energie' => [
            'table' => 'remboursement_audit_energie_instruction',
            'jointures' => 'JOIN remboursement_audit_energie rae ON rae.instruction_id = i.id
                            JOIN remboursement_ r ON r.remboursement_audit_energie_id = rae.id',
            'dossier' => 5,
        ],
        'audit numerique' => [
            'table' => 'remboursement_audit_numerique_instruction',
            'jointures' => 'JOIN remboursement_audit_numerique ran ON ran.instruction_id = i.id
                            JOIN remboursement_ r ON r.remboursement_audit_numerique_id = ran.id',
            'dossier' => 8,
        ],
    ];

    /** Type de pièce du RIB, cf. InventairePieces::SUFFIXES. */
    private const TYPE_PIECE_RIB = 6;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $appRootDossierDataSymfony,
    ) {
    }

    /**
     * Les instructions dont les coordonnées bancaires sont échues.
     *
     * Une instruction SANS date de paiement n'est jamais retenue : le délai
     * court à partir du paiement, et une date absente ne permet pas de dire
     * qu'il est écoulé.
     *
     * @return array<string, list<array{id: int, rib: ?string, date_paiement: string}>>
     *
     * @throws DBALException
     */
    public function instructionsEchues(\DateTimeInterface $dateReference, int $annees = 5): array
    {
        $parOrigine = [];

        foreach (self::ORIGINES as $origine => $definition) {
            $sql = sprintf(
                <<<'SQL'
                SELECT i.id, i.rib_url AS rib, dr.date_RMH AS date_paiement
                  FROM %s i
                  %s
                  JOIN date_RMH dr ON dr.id = r.dateRMH_id
                 WHERE dr.date_RMH IS NOT NULL
                   AND dr.date_RMH < DATE_SUB(:date_reference, INTERVAL %d YEAR)
                   AND (
                        (i.iban IS NOT NULL AND i.iban <> '')
                     OR (i.bic IS NOT NULL AND i.bic <> '')
                     OR (i.rib_url IS NOT NULL AND i.rib_url <> '')
                   )
                 ORDER BY i.id
                SQL,
                $definition['table'],
                $definition['jointures'],
                $annees
            );

            $lignes = $this->connection->executeQuery(
                $sql,
                ['date_reference' => $dateReference->format('Y-m-d')]
            )->fetchAllAssociative();

            $parOrigine[$origine] = array_map(
                static fn (array $ligne): array => [
                    'id' => (int) $ligne['id'],
                    'rib' => $ligne['rib'],
                    'date_paiement' => (string) $ligne['date_paiement'],
                ],
                $lignes
            );
        }

        return $parOrigine;
    }

    /**
     * Chemin du RIB sur le disque, ou null si l'instruction n'en porte pas.
     */
    public function cheminDuRib(string $origine, int $instructionId, ?string $extension): ?string
    {
        if (null === $extension || '' === $extension) {
            return null;
        }

        $relatif = InventairePieces::cheminRelatif(
            ['dossier' => self::ORIGINES[$origine]['dossier'], 'type' => self::TYPE_PIECE_RIB],
            $instructionId,
            $extension
        );

        return rtrim($this->appRootDossierDataSymfony, '/') . '/uploads/' . $relatif;
    }

    /**
     * Vide les coordonnées bancaires des instructions données.
     *
     * Les champs sont mis à NULL, la ligne d'instruction est conservée : elle
     * porte l'instruction du remboursement, qui reste nécessaire aux
     * statistiques et au suivi.
     *
     * @param list<int> $ids
     *
     * @throws DBALException
     */
    public function purger(string $origine, array $ids): int
    {
        if ([] === $ids) {
            return 0;
        }

        $sql = sprintf(
            'UPDATE %s SET iban = NULL, bic = NULL, rib_url = NULL, rib_alt = NULL WHERE id IN (%s)',
            self::ORIGINES[$origine]['table'],
            ArchivageSuppressionRepository::listeDIdentifiants($ids)
        );

        return (int) $this->connection->executeStatement($sql);
    }
}

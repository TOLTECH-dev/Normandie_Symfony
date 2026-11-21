<?php

declare(strict_types=1);

namespace App\Repository\Partenaire;

use App\Archive\InventairePieces;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Coordonnées bancaires des partenaires et historique de leur activité
 * (Reco-13).
 *
 * « Suppression de la rubrique coordonnées bancaires : 5 ans après date de
 * désactivation du compte sur la plateforme. » Le RIB figure déjà dans les
 * dossiers de conventionnement et les coordonnées bancaires sur les synthèses
 * RMH : la copie détenue par la plateforme peut disparaître.
 *
 * Deux différences avec la purge des bénéficiaires (Reco-03), qui justifient
 * un service à part plutôt qu'une quatrième origine :
 *
 *  - la date de référence est la DÉSACTIVATION DU COMPTE, et non un paiement ;
 *  - `partenaire_statut.date_inactif` est, elle, une vraie date de
 *    désactivation — contrairement à `user.date_inactif`, qui porte la date de
 *    création et avait obligé à rebaser la règle des bénéficiaires. Aucune
 *    colonne n'est donc à ajouter ici.
 *
 * Seuls les AUDITEURS portent des données bancaires :
 * `partenaire_option_renovateur` ne contient que `id`, `type_acteur` et
 * `complement`. L'intitulé « IBAN auditeurs / rénovateurs » ne concerne en
 * pratique que les auditeurs.
 *
 * Le délai se calcule sur la DERNIÈRE désactivation, une réactivation remettant
 * le compteur à zéro — ce que `partenaire_statut` donne déjà, puisqu'une
 * réactivation y repasse `enabled` à 1. L'historique
 * (`partenaire_statut_historique`) répond au besoin de traçabilité, pas au
 * calcul du délai.
 */
class PurgeBancairePartenaireRepository
{
    /** Répertoire de dépôt des RIB de partenaires, cf. InventairePieces::DOSSIERS. */
    private const DOSSIER_PARTENAIRE_AUDITEUR = 6;

    /** Type de pièce du RIB, cf. InventairePieces::SUFFIXES. */
    private const TYPE_PIECE_RIB = 6;

    /** Date que portent les partenaires actifs : ce n'est pas une désactivation. */
    private const SENTINELLE = '1900-01-01';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $appRootDossierDataSymfony,
    ) {
    }

    /**
     * Les partenaires dont les coordonnées bancaires sont échues.
     *
     * Un partenaire ACTIF n'est jamais retenu, quelle que soit sa date : il
     * exerce, ses coordonnées servent. Un partenaire inactif sans date de
     * désactivation réelle ne l'est pas non plus — un délai ne peut pas être
     * échu si son point de départ est inconnu.
     *
     * @return list<array{
     *     partenaire_id: int,
     *     option_id: int,
     *     raison_sociale: ?string,
     *     date_inactif: string,
     *     iban: bool,
     *     bic: bool,
     *     rib: ?string
     * }>
     *
     * @throws DBALException
     */
    public function partenairesEchus(\DateTimeInterface $dateReference, int $annees = 5): array
    {
        $lignes = $this->connection->executeQuery(
            <<<'SQL'
            SELECT
                p.id                AS partenaire_id,
                oa.id               AS option_id,
                pi.raison_sociale,
                ps.date_inactif,
                oa.iban,
                oa.bic,
                oa.rib_url          AS rib
              FROM partenaire_ p
              JOIN partenaire_statut ps            ON ps.id = p.partenaire_statut_id
              JOIN partenaire_option_auditeur oa   ON oa.id = p.partenaire_option_auditeur_id
              LEFT JOIN partenaire_identification pi ON pi.id = p.partenaire_identification_id
             WHERE ps.enabled = 0
               AND ps.date_inactif IS NOT NULL
               AND ps.date_inactif <> :sentinelle
               AND ps.date_inactif < DATE_SUB(:date_reference, INTERVAL :annees YEAR)
               AND (
                    (oa.iban IS NOT NULL AND oa.iban <> '')
                 OR (oa.bic IS NOT NULL AND oa.bic <> '')
                 OR (oa.rib_url IS NOT NULL AND oa.rib_url <> '')
               )
             ORDER BY ps.date_inactif, p.id
            SQL,
            [
                'sentinelle' => self::SENTINELLE,
                'date_reference' => $dateReference->format('Y-m-d'),
                'annees' => $annees,
            ]
        )->fetchAllAssociative();

        return array_map(
            static fn (array $ligne): array => [
                'partenaire_id' => (int) $ligne['partenaire_id'],
                'option_id' => (int) $ligne['option_id'],
                'raison_sociale' => $ligne['raison_sociale'],
                'date_inactif' => (string) $ligne['date_inactif'],
                'iban' => null !== $ligne['iban'] && '' !== $ligne['iban'],
                'bic' => null !== $ligne['bic'] && '' !== $ligne['bic'],
                'rib' => $ligne['rib'],
            ],
            $lignes
        );
    }

    /**
     * Chemin du RIB sur le disque, ou null si le partenaire n'en porte pas.
     *
     * Le nom du fichier suit la même convention que les pièces de dossier :
     * répertoire du type, identifiant de la LIGNE PORTEUSE — ici celle de
     * `partenaire_option_auditeur` — puis suffixe et extension.
     */
    public function cheminDuRib(int $optionId, ?string $extension): ?string
    {
        if (null === $extension || '' === $extension) {
            return null;
        }

        $relatif = InventairePieces::cheminRelatif(
            ['dossier' => self::DOSSIER_PARTENAIRE_AUDITEUR, 'type' => self::TYPE_PIECE_RIB],
            $optionId,
            $extension
        );

        return rtrim($this->appRootDossierDataSymfony, '/') . '/uploads/' . $relatif;
    }

    /**
     * Vide les coordonnées bancaires des options d'auditeur données.
     *
     * La ligne est conservée : elle porte aussi le type d'acteur et les
     * compléments, qui n'ont rien de bancaire. Seuls les quatre champs de la
     * rubrique disparaissent.
     *
     * @param list<int> $optionIds
     *
     * @throws DBALException
     */
    public function purger(array $optionIds): int
    {
        if ([] === $optionIds) {
            return 0;
        }

        $liste = implode(',', array_map(static fn ($id): int => (int) $id, $optionIds));

        return (int) $this->connection->executeStatement(
            sprintf(
                'UPDATE partenaire_option_auditeur
                    SET domicile_bancaire = NULL, titulaire = NULL, iban = NULL, bic = NULL,
                        rib_url = NULL, rib_alt = NULL, rib_updated_at = NULL
                  WHERE id IN (%s)',
                $liste
            )
        );
    }

    /**
     * Les périodes d'activité d'un partenaire, reconstituées depuis le journal.
     *
     * C'est la restitution que demande la recommandation : « un historique
     * précis des comptes actifs / inactifs ». Une période court d'un changement
     * au suivant ; la dernière reste ouverte.
     *
     * Les lignes d'origine « reconstitution » sont celles déduites de l'état
     * connu à la mise en service : elles sont rendues telles quelles, avec leur
     * origine, pour qu'on ne les prenne pas pour des changements observés.
     *
     * @return list<array{etat: string, debut: ?string, fin: ?string, auteur: ?string, origine: string}>
     *
     * @throws DBALException
     */
    public function periodes(int $partenaireId): array
    {
        $changements = $this->connection->executeQuery(
            <<<'SQL'
            SELECT enabled, date_evenement, date_creation, auteur, origine
              FROM partenaire_statut_historique
             WHERE partenaire_id = :partenaire
             ORDER BY date_creation, id
            SQL,
            ['partenaire' => $partenaireId]
        )->fetchAllAssociative();

        return self::reconstituerPeriodes($changements);
    }

    /**
     * Reconstitue les périodes à partir des changements, du plus ancien au
     * plus récent.
     *
     * Fonction pure, et publique pour être testée : c'est elle qui porte la
     * restitution demandée par la recommandation, et une erreur ici ferait
     * dire à l'historique l'inverse de ce qui s'est passé.
     *
     * Règles : une période court d'un changement au suivant ; la dernière
     * reste ouverte ; deux changements consécutifs vers le même état sont
     * fusionnés — ils n'en décrivent qu'un seul, et afficher deux périodes
     * « inactif » qui se suivent laisserait croire à une réactivation entre
     * les deux.
     *
     * @param list<array{enabled: int|bool, date_evenement: ?string, date_creation: string, auteur: ?string, origine: string}> $changements
     *
     * @return list<array{etat: string, debut: ?string, fin: ?string, auteur: ?string, origine: string}>
     */
    public static function reconstituerPeriodes(array $changements): array
    {
        $significatifs = [];
        $dernierEtat = null;

        foreach ($changements as $changement) {
            $etat = (bool) $changement['enabled'];

            if ($etat === $dernierEtat) {
                continue;
            }

            $significatifs[] = $changement;
            $dernierEtat = $etat;
        }

        $periodes = [];

        foreach ($significatifs as $index => $changement) {
            $suivant = $significatifs[$index + 1] ?? null;

            $periodes[] = [
                'etat' => (bool) $changement['enabled'] ? 'actif' : 'inactif',
                'debut' => $changement['date_evenement'] ?? $changement['date_creation'],
                'fin' => null === $suivant
                    ? null
                    : ($suivant['date_evenement'] ?? $suivant['date_creation']),
                'auteur' => $changement['auteur'],
                'origine' => (string) $changement['origine'],
            ];
        }

        return $periodes;
    }
}

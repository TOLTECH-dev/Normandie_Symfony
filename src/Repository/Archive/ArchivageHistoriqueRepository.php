<?php

declare(strict_types=1);

namespace App\Repository\Archive;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Lecture de l'historique et des commentaires d'un dossier, pour les verser
 * dans l'archive (Reco-03).
 *
 * Le compte rendu du 17/11/2026 tranche : « Commentaires et historique : versés
 * à l'archive. Décision de supprimer ces informations après archives car ce
 * besoin n'est jamais arrivé depuis 2018. » Ce service lit donc ce qui doit
 * survivre dans l'archive avant de disparaître de la base.
 *
 * Trois tables, qu'il faut lire ensemble :
 *  - `historique_` ............ l'entrée (date, auteur, rôle, action, statut) ;
 *  - `historique_post` ........ son texte, y compris celui des commentaires ;
 *  - `historique_email` ....... les courriels envoyés à cette occasion.
 *
 * Volumes mesurés le 21/11/2026 sur toute la base : 411 706 textes d'entrées
 * (239 Mio) et 169 341 courriels (137 Mio). Compressés dans les archives, c'est
 * tenable — d'où le choix de verser le contenu intégral des courriels, et non
 * leurs seules métadonnées.
 */
class ArchivageHistoriqueRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * L'historique des dossiers donnés, du plus ancien au plus récent.
     *
     * @param list<int> $demandeIds
     *
     * @return array<int, list<array{
     *     date: string,
     *     auteur: string,
     *     role: ?string,
     *     action: string,
     *     statut: ?string,
     *     texte: ?string,
     *     courriels: list<array{destinataire: ?string, objet: ?string, expediteur: ?string, contenu: ?string}>
     * }>> demande_id => entrées
     *
     * @throws DBALException
     */
    public function historiqueDesDossiers(array $demandeIds): array
    {
        if ([] === $demandeIds) {
            return [];
        }

        $entrees = $this->connection->executeQuery(
            <<<'SQL'
            SELECT
                h.id,
                h.demande_id,
                h.date_creation,
                h.auteur_creation,
                h.auteur_role,
                h.action,
                h.statut_slug,
                -- Un historique peut porter plusieurs textes : on les concatene
                -- dans l ordre, plutot que de multiplier les lignes.
                GROUP_CONCAT(hp.post ORDER BY hp.id SEPARATOR '\n') AS texte
              FROM historique_ h
              LEFT JOIN historique_post hp ON hp.historique_id = h.id
             WHERE h.demande_id IN (:demande_ids)
             GROUP BY h.id, h.demande_id, h.date_creation, h.auteur_creation,
                      h.auteur_role, h.action, h.statut_slug
             ORDER BY h.demande_id, h.date_creation, h.id
            SQL,
            ['demande_ids' => $demandeIds],
            ['demande_ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        $courriels = $this->courrielsParHistorique(array_map(
            static fn (array $entree): int => (int) $entree['id'],
            $entrees
        ));

        $parDossier = [];

        foreach ($entrees as $entree) {
            $parDossier[(int) $entree['demande_id']][] = [
                'date' => (string) $entree['date_creation'],
                'auteur' => (string) $entree['auteur_creation'],
                'role' => $entree['auteur_role'],
                'action' => (string) $entree['action'],
                'statut' => $entree['statut_slug'],
                'texte' => $entree['texte'],
                'courriels' => $courriels[(int) $entree['id']] ?? [],
            ];
        }

        return $parDossier;
    }

    /**
     * @param list<int> $historiqueIds
     *
     * @return array<int, list<array{destinataire: ?string, objet: ?string, expediteur: ?string, contenu: ?string}>>
     *
     * @throws DBALException
     */
    private function courrielsParHistorique(array $historiqueIds): array
    {
        if ([] === $historiqueIds) {
            return [];
        }

        $lignes = $this->connection->executeQuery(
            <<<'SQL'
            SELECT historique_id, recipient, subject, sender, content
              FROM historique_email
             WHERE historique_id IN (:historique_ids)
             ORDER BY historique_id, id
            SQL,
            ['historique_ids' => $historiqueIds],
            ['historique_ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        $parHistorique = [];

        foreach ($lignes as $ligne) {
            $parHistorique[(int) $ligne['historique_id']][] = [
                'destinataire' => $ligne['recipient'],
                'objet' => $ligne['subject'],
                'expediteur' => $ligne['sender'],
                'contenu' => $ligne['content'],
            ];
        }

        return $parHistorique;
    }

    /**
     * Rend l'historique d'un dossier sous forme de texte, tel qu'il figurera
     * dans l'archive. Format délibérément simple et lisible sans outil : un
     * archiviste doit pouvoir l'ouvrir dans dix ans avec un éditeur de texte.
     *
     * @param list<array{date: string, auteur: string, role: ?string, action: string, statut: ?string, texte: ?string, courriels: list<array{destinataire: ?string, objet: ?string, expediteur: ?string, contenu: ?string}>}> $entrees
     */
    public function rendreEnTexte(int $demandeId, array $entrees): string
    {
        $lignes = [
            sprintf('Historique du dossier %d', $demandeId),
            str_repeat('=', 60),
            sprintf('%d entrees', count($entrees)),
            '',
        ];

        foreach ($entrees as $entree) {
            $lignes[] = sprintf(
                '[%s] %s%s',
                $entree['date'],
                $entree['action'],
                null !== $entree['statut'] && '' !== $entree['statut'] ? ' -> ' . $entree['statut'] : ''
            );
            $lignes[] = sprintf(
                '  auteur : %s%s',
                '' !== $entree['auteur'] ? $entree['auteur'] : '(inconnu)',
                null !== $entree['role'] && '' !== $entree['role'] ? ' (' . $entree['role'] . ')' : ''
            );

            if (null !== $entree['texte'] && '' !== trim($entree['texte'])) {
                $lignes[] = '  texte :';

                foreach (explode("\n", trim($entree['texte'])) as $ligneTexte) {
                    $lignes[] = '    ' . $ligneTexte;
                }
            }

            foreach ($entree['courriels'] as $courriel) {
                $lignes[] = sprintf(
                    '  courriel : %s -> %s : %s',
                    $courriel['expediteur'] ?? '(expediteur inconnu)',
                    $courriel['destinataire'] ?? '(destinataire inconnu)',
                    $courriel['objet'] ?? '(sans objet)'
                );

                if (null !== $courriel['contenu'] && '' !== trim($courriel['contenu'])) {
                    foreach (explode("\n", trim($courriel['contenu'])) as $ligneCourriel) {
                        $lignes[] = '    | ' . rtrim($ligneCourriel, "\r");
                    }
                }
            }

            $lignes[] = '';
        }

        return implode("\n", $lignes) . "\n";
    }
}

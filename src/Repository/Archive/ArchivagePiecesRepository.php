<?php

declare(strict_types=1);

namespace App\Repository\Archive;

use App\Archive\InventairePieces;
use App\Entity\ArchiveLot;
use App\Entity\ArchiveLotBeneficiaire;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Recensement des pièces à verser dans les archives (Reco-03).
 *
 * Service de LECTURE SEULE. Il rend, pour chaque pièce, son chemin attendu sur
 * le disque et sa présence réelle : une pièce référencée en base mais absente
 * du disque est DÉCLARÉE, jamais passée sous silence. Sur huit ans
 * d'historique, il y en aura — et c'est ce qui rend l'attestation de
 * destruction honnête.
 */
class ArchivagePiecesRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $appRootDossierDataSymfony,
    ) {
    }

    /**
     * Les pièces d'un lot, regroupées par compte puis par dossier.
     *
     * @return array<int, array<int, list<array{
     *     libelle: string,
     *     nom_archive: string,
     *     chemin: string,
     *     presente: bool,
     *     taille: int
     * }>>> user_id => demande_id => pièces
     *
     * @throws DBALException
     */
    public function piecesParCompteEtDossier(ArchiveLot $lot, string $etat = ArchiveLotBeneficiaire::ETAT_A_PRODUIRE): array
    {
        $parCompte = [];

        foreach ($this->lire($lot, $etat) as $piece) {
            $parCompte[$piece['user_id']][$piece['demande_id']][] = $this->decrire($piece);
        }

        return $parCompte;
    }

    /**
     * Comptage des pièces et de celles qui manquent sur le disque, par libellé.
     *
     * @return list<array{libelle: string, pieces: int, dossiers: int, presentes: int, octets: int}>
     *
     * @throws DBALException
     */
    public function syntheseParLibelle(ArchiveLot $lot, string $etat = ArchiveLotBeneficiaire::ETAT_A_PRODUIRE): array
    {
        $parLibelle = [];

        foreach ($this->lire($lot, $etat) as $piece) {
            $decrite = $this->decrire($piece);
            $libelle = $piece['libelle'];

            if (!isset($parLibelle[$libelle])) {
                $parLibelle[$libelle] = [
                    'libelle' => $libelle,
                    'pieces' => 0,
                    'dossiers' => [],
                    'presentes' => 0,
                    'octets' => 0,
                ];
            }

            ++$parLibelle[$libelle]['pieces'];
            $parLibelle[$libelle]['dossiers'][$piece['demande_id']] = true;

            if ($decrite['presente']) {
                ++$parLibelle[$libelle]['presentes'];
                $parLibelle[$libelle]['octets'] += $decrite['taille'];
            }
        }

        $synthese = array_map(
            static fn (array $ligne): array => [
                'libelle' => $ligne['libelle'],
                'pieces' => $ligne['pieces'],
                'dossiers' => count($ligne['dossiers']),
                'presentes' => $ligne['presentes'],
                'octets' => $ligne['octets'],
            ],
            array_values($parLibelle)
        );

        usort($synthese, static fn (array $a, array $b): int => $b['pieces'] <=> $a['pieces']);

        return $synthese;
    }

    /**
     * @return list<array{user_id: int, demande_id: int, porteur_id: int, extension: string, dossier_type: int, piece_type: int, libelle: string}>
     *
     * @throws DBALException
     */
    private function lire(ArchiveLot $lot, string $etat): array
    {
        $lignes = $this->connection->executeQuery(
            ArchivagePiecesSql::piecesDesDossiers(),
            [
                'lot_id' => $lot->getId(),
                'etat' => $etat,
            ]
        )->fetchAllAssociative();

        return array_map(
            static fn (array $ligne): array => [
                'user_id' => (int) $ligne['user_id'],
                'demande_id' => (int) $ligne['demande_id'],
                'porteur_id' => (int) $ligne['porteur_id'],
                'extension' => (string) $ligne['extension'],
                'dossier_type' => (int) $ligne['dossier_type'],
                'piece_type' => (int) $ligne['piece_type'],
                'libelle' => (string) $ligne['libelle'],
            ],
            $lignes
        );
    }

    /**
     * @param array{libelle: string, porteur_id: int, extension: string, dossier_type: int, piece_type: int} $piece
     *
     * @return array{libelle: string, nom_archive: string, chemin: string, presente: bool, taille: int}
     */
    private function decrire(array $piece): array
    {
        $relatif = InventairePieces::cheminRelatif(
            ['dossier' => $piece['dossier_type'], 'type' => $piece['piece_type']],
            $piece['porteur_id'],
            $piece['extension']
        );

        $chemin = rtrim($this->appRootDossierDataSymfony, '/') . '/uploads/' . $relatif;
        $presente = is_file($chemin) && is_readable($chemin);

        return [
            'libelle' => $piece['libelle'],
            'nom_archive' => InventairePieces::nomDansArchive(
                $piece['libelle'],
                $piece['porteur_id'],
                $piece['extension']
            ),
            'chemin' => $chemin,
            'presente' => $presente,
            'taille' => $presente ? (int) filesize($chemin) : 0,
        ];
    }
}

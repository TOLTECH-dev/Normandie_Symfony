<?php

declare(strict_types=1);

namespace App\Repository\Archive;

use App\Entity\ArchiveLot;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Écriture de la photo de l'export (Reco-03).
 *
 * C'est la seule donnée de dossier qui survivra à la destruction : elle est
 * donc écrite AVANT que quoi que ce soit ne soit supprimé, et déjà anonymisée.
 */
class ArchiveExportPhotoRepository
{
    /** Lignes par instruction INSERT. */
    private const TAILLE_PAQUET = 200;

    /**
     * Colonnes nommées de la table, et le libellé d'export dont chacune est
     * extraite. Seules celles qui se remplissent fidèlement figurent ici.
     *
     * @var array<string, string>
     */
    private const COLONNES_EXTRAITES = [
        'thematique' => 'Thématique',
        'demande_date' => 'Date demande',
        'demande_statut' => 'Statut demande',
        'commission_date' => 'Date commission',
        'date_rmh' => 'Date RMH',
    ];

    /** Celles de ces colonnes qui sont des dates, à normaliser. */
    private const COLONNES_DATE = ['demande_date', 'commission_date', 'date_rmh'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Enregistre la photo des dossiers d'un compte.
     *
     * @param array<int, array{complete: array<string, string>, anonymisee: array<string, string>}> $lignesParDossier
     *
     * @return int nombre de lignes écrites
     *
     * @throws DBALException
     */
    public function enregistrer(ArchiveLot $lot, int $userId, array $lignesParDossier): int
    {
        if ([] === $lignesParDossier) {
            return 0;
        }

        $paquets = array_chunk($lignesParDossier, self::TAILLE_PAQUET, true);
        $ecrites = 0;

        foreach ($paquets as $paquet) {
            $valeurs = [];
            $parametres = [];
            $i = 0;

            foreach ($paquet as $demandeId => $ligne) {
                $anonymisee = $ligne['anonymisee'];

                $valeurs[] = sprintf(
                    '(:lot%1$d, :user%1$d, :dem%1$d, :them%1$d, :dd%1$d, :st%1$d, :cd%1$d, :rmh%1$d, :don%1$d, NOW())',
                    $i
                );

                $parametres['lot' . $i] = $lot->getId();
                $parametres['user' . $i] = $userId;
                $parametres['dem' . $i] = $demandeId;
                $parametres['them' . $i] = $this->valeur($anonymisee, 'thematique');
                $parametres['dd' . $i] = $this->valeur($anonymisee, 'demande_date');
                $parametres['st' . $i] = $this->valeur($anonymisee, 'demande_statut');
                $parametres['cd' . $i] = $this->valeur($anonymisee, 'commission_date');
                $parametres['rmh' . $i] = $this->valeur($anonymisee, 'date_rmh');
                $parametres['don' . $i] = json_encode($anonymisee, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

                ++$i;
            }

            $sql = 'INSERT INTO archive_demande_export '
                . '(lot_id, user_id, demande_id, thematique, demande_date, demande_statut, '
                . 'commission_date, date_rmh, donnees, date_creation) VALUES '
                . implode(', ', $valeurs);

            $ecrites += (int) $this->connection->executeStatement($sql, $parametres);
        }

        return $ecrites;
    }

    /**
     * Les dossiers déjà photographiés d'un lot : la production est ainsi
     * reprenable après une interruption, sans dupliquer de ligne.
     *
     * @return array<int, true> identifiants de dossier en clés
     *
     * @throws DBALException
     */
    public function dossiersDejaPhotographies(ArchiveLot $lot): array
    {
        $ids = $this->connection->executeQuery(
            'SELECT demande_id FROM archive_demande_export WHERE lot_id = :lot_id',
            ['lot_id' => $lot->getId()]
        )->fetchFirstColumn();

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * @param array<string, string> $ligne
     */
    private function valeur(array $ligne, string $colonne): ?string
    {
        $brute = trim($ligne[self::COLONNES_EXTRAITES[$colonne]] ?? '');

        if ('' === $brute) {
            return null;
        }

        if (!in_array($colonne, self::COLONNES_DATE, true)) {
            return $brute;
        }

        // L'export écrit ses dates en Y-m-d, mais toutes ses colonnes ne
        // passent pas par le même formatage : une valeur illisible devient NULL
        // plutôt que de faire échouer l'insertion — la valeur d'origine reste
        // de toute façon dans le JSON.
        $date = date_create($brute);

        return false === $date ? null : $date->format('Y-m-d');
    }
}

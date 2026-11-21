<?php

declare(strict_types=1);

namespace App\Repository\Archive;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Lecture de l'éligibilité à l'archivage (Reco-03).
 *
 * Service de LECTURE SEULE : il ne connaît ni insertion, ni mise à jour, ni
 * suppression. La sélection peut donc être rejouée autant de fois que voulu
 * sans aucun effet de bord, ce qui est la première garantie demandée par la
 * procédure.
 */
class ArchivageEligibiliteRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Les comptes éligibles à la date donnée, un par ligne.
     *
     * @return list<array{user_id: int, enabled: bool, nb_dossiers: int, date_eligibilite: ?string, situation_determinante: ?string}>
     *
     * @throws DBALException
     */
    public function comptesEligibles(\DateTimeInterface $dateReference): array
    {
        $lignes = $this->connection->executeQuery(
            ArchivageEligibiliteSql::comptesEligibles(),
            ['date_reference' => $dateReference->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();

        return array_map(
            static fn (array $ligne): array => [
                'user_id' => (int) $ligne['user_id'],
                'enabled' => (bool) $ligne['enabled'],
                'nb_dossiers' => (int) $ligne['nb_dossiers'],
                'date_eligibilite' => $ligne['date_eligibilite'],
                'situation_determinante' => $ligne['situation_determinante'],
            ],
            $lignes
        );
    }

    /**
     * Synthèse par déclencheur, sans donnée nominative.
     *
     * @return list<array{declencheur: string, comptes: int, comptes_actifs: int, dossiers: int}>
     *
     * @throws DBALException
     */
    public function syntheseParDeclencheur(\DateTimeInterface $dateReference): array
    {
        $lignes = $this->connection->executeQuery(
            ArchivageEligibiliteSql::syntheseParDeclencheur(),
            ['date_reference' => $dateReference->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();

        return array_map(
            static fn (array $ligne): array => [
                'declencheur' => (string) $ligne['declencheur'],
                'comptes' => (int) $ligne['comptes'],
                'comptes_actifs' => (int) $ligne['comptes_actifs'],
                'dossiers' => (int) $ligne['dossiers'],
            ],
            $lignes
        );
    }

    /**
     * Parmi les comptes donnés, lesquels sont ENCORE éligibles ?
     *
     * Appelé avant de constituer les archives, puis de nouveau avant de les
     * détruire : plusieurs semaines séparent ces deux moments, et un dossier
     * peut avoir été ré-instruit entre-temps. C'est le verrou le plus important
     * de la procédure, et le seul qui dépende du temps qui passe.
     *
     * En un seul appel, et non un par compte : la requête parcourt l'ensemble
     * des dossiers et de l'historique, la lancer 5 000 fois serait hors de
     * portée.
     *
     * @param list<int> $userIds
     *
     * @return list<int> les identifiants encore éligibles, dans l'ordre croissant
     *
     * @throws DBALException
     */
    public function comptesEncoreEligibles(array $userIds, \DateTimeInterface $dateReference): array
    {
        if ([] === $userIds) {
            return [];
        }

        $lignes = $this->connection->executeQuery(
            ArchivageEligibiliteSql::comptesEligibles(true),
            [
                'date_reference' => $dateReference->format('Y-m-d H:i:s'),
                'user_ids' => $userIds,
            ],
            ['user_ids' => ArrayParameterType::INTEGER]
        )->fetchFirstColumn();

        return array_map('intval', $lignes);
    }
}

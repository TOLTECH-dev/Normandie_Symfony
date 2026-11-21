<?php

declare(strict_types=1);

namespace App\Security\Csp;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Lecture du journal des violations CSP (Reco-04).
 *
 * Deux lectures, qui ne servent pas la même chose :
 *
 *  - les violations de la politique EN VIGUEUR signalent une ressource
 *    réellement bloquée pour l'utilisateur. C'est un incident, à traiter ;
 *  - celles de la politique en OBSERVATION disent ce que le durcissement
 *    casserait. C'est la mesure, à lire avant de décider.
 */
class ViolationsCspRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array{directive: string, source: string, page: string, occurrences: int, premiere: string, derniere: string}>
     *
     * @throws DBALException
     */
    public function violations(bool $politiqueAppliquee, ?\DateTimeInterface $depuis = null, int $limite = 50): array
    {
        $sql = <<<'SQL'
            SELECT directive, source_bloquee AS source, page, occurrences,
                   premiere_vue AS premiere, derniere_vue AS derniere
              FROM csp_violation
             WHERE politique_appliquee = :appliquee
            SQL;

        $parametres = ['appliquee' => $politiqueAppliquee ? 1 : 0];

        if (null !== $depuis) {
            $sql .= ' AND derniere_vue >= :depuis';
            $parametres['depuis'] = $depuis->format('Y-m-d H:i:s');
        }

        $sql .= ' ORDER BY occurrences DESC, derniere_vue DESC LIMIT ' . max(1, $limite);

        $lignes = $this->connection->executeQuery($sql, $parametres)->fetchAllAssociative();

        return array_map(
            static fn (array $ligne): array => [
                'directive' => (string) $ligne['directive'],
                'source' => (string) $ligne['source'],
                'page' => (string) $ligne['page'],
                'occurrences' => (int) $ligne['occurrences'],
                'premiere' => (string) $ligne['premiere'],
                'derniere' => (string) $ligne['derniere'],
            ],
            $lignes
        );
    }

    /**
     * Synthèse par directive : c'est elle qui dit où porter l'effort.
     *
     * @return list<array{directive: string, cas: int, occurrences: int}>
     *
     * @throws DBALException
     */
    public function syntheseParDirective(bool $politiqueAppliquee, ?\DateTimeInterface $depuis = null): array
    {
        $sql = <<<'SQL'
            SELECT directive, COUNT(*) AS cas, SUM(occurrences) AS occurrences
              FROM csp_violation
             WHERE politique_appliquee = :appliquee
            SQL;

        $parametres = ['appliquee' => $politiqueAppliquee ? 1 : 0];

        if (null !== $depuis) {
            $sql .= ' AND derniere_vue >= :depuis';
            $parametres['depuis'] = $depuis->format('Y-m-d H:i:s');
        }

        $sql .= ' GROUP BY directive ORDER BY occurrences DESC';

        $lignes = $this->connection->executeQuery($sql, $parametres)->fetchAllAssociative();

        return array_map(
            static fn (array $ligne): array => [
                'directive' => (string) $ligne['directive'],
                'cas' => (int) $ligne['cas'],
                'occurrences' => (int) $ligne['occurrences'],
            ],
            $lignes
        );
    }

    /**
     * Purge les violations qu'on n'a plus revues depuis longtemps : une page
     * corrigée doit finir par disparaître du journal, sans quoi la mesure
     * reste encombrée de cas résolus.
     *
     * @throws DBALException
     */
    public function purger(\DateTimeInterface $avant): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM csp_violation WHERE derniere_vue < :avant',
            ['avant' => $avant->format('Y-m-d H:i:s')]
        );
    }
}

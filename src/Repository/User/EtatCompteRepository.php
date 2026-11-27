<?php

declare(strict_types=1);

namespace App\Repository\User;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Historique des états d'un compte (D26013, point 4).
 *
 * Lecture en DBAL et non par l'ORM : `user_etat_historique` n'est pas une
 * entité, exactement comme `partenaire_statut_historique` (Reco-13). Elle ne
 * porte aucune clé étrangère vers `user` — l'historique doit survivre à la
 * suppression du compte — et n'a donc rien à faire dans les associations
 * Doctrine.
 */
class EtatCompteRepository
{
    /** Libellés affichés, dans la langue de l'écran. */
    public const LIBELLES = [
        'creation' => 'Création du compte',
        'desactivation' => 'Désactivation',
        'reactivation' => 'Réactivation',
        'suppression' => 'Suppression',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Les changements d'état d'un compte, du plus récent au plus ancien.
     *
     * Le tri secondaire sur l'identifiant départage deux événements de la même
     * seconde — une désactivation suivie d'une réactivation immédiate, par
     * exemple — sans quoi l'ordre dépendrait du moteur.
     *
     * Le volume est faible par nature : quelques lignes par compte.
     *
     * @return list<array{etat: string, libelle: string, date_evenement: string, auteur: ?string, origine: string}>
     *
     * @throws DBALException
     */
    public function lesEtatsDe(int $userId): array
    {
        $lignes = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT etat, date_evenement, auteur, origine
            FROM user_etat_historique
            WHERE user_id = :user
            ORDER BY date_evenement DESC, id DESC
            SQL,
            ['user' => $userId]
        );

        return array_map(
            static fn (array $ligne): array => $ligne + [
                'libelle' => self::LIBELLES[$ligne['etat']] ?? $ligne['etat'],
            ],
            $lignes
        );
    }
}

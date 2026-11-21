<?php

declare(strict_types=1);

namespace App\Repository\Archive;

use App\Archive\InventaireSuppression;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Collecte et suppression des données d'un compte bénéficiaire (Reco-03).
 *
 * Deux méthodes, et l'ordre compte : `collecter()` relève tous les
 * identifiants pendant que le graphe est intact, `supprimer()` les efface des
 * feuilles vers la racine. Voir InventaireSuppression pour le détail et les
 * pièges.
 *
 * Les identifiants sont injectés dans les requêtes après conversion en
 * entiers, et non liés en paramètres. Ce n'est pas un relâchement : les
 * requêtes comportent jusqu'à deux ensembles différents, les ensembles vides
 * doivent devenir `IN (NULL)` plutôt que du SQL invalide, et `(int)` rend toute
 * injection impossible par construction. La règle est tenue en un seul endroit,
 * `listeDIdentifiants()`, qui est testée.
 */
class ArchivageSuppressionRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Relève tous les identifiants à supprimer pour un compte.
     *
     * @return array<string, list<int>> nom de l'ensemble => identifiants
     *
     * @throws DBALException
     */
    public function collecter(int $userId): array
    {
        $ensembles = ['beneficiaire' => $this->identifiants(
            'SELECT id FROM beneficiaire WHERE user_id = ' . $userId
        )];

        foreach (InventaireSuppression::COLLECTES as $nom => $collecte) {
            // Une collecte n'est passée que si TOUS ses ensembles d'appui sont
            // vides : une requête à deux branches doit pouvoir fonctionner sur
            // une seule.
            $utilisables = array_filter(
                $collecte['depend'],
                static fn (string $appui): bool => [] !== ($ensembles[$appui] ?? [])
            );

            if ([] === $utilisables) {
                $ensembles[$nom] = [];

                continue;
            }

            $ensembles[$nom] = $this->identifiants($this->resoudre($collecte['sql'], $ensembles));
        }

        return $ensembles;
    }

    /**
     * Supprime, dans l'ordre, et rend le nombre de lignes effacées par table.
     *
     * N'ouvre pas de transaction : c'est l'appelant qui en tient une par
     * compte, de sorte qu'un compte en échec n'entame pas les autres.
     *
     * @param array<string, list<int>> $ensembles
     *
     * @return array<string, int>
     *
     * @throws DBALException
     */
    public function supprimer(array $ensembles): array
    {
        $effacees = [];

        foreach (InventaireSuppression::SUPPRESSIONS as $suppression) {
            $ids = $ensembles[$suppression['ensemble']] ?? [];

            if ([] === $ids) {
                continue;
            }

            $sql = str_replace(':ids', self::listeDIdentifiants($ids), $suppression['sql']);
            $nombre = (int) $this->connection->executeStatement($sql);

            if ($nombre > 0) {
                $effacees[$suppression['table']] = ($effacees[$suppression['table']] ?? 0) + $nombre;
            }
        }

        return $effacees;
    }

    /**
     * Compte ce que la suppression effacerait, sans rien supprimer.
     *
     * @param array<string, list<int>> $ensembles
     *
     * @return array<string, int>
     *
     * @throws DBALException
     */
    public function compter(array $ensembles): array
    {
        $compte = [];

        foreach (InventaireSuppression::SUPPRESSIONS as $suppression) {
            $ids = $ensembles[$suppression['ensemble']] ?? [];

            if ([] === $ids) {
                continue;
            }

            $sql = str_replace(
                ['DELETE FROM', ':ids'],
                ['SELECT COUNT(*) FROM', self::listeDIdentifiants($ids)],
                $suppression['sql']
            );

            $nombre = (int) $this->connection->executeQuery($sql)->fetchOne();

            if ($nombre > 0) {
                $compte[$suppression['table']] = ($compte[$suppression['table']] ?? 0) + $nombre;
            }
        }

        return $compte;
    }

    /**
     * Anonymise le compte : les Archives refusent la suppression directe des
     * comptes, ils sont donc conservés, vidés de ce qui identifie la personne.
     *
     * Le nom de connexion est remplacé par un libellé stable et unique, et non
     * vidé : il est la clé d'authentification, et deux comptes anonymisés ne
     * doivent pas devenir indiscernables. Le mot de passe est remplacé par une
     * valeur qui ne correspond à aucune empreinte calculable, ce qui rend le
     * compte inutilisable sans le supprimer.
     *
     * @throws DBALException
     */
    public function anonymiserLeCompte(int $userId, string $reference): int
    {
        return (int) $this->connection->executeStatement(
            <<<'SQL'
            UPDATE user
               SET username           = :username,
                   username_canonical = :username,
                   email              = :email,
                   email_canonical    = :email,
                   firstname          = NULL,
                   lastname           = NULL,
                   password           = :password,
                   salt               = NULL,
                   confirmation_token = NULL,
                   password_requested_at = NULL,
                   enabled            = 0,
                   date_modif         = NOW(),
                   auteur_modif       = :auteur
             WHERE id = :id
            SQL,
            [
                'username' => sprintf('archive-%d', $userId),
                // `email` et `email_canonical` sont NOT NULL : on ne peut pas
                // les vider, on les remplace. Le domaine « .invalid » est
                // réservé par la RFC 2606 — aucun message ne pourra jamais y
                // être acheminé, même par erreur de configuration.
                'email' => sprintf('archive-%d@archive.invalid', $userId),
                'password' => sprintf('archive-%s', $reference),
                'auteur' => 'Archivage ' . $reference,
                'id' => $userId,
            ]
        );
    }

    /**
     * Les chemins des pièces déjà recensées restent à effacer du disque : la
     * base ne les porte pas, c'est l'appelant qui les fournit.
     *
     * @param list<string> $chemins
     *
     * @return array{supprimes: int, absents: int, echecs: list<string>}
     */
    public function supprimerLesFichiers(array $chemins): array
    {
        $supprimes = 0;
        $absents = 0;
        $echecs = [];

        foreach ($chemins as $chemin) {
            if (!is_file($chemin)) {
                ++$absents;

                continue;
            }

            if (unlink($chemin)) {
                ++$supprimes;
            } else {
                $echecs[] = $chemin;
            }
        }

        return ['supprimes' => $supprimes, 'absents' => $absents, 'echecs' => $echecs];
    }

    /**
     * Remplace chaque `:ensemble` par la liste de ses identifiants.
     *
     * @param array<string, list<int>> $ensembles
     */
    private function resoudre(string $sql, array $ensembles): string
    {
        // Les noms les plus longs d'abord : « :demande_travaux_devis » ne doit
        // pas être entamé par « :demande ».
        $noms = array_keys($ensembles);
        usort($noms, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($noms as $nom) {
            $sql = str_replace(':' . $nom, self::listeDIdentifiants($ensembles[$nom]), $sql);
        }

        return $sql;
    }

    /**
     * Une liste d'identifiants utilisable dans un `IN (...)`.
     *
     * Le `(int)` est la garantie : quelle que soit la valeur reçue, ce qui sort
     * est un entier. Un ensemble vide donne `NULL`, qui ne correspond à rien —
     * et non une parenthèse vide, qui serait du SQL invalide.
     *
     * @param list<int> $ids
     */
    public static function listeDIdentifiants(array $ids): string
    {
        if ([] === $ids) {
            return 'NULL';
        }

        return implode(',', array_map(static fn ($id): int => (int) $id, $ids));
    }

    /**
     * @return list<int>
     *
     * @throws DBALException
     */
    private function identifiants(string $sql): array
    {
        $valeurs = $this->connection->executeQuery($sql)->fetchFirstColumn();

        return array_values(array_unique(array_map('intval', array_filter(
            $valeurs,
            static fn ($valeur): bool => null !== $valeur
        ))));
    }
}

<?php

declare(strict_types=1);

namespace App\Security\Csp;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Enregistrement des violations de la politique de sécurité du contenu
 * (Reco-04, étape « observation »).
 *
 * Le contenu d'un rapport vient du NAVIGATEUR, donc de l'extérieur : il est
 * traité comme tel. Tout est tronqué, la chaîne de requête des URL est
 * retirée, et rien n'est jamais réinjecté dans une page.
 *
 * L'enregistrement est AGRÉGÉ : une ligne par (directive, source bloquée,
 * page), avec un compteur. Un navigateur envoie un rapport par violation et
 * par chargement ; sans agrégation, une seule page fautive noierait la mesure.
 */
final class JournalViolationsCsp
{
    /** Longueur maximale conservée pour une URL ou une source. */
    private const LONGUEUR_MAX = 500;

    /** Longueur maximale d'un nom de directive. */
    private const LONGUEUR_DIRECTIVE = 64;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Enregistre un rapport. Rend false si le rapport est inexploitable —
     * ce qui n'est pas une erreur : n'importe qui peut poster n'importe quoi
     * sur un point de collecte public.
     *
     * @param array<string, mixed> $rapport contenu de la clé « csp-report »
     */
    public function enregistrer(array $rapport): bool
    {
        $directive = $this->directive($rapport);
        $page = $this->page($rapport);

        if (null === $directive || null === $page) {
            return false;
        }

        $source = $this->source($rapport);
        $appliquee = 'enforce' === ($rapport['disposition'] ?? null);
        $empreinte = hash('sha256', $directive . '|' . $source . '|' . $page . '|' . ($appliquee ? '1' : '0'));

        try {
            $this->connection->executeStatement(
                <<<'SQL'
                INSERT INTO csp_violation
                    (empreinte, directive, source_bloquee, page, politique_appliquee,
                     occurrences, premiere_vue, derniere_vue)
                VALUES (:empreinte, :directive, :source, :page, :appliquee, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    occurrences = occurrences + 1,
                    derniere_vue = NOW()
                SQL,
                [
                    'empreinte' => $empreinte,
                    'directive' => $directive,
                    'source' => $source,
                    'page' => $page,
                    'appliquee' => $appliquee ? 1 : 0,
                ]
            );
        } catch (DBALException) {
            // Le point de collecte ne doit jamais faire échouer une page :
            // il répond 204 quoi qu'il arrive. Un incident de base est sans
            // conséquence pour l'utilisateur, qui n'attend rien de ce POST.
            return false;
        }

        return true;
    }

    /**
     * La directive violée. Les navigateurs renseignent « effective-directive »
     * ou, pour les plus anciens, « violated-directive » — qui peut porter les
     * sources à la suite du nom.
     *
     * @param array<string, mixed> $rapport
     */
    private function directive(array $rapport): ?string
    {
        $brute = $rapport['effective-directive'] ?? $rapport['violated-directive'] ?? null;

        if (!is_string($brute) || '' === trim($brute)) {
            return null;
        }

        // « style-src 'self' https://… » → « style-src »
        $nom = strtolower(trim(explode(' ', trim($brute))[0]));

        return 1 === preg_match('/^[a-z][a-z0-9-]{0,62}$/', $nom)
            ? substr($nom, 0, self::LONGUEUR_DIRECTIVE)
            : null;
    }

    /**
     * Ce qui a été refusé. Pour du code embarqué, les navigateurs renvoient
     * « inline » ou « eval » ; sinon une URL, dont on ne garde que l'origine
     * et le chemin.
     *
     * @param array<string, mixed> $rapport
     */
    private function source(array $rapport): string
    {
        $brute = $rapport['blocked-uri'] ?? '';

        if (!is_string($brute) || '' === trim($brute)) {
            return '(non precise)';
        }

        $brute = trim($brute);

        if (in_array($brute, ['inline', 'eval', 'self', 'data', 'blob'], true)) {
            return $brute;
        }

        return $this->sansChaineDeRequete($brute);
    }

    /**
     * @param array<string, mixed> $rapport
     */
    private function page(array $rapport): ?string
    {
        $brute = $rapport['document-uri'] ?? null;

        if (!is_string($brute) || '' === trim($brute)) {
            return null;
        }

        return $this->sansChaineDeRequete(trim($brute));
    }

    /**
     * Retire la chaîne de requête et le fragment.
     *
     * Ce n'est pas de la cosmétique : l'adresse d'une page de
     * réinitialisation porte le JETON de réinitialisation, et celle d'un
     * document son identifiant. Un journal technique n'a pas à les conserver.
     */
    private function sansChaineDeRequete(string $url): string
    {
        $coupe = strcspn($url, '?#');

        return substr(substr($url, 0, $coupe), 0, self::LONGUEUR_MAX);
    }
}

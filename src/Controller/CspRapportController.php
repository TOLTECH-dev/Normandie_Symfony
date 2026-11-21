<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\Csp\JournalViolationsCsp;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Point de collecte des violations de la politique de sécurité du contenu
 * (Reco-04, étape « observation »).
 *
 * C'est un point d'entrée PUBLIC et NON AUTHENTIFIÉ : les navigateurs y
 * postent sans session. Il est donc traité comme tel.
 *
 *  - il répond TOUJOURS 204, quoi qu'on lui envoie. Un point de collecte qui
 *    renverrait des erreurs renseignerait un attaquant sur ce qu'il contient
 *    et sur ce qui le fait échouer ;
 *  - le corps est plafonné : un rapport légitime pèse quelques centaines
 *    d'octets, pas un mégaoctet ;
 *  - il est limité en débit par adresse, comme les autres formulaires publics
 *    du téléservice (R7 de l'audit) : sans cela, il suffirait de le marteler
 *    pour remplir la base ;
 *  - rien de ce qu'il reçoit n'est journalisé en clair ni réaffiché : le
 *    contenu vient de l'extérieur.
 */
final class CspRapportController
{
    /**
     * Au-delà, le corps n'est pas lu. Un rapport de violation tient largement
     * dans cette taille ; ce qui la dépasse n'est pas un rapport.
     */
    private const TAILLE_MAX = 16384;

    public function __construct(
        private readonly JournalViolationsCsp $journal,
        private readonly RateLimiterFactory $cspReportIpLimiter,
    ) {
    }

    public function recevoir(Request $request): Response
    {
        // 204 sans corps : le navigateur n'attend rien, et nous n'avons rien
        // à lui dire.
        $reponse = new Response('', Response::HTTP_NO_CONTENT);

        if (!$this->cspReportIpLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $reponse;
        }

        $corps = $request->getContent();

        if ('' === $corps || strlen($corps) > self::TAILLE_MAX) {
            return $reponse;
        }

        try {
            $donnees = json_decode($corps, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $reponse;
        }

        if (!is_array($donnees)) {
            return $reponse;
        }

        foreach ($this->rapports($donnees) as $rapport) {
            $this->journal->enregistrer($rapport);
        }

        return $reponse;
    }

    /**
     * Deux formats coexistent, et il faut accepter les deux :
     *
     *  - l'ancien, posé par `report-uri` : un objet `{"csp-report": {...}}` ;
     *  - le nouveau, posé par `report-to` : un TABLEAU d'objets
     *    `[{"type": "csp-violation", "body": {...}}, ...]`.
     *
     * @param array<mixed> $donnees
     *
     * @return iterable<array<string, mixed>>
     */
    private function rapports(array $donnees): iterable
    {
        if (isset($donnees['csp-report']) && is_array($donnees['csp-report'])) {
            yield $donnees['csp-report'];

            return;
        }

        foreach ($donnees as $entree) {
            if (is_array($entree) && isset($entree['body']) && is_array($entree['body'])) {
                yield $entree['body'];
            }
        }
    }
}

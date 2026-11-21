<?php

declare(strict_types=1);

namespace App\Security\Csp;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Pose la politique de sécurité du contenu sur chaque réponse HTML (Reco-04).
 *
 * Remplace App\Listener\CspNonceInjectorListener, dont il reprend le nonce par
 * requête en corrigeant trois choses :
 *
 *  1. la politique n'est plus écrite en dur mais injectée, donc variable d'un
 *     environnement à l'autre — c'est l'un des points de la recommandation ;
 *
 *  2. le nonce n'est plus injecté dans les `<script>` par une expression
 *     régulière appliquée à TOUT le HTML de chaque réponse. Ce traitement
 *     coûtait à chaque page et réécrivait aussi les `<script` apparaissant
 *     dans une chaîne ou un commentaire. Le projet ne compte qu'UN script
 *     inline : il porte désormais son nonce explicitement, via la fonction
 *     Twig `csp_nonce()` ;
 *
 *  3. `apache_setenv()` a disparu : sans effet sous PHP-FPM, donc en
 *     production, et aucune configuration Apache ne pose d'en-tête CSP.
 *
 * Le mode OBSERVATION est la clé de la suite : une seconde politique, plus
 * stricte, peut être posée en `Content-Security-Policy-Report-Only` à côté de
 * celle en vigueur. Le navigateur applique la première et se contente de
 * signaler ce que la seconde aurait bloqué. C'est ce qui permet de mesurer
 * avant de durcir, sans jamais rien casser pour l'utilisateur.
 */
final class CspListener
{
    /** Attribut de requête portant le nonce, lu par la fonction Twig. */
    final public const ATTRIBUT_NONCE = 'csp_nonce';

    private readonly ?PolitiqueCsp $politique;

    private readonly ?PolitiqueCsp $politiqueObservee;

    /**
     * Les deux politiques sont facultatives, et cela sert :
     *
     *  - en production, les deux sont posées : celle en vigueur protège,
     *    celle en observation mesure ce que le durcissement casserait ;
     *  - en développement, seule l'observation est posée. La barre de
     *    débogage Symfony vit d'inline, et une politique appliquée la
     *    briserait — ce pour quoi la CSP était jusqu'ici purement et
     *    simplement désactivée en dev. Les développeurs voient désormais les
     *    violations sans subir les blocages.
     *
     * Le point de collecte est ajouté aux deux : une violation de la
     * politique en vigueur mérite d'être connue au moins autant qu'une
     * violation de la cible.
     */
    public function __construct(
        ?PolitiqueCsp $politique,
        ?PolitiqueCsp $politiqueObservee,
        private readonly bool $active,
        private readonly string $uriDeRapport = '',
    ) {
        $this->politique = $politique?->avecRapportVers($uriDeRapport);
        $this->politiqueObservee = $politiqueObservee?->avecRapportVers($uriDeRapport);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Un nonce est posé même quand la politique est inactive : un gabarit
        // qui appelle csp_nonce() ne doit pas rendre un attribut vide selon
        // l'environnement.
        $event->getRequest()->attributes->set(
            self::ATTRIBUT_NONCE,
            base64_encode(random_bytes(16))
        );
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->active) {
            return;
        }

        $response = $event->getResponse();

        if (!$this->estDuHtml($response)) {
            return;
        }

        $nonce = (string) $event->getRequest()->attributes->get(self::ATTRIBUT_NONCE, '');

        if ('' === $nonce) {
            return;
        }

        // La directive « report-to csp » ne désigne rien tant que le groupe
        // « csp » n'est pas déclaré par cet en-tête : sans lui, seul l'ancien
        // « report-uri » fonctionne — et il est en voie de disparition. La
        // mesure s'appauvrirait sans que rien ne le signale.
        if ('' !== $this->uriDeRapport) {
            $response->headers->set(
                'Reporting-Endpoints',
                sprintf('csp="%s"', $this->uriDeRapport)
            );
        }

        if (null !== $this->politique) {
            $response->headers->set('Content-Security-Policy', $this->politique->enTete($nonce));
        }

        if (null !== $this->politiqueObservee) {
            $response->headers->set(
                'Content-Security-Policy-Report-Only',
                $this->politiqueObservee->enTete($nonce)
            );
        }
    }

    /**
     * Seules les pages HTML sont concernées : poser la politique sur un PDF ou
     * un CSV n'a aucun effet et alourdit chaque téléchargement.
     */
    private function estDuHtml(Response $response): bool
    {
        $type = $response->headers->get('Content-Type');

        return null !== $type && str_contains(strtolower($type), 'text/html');
    }
}

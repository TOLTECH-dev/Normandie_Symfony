<?php

namespace App\Listener;


use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class CspNonceInjectorListener
{
    private $nonce;

    /**
     *
     * Génération des nonce au niveau de toutes les requests
     * @param RequestEvent $event
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Générer un secure nonce
        $this->nonce = base64_encode(random_bytes(16));

        // Enregistrer me nonce dans les attributs de la request pour qu'elle soit accessible partout
        $request->attributes->set('csp_nonce', $this->nonce);

        // Set as Apache environment variable
        if (function_exists('apache_setenv')) {
            apache_setenv('CSP_NONCE', $this->nonce);
        }
    }

    /**
     * Inject les nonce dans les requetes pour pouvoir executer les consent Didomi
     *
     * @param ResponseEvent $event
     * @return void
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type');

        if (strpos($contentType, 'text/html') === false) {
            return;
        }

        $content = $response->getContent();

        // Injecter le nonce dans tous les inline script tags
        $content = $this->injectNonceIntoScripts($content);

        $response->setContent($content);

        // Set CSP header avec nonce
        $nonce = $this->nonce; // généré dans ton listener

        $csp = sprintf(
            "default-src 'self'; " .
            "script-src 'self' 'nonce-%s' http://fcp.integ01.dev-franceconnect.fr https://fcp.integ01.dev-franceconnect.fr https://app.franceconnect.gouv.fr https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; " .
            "style-src 'self' 'unsafe-inline' http://fcp.integ01.dev-franceconnect.fr https://fcp.integ01.dev-franceconnect.fr https://app.franceconnect.gouv.fr https://fonts.googleapis.com; " .
            "img-src 'self' data:; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "frame-src 'self' https://www.google.com/recaptcha/; " .
            "connect-src 'self' https://www.google.com https://www.gstatic.com; " .
            "base-uri 'self'; " .
            "form-action 'self'; " .
            "frame-ancestors 'self' https://www.google.com https://www.gstatic.com;",
            $nonce
        );

        // Temporarily use report-only mode to test
        // Change back to 'Content-Security-Policy' when ready
        //$response->headers->set('Content-Security-Policy-Report-Only', $csp);
        $response->headers->set('Content-Security-Policy', $csp);
    }

    /**
     * retourne la valeur de nonce
     *
     * @return void
     */
    public function getNonce()
    {
        return $this->nonce;
    }

    /**
     * @param $content
     *
     * @return array|string|string[]|null
     */
    private function injectNonceIntoScripts($content)
    {
        // Pattern to match inline script tags that don't already have a nonce
        // Matches: <script> or <script type="..."> but not <script src="...">
        $pattern = '/<script(?![^>]*\snonce=)(?![^>]*\ssrc=)([^>]*)>/i';

        $replacement = sprintf('<script nonce="%s"$1>', $this->nonce);

        return preg_replace($pattern, $replacement, $content);
    }
}
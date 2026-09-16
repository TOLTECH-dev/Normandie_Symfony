<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Réponse de restitution des fichiers déposés par les utilisateurs.
 *
 * Vulnérabilité 4 / recommandation 8 de l'audit ALMOND : un document téléversé puis affiché par le
 * navigateur s'exécute dans l'origine du site. Les auditeurs l'ont démontré avec un XML polyglotte,
 * accepté comme XML au dépôt mais interprété comme une image SVG porteuse de script, qui affichait
 * une fausse page de connexion. Le même risque existe pour d'autres formats (PDF, HTML).
 *
 * Tous les fichiers sont donc renvoyés en pièce jointe. Les en-têtes suivants ne servent qu'en
 * défense en profondeur, si un navigateur passait outre la disposition : type neutre, interdiction
 * de deviner le type d'après le contenu, et politique interdisant toute exécution.
 */
final class SecureFileResponseBuilder
{
    /**
     * @param string      $path     chemin absolu du fichier à renvoyer
     * @param string|null $filename nom proposé à l'enregistrement ; par défaut celui du fichier
     *
     * @throws NotFoundHttpException si le fichier n'existe pas ou n'est pas lisible
     */
    public function download(string $path, ?string $filename = null): BinaryFileResponse
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new NotFoundHttpException('Document introuvable.');
        }

        $response = new BinaryFileResponse($path);

        // Téléchargement au lieu d'un affichage : le contenu n'est plus interprété par le navigateur.
        // Symfony calcule lui-même la variante ASCII du nom pour les navigateurs anciens.
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename ?? basename($path));

        // Sans cela, le type est deviné d'après le contenu : un .xml contenant du SVG ressort en
        // image/svg+xml, que le navigateur affiche.
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        $response->headers->set('X-Frame-Options', 'DENY');

        // Documents personnels (avis d'imposition, RIB...) : pas de cache partagé.
        $response->setPrivate();

        return $response;
    }
}

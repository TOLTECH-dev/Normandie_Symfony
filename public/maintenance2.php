<?php
/**
 * Enveloppe de la page de maintenance du Chèque éco-énergie Normandie.
 *
 * Ne contient aucun contenu : elle pose le statut et les en-têtes, puis recopie
 * maintenance-contenu.html. Ce découpage est volontaire — voir le générateur,
 * evolutions/securite/34_generateur_page_maintenance.php.
 *
 * Le contenu étant LU et non compilé, le modifier ne demande ni rechargement de php-fpm ni
 * changement de nom, même avec « opcache.validate_timestamps=0 ».
 *
 * Ce fichier-ci, en revanche, est compilé : toute modification de ces dix lignes exigera un
 * rechargement de php-fpm sur les serveurs. Il n'a pas vocation à changer.
 *
 * LIMITE : la page reste exécutée par PHP. Si l'interruption porte sur PHP lui-même (montée de
 * version, php-fpm arrêté), elle ne s'affichera pas davantage que le site ; il faut alors
 * qu'Apache serve maintenance-contenu.html directement, par ErrorDocument.
 *
 * Le code 503 et l'en-tête Retry-After sont indispensables : sans eux, les moteurs de recherche
 * indexent cette page à la place du site, et la supervision le croit en bon état.
 */

const DUREE_ESTIMEE_SECONDES = 3600;

$contenu = __DIR__ . '/maintenance-contenu.html';

http_response_code(503);
header('Retry-After: ' . DUREE_ESTIMEE_SECONDES);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex');

if (is_readable($contenu)) {
    readfile($contenu);
} else {
    // Repli minimal : mieux vaut une phrase nue qu'une page blanche.
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
       . '<title>Site provisoirement indisponible</title></head><body>'
       . '<h1>Site provisoirement indisponible</h1>'
       . '<p>Le service est momentanément interrompu. Merci de réessayer dans quelques instants.</p>'
       . '</body></html>';
}

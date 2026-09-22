<?php

declare(strict_types=1);

/**
 * Faux service clamd, pour les tests : accepte une connexion, lit l'envoi jusqu'au morceau de
 * taille nulle, consigne les octets reçus, puis renvoie la réponse demandée.
 *
 * Lancé dans un processus séparé par ClamavScannerTest (le client et le service ne peuvent pas
 * cohabiter dans le même processus : chacun attendrait l'autre).
 *
 * Usage : php faux_clamd.php <fichier-journal> <réponse>
 * Écrit sur sa sortie standard la ligne « prêt <hôte:port> ».
 *
 * La réponse est passée sans son octet nul final, qu'un argument de commande ne peut pas porter :
 * il est ajouté ici, comme le fait clamd.
 */
$journal = $argv[1] ?? '';
$reponse = ($argv[2] ?? 'stream: OK') . chr(0);

$erreur = 0;
$message = '';
$service = stream_socket_server('tcp://127.0.0.1:0', $erreur, $message);
if (false === $service) {
    fwrite(STDERR, "Service non démarré : $message\n");
    exit(1);
}

echo 'prêt ' . stream_socket_get_name($service, false) . "\n";
flush();

$client = @stream_socket_accept($service, 10);
if (false === $client) {
    exit(2);
}

$recu = '';
$finEnvoi = false;
stream_set_timeout($client, 5);

while (!$finEnvoi) {
    $morceau = fread($client, 8192);
    if (false === $morceau || '' === $morceau) {
        break;
    }
    $recu .= $morceau;

    // L'envoi se termine par une taille nulle sur 4 octets.
    $finEnvoi = str_ends_with($recu, pack('N', 0));
}

if ('' !== $journal) {
    file_put_contents($journal, $recu);
}

fwrite($client, $reponse);
fclose($client);
fclose($service);

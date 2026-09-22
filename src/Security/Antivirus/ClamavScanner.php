<?php

declare(strict_types=1);

namespace App\Security\Antivirus;

use Psr\Log\LoggerInterface;

/**
 * Analyse un fichier en le transmettant au service résident ClamAV (clamd).
 *
 * Le fichier est envoyé par flux (commande INSTREAM) plutôt que par son chemin : le service n'a
 * ainsi pas besoin d'accéder au dossier des fichiers temporaires de PHP, ce qui évite toute
 * question de droits et fonctionne même si clamd tourne sur une autre machine.
 *
 * Protocole (documentation clamd, section « INSTREAM ») : la commande est encadrée par un octet
 * nul, chaque morceau de fichier est précédé de sa taille sur 4 octets en gros-boutien, et un
 * morceau de taille nulle termine l'envoi. La réponse vaut « stream: OK » ou
 * « stream: <signature> FOUND ».
 */
final class ClamavScanner implements AntivirusScanner
{
    private const TAILLE_MORCEAU = 8192;

    /**
     * @param string $dsn         « unix:///var/run/clamav/clamd.ctl » ou « tcp://127.0.0.1:3310 »
     * @param int    $delaiMax    délai d'attente, en secondes, à la connexion comme à la lecture
     * @param int    $tailleMaxFlux taille maximale acceptée par clamd (StreamMaxLength), en octets
     */
    public function __construct(
        private readonly string $dsn,
        private readonly int $delaiMax,
        private readonly int $tailleMaxFlux,
        private readonly LoggerInterface $logger
    ) {
    }

    public function analyser(string $chemin): ResultatAnalyse
    {
        if (!is_file($chemin) || !is_readable($chemin)) {
            throw new AntivirusIndisponibleException(sprintf('Fichier illisible pour l\'analyse : %s', $chemin));
        }

        $taille = (int) filesize($chemin);
        if ($taille > $this->tailleMaxFlux) {
            throw new AntivirusIndisponibleException(sprintf(
                'Fichier de %d octets supérieur à la limite acceptée par l\'antivirus (%d octets).',
                $taille,
                $this->tailleMaxFlux
            ));
        }

        $fichier = fopen($chemin, 'rb');
        if (false === $fichier) {
            throw new AntivirusIndisponibleException('Ouverture du fichier impossible pour l\'analyse.');
        }

        $socket = $this->ouvrirConnexion();

        try {
            $this->ecrire($socket, "zINSTREAM\0");

            while (!feof($fichier)) {
                $morceau = fread($fichier, self::TAILLE_MORCEAU);
                if (false === $morceau) {
                    throw new AntivirusIndisponibleException('Lecture du fichier interrompue pendant l\'analyse.');
                }
                if ('' === $morceau) {
                    continue;
                }
                $this->ecrire($socket, pack('N', \strlen($morceau)) . $morceau);
            }

            // Morceau de taille nulle : fin de l'envoi, clamd répond.
            $this->ecrire($socket, pack('N', 0));

            $reponse = trim((string) stream_get_contents($socket));
        } finally {
            fclose($fichier);
            fclose($socket);
        }

        return $this->interpreter($reponse);
    }

    /**
     * @return resource
     */
    private function ouvrirConnexion()
    {
        $erreur = 0;
        $message = '';
        $socket = @stream_socket_client($this->dsn, $erreur, $message, $this->delaiMax);

        if (false === $socket) {
            $this->logger->error('Antivirus injoignable', ['dsn' => $this->dsn, 'erreur' => $message]);

            throw new AntivirusIndisponibleException(sprintf('Antivirus injoignable (%s) : %s', $this->dsn, $message));
        }

        stream_set_timeout($socket, $this->delaiMax);

        return $socket;
    }

    /**
     * @param resource $socket
     */
    private function ecrire($socket, string $donnees): void
    {
        $ecrits = @fwrite($socket, $donnees);

        if (false === $ecrits || $ecrits !== \strlen($donnees)) {
            throw new AntivirusIndisponibleException('Envoi du fichier à l\'antivirus interrompu.');
        }
    }

    private function interpreter(string $reponse): ResultatAnalyse
    {
        if (str_ends_with($reponse, 'OK') && !str_contains($reponse, 'FOUND')) {
            return ResultatAnalyse::sain();
        }

        if (preg_match('/^stream: (.+) FOUND$/', $reponse, $correspondances)) {
            return ResultatAnalyse::infecte($correspondances[1]);
        }

        // « INSTREAM size limit exceeded », « ERROR », ou réponse inconnue : on ne conclut pas.
        $this->logger->error('Réponse inattendue de l\'antivirus', ['reponse' => $reponse]);

        throw new AntivirusIndisponibleException(sprintf('Réponse inattendue de l\'antivirus : %s', $reponse));
    }
}

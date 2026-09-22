<?php

declare(strict_types=1);

namespace App\Security\Antivirus;

use Psr\Log\LoggerInterface;

/**
 * Analyse neutralisée : tout fichier est déclaré sain.
 *
 * Réservé aux environnements sans service antivirus, à commencer par les postes de développement.
 * En recette et en production, le service ClamAV doit être actif (SECURITY_ANTIVIRUS_ENABLED=true) :
 * la trace ci-dessous permet de repérer une mise en service oubliée.
 */
final class AnalyseDesactiveeScanner implements AntivirusScanner
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function analyser(string $chemin): ResultatAnalyse
    {
        $this->logger->notice('Fichier déposé sans analyse antivirale : l\'analyse est désactivée sur cet environnement.');

        return ResultatAnalyse::sain();
    }
}

<?php

declare(strict_types=1);

namespace App\Security\Antivirus;

/**
 * Analyse antivirale d'un fichier déposé (vulnérabilité 5 / recommandation 9 de l'audit ALMOND).
 *
 * L'analyse a lieu au moment du dépôt, sur le fichier temporaire, avant tout enregistrement.
 */
interface AntivirusScanner
{
    /**
     * @param string $chemin chemin du fichier à analyser
     *
     * @throws AntivirusIndisponibleException si l'analyse n'a pas pu être menée
     */
    public function analyser(string $chemin): ResultatAnalyse;
}

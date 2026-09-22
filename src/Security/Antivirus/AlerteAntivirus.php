<?php

declare(strict_types=1);

namespace App\Security\Antivirus;

/**
 * Signalement d'un fichier infecté refusé au dépôt (vulnérabilité 5 / recommandation 9).
 */
interface AlerteAntivirus
{
    /**
     * @param string $menace signature rapportée par l'antivirus
     * @param string $champ  type de pièce concernée, sans donnée nominative
     */
    public function signalerFichierInfecte(string $menace, string $champ): void;
}

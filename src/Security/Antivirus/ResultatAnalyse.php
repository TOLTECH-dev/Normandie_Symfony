<?php

declare(strict_types=1);

namespace App\Security\Antivirus;

/**
 * Résultat d'une analyse antivirale : fichier sain, ou nom de la menace détectée.
 */
final class ResultatAnalyse
{
    private function __construct(
        private readonly bool $sain,
        private readonly ?string $menace
    ) {
    }

    public static function sain(): self
    {
        return new self(true, null);
    }

    public static function infecte(string $menace): self
    {
        return new self(false, $menace);
    }

    public function estSain(): bool
    {
        return $this->sain;
    }

    /**
     * Nom de la signature détectée, tel que rapporté par l'antivirus (null si le fichier est sain).
     */
    public function menace(): ?string
    {
        return $this->menace;
    }
}

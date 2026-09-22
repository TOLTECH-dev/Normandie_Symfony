<?php

declare(strict_types=1);

namespace App\Security\Antivirus;

use Psr\Log\LoggerInterface;

/**
 * Choisit l'analyse à utiliser selon l'environnement : le service ClamAV là où il existe, une
 * analyse neutralisée sur les postes de développement (SECURITY_ANTIVIRUS_ENABLED=false).
 */
final class ScannerFactory
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $analyseActive,
        private readonly string $dsn,
        private readonly int $delaiMax,
        private readonly int $tailleMaxFlux
    ) {
    }

    public function creer(): AntivirusScanner
    {
        if (!$this->analyseActive) {
            return new AnalyseDesactiveeScanner($this->logger);
        }

        return new ClamavScanner($this->dsn, $this->delaiMax, $this->tailleMaxFlux, $this->logger);
    }
}

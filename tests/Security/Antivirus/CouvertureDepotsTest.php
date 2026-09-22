<?php

declare(strict_types=1);

namespace App\Tests\Security\Antivirus;

use PHPUnit\Framework\TestCase;

/**
 * Vulnérabilité 5 : aucun point de dépôt ne doit échapper à l'analyse antivirale.
 *
 * Ce test parcourt les entités et vérifie que chaque propriété recevant un fichier porte à la fois
 * la contrainte de type (Assert\File, déjà en place) et la contrainte d'analyse. Un nouveau champ
 * de dépôt ajouté sans analyse fera échouer la suite.
 */
class CouvertureDepotsTest extends TestCase
{
    private const ENTITES = __DIR__ . '/../../../src/Entity';

    public function testChaqueProprieteRecevantUnFichierEstAnalysee(): void
    {
        $manquantes = [];
        $total = 0;

        foreach (glob(self::ENTITES . '/*.php') as $fichier) {
            $lignes = explode("\n", (string) file_get_contents($fichier));

            foreach ($lignes as $index => $ligne) {
                if (!preg_match('/private \?UploadedFile \$(\w+)/', $ligne, $correspondances)) {
                    continue;
                }

                ++$total;
                $attributs = implode("\n", \array_slice($lignes, max(0, $index - 12), min(12, $index)));

                if (!str_contains($attributs, 'FichierSansVirus')) {
                    $manquantes[] = basename($fichier) . '::$' . $correspondances[1] . ' (analyse antivirale)';
                }

                if (!str_contains($attributs, 'Assert\File(')) {
                    $manquantes[] = basename($fichier) . '::$' . $correspondances[1] . ' (contrainte de type)';
                }
            }
        }

        self::assertGreaterThanOrEqual(33, $total, 'Les points de dépôt connus ne sont plus tous détectés');
        self::assertSame([], $manquantes, "Points de dépôt sans contrôle :\n" . implode("\n", $manquantes));
    }
}

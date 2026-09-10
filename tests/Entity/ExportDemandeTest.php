<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ExportDemande;
use PHPUnit\Framework\TestCase;

class ExportDemandeTest extends TestCase
{
    public function testLesRecherchesSontEnregistreesCommeDesDonnees(): void
    {
        $filters = ['beneficiaireIdentifiant' => 'Hélène " OR "1"="1', 'demandeType' => '31'];

        $export = (new ExportDemande())->setFilters($filters);

        self::assertSame($filters, $export->getFilters());
        self::assertJson($this->storedValue($export));
    }

    public function testSansRechercheLaListeEstVide(): void
    {
        self::assertSame([], (new ExportDemande())->getFilters());
        self::assertSame([], (new ExportDemande())->setFilters([])->getFilters());
    }

    public function testUnAncienFragmentSqlNestJamaisRestitue(): void
    {
        $export = new ExportDemande();
        $property = new \ReflectionProperty(ExportDemande::class, 'filters');
        $property->setAccessible(true);
        $property->setValue($export, ' AND (b.nom LIKE "%dupont%")');

        $this->expectException(\UnexpectedValueException::class);

        $export->getFilters();
    }

    private function storedValue(ExportDemande $export): string
    {
        $property = new \ReflectionProperty(ExportDemande::class, 'filters');
        $property->setAccessible(true);

        return (string) $property->getValue($export);
    }
}

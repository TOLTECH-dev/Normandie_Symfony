<?php

declare(strict_types=1);

namespace App\Tests\Archive;

use App\Archive\AttestationDestruction;
use App\Entity\ArchiveLot;
use PHPUnit\Framework\TestCase;

/**
 * Remplissage de l'attestation de destruction (Reco-03).
 *
 * L'attestation est le document que la Région signe et transmet au service
 * des Archives. Elle est produite à partir de la trame fournie par la Région,
 * qui est remplie et jamais recréée : sa mise en forme et ses logos sont les
 * siens.
 *
 * Les tests travaillent sur la vraie trame versionnée, pas sur un document
 * fabriqué : c'est la seule façon de s'apercevoir qu'elle a changé.
 */
class AttestationDestructionTest extends TestCase
{
    private const MODELE = __DIR__ . '/../../resources/Template/attestation_destruction_modele.docx';

    private string $sortie;

    protected function setUp(): void
    {
        $this->sortie = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'attestation-' . bin2hex(random_bytes(6)) . '.docx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->sortie)) {
            unlink($this->sortie);
        }
    }

    private function lot(): ArchiveLot
    {
        $lot = new ArchiveLot('ARCH-2027', new \DateTime('2027-01-10'), 'test');
        $lot->passerA(ArchiveLot::ETAT_CONSTITUE)
            ->passerA(ArchiveLot::ETAT_VERSE)
            ->passerA(ArchiveLot::ETAT_RECEPTION_VALIDEE)
            ->passerA(ArchiveLot::ETAT_DETRUIT)
            ->setDateDestruction(new \DateTime('2027-02-18'));

        return $lot;
    }

    /**
     * @param list<array{description: string, volumetrie: string, date: string}> $lignes
     */
    private function produire(array $lignes): string
    {
        (new AttestationDestruction(self::MODELE))->produire($this->lot(), $lignes, $this->sortie);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->sortie), 'attestation illisible');
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }

    /**
     * @return list<list<string>>
     */
    private function lignesDuTableau(string $xml): array
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml));

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $tableau = $xp->query('//w:tbl')->item(0);
        self::assertNotNull($tableau);

        $lignes = [];

        foreach ($xp->query('./w:tr', $tableau) as $tr) {
            $cellules = [];

            foreach ($xp->query('./w:tc', $tr) as $tc) {
                $texte = '';

                foreach ($xp->query('.//w:t', $tc) as $t) {
                    $texte .= $t->textContent;
                }

                $cellules[] = trim($texte);
            }

            $lignes[] = $cellules;
        }

        return $lignes;
    }

    public function testLaTrameEstPresenteDansLeDepot(): void
    {
        self::assertFileExists(
            self::MODELE,
            'la trame fournie par la Région doit être versionnée : evolutions/ est hors dépôt'
        );
    }

    public function testLeNomDeLaSocieteEtDeLApplicationRemplaceLEmplacement(): void
    {
        $xml = $this->produire([
            ['description' => 'Dossiers payes', 'volumetrie' => '10 pieces', 'date' => '18/02/2027'],
        ]);

        self::assertStringNotContainsString('NOM SOCIETE', $xml);
        self::assertStringContainsString('TOLTECH', $xml);
        self::assertStringContainsString('Chèque éco-énergie Normandie', $xml);
    }

    public function testLesTroisColonnesSontRemplies(): void
    {
        $lignes = $this->lignesDuTableau($this->produire([
            ['description' => 'Dossiers payes, campagne ARCH-2027', 'volumetrie' => '120 pieces, 4.2 Mio', 'date' => '18/02/2027'],
        ]));

        self::assertSame(['Description du contenu', 'Volumétrie', 'Date de destruction'], $lignes[0]);
        self::assertSame(
            ['Dossiers payes, campagne ARCH-2027', '120 pieces, 4.2 Mio', '18/02/2027'],
            $lignes[1]
        );
    }

    /**
     * La trame comporte quatre lignes vides. Celles qui ne servent pas sont
     * retirées : une ligne blanche sur une attestation signée laisse croire
     * qu'on a effacé quelque chose.
     */
    public function testLesLignesVidesEnTropSontRetirees(): void
    {
        $lignes = $this->lignesDuTableau($this->produire([
            ['description' => 'Un', 'volumetrie' => '1', 'date' => '18/02/2027'],
            ['description' => 'Deux', 'volumetrie' => '2', 'date' => '18/02/2027'],
        ]));

        self::assertCount(3, $lignes, 'en-tête et deux lignes, pas davantage');
    }

    /**
     * Plus de lignes que la trame n'en prévoit : il faut en ajouter, pas
     * perdre les dernières. Une campagne peut compter jusqu'à six
     * déclencheurs.
     */
    public function testLesLignesSupplementairesSontAjoutees(): void
    {
        $donnees = [];

        for ($i = 1; $i <= 6; ++$i) {
            $donnees[] = ['description' => 'Motif ' . $i, 'volumetrie' => $i . ' pieces', 'date' => '18/02/2027'];
        }

        $lignes = $this->lignesDuTableau($this->produire($donnees));

        self::assertCount(7, $lignes, 'en-tête et six lignes');
        self::assertSame(['Motif 6', '6 pieces', '18/02/2027'], $lignes[6]);
    }

    public function testLaDateDeDestructionEstPortee(): void
    {
        $xml = $this->produire([
            ['description' => 'Dossiers', 'volumetrie' => '1', 'date' => '18/02/2027'],
        ]);

        // La trame emploie une espace insécable avant le deux-points, comme le
        // veut la typographie française : la date doit la conserver.
        self::assertStringContainsString("Date\u{00A0}: 18/02/2027", $xml);
        self::assertStringNotContainsString('Date : 18/02/2027', $xml);
    }

    public function testLesAccentsEtLesCaracteresSpeciauxSurvivent(): void
    {
        $lignes = $this->lignesDuTableau($this->produire([
            [
                'description' => 'Dossiers « payés » & archivés <2027>',
                'volumetrie' => '1 pièce',
                'date' => '18/02/2027',
            ],
        ]));

        self::assertSame('Dossiers « payés » & archivés <2027>', $lignes[1][0]);
        self::assertSame('1 pièce', $lignes[1][1]);
    }

    /**
     * Une trame dont l'emplacement a disparu doit arrêter la production :
     * une attestation portant encore « NOM SOCIETE » serait renvoyée par le
     * service des Archives.
     */
    public function testUneTrameSansEmplacementArreteLaProduction(): void
    {
        $trameModifiee = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'trame-' . bin2hex(random_bytes(6)) . '.docx';
        copy(self::MODELE, $trameModifiee);

        $zip = new \ZipArchive();
        $zip->open($trameModifiee);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->addFromString('word/document.xml', str_replace('NOM SOCIETE', 'AUTRE CHOSE', $xml));
        $zip->close();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/emplacement/');

            (new AttestationDestruction($trameModifiee))->produire($this->lot(), [
                ['description' => 'x', 'volumetrie' => 'y', 'date' => 'z'],
            ], $this->sortie);
        } finally {
            unlink($trameModifiee);
        }
    }

    public function testUneTrameAbsenteEstSignalee(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/introuvable/');

        (new AttestationDestruction('/chemin/qui/n/existe/pas.docx'))->produire($this->lot(), [], $this->sortie);
    }

    /**
     * Un échec en cours de remplissage ne doit pas laisser un document
     * incomplet derrière lui : on ne signe pas une attestation à moitié
     * écrite.
     */
    public function testUnEchecNeLaissePasDeDocumentIncomplet(): void
    {
        $trameModifiee = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'trame-' . bin2hex(random_bytes(6)) . '.docx';
        copy(self::MODELE, $trameModifiee);

        $zip = new \ZipArchive();
        $zip->open($trameModifiee);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->addFromString('word/document.xml', str_replace('NOM SOCIETE', 'AUTRE CHOSE', $xml));
        $zip->close();

        try {
            (new AttestationDestruction($trameModifiee))->produire($this->lot(), [], $this->sortie);
            self::fail('la production aurait dû échouer');
        } catch (\RuntimeException) {
            self::assertFileDoesNotExist($this->sortie);
        } finally {
            unlink($trameModifiee);
        }
    }
}

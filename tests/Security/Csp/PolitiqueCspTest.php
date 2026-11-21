<?php

declare(strict_types=1);

namespace App\Tests\Security\Csp;

use App\Security\Csp\PolitiqueCsp;
use PHPUnit\Framework\TestCase;

/**
 * Assemblage de la politique de sécurité du contenu (Reco-04).
 *
 * Une politique CSP est une chaîne assemblée par concaténation et posée en
 * en-tête HTTP. Les deux erreurs qui la rendent inopérante sans que rien ne le
 * signale sont vérifiées ici :
 *
 *  - un mot-clé écrit sans apostrophes — `default-src self` désigne un HÔTE
 *    nommé « self », et non le mot-clé. Erreur commise à l'écriture de la
 *    configuration le 21/11/2026, et rattrapée par la lecture de l'en-tête
 *    réellement émis ;
 *  - une source contenant un point-virgule ou un retour à la ligne, qui
 *    ajouterait une directive ou un en-tête entier.
 */
class PolitiqueCspTest extends TestCase
{
    public function testLesDirectivesSontAssembleesDansLOrdre(): void
    {
        $politique = new PolitiqueCsp([
            'default-src' => ["'self'"],
            'img-src' => ["'self'", 'data:'],
        ]);

        self::assertSame(
            "default-src 'self'; img-src 'self' data:",
            $politique->enTete('abc')
        );
    }

    /**
     * Le marqueur est remplacé par le nonce de la requête, sous la forme
     * attendue par la norme. La configuration n'a ainsi pas à connaître cette
     * forme.
     */
    public function testLeMarqueurDeNonceEstRemplace(): void
    {
        $politique = new PolitiqueCsp([
            'script-src' => ["'self'", PolitiqueCsp::JETON_NONCE, 'https://example.org'],
        ]);

        self::assertSame(
            "script-src 'self' 'nonce-ZmFpdGVz' https://example.org",
            $politique->enTete('ZmFpdGVz')
        );
    }

    public function testChaqueRequeteObtientSonPropreNonce(): void
    {
        $politique = new PolitiqueCsp(['script-src' => [PolitiqueCsp::JETON_NONCE]]);

        self::assertSame("script-src 'nonce-un'", $politique->enTete('un'));
        self::assertSame("script-src 'nonce-deux'", $politique->enTete('deux'));
    }

    /**
     * Le mot-clé doit garder ses apostrophes de bout en bout : la politique
     * les transporte telles quelles, sans les interpréter.
     */
    public function testLesApostrophesDesMotsClesSontConservees(): void
    {
        $entete = (new PolitiqueCsp([
            'default-src' => ["'self'"],
            'object-src' => ["'none'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
        ]))->enTete('x');

        self::assertStringContainsString("default-src 'self'", $entete);
        self::assertStringContainsString("object-src 'none'", $entete);
        self::assertStringContainsString("'unsafe-inline'", $entete);
        self::assertStringNotContainsString('default-src self;', $entete, 'un mot-clé sans apostrophes désigne un hôte');
    }

    /**
     * @dataProvider fournirSourcesInterdites
     */
    public function testUneSourceQuiPourraitInjecterEstRefusee(string $source): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Source interdite/');

        new PolitiqueCsp(['default-src' => ["'self'", $source]]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fournirSourcesInterdites(): iterable
    {
        yield 'point-virgule' => ["'self'; default-src *"];
        yield 'retour chariot' => ["https://exemple.fr\r\nX-Injecte: oui"];
        yield 'saut de ligne' => ["https://exemple.fr\nSet-Cookie: a=b"];
    }

    public function testUneSourceVideEstRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Source vide/');

        new PolitiqueCsp(['default-src' => ["'self'", '   ']]);
    }

    public function testUnNomDeDirectiveInvalideEstRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Nom de directive/');

        new PolitiqueCsp(['Default-Src' => ["'self'"]]);
    }

    public function testUnePolitiqueSansDirectiveEstRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PolitiqueCsp([]);
    }

    /**
     * Les deux directives de rapport sont posées : `report-uri` est dépassée
     * mais reste la seule comprise d'une partie des navigateurs, `report-to`
     * est la forme actuelle.
     */
    public function testLePointDeCollecteEstAjouteSousLesDeuxFormes(): void
    {
        $entete = (new PolitiqueCsp(['default-src' => ["'self'"]]))
            ->avecRapportVers('/csp/rapport')
            ->enTete('x');

        self::assertStringContainsString('report-uri /csp/rapport', $entete);
        self::assertStringContainsString('report-to csp', $entete);
    }

    public function testSansPointDeCollecteLaPolitiqueEstInchangee(): void
    {
        $politique = new PolitiqueCsp(['default-src' => ["'self'"]]);

        self::assertSame($politique->enTete('x'), $politique->avecRapportVers('  ')->enTete('x'));
    }

    public function testLesSourcesDUneDirectiveSontConsultables(): void
    {
        $politique = new PolitiqueCsp(['img-src' => ["'self'", 'data:']]);

        self::assertSame(["'self'", 'data:'], $politique->sources('img-src'));
        self::assertSame([], $politique->sources('font-src'));
    }

    /**
     * Une directive sans source se rend seule : c'est la forme attendue pour
     * `upgrade-insecure-requests`.
     */
    public function testUneDirectiveSansSourceSeRendSeule(): void
    {
        $entete = (new PolitiqueCsp([
            'default-src' => ["'self'"],
            'upgrade-insecure-requests' => [],
        ]))->enTete('x');

        self::assertSame("default-src 'self'; upgrade-insecure-requests", $entete);
    }
}

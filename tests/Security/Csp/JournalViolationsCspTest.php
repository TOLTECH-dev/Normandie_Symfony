<?php

declare(strict_types=1);

namespace App\Tests\Security\Csp;

use App\Security\Csp\JournalViolationsCsp;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Enregistrement des violations CSP (Reco-04, étape « observation »).
 *
 * Le contenu d'un rapport vient du navigateur, donc de l'extérieur. Deux
 * choses sont vérifiées ici :
 *
 *  - la CHAÎNE DE REQUÊTE est retirée des URL. Ce n'est pas de la cosmétique :
 *    l'adresse d'une page de réinitialisation porte le jeton de
 *    réinitialisation, et un journal technique n'a pas à le conserver ;
 *  - un rapport malformé ou hostile ne fait rien écrire, et ne lève rien :
 *    n'importe qui peut poster n'importe quoi sur un point de collecte
 *    public.
 */
class JournalViolationsCspTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $ecritures = [];

    private function journal(): JournalViolationsCsp
    {
        $this->ecritures = [];

        $connexion = $this->createMock(Connection::class);
        $connexion->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $parametres = []): int {
                $this->ecritures[] = $parametres;

                return 1;
            });

        return new JournalViolationsCsp($connexion);
    }

    /**
     * @param array<string, mixed> $rapport
     */
    private function enregistrer(array $rapport): bool
    {
        return $this->journal()->enregistrer($rapport);
    }

    public function testUnRapportCompletEstEnregistre(): void
    {
        $journal = $this->journal();

        self::assertTrue($journal->enregistrer([
            'document-uri' => 'https://cheque-eco-energie.normandie.fr/admin/demande/liste',
            'effective-directive' => 'style-src',
            'blocked-uri' => 'inline',
            'disposition' => 'report',
        ]));

        self::assertCount(1, $this->ecritures);
        self::assertSame('style-src', $this->ecritures[0]['directive']);
        self::assertSame('inline', $this->ecritures[0]['source']);
        self::assertSame(
            'https://cheque-eco-energie.normandie.fr/admin/demande/liste',
            $this->ecritures[0]['page']
        );
        self::assertSame(0, $this->ecritures[0]['appliquee'], 'une violation en observation');
    }

    /**
     * Le point décisif : une violation de la politique EN VIGUEUR signale une
     * ressource réellement bloquée pour l'utilisateur. Elle doit se
     * distinguer de ce que le durcissement casserait.
     */
    public function testUneViolationDeLaPolitiqueEnVigueurEstDistinguee(): void
    {
        $journal = $this->journal();
        $journal->enregistrer([
            'document-uri' => 'https://exemple.fr/page',
            'effective-directive' => 'script-src',
            'blocked-uri' => 'https://tiers.example/malveillant.js',
            'disposition' => 'enforce',
        ]);

        self::assertSame(1, $this->ecritures[0]['appliquee']);
    }

    /**
     * @dataProvider fournirUrlAvecSecret
     */
    public function testLaChaineDeRequeteNestJamaisConservee(string $url, string $attendu): void
    {
        $journal = $this->journal();
        $journal->enregistrer([
            'document-uri' => $url,
            'effective-directive' => 'style-src',
            'blocked-uri' => 'inline',
        ]);

        self::assertSame($attendu, $this->ecritures[0]['page']);
        self::assertStringNotContainsString('token', $this->ecritures[0]['page']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fournirUrlAvecSecret(): iterable
    {
        yield 'jeton de reinitialisation' => [
            'https://exemple.fr/resetting/reset?token=aBcD1234secret',
            'https://exemple.fr/resetting/reset',
        ];
        yield 'fragment' => [
            'https://exemple.fr/page#token=secret',
            'https://exemple.fr/page',
        ];
        yield 'les deux' => [
            'https://exemple.fr/page?token=a#token=b',
            'https://exemple.fr/page',
        ];
        yield 'sans chaine de requete' => [
            'https://exemple.fr/page',
            'https://exemple.fr/page',
        ];
    }

    public function testLaSourceBloqueeEstAussiDebarrasseeDeSaChaineDeRequete(): void
    {
        $journal = $this->journal();
        $journal->enregistrer([
            'document-uri' => 'https://exemple.fr/page',
            'effective-directive' => 'img-src',
            'blocked-uri' => 'https://tiers.example/pixel.gif?identifiant=12345',
        ]);

        self::assertSame('https://tiers.example/pixel.gif', $this->ecritures[0]['source']);
    }

    /**
     * Les navigateurs anciens renvoient « violated-directive », qui porte les
     * sources à la suite du nom.
     */
    public function testLaDirectiveEstExtraiteDuFormatAncien(): void
    {
        $journal = $this->journal();
        $journal->enregistrer([
            'document-uri' => 'https://exemple.fr/page',
            'violated-directive' => "style-src 'self' https://fonts.googleapis.com",
            'blocked-uri' => 'inline',
        ]);

        self::assertSame('style-src', $this->ecritures[0]['directive']);
    }

    /**
     * @dataProvider fournirRapportsInexploitables
     *
     * @param array<string, mixed> $rapport
     */
    public function testUnRapportInexploitableNecritRien(array $rapport): void
    {
        $journal = $this->journal();

        self::assertFalse($journal->enregistrer($rapport));
        self::assertSame([], $this->ecritures);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function fournirRapportsInexploitables(): iterable
    {
        yield 'vide' => [[]];
        yield 'sans page' => [['effective-directive' => 'style-src']];
        yield 'sans directive' => [['document-uri' => 'https://exemple.fr/page']];
        yield 'directive non textuelle' => [[
            'document-uri' => 'https://exemple.fr/page',
            'effective-directive' => ['style-src'],
        ]];
        yield 'directive hostile' => [[
            'document-uri' => 'https://exemple.fr/page',
            'effective-directive' => "style-src'; DROP TABLE user --",
        ]];
        yield 'page non textuelle' => [[
            'document-uri' => 42,
            'effective-directive' => 'style-src',
        ]];
    }

    public function testUneSourceAbsenteEstNommeeExplicitement(): void
    {
        $journal = $this->journal();
        $journal->enregistrer([
            'document-uri' => 'https://exemple.fr/page',
            'effective-directive' => 'style-src',
        ]);

        self::assertSame('(non precise)', $this->ecritures[0]['source']);
    }

    /**
     * Les URL peuvent être énormes — une image en `data:` tient des centaines
     * de kilo-octets. Rien n'est écrit au-delà de la taille de la colonne.
     */
    public function testLesValeursSontTronquees(): void
    {
        $journal = $this->journal();
        $journal->enregistrer([
            'document-uri' => 'https://exemple.fr/' . str_repeat('a', 2000),
            'effective-directive' => 'img-src',
            'blocked-uri' => 'data:image/png;base64,' . str_repeat('A', 5000),
        ]);

        self::assertLessThanOrEqual(500, strlen($this->ecritures[0]['page']));
        self::assertLessThanOrEqual(500, strlen($this->ecritures[0]['source']));
    }

    /**
     * L'agrégation repose sur l'empreinte : deux rapports identiques doivent
     * la partager, deux rapports distincts non.
     */
    public function testLEmpreinteDistingueLesCasSansLesMultiplier(): void
    {
        $journal = $this->journal();

        $base = [
            'document-uri' => 'https://exemple.fr/page',
            'effective-directive' => 'style-src',
            'blocked-uri' => 'inline',
        ];

        $journal->enregistrer($base);
        $journal->enregistrer($base);
        $journal->enregistrer(['blocked-uri' => 'eval'] + $base);

        self::assertSame($this->ecritures[0]['empreinte'], $this->ecritures[1]['empreinte']);
        self::assertNotSame($this->ecritures[0]['empreinte'], $this->ecritures[2]['empreinte']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->ecritures[0]['empreinte']);
    }
}

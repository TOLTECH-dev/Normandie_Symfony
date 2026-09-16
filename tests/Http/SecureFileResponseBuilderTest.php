<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\SecureFileResponseBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SecureFileResponseBuilderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/vul04_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    /**
     * Le cas de l'audit : un fichier accepté comme XML au dépôt, mais dont le contenu est du SVG
     * porteur de script. Sans en-tête de disposition, Symfony le typait en image/svg+xml et le
     * navigateur l'exécutait dans l'origine du site.
     *
     * @dataProvider provideFichiers
     */
    public function testToutFichierEstRenvoyeEnTelechargementNonInterpretable(string $nom, string $contenu): void
    {
        $response = $this->builderResponse($nom, $contenu);

        self::assertSame('attachment; filename=' . $nom, $response->headers->get('Content-Disposition'));
        self::assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame("default-src 'none'; sandbox", $response->headers->get('Content-Security-Policy'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertStringNotContainsString('svg', (string) $response->headers->get('Content-Type'));
    }

    public static function provideFichiers(): iterable
    {
        yield 'XML polyglotte (SVG avec script)' => ['1234_xml_document.xml', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'];
        yield 'HTML' => ['12_file.html', '<html><body><script>alert(1)</script></body></html>'];
        yield 'PDF' => ['1234_avis_imposition.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>"];
        yield 'image PNG' => ['1234_rib.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')];
    }

    public function testLaReponseNestPasMiseEnCacheParUnIntermediaire(): void
    {
        $response = $this->builderResponse('1234_rib.pdf', '%PDF-1.4');

        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function testUnNomAccentueResteLisibleEtFournitUneVarianteAscii(): void
    {
        $response = $this->builderResponse('fichier.pdf', '%PDF-1.4', 'avis d\'imposition été.pdf');

        $disposition = (string) $response->headers->get('Content-Disposition');
        self::assertStringContainsString("filename*=utf-8''", $disposition);
        self::assertStringContainsString('%C3%A9t%C3%A9', $disposition);
    }

    public function testFichierAbsent(): void
    {
        $this->expectException(NotFoundHttpException::class);

        (new SecureFileResponseBuilder())->download($this->dir . '/inexistant.pdf');
    }

    /**
     * Garde-fou : aucun contrôleur ne doit renvoyer un fichier sans passer par la fabrique.
     */
    public function testAucunControleurNeRenvoieUnFichierBrut(): void
    {
        $fautifs = [];
        foreach (glob(\dirname(__DIR__, 2) . '/src/Controller/*.php') ?: [] as $fichier) {
            $code = file_get_contents($fichier);
            if (preg_match('/new BinaryFileResponse\(|new Response\(\s*file_get_contents\(/', $code)) {
                $fautifs[] = basename($fichier);
            }
        }

        self::assertSame([], $fautifs);
    }

    private function builderResponse(string $nom, string $contenu, ?string $nomPropose = null)
    {
        file_put_contents($this->dir . '/' . $nom, $contenu);
        $response = (new SecureFileResponseBuilder())->download($this->dir . '/' . $nom, $nomPropose);
        // prepare() est ce que fait le noyau avant l'envoi : il ne doit rien écraser
        $response->prepare(Request::create('http://dev.normandie-symfony-bo.fr/document'));

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Security\Antivirus;

use App\Security\Antivirus\AntivirusIndisponibleException;
use App\Security\Antivirus\ClamavScanner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Vulnérabilité 5 : dialogue avec le service résident ClamAV.
 *
 * ClamAV n'existe pas sur les postes de développement : les échanges sont rejoués contre un faux
 * service (faux_clamd.php), lancé dans un processus séparé, qui consigne ce qu'il reçoit et
 * renvoie les réponses documentées de clamd.
 */
class ClamavScannerTest extends TestCase
{
    /**
     * Ces tests n'écrivent jamais la signature de test EICAR sur le disque : c'est le faux service
     * qui décide de la réponse, le contenu du fichier ne joue aucun rôle. L'antivirus du poste de
     * développement mettrait d'ailleurs le fichier en quarantaine, et le test échouerait.
     *
     * L'essai avec un vrai fichier EICAR est décrit dans tests/Security/Antivirus/README.md : il se
     * fait sur la recette, une fois ClamAV en service.
     */
    /** @var resource|null */
    private $processus;

    /** @var array<int, resource> */
    private array $tuyaux = [];

    private string $journal = '';

    protected function tearDown(): void
    {
        $this->arreterFauxService();

        if ('' !== $this->journal && is_file($this->journal)) {
            unlink($this->journal);
        }
    }

    public function testUnFichierSainEstAccepte(): void
    {
        $dsn = $this->demarrerFauxService('stream: OK');

        $resultat = $this->scanner($dsn)->analyser($this->fichierTemporaire('contenu inoffensif'));

        self::assertTrue($resultat->estSain());
        self::assertNull($resultat->menace());
    }

    public function testUnFichierInfecteEstSignaleAvecSaSignature(): void
    {
        $dsn = $this->demarrerFauxService('stream: Eicar-Test-Signature FOUND');

        $resultat = $this->scanner($dsn)->analyser($this->fichierTemporaire('contenu reconnu comme menace par le service'));

        self::assertFalse($resultat->estSain());
        self::assertSame('Eicar-Test-Signature', $resultat->menace());
    }

    public function testLeFichierEstEnvoyeSelonLeProtocoleInstream(): void
    {
        $dsn = $this->demarrerFauxService('stream: OK');

        $this->scanner($dsn)->analyser($this->fichierTemporaire('abc'));
        $this->attendreFinDuService();

        $echange = file_get_contents($this->journal);
        self::assertStringStartsWith("zINSTREAM\0", $echange, 'Commande INSTREAM close par un octet nul');
        self::assertStringContainsString(pack('N', 3) . 'abc', $echange, 'Morceau précédé de sa taille sur 4 octets');
        self::assertStringEndsWith(pack('N', 0), $echange, 'Morceau de taille nulle pour clore l\'envoi');
    }

    public function testUneReponseIncompriseNeConclutPas(): void
    {
        $dsn = $this->demarrerFauxService('ERROR: something went wrong');

        $this->expectException(AntivirusIndisponibleException::class);

        $this->scanner($dsn)->analyser($this->fichierTemporaire('contenu'));
    }

    public function testUnServiceInjoignableEstSignale(): void
    {
        $this->expectException(AntivirusIndisponibleException::class);

        $this->scanner('tcp://127.0.0.1:1')->analyser($this->fichierTemporaire('contenu'));
    }

    public function testUnFichierPlusGrandQueLaLimiteDuServiceEstRefuse(): void
    {
        $scanner = new ClamavScanner('tcp://127.0.0.1:1', 1, 4, new NullLogger());

        $this->expectException(AntivirusIndisponibleException::class);
        $this->expectExceptionMessageMatches('/limite acceptée par l\'antivirus/');

        $scanner->analyser($this->fichierTemporaire('contenu plus long que quatre octets'));
    }

    public function testUnFichierAbsentEstSignale(): void
    {
        $this->expectException(AntivirusIndisponibleException::class);

        $this->scanner('tcp://127.0.0.1:1')->analyser(sys_get_temp_dir() . '/absent-' . uniqid('', true));
    }

    private function scanner(string $dsn): ClamavScanner
    {
        return new ClamavScanner($dsn, 5, 26214400, new NullLogger());
    }

    private function demarrerFauxService(string $reponse): string
    {
        $this->journal = tempnam(sys_get_temp_dir(), 'clamd');

        $descripteurs = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $this->processus = proc_open(
            [PHP_BINARY, __DIR__ . '/faux_clamd.php', $this->journal, $reponse],
            $descripteurs,
            $this->tuyaux
        );
        self::assertIsResource($this->processus, 'Faux service clamd non démarré');

        $annonce = fgets($this->tuyaux[1]);
        self::assertIsString($annonce, 'Le faux service n\'a pas annoncé son port');
        self::assertStringStartsWith('prêt ', $annonce);

        return 'tcp://' . trim(substr($annonce, \strlen('prêt ')));
    }

    private function attendreFinDuService(): void
    {
        if (!\is_resource($this->processus)) {
            return;
        }

        for ($i = 0; $i < 50 && proc_get_status($this->processus)['running']; ++$i) {
            usleep(20000);
        }
    }

    private function arreterFauxService(): void
    {
        foreach ($this->tuyaux as $tuyau) {
            if (\is_resource($tuyau)) {
                fclose($tuyau);
            }
        }
        $this->tuyaux = [];

        if (\is_resource($this->processus)) {
            proc_terminate($this->processus);
            proc_close($this->processus);
            $this->processus = null;
        }
    }

    private function fichierTemporaire(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'antivirus');
        file_put_contents($chemin, $contenu);

        return $chemin;
    }
}

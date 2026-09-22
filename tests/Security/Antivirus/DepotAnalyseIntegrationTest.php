<?php

declare(strict_types=1);

namespace App\Tests\Security\Antivirus;

use App\Entity\Demande_auditEnergie;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Vulnérabilité 5, chaîne complète : la contrainte posée sur l'entité déclenche l'analyse du
 * fichier déposé, et un verdict d'infection refuse le dépôt.
 *
 * L'analyse est branchée sur un faux service (faux_clamd.php) : aucun ClamAV n'est nécessaire.
 * L'alerte aux administrateurs est désactivée ici, faute de base de données dans ce test.
 */
class DepotAnalyseIntegrationTest extends KernelTestCase
{
    /** @var resource|null */
    private $processus;

    /** @var array<int, resource> */
    private array $tuyaux = [];

    protected function tearDown(): void
    {
        foreach ($this->tuyaux as $tuyau) {
            if (\is_resource($tuyau)) {
                fclose($tuyau);
            }
        }
        if (\is_resource($this->processus)) {
            proc_terminate($this->processus);
            proc_close($this->processus);
        }

        parent::tearDown();
    }

    public function testUnAvisDImpositionInfecteEstRefuse(): void
    {
        $violations = $this->validerAvecService('stream: Win.Test.EICAR_HDB-1 FOUND');

        self::assertGreaterThanOrEqual(1, $violations->count());
        self::assertStringContainsString('antivirus', (string) $violations->get(0)->getMessage());
    }

    public function testUnAvisDImpositionSainEstAccepte(): void
    {
        self::assertCount(0, $this->validerAvecService('stream: OK'));
    }

    public function testServiceInjoignableLeDepotEstRefuse(): void
    {
        $violations = $this->valider('tcp://127.0.0.1:1');

        self::assertGreaterThanOrEqual(1, $violations->count());
        self::assertStringContainsString('momentanément impossible', (string) $violations->get(0)->getMessage());
    }

    private function validerAvecService(string $reponse): \Symfony\Component\Validator\ConstraintViolationListInterface
    {
        $descripteurs = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $this->processus = proc_open(
            [PHP_BINARY, __DIR__ . '/faux_clamd.php', '', $reponse],
            $descripteurs,
            $this->tuyaux
        );
        self::assertIsResource($this->processus);

        $annonce = (string) fgets($this->tuyaux[1]);
        self::assertStringStartsWith('prêt ', $annonce);

        return $this->valider('tcp://' . trim(substr($annonce, \strlen('prêt '))));
    }

    private function valider(string $dsn): \Symfony\Component\Validator\ConstraintViolationListInterface
    {
        $_SERVER['SECURITY_ANTIVIRUS_ENABLED'] = $_ENV['SECURITY_ANTIVIRUS_ENABLED'] = 'true';
        $_SERVER['SECURITY_ANTIVIRUS_DSN'] = $_ENV['SECURITY_ANTIVIRUS_DSN'] = $dsn;
        $_SERVER['SECURITY_ANTIVIRUS_TIMEOUT'] = $_ENV['SECURITY_ANTIVIRUS_TIMEOUT'] = '5';
        $_SERVER['SECURITY_ANTIVIRUS_ALERT_ENABLED'] = $_ENV['SECURITY_ANTIVIRUS_ALERT_ENABLED'] = 'false';

        self::bootKernel();
        /** @var ValidatorInterface $validateur */
        $validateur = static::getContainer()->get(ValidatorInterface::class);

        $demande = new Demande_auditEnergie();
        $demande->setAvisImposition($this->fichierDepose());

        // Seule la propriété portant le fichier est validée : le reste de l'entité n'est pas rempli.
        return $validateur->validateProperty($demande, 'avisImposition');
    }

    private function fichierDepose(): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'depot');
        file_put_contents($chemin, '%PDF-1.4 contenu de test');

        return new UploadedFile($chemin, 'avis.pdf', 'application/pdf', null, true);
    }
}

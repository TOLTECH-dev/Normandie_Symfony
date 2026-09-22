<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Security\Antivirus\AlerteAntivirus;
use App\Security\Antivirus\AntivirusIndisponibleException;
use App\Security\Antivirus\AntivirusScanner;
use App\Security\Antivirus\ResultatAnalyse;
use App\Validator\FichierSansVirus;
use App\Validator\FichierSansVirusValidator;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Vulnérabilité 5 : un fichier reconnu comme dangereux est refusé avant tout enregistrement, et
 * les administrateurs en sont avertis.
 */
class FichierSansVirusValidatorTest extends ConstraintValidatorTestCase
{
    private AntivirusScanner $scanner;

    private AlerteAntivirus $alerteMailer;

    private bool $refuserSiIndisponible = true;

    protected function createValidator(): FichierSansVirusValidator
    {
        return new FichierSansVirusValidator(
            $this->scanner,
            $this->alerteMailer,
            new NullLogger(),
            $this->refuserSiIndisponible
        );
    }

    public function testUnFichierSainPasse(): void
    {
        $this->avecScanner(ResultatAnalyse::sain());
        $this->alerteMailer->expects(self::never())->method('signalerFichierInfecte');

        $this->validator->validate($this->fichierDepose(), new FichierSansVirus());

        $this->assertNoViolation();
    }

    public function testUnFichierInfecteEstRefuse(): void
    {
        $this->avecScanner(ResultatAnalyse::infecte('Eicar-Test-Signature'));

        $contrainte = new FichierSansVirus();
        $this->validator->validate($this->fichierDepose(), $contrainte);

        $this->buildViolation($contrainte->message)->assertRaised();
    }

    public function testUnFichierInfecteDeclencheLAlerteAuxAdministrateurs(): void
    {
        $this->avecScanner(ResultatAnalyse::infecte('Eicar-Test-Signature'));
        $this->alerteMailer->expects(self::once())
            ->method('signalerFichierInfecte')
            ->with('Eicar-Test-Signature', 'avis d\'imposition');

        $this->validator->validate($this->fichierDepose(), new FichierSansVirus(['libelle' => 'avis d\'imposition']));
    }

    public function testAnalyseImpossibleLeDepotEstRefuseParDefaut(): void
    {
        $this->avecScannerIndisponible();

        $contrainte = new FichierSansVirus();
        $this->validator->validate($this->fichierDepose(), $contrainte);

        $this->buildViolation($contrainte->messageIndisponible)->assertRaised();
    }

    public function testAnalyseImpossibleLeDepotPeutEtreAccepteParConfiguration(): void
    {
        $this->refuserSiIndisponible = false;
        $this->avecScannerIndisponible();

        // Le validateur est construit à l'initialisation du test : il faut le reconstruire pour
        // qu'il prenne le réglage.
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate($this->fichierDepose(), new FichierSansVirus());

        $this->assertNoViolation();
    }

    public function testAucunFichierDeposeRienAAnalyser(): void
    {
        $this->avecScanner(ResultatAnalyse::sain());
        $this->scanner->expects(self::never())->method('analyser');

        $this->validator->validate(null, new FichierSansVirus());

        $this->assertNoViolation();
    }

    private function avecScanner(ResultatAnalyse $resultat): void
    {
        $this->scanner->method('analyser')->willReturn($resultat);
    }

    private function avecScannerIndisponible(): void
    {
        $this->scanner->method('analyser')->willThrowException(new AntivirusIndisponibleException('service arrêté'));
    }

    protected function setUp(): void
    {
        $this->scanner = $this->createMock(AntivirusScanner::class);
        $this->alerteMailer = $this->createMock(AlerteAntivirus::class);

        parent::setUp();
    }

    private function fichierDepose(): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'depot');
        file_put_contents($chemin, 'contenu quelconque');

        // test: true — le fichier ne provient pas d'une vraie requête HTTP
        return new UploadedFile($chemin, 'avis.pdf', 'application/pdf', null, true);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Security\Authorization;

use App\Entity\Demande_;
use App\Repository\EPCI_Repository;
use App\Repository\Structure_Repository;
use App\Security\Perimetre\PerimetreResolver;
use App\Service\ANAHService;
use App\Service\DemandeServiceBO;
use App\Service\HistoriqueService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * Vulnérabilité 6 : un dossier hors périmètre reçoit la même réponse qu'un dossier inexistant (404),
 * pour que la réponse ne révèle pas l'existence d'un dossier. Les pages d'erreur n'affichent aucun détail.
 */
class RefusAccesTest extends TestCase
{
    private const GABARITS = __DIR__ . '/../../../templates/bundles/TwigBundle/Exception/';

    public function testUnDossierInexistantRepond404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->service()->checkAccesByRole(null, ['roles' => ['ROLE_AUDITEUR'], 'username' => 'A00007']);
    }

    public function testUnDossierHorsPerimetreRepondCommeUnDossierInexistantEtEstJournalise(): void
    {
        $journal = new class () extends AbstractLogger {
            /** @var list<array{string, array<string, mixed>}> */
            public array $lignes = [];

            public function log($level, $message, array $context = []): void
            {
                $this->lignes[] = [(string) $message, $context];
            }
        };
        $demande = (new Demande_())->setType(999); // type inconnu : aucun droit pour un auditeur

        try {
            $this->service($journal)->checkAccesByRole($demande, ['roles' => ['ROLE_AUDITEUR'], 'username' => 'A00007']);
            self::fail('Le refus doit lever une 404');
        } catch (NotFoundHttpException $refus) {
            self::assertSame('', $refus->getMessage(), 'Aucun motif dans la réponse');
        }

        self::assertCount(1, $journal->lignes);
        self::assertSame('A00007', $journal->lignes[0][1]['username']);
        self::assertSame('ROLE_AUDITEUR', $journal->lignes[0][1]['profil']);
    }

    public function testLesProfilsSansPerimetreVoientLeDossier(): void
    {
        $this->service()->checkAccesByRole((new Demande_())->setType(999), ['roles' => ['ROLE_INSTRUCTEUR'], 'username' => 'test.instructeur']);

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider provideGabarits
     */
    public function testLesPagesDErreurNAffichentAucunDetailTechnique(string $gabarit, string $titre): void
    {
        $contenu = file_get_contents(self::GABARITS . $gabarit);

        self::assertStringContainsString($titre, $contenu);
        self::assertStringContainsString("extends '@Twig/Exception/layout_erreur.html.twig'", $contenu);
        foreach (['exception', 'status_text', 'app.user', 'app.request'] as $interdit) {
            self::assertStringNotContainsString($interdit, $contenu, "$gabarit ne doit pas afficher « $interdit »");
        }
    }

    public static function provideGabarits(): iterable
    {
        yield '403' => ['error403.html.twig', 'Accès refusé'];
        yield '404' => ['error404.html.twig', 'Page introuvable'];
        yield '429' => ['error429.html.twig', 'Trop de tentatives'];
        yield 'autres' => ['error.html.twig', 'Une erreur est survenue'];
    }

    public function testLeGabaritCommunEstAutonome(): void
    {
        $contenu = file_get_contents(self::GABARITS . 'layout_erreur.html.twig');

        // Une erreur pendant le rendu de la page d'erreur ferait retomber sur la page brute de Symfony
        foreach (['render(', 'encore_entry', '<script', 'app.user', 'exception.message'] as $interdit) {
            self::assertStringNotContainsString($interdit, $contenu);
        }
        self::assertStringContainsString('<html lang="fr">', $contenu);
    }

    private function service(?AbstractLogger $journal = null): DemandeServiceBO
    {
        $resolver = new PerimetreResolver(
            $this->createMock(Structure_Repository::class),
            $this->createMock(EPCI_Repository::class),
            new TokenStorage()
        );

        return new DemandeServiceBO(
            $this->createMock(EntityManagerInterface::class),
            new TokenStorage(),
            $this->createMock(HistoriqueService::class),
            $this->createMock(ANAHService::class),
            new ParameterBag([]),
            $resolver,
            $journal
        );
    }
}

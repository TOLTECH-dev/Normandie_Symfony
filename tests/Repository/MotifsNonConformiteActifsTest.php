<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\Instruction_reasonRepository;
use App\Repository\Remboursement_reasonRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Filtrage des motifs de non-conformité actifs (D26013).
 *
 * Les requêtes sont analysées sans lire de données, comme le fait déjà
 * UserRepositoryFindByRoleTest : il n'existe pas de base de test, et c'est la
 * forme du DQL qui porte la règle.
 *
 * Deux garanties, de nature différente :
 *
 *  - seuls les motifs ACTIFS sont proposés — c'est la demande ;
 *  - les motifs déjà retenus sur le dossier édité le restent, même
 *    désactivés depuis — faute de quoi rouvrir un ancien dossier ferait
 *    échouer le formulaire.
 */
class MotifsNonConformiteActifsTest extends KernelTestCase
{
    private Instruction_reasonRepository $instruction;

    private Remboursement_reasonRepository $remboursement;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->instruction = static::getContainer()->get(Instruction_reasonRepository::class);
        $this->remboursement = static::getContainer()->get(Remboursement_reasonRepository::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fournirLesDeuxDepots(): iterable
    {
        yield 'instruction' => ['instruction'];
        yield 'remboursement' => ['remboursement'];
    }

    private function depot(string $nom): Instruction_reasonRepository|Remboursement_reasonRepository
    {
        return 'instruction' === $nom ? $this->instruction : $this->remboursement;
    }

    /**
     * @dataProvider fournirLesDeuxDepots
     */
    public function testSansSelectionSeulsLesMotifsActifsSontProposes(string $nom): void
    {
        $dql = $this->depot($nom)->findByFiltre('JP')->getQuery()->getDQL();

        self::assertStringContainsString('a.actif = true', $dql);
        self::assertStringNotContainsString('IN(:dejaChoisis)', $dql);
        self::assertStringNotContainsString('IN (:dejaChoisis)', $dql);
    }

    /**
     * @dataProvider fournirLesDeuxDepots
     */
    public function testLesMotifsDejaChoisisSontConserves(string $nom): void
    {
        $requete = $this->depot($nom)->findByFiltre('JP', [5, 14])->getQuery();
        $dql = $requete->getDQL();

        self::assertStringContainsString('a.actif = true', $dql);
        self::assertMatchesRegularExpression('/a\.id IN\s*\(:dejaChoisis\)/', $dql);
        self::assertSame([5, 14], $requete->getParameter('dejaChoisis')?->getValue());
    }

    /**
     * Le OU doit porter sur la seule condition d'activité, et rester dans la
     * même clause que le filtre : un dossier de type « JP » ne doit pas voir
     * remonter un motif « KBIS » au prétexte qu'il a été coché ailleurs.
     *
     * @dataProvider fournirLesDeuxDepots
     */
    public function testLeFiltreResteImperatif(string $nom): void
    {
        $dql = $this->depot($nom)->findByFiltre('KBIS', [5])->getQuery()->getDQL();

        self::assertStringContainsString('a.filtre LIKE :filtre', $dql);
        self::assertMatchesRegularExpression(
            '/WHERE\s+a\.filtre LIKE :filtre\s+AND\s*\(/i',
            $dql,
            'le filtre est obligatoire, la condition d\'activité est l\'alternative'
        );
    }

    /**
     * @dataProvider fournirLesDeuxDepots
     */
    public function testLOrdreDAffichageEstConserve(string $nom): void
    {
        $dql = $this->depot($nom)->findByFiltre('JP')->getQuery()->getDQL();

        self::assertStringContainsString('ORDER BY a.positionLast ASC', $dql);
    }

    /**
     * @dataProvider fournirLesDeuxDepots
     */
    public function testLeDqlEstInterpretable(string $nom): void
    {
        // L'analyse échouerait sur un champ inexistant : c'est elle qui
        // vérifie que la propriété « actif » est bien cartographiée.
        self::assertNotNull($this->depot($nom)->findByFiltre('JP', [1])->getQuery()->getAST());
    }
}

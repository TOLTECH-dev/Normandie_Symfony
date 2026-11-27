<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Instruction_reason;
use App\Form\MotifsDejaChoisis;
use PHPUnit\Framework\TestCase;

/**
 * Récupération des motifs déjà retenus sur un dossier (D26013).
 *
 * Ce que cela protège : depuis que les motifs peuvent être désactivés, les
 * listes de choix ne proposent plus que les motifs actifs. Si les motifs déjà
 * retenus n'y étaient pas réintroduits, rouvrir un dossier instruit avec un
 * motif depuis retiré ferait échouer le formulaire — la valeur liée ne
 * figurant plus parmi les choix.
 */
class MotifsDejaChoisisTest extends TestCase
{
    private function motif(int $id): Instruction_reason
    {
        $motif = new Instruction_reason();

        // L'identifiant est généré par la base : on le pose par réflexion,
        // comme le fait Doctrine à l'hydratation.
        $propriete = new \ReflectionProperty(Instruction_reason::class, 'id');
        $propriete->setValue($motif, $id);

        return $motif;
    }

    /**
     * La forme réellement stockée : les colonnes `*_reason` contiennent les
     * entités sérialisées, pas des identifiants.
     */
    public function testLesEntitesDonnentLeursIdentifiants(): void
    {
        $donnees = new class($this->motif(5), $this->motif(14)) {
            public function __construct(private readonly object $a, private readonly object $b)
            {
            }

            /** @return list<object> */
            public function getJPreason(): array
            {
                return [$this->a, $this->b];
            }
        };

        self::assertSame([5, 14], MotifsDejaChoisis::ids($donnees, 'getJPreason'));
    }

    /**
     * Forme défensive : une donnée ancienne, un import ou une évolution du
     * stockage ne doit pas faire tomber un écran d'instruction.
     *
     * @dataProvider fournirFormesAcceptees
     *
     * @param array<int, mixed> $stockees
     * @param list<int>         $attendus
     */
    public function testLesFormesEntieresEtTextuellesSontAcceptees(array $stockees, array $attendus): void
    {
        $donnees = new class($stockees) {
            /** @param array<int, mixed> $motifs */
            public function __construct(private readonly array $motifs)
            {
            }

            /** @return array<int, mixed> */
            public function getJPreason(): array
            {
                return $this->motifs;
            }
        };

        self::assertSame($attendus, MotifsDejaChoisis::ids($donnees, 'getJPreason'));
    }

    /**
     * @return iterable<string, array{array<int, mixed>, list<int>}>
     */
    public static function fournirFormesAcceptees(): iterable
    {
        yield 'entiers' => [[3, 7], [3, 7]];
        yield 'chaines numeriques' => [['3', '7'], [3, 7]];
        yield 'doublons' => [[3, 3, 7], [3, 7]];
        yield 'valeurs inexploitables' => [[3, null, 'abc', [], 7], [3, 7]];
        yield 'liste vide' => [[], []];
    }

    public function testUneCreationSansDonneesNeRendRien(): void
    {
        self::assertSame([], MotifsDejaChoisis::ids(null, 'getJPreason'));
    }

    /**
     * Le getter est désigné par son nom : une faute de frappe ne doit pas
     * faire tomber l'écran, seulement ne rien conserver.
     */
    public function testUnAccesseurInconnuNeRendRien(): void
    {
        $donnees = new class() {
            /** @return list<int> */
            public function getJPreason(): array
            {
                return [1];
            }
        };

        self::assertSame([], MotifsDejaChoisis::ids($donnees, 'getMotifInexistant'));
    }

    public function testUnGetterQuiNeRendPasUneListeNeRendRien(): void
    {
        $donnees = new class() {
            public function getJPreason(): ?string
            {
                return 'pas une liste';
            }
        };

        self::assertSame([], MotifsDejaChoisis::ids($donnees, 'getJPreason'));
    }
}

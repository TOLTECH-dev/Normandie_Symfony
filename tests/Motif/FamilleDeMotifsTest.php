<?php

declare(strict_types=1);

namespace App\Tests\Motif;

use App\Entity\Instruction_reason;
use App\Entity\Remboursement_reason;
use App\Motif\FamilleDeMotifs;
use PHPUnit\Framework\TestCase;

/**
 * Les deux familles de motifs de non-conformité (D26013).
 *
 * Cette classe fait le lien entre l'écran d'administration et les deux tables
 * de motifs. Trois erreurs y seraient silencieuses et coûteuses :
 *
 *  - rattacher un motif à la mauvaise famille, donc à la mauvaise table ;
 *  - écrire un code de document qui ne correspond à aucun `findByFiltre`, ce
 *    qui créerait un motif que personne ne verrait jamais ;
 *  - laisser toucher à la position 99, qui commande l'ouverture du champ de
 *    saisie libre sur les écrans d'instruction.
 */
class FamilleDeMotifsTest extends TestCase
{
    public function testLesDeuxFamillesPointentVersLeursEntites(): void
    {
        self::assertSame(
            Instruction_reason::class,
            FamilleDeMotifs::entite(FamilleDeMotifs::INSTRUCTION)
        );

        self::assertSame(
            Remboursement_reason::class,
            FamilleDeMotifs::entite(FamilleDeMotifs::REMBOURSEMENT)
        );
    }

    public function testUneFamilleInconnueEstRefuseeEtNonDevinee(): void
    {
        self::assertFalse(FamilleDeMotifs::existe('audit'));

        $this->expectException(\InvalidArgumentException::class);
        FamilleDeMotifs::entite('audit');
    }

    /**
     * Les codes de document doivent être exactement ceux attendus par les
     * `findByFiltre` des formulaires d'instruction. Un code approchant
     * produirait un motif orphelin, créé sans erreur et jamais affiché.
     */
    public function testLesCodesDeDocumentSontCeuxDesFormulairesDInstruction(): void
    {
        self::assertSame(
            ['JP', 'KBIS', 'AI', 'MANDAT'],
            array_keys(FamilleDeMotifs::documents(FamilleDeMotifs::INSTRUCTION))
        );

        self::assertSame(
            ['cheque', 'facture', 'rib', 'fiche_travaux'],
            array_keys(FamilleDeMotifs::documents(FamilleDeMotifs::REMBOURSEMENT))
        );
    }

    public function testLeChoixDUnDocumentPorteSaFamilleEtSonCode(): void
    {
        $groupes = FamilleDeMotifs::choixGroupes();

        self::assertArrayHasKey('Instruction administrative', $groupes);
        self::assertArrayHasKey('Remboursement', $groupes);

        self::assertSame(
            'instruction|MANDAT',
            $groupes['Instruction administrative']['Mandat donné au conseiller']
        );

        self::assertSame(
            'remboursement|fiche_travaux',
            $groupes['Remboursement']['Fiche descriptive des travaux']
        );
    }

    public function testUnChoixSeSepareEnFamilleEtCode(): void
    {
        self::assertSame(['instruction', 'AI'], FamilleDeMotifs::separerLeChoix('instruction|AI'));
        self::assertSame(['remboursement', 'rib'], FamilleDeMotifs::separerLeChoix('remboursement|rib'));
    }

    /**
     * @dataProvider lesChoixInvalides
     */
    public function testUnChoixInvalideEstRefuse(string $valeur): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FamilleDeMotifs::separerLeChoix($valeur);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lesChoixInvalides(): array
    {
        return [
            'sans séparateur' => ['instruction'],
            'famille inconnue' => ['audit|AI'],
            'code inconnu dans la bonne famille' => ['instruction|RIB'],
            'code de l\'autre famille' => ['instruction|cheque'],
            'vide' => [''],
        ];
    }

    /**
     * La garantie qui protège le champ de saisie libre : le motif en position
     * 99 est intouchable, quel que soit son libellé.
     */
    public function testSeuleLaPositionReserveeEstProtegee(): void
    {
        self::assertTrue(FamilleDeMotifs::estProtege(99));

        foreach ([null, 1, 2, 50, 98, 100] as $position) {
            self::assertFalse(
                FamilleDeMotifs::estProtege($position),
                'La position ' . var_export($position, true) . ' ne doit pas être protégée.'
            );
        }
    }

    /**
     * Les places proposées pour un nouveau motif.
     *
     * Le cas de référence est celui donné en recette : places 1, 2, 3 et 5
     * prises, on propose 4 puis 6. La liste s'arrête après la première place
     * libre qui suit la dernière occupée — proposer tout jusqu'à 98
     * n'apporterait rien, les positions n'étant qu'un ordre d'affichage.
     *
     * @dataProvider lesListesDePositions
     *
     * @param list<int> $prises
     * @param list<int> $attendues
     */
    public function testSeulesLesPlacesLibresSontProposees(array $prises, ?int $conservee, array $attendues): void
    {
        self::assertSame($attendues, FamilleDeMotifs::positionsDisponibles($prises, $conservee));
    }

    /**
     * @return array<string, array{0: list<int>, 1: int|null, 2: list<int>}>
     */
    public static function lesListesDePositions(): array
    {
        return [
            'le cas de la recette : 1, 2, 3 et 5 prises' => [[1, 2, 3, 5], null, [4, 6]],
            'liste vide' => [[], null, [1]],
            'liste continue' => [[1, 2, 3], null, [4]],
            'plusieurs trous' => [[1, 4, 6], null, [2, 3, 5, 7]],
            // « Autre » occupe la dernière place et n'est jamais proposé : il
            // ne doit pas non plus allonger la liste jusqu'à 98.
            'la place réservée est ignorée' => [[1, 2, 99], null, [3]],
            // En modification, le motif garde sa place, même s'il est le
            // dernier de la liste.
            'le motif garde sa place au milieu' => [[1, 2, 3, 5], 2, [2, 4, 6]],
            'le motif garde sa dernière place' => [[1, 2, 3, 5], 5, [4, 5, 6]],
            // Les places libres AVANT ce motif doivent être proposées : c'est
            // ainsi qu'on le remonte dans la liste.
            'seul motif de sa liste' => [[3], 3, [1, 2, 3, 4]],
        ];
    }

    public function testLaPlaceReserveeNEstJamaisProposee(): void
    {
        $prises = range(1, 97);

        self::assertSame([98], FamilleDeMotifs::positionsDisponibles($prises));
        self::assertNotContains(
            FamilleDeMotifs::POSITION_AUTRE,
            FamilleDeMotifs::positionsDisponibles(range(1, 98))
        );
    }

    /**
     * Le libellé d'un document inconnu est rendu tel quel, et non masqué : une
     * ligne de base qui porterait un filtre hors nomenclature doit rester
     * visible dans l'écran d'administration, pas disparaître.
     */
    public function testUnDocumentHorsNomenclatureResteAffiche(): void
    {
        self::assertSame(
            'INCONNU',
            FamilleDeMotifs::libelleDuDocument(FamilleDeMotifs::INSTRUCTION, 'INCONNU')
        );

        self::assertSame('', FamilleDeMotifs::libelleDuDocument(FamilleDeMotifs::INSTRUCTION, null));
    }
}

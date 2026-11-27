<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\MotifNonConformiteType;
use App\Motif\FamilleDeMotifs;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;

/**
 * Le formulaire d'un motif de non-conformité (D26013).
 *
 * Deux garanties y sont vérifiées, toutes deux issues de dégâts constatés en
 * recette le 23/11/2026 :
 *
 *  1. LE DOCUMENT NE CHANGE PAS. Déplacer un motif d'un document à un autre
 *     détache silencieusement les dossiers qui le citent : la case à cocher est
 *     retrouvée par l'identifiant du motif DANS la liste du document, et un
 *     motif sorti de cette liste n'y est plus coché. La non-conformité
 *     disparaît alors du dossier au prochain enregistrement. C'est ainsi qu'un
 *     motif « Autre » s'est retrouvé en double sur une liste et absent d'une
 *     autre ;
 *
 *  2. SEULES LES POSITIONS LIBRES SONT PROPOSÉES. Le doublon de position n'est
 *     pas refusé après coup, il est impossible à saisir.
 *
 * Le formulaire se construit sans base : son document et ses positions lui sont
 * fournis par le contrôleur.
 */
class MotifNonConformiteTypeTest extends KernelTestCase
{
    /**
     * Le jeton CSRF est écarté : il n'est pas l'objet de ces tests, et sans
     * cela TOUTES les soumissions échouent — y compris celles qui doivent
     * échouer, qui passeraient alors pour la mauvaise raison.
     *
     * @param list<int> $positions
     */
    private function creer(string $document, array $positions, array $donnees = []): FormInterface
    {
        self::bootKernel();

        return self::getContainer()->get('form.factory')->create(
            MotifNonConformiteType::class,
            $donnees + ['document' => $document, 'actif' => true],
            ['document' => $document, 'positions' => $positions, 'csrf_protection' => false]
        );
    }

    /**
     * @return array<string, string>
     */
    private function erreurs(FormInterface $formulaire): array
    {
        $erreurs = [];

        foreach ($formulaire->getErrors(true) as $erreur) {
            $erreurs[$erreur->getOrigin()?->getName() ?? '_'] = $erreur->getMessage();
        }

        return $erreurs;
    }

    public function testSeulLeDocumentFourniEstProposeEtIlEstInerte(): void
    {
        $vue = $this->creer('instruction|MANDAT', [4, 6])->get('document')->createView();

        self::assertTrue($vue->vars['disabled'], "Le document ne doit pas être modifiable : le déplacer détacherait les dossiers qui citent ce motif.");
        self::assertCount(1, $vue->vars['choices']);
        self::assertSame('Mandat donné au conseiller', $vue->vars['choices'][0]->label);
    }

    public function testSeulesLesPositionsFourniesSontProposees(): void
    {
        $vue = $this->creer('instruction|MANDAT', [4, 6, 7])->get('position')->createView();

        self::assertSame(
            ['4', '6', '7'],
            array_map(static fn ($choix): string => (string) $choix->value, $vue->vars['choices'])
        );
    }

    /**
     * La conséquence concrète du champ désactivé : une valeur soumise pour le
     * document est ignorée, et le motif reste sur sa liste d'origine. C'est la
     * protection réelle — masquer le champ dans le gabarit n'y suffirait pas.
     */
    public function testUnDocumentSoumisEstIgnore(): void
    {
        $formulaire = $this->creer('instruction|AI', [12, 13], ['libelle' => 'Essai', 'position' => 12]);

        $formulaire->submit([
            'document' => 'instruction|MANDAT',
            'libelle' => 'Essai',
            'position' => '12',
            'actif' => '1',
        ]);

        self::assertTrue($formulaire->isValid(), implode(' / ', $this->erreurs($formulaire)));
        self::assertSame('instruction|AI', $formulaire->getData()['document']);
    }

    /**
     * Une position absente de la liste est refusée, qu'elle soit déjà occupée,
     * réservée à « Autre », ou simplement inventée. Le formulaire ne peut pas
     * être contourné en forgeant la requête.
     *
     * @dataProvider lesPositionsRefusees
     */
    public function testUnePositionHorsListeEstRefusee(string $position): void
    {
        $formulaire = $this->creer('instruction|MANDAT', [4, 6]);
        $formulaire->submit([
            'libelle' => 'Essai',
            'position' => $position,
            'actif' => '1',
        ]);

        self::assertFalse($formulaire->isValid(), 'La position ' . $position . ' aurait dû être refusée.');
        self::assertArrayHasKey('position', $this->erreurs($formulaire));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lesPositionsRefusees(): array
    {
        return [
            'une place déjà occupée' => ['3'],
            'la place réservée à « Autre »' => ['99'],
            'au-delà de la place réservée' => ['100'],
            'zéro' => ['0'],
            'négative' => ['-1'],
            'vide' => [''],
        ];
    }

    public function testUnePositionProposeeEstAcceptee(): void
    {
        $formulaire = $this->creer('remboursement|rib', [4, 8]);
        $formulaire->submit([
            'libelle' => 'Le RIB est illisible',
            'position' => '8',
            'actif' => '1',
        ]);

        self::assertTrue($formulaire->isValid(), implode(' / ', $this->erreurs($formulaire)));
        self::assertSame(8, $formulaire->getData()['position']);
        self::assertSame('remboursement|rib', $formulaire->getData()['document']);
    }

    public function testUnLibelleVideOuTropLongEstRefuse(): void
    {
        foreach (['', str_repeat('a', FamilleDeMotifs::LONGUEUR_MAX_LIBELLE + 1)] as $libelle) {
            $formulaire = $this->creer('instruction|MANDAT', [4]);
            $formulaire->submit([
                'libelle' => $libelle,
                'position' => '4',
                'actif' => '1',
            ]);

            self::assertFalse($formulaire->isValid(), 'Un libellé de ' . mb_strlen($libelle) . ' caractères aurait dû être refusé.');
            self::assertArrayHasKey('libelle', $this->erreurs($formulaire));
        }
    }

    /**
     * Un document inventé ne passe pas la construction du formulaire : le motif
     * créé n'apparaîtrait sur aucun écran d'instruction, sans que rien ne le
     * signale.
     */
    public function testUnDocumentInconnuEmpecheLaConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->creer('instruction|INVENTE', [1]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Form;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Les motifs déjà retenus sur un dossier restent proposés (D26013).
 *
 * Depuis que les motifs peuvent être désactivés, les listes ne proposent plus
 * que les motifs actifs. Un dossier instruit autrefois avec un motif depuis
 * retiré doit malgré tout le conserver : sa case doit rester cochée, et le
 * motif ne doit pas disparaître au prochain enregistrement.
 *
 * CE QUE CE FICHIER PROTÈGE, et pourquoi il existe :
 *
 * la première version lisait les identifiants déjà retenus depuis
 * `$options['data']` dans buildForm(). Or Symfony ne renseigne cette option
 * que sur le formulaire RACINE. Les quatre formulaires concernés sont tous des
 * enfants — Instruction_travauxType et Instruction_auditEnergieType sous
 * Instruction_Type, les deux types de remboursement sous le leur. L'option y
 * était absente, la liste des identifiants donc toujours vide, et la
 * correction ne faisait rien.
 *
 * Rien ne le signalait : ni exception, ni trace. Le motif disparaissait
 * simplement de l'écran, puis du dossier au premier enregistrement.
 *
 * CE QU'IL NE PEUT PAS FAIRE : construire réellement ces formulaires. Les
 * listes sont des EntityType en `expanded`, ce qui oblige Symfony à charger
 * tous les choix dès la construction, donc à interroger la base. Il n'existe
 * pas de base de test sur ce projet. Le premier test vérifie donc la règle du
 * framework sur un couple de formulaires jetables, et le second que le code
 * s'y conforme.
 */
class MotifsDejaChoisisDansLeFormulaireTest extends KernelTestCase
{
    private const FORMULAIRES = [
        'Instruction_travauxType',
        'Instruction_auditEnergieType',
        'Remboursement_instructionType',
        'Remboursement_travaux_instructionType',
    ];

    /**
     * La règle du framework, vérifiée et non supposée : un sous-formulaire ne
     * reçoit PAS l'option « data », là où le formulaire racine la porte.
     *
     * C'est toute la raison d'être de l'écoute de PRE_SET_DATA. Si ce test
     * venait à tomber — changement de version de Symfony —, alors la lecture
     * directe de `$options['data']` redeviendrait possible, et ce fichier
     * pourrait être reconsidéré.
     */
    public function testUnSousFormulaireNeRecoitPasLOptionData(): void
    {
        self::bootKernel();

        SondeParentType::$vuParLeParent = [];
        SondeEnfantType::$vuParLEnfant = [];

        self::getContainer()->get('form.factory')->create(SondeParentType::class, ['enfant' => null]);

        self::assertTrue(
            SondeParentType::$vuParLeParent['presente'],
            'Le formulaire racine devrait porter son option data.'
        );

        self::assertFalse(
            SondeEnfantType::$vuParLEnfant['presente'],
            "Symfony transmet désormais « data » aux sous-formulaires. Les listes de motifs pourraient repasser dans buildForm()."
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lesQuatreFormulaires(): array
    {
        $cas = [];

        foreach (self::FORMULAIRES as $formulaire) {
            $cas[$formulaire] = [$formulaire];
        }

        return $cas;
    }

    /**
     * @dataProvider lesQuatreFormulaires
     */
    public function testLesListesSontConstruitesAPreSetDataEtNonDepuisLesOptions(string $formulaire): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../src/Form/' . $formulaire . '.php');

        self::assertStringContainsString(
            'FormEvents::PRE_SET_DATA',
            $source,
            $formulaire . " : les listes de motifs doivent être ajoutées à PRE_SET_DATA, seul moment où les données du dossier sont connues."
        );

        self::assertStringContainsString(
            'MotifsDejaChoisis::ids($donnees',
            $source,
            $formulaire . " : les identifiants déjà retenus doivent être tirés des données de l'événement."
        );

        self::assertStringNotContainsString(
            "MotifsDejaChoisis::ids(\$options['data']",
            $source,
            $formulaire . " : \$options['data'] est toujours absent sur un sous-formulaire ; la liste des motifs retenus serait vide et le motif serait perdu en silence."
        );
    }

    /**
     * Les listes doivent toutes être déclarées : une liste oubliée laisserait
     * le champ absent du formulaire, et l'écran d'instruction lèverait une
     * erreur Twig à l'inclusion de son bloc.
     */
    public function testChaqueListeDeclareSonFiltreEtSonAccesseur(): void
    {
        $attendu = [
            'Instruction_travauxType' => ['JP' => 'getJPreason', 'KBIS' => 'getKBISreason', 'AI' => 'getAIreason', 'MANDAT' => 'getMandatReason'],
            'Instruction_auditEnergieType' => ['JP' => 'getJPreason', 'KBIS' => 'getKBISreason', 'AI' => 'getAIreason', 'MANDAT' => 'getMandatReason'],
            'Remboursement_instructionType' => ['cheque' => 'getChequeReason', 'facture' => 'getFactureReason', 'rib' => 'getRibReason'],
            'Remboursement_travaux_instructionType' => ['fiche_travaux' => 'getFicheTravauxReason'],
        ];

        foreach ($attendu as $formulaire => $listes) {
            $source = (string) file_get_contents(__DIR__ . '/../../src/Form/' . $formulaire . '.php');

            foreach ($listes as $filtre => $accesseur) {
                self::assertStringContainsString(
                    "'filtre' => '" . $filtre . "', 'accesseur' => '" . $accesseur . "'",
                    $source,
                    $formulaire . ' : la liste ' . $filtre . ' est absente ou mal rattachée.'
                );
            }
        }
    }
}

/**
 * Formulaires jetables, qui ne servent qu'à observer ce que Symfony transmet.
 */
class SondeParentType extends AbstractType
{
    /** @var array<string, bool> */
    public static array $vuParLeParent = [];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        self::$vuParLeParent['presente'] = \array_key_exists('data', $options);

        $builder->add('enfant', SondeEnfantType::class, ['label' => false]);
    }
}

class SondeEnfantType extends AbstractType
{
    /** @var array<string, bool> */
    public static array $vuParLEnfant = [];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        self::$vuParLEnfant['presente'] = \array_key_exists('data', $options);
    }
}

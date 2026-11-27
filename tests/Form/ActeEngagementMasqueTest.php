<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Demande_travaux_devis;
use PHPUnit\Framework\TestCase;

/**
 * Masquage du bloc « Votre acte d'engagement » (D26013).
 *
 * Le bloc ne doit plus apparaître : ni côté bénéficiaire au dépôt d'une
 * demande de travaux, ni côté auditeur ou administrateur dans l'onglet devis
 * de la consultation d'un dossier.
 *
 * Retirer les inclusions ne suffit PAS, et c'est tout l'objet de ce fichier.
 * Les cinq formulaires concernés appellent `form_rest(form)` : un champ non
 * rendu par un gabarit y est émis d'office, à la fin du formulaire. Le bloc
 * aurait donc disparu, mais le champ de dépôt serait resté visible, nu et hors
 * de toute section — exactement le contraire du résultat demandé.
 *
 * Les deux conditions sont liées, et ce test les tient ensemble :
 *
 *   - le champ a quitté le formulaire, sinon form_rest le réaffiche ;
 *   - aucun gabarit n'inclut plus les partiels, dont l'un référence
 *     `form.acteEngagement` et lèverait désormais une erreur Twig.
 *
 * Ce qui reste volontairement en place : l'entité, ses accesseurs, la route de
 * téléchargement (typeFile 15) et l'inventaire d'archivage. Les actes déjà
 * déposés ne sont pas perdus, et le dernier test le vérifie.
 */
class ActeEngagementMasqueTest extends TestCase
{
    private const RACINE = __DIR__ . '/../..';

    /**
     * Vérification sur le source, et non sur le formulaire construit.
     *
     * Construire Demande_travaux_devisType demande de fournir l'option
     * `trait_choices`, un tableau d'une quinzaine d'entrées que buildForm lit
     * dès ses premières lignes. Un test qui le reconstituerait casserait au
     * moindre ajout dans ce tableau, sans rapport avec l'acte d'engagement. La
     * lecture du source dit exactement ce qui nous intéresse, et rien d'autre.
     */
    public function testLeChampNEstPlusDeclareDansLeFormulaireDeDevis(): void
    {
        $source = (string) file_get_contents(self::RACINE . '/src/Form/Demande_travaux_devisType.php');

        self::assertStringNotContainsString(
            "->add('acteEngagement'",
            $source,
            "Le champ est de retour dans le formulaire : form_rest() va le rendre à la fin de la page, hors de toute section. Si le bloc doit revenir, remettre aussi les inclusions des partiels."
        );
    }

    public function testAucunGabaritNIncluPlusLesPartiels(): void
    {
        $fautifs = [];

        $parcours = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::RACINE . '/templates', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($parcours as $fichier) {
            if (!$fichier->isFile() || 'twig' !== $fichier->getExtension()) {
                continue;
            }

            $chemin = $fichier->getPathname();

            // Les partiels eux-mêmes sont conservés pour un éventuel retour du
            // bloc : c'est leur INCLUSION qui est proscrite, pas leur présence.
            if ('acte_engagement.html.twig' === basename($chemin)) {
                continue;
            }

            if (str_contains((string) file_get_contents($chemin), 'acte_engagement.html.twig')) {
                $fautifs[] = basename(\dirname($chemin)) . '/' . basename($chemin);
            }
        }

        self::assertSame(
            [],
            $fautifs,
            "Ces gabarits incluent encore un partiel d'acte d'engagement. Le partiel de formulaire référence form.acteEngagement, qui n'existe plus : la page lèvera une erreur Twig."
        );
    }

    /**
     * Le champ reste porté par l'entité : c'est lui qui désigne les documents
     * déjà déposés, que le téléchargement et l'archivage continuent de servir.
     *
     * L'entité n'est pas instanciée : son constructeur lit $_SESSION['login'],
     * qui n'existe pas hors d'une requête.
     */
    public function testLesActesDejaDeposesRestentAtteignables(): void
    {
        foreach ([
            'getActeEngagementUrl',
            'getActeEngagementAlt',
            'acteEngagement_getWebPath',
        ] as $methode) {
            self::assertTrue(
                method_exists(Demande_travaux_devis::class, $methode),
                $methode . ' a disparu : les actes déjà déposés deviennent inatteignables.'
            );
        }
    }
}

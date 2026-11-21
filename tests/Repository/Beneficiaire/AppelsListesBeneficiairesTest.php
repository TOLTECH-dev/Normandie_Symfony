<?php

declare(strict_types=1);

namespace App\Tests\Repository\Beneficiaire;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fou sur les appels aux listes de bénéficiaires.
 *
 * La correction de l'injection SQL a remplacé les fragments « AND … » concaténés par un objet de
 * critères. Un appelant avait été oublié : la page « Assistance bénéficiaire » continuait de passer
 * une chaîne, ce qui n'a échoué qu'à l'exécution — et en production, par une TypeError, car les
 * contrôleurs déclarent strict_types et aucune analyse statique n'est installée.
 *
 * Ce test relit le code source des contrôleurs et exige que ces méthodes ne reçoivent que des
 * critères construits par BeneficiaireListCriteria. Il suit les affectations locales, car la valeur
 * fautive n'était pas un littéral mais une variable. Ni base de données ni conteneur requis.
 */
final class AppelsListesBeneficiairesTest extends TestCase
{
    /**
     * Méthodes de BeneficiaireRepository qui n'acceptent plus que des critères validés.
     *
     * « countAll » et « findAllAjax » sont des noms partagés avec d'autres dépôts, dont les
     * signatures diffèrent : ils ne sont examinés que dans le contrôleur des bénéficiaires.
     */
    private const METHODES = [
        'countAllForAssistanceBeneficiaire',
        'findAllAjaxForAssistanceBeneficiaire',
    ];

    private const METHODES_AMBIGUES = ['countAll', 'findAllAjax'];

    private const CONTROLEUR_BENEFICIAIRE = 'BeneficiaireController.php';

    /** Fabriques de critères */
    private const FABRIQUES = ['pourListe', 'pourAssistance'];

    public function testLesListesNeRecoiventQueDesCriteresValides(): void
    {
        if (!class_exists(ParserFactory::class)) {
            self::markTestSkipped('nikic/php-parser absent.');
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $finder = new NodeFinder();
        $fautifs = [];
        $verifies = 0;

        foreach ($this->fichiersControleurs() as $fichier) {
            $arbre = $parser->parse((string) file_get_contents($fichier));
            if (null === $arbre) {
                continue;
            }

            /** @var list<Node\Stmt\ClassMethod> $methodes */
            $methodes = $finder->findInstanceOf($arbre, Node\Stmt\ClassMethod::class);

            $noms = self::CONTROLEUR_BENEFICIAIRE === basename($fichier)
                ? array_merge(self::METHODES, self::METHODES_AMBIGUES)
                : self::METHODES;

            foreach ($methodes as $methode) {
                $affectations = $this->affectationsLocales($finder, $methode);

                foreach ($this->appelsDeListe($finder, $methode, $noms) as $appel) {
                    foreach ($appel->args as $argument) {
                        if (!$argument instanceof Node\Arg) {
                            continue;
                        }

                        ++$verifies;
                        if ($this->estUnCritere($argument->value, $affectations)) {
                            continue;
                        }

                        $fautifs[] = sprintf(
                            '%s ligne %d : %s() ne reçoit pas de BeneficiaireListCriteria',
                            basename($fichier),
                            $appel->getLine(),
                            (string) $appel->name
                        );
                    }
                }
            }
        }

        self::assertGreaterThan(0, $verifies, 'Aucun appel analysé : le test ne garde plus rien.');
        self::assertSame([], $fautifs, implode("\n", $fautifs));
    }

    /**
     * Appels aux méthodes de liste, quel que soit l'objet sur lequel ils portent.
     *
     * @param list<string> $noms
     *
     * @return list<Node\Expr\MethodCall>
     */
    private function appelsDeListe(NodeFinder $finder, Node\Stmt\ClassMethod $methode, array $noms): array
    {
        /** @var list<Node\Expr\MethodCall> $appels */
        $appels = $finder->find($methode, static function (Node $noeud) use ($noms): bool {
            return $noeud instanceof Node\Expr\MethodCall
                && $noeud->name instanceof Node\Identifier
                && in_array($noeud->name->toString(), $noms, true);
        });

        return $appels;
    }

    /**
     * Valeurs affectées à chaque variable locale de la méthode.
     *
     * @return array<string, list<Node\Expr>>
     */
    private function affectationsLocales(NodeFinder $finder, Node\Stmt\ClassMethod $methode): array
    {
        $affectations = [];

        /** @var list<Node\Expr\Assign> $assignations */
        $assignations = $finder->findInstanceOf($methode, Node\Expr\Assign::class);

        foreach ($assignations as $assignation) {
            if ($assignation->var instanceof Node\Expr\Variable && is_string($assignation->var->name)) {
                $affectations[$assignation->var->name][] = $assignation->expr;
            }
        }

        return $affectations;
    }

    /**
     * Une expression est un critère si elle sort d'une fabrique de BeneficiaireListCriteria, ou
     * d'un appel enchaîné sur un tel objet (->pourStructure(…)). Une variable est un critère si
     * TOUTES ses affectations en sont : une seule chaîne suffit à la refuser.
     *
     * @param array<string, list<Node\Expr>> $affectations
     */
    private function estUnCritere(Node\Expr $expression, array $affectations, int $profondeur = 0): bool
    {
        if ($profondeur > 10) {
            return false;
        }

        if ($expression instanceof Node\Expr\StaticCall && $expression->name instanceof Node\Identifier) {
            return $expression->class instanceof Node\Name
                && 'BeneficiaireListCriteria' === $expression->class->getLast()
                && in_array($expression->name->toString(), self::FABRIQUES, true);
        }

        if ($expression instanceof Node\Expr\MethodCall) {
            return $this->estUnCritere($expression->var, $affectations, $profondeur + 1);
        }

        if ($expression instanceof Node\Expr\Variable && is_string($expression->name)) {
            $valeurs = $affectations[$expression->name] ?? [];
            if ([] === $valeurs) {
                return false;
            }

            foreach ($valeurs as $valeur) {
                if (!$this->estUnCritere($valeur, $affectations, $profondeur + 1)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function fichiersControleurs(): array
    {
        $fichiers = glob(dirname(__DIR__, 3) . '/src/Controller/*.php');
        self::assertNotFalse($fichiers, 'Répertoire des contrôleurs introuvable.');
        self::assertNotEmpty($fichiers);

        return array_values($fichiers);
    }
}

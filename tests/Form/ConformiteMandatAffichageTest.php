<?php

declare(strict_types=1);

namespace App\Tests\Form;

use PHPUnit\Framework\TestCase;

/**
 * Affichage de la conformité du mandat en instruction administrative (D26013).
 *
 * Trois blocs, comme pour les conformités déjà en place : la conformité
 * elle-même, les motifs de non-conformité, le motif libre. Les deux derniers
 * sont rendus masqués et ne s'ouvrent que par JavaScript, selon la valeur
 * choisie.
 *
 * Toute la mécanique repose donc sur des chaînes de caractères partagées entre
 * trois fichiers qui s'ignorent : l'identifiant du bloc dans le gabarit, le
 * sélecteur dans displayOption.js, et le nom du champ Symfony dont dérive
 * l'identifiant du widget. Si l'une d'elles change d'un seul caractère, rien
 * ne casse : le bloc des motifs reste simplement masqué pour toujours, et
 * l'instructeur ne peut plus motiver un refus. Aucune erreur, aucune trace.
 *
 * D'où ce test, qui vérifie la concordance plutôt que le comportement.
 */
class ConformiteMandatAffichageTest extends TestCase
{
    private const RACINE = __DIR__ . '/../..';

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function lesDeuxEcrans(): array
    {
        return [
            'travaux' => ['Travaux', 'instruction_travaux'],
            'audit énergétique' => ['AuditEnergie', 'instruction_auditEnergie'],
        ];
    }

    private function lire(string $chemin): string
    {
        $complet = self::RACINE . '/' . $chemin;

        self::assertFileExists($complet, $chemin . ' est absent.');

        return (string) file_get_contents($complet);
    }

    /**
     * @dataProvider lesDeuxEcrans
     */
    public function testLesTroisBlocsExistentEtPortentLesIdentifiantsAttendus(string $ecran, string $champ): void
    {
        $base = 'templates/BackOffice/Demande/' . $ecran . '/inc/form_instruction/';

        $attendus = [
            'Mandat.html.twig' => ['id="bloc-MANDAT"', $champ . '.mandatConformite'],
            'Mandat_reason.html.twig' => ['id="bloc-MANDATcase"', $champ . '.mandatReason'],
            'Mandat_reason_autre.html.twig' => ['id="bloc-MANDATcaseAutre"', $champ . '.mandatReasonAutre'],
        ];

        foreach ($attendus as $partiel => $marqueurs) {
            $contenu = $this->lire($base . $partiel);

            foreach ($marqueurs as $marqueur) {
                self::assertStringContainsString($marqueur, $contenu, $partiel . ' : ' . $marqueur . ' manquant.');
            }
        }

        // Les deux blocs de motifs doivent partir MASQUÉS : la non-conformité
        // est l'exception, et les ouvrir d'emblée inviterait à cocher des
        // motifs sur un dossier déclaré conforme.
        foreach (['Mandat_reason.html.twig', 'Mandat_reason_autre.html.twig'] as $partiel) {
            self::assertStringContainsString(
                'bloc hidden',
                $this->lire($base . $partiel),
                $partiel . " doit partir masqué."
            );
        }
    }

    /**
     * @dataProvider lesDeuxEcrans
     */
    public function testLEcranDInstructionIncluLesTroisBlocs(string $ecran, string $champ): void
    {
        $contenu = $this->lire('templates/BackOffice/Demande/' . $ecran . '/examine.html.twig');

        foreach (['Mandat.html.twig', 'Mandat_reason.html.twig', 'Mandat_reason_autre.html.twig'] as $partiel) {
            self::assertStringContainsString(
                'form_instruction/' . $partiel,
                $contenu,
                $ecran . '/examine.html.twig n\'inclut pas ' . $partiel . ' : le bloc ne s\'affichera jamais.'
            );
        }
    }

    /**
     * @dataProvider lesDeuxEcrans
     */
    public function testLeScriptEtLesGabaritsDesignentLesMemesElements(string $ecran, string $champ): void
    {
        $repertoire = 'travaux' === strtolower($ecran) ? 'travaux' : 'auditEnergie';
        $script = $this->lire('assets/js/backOffice/demande/examineDemande/' . $repertoire . '/displayOption.js');

        $prefixe = 'whitelabel_backofficebundle_instruction__' . $champ;

        $attendus = [
            // Les identifiants de bloc, tels que les gabarits les posent.
            "#bloc-MANDATcase'",
            "#bloc-MANDATcaseAutre'",
            // La classe portée par le groupe de cases, qui sert à les décocher
            // quand le dossier redevient conforme.
            '.mandatReason input',
            // Les identifiants de widget, dérivés des noms de champs Symfony.
            '#' . $prefixe . '_mandatConformite',
            '#' . $prefixe . '_mandatReason input:last',
            '#' . $prefixe . '_mandatReasonAutre',
        ];

        foreach ($attendus as $attendu) {
            self::assertStringContainsString(
                $attendu,
                $script,
                $repertoire . '/displayOption.js ne désigne pas ' . $attendu . '.'
            );
        }
    }

    /**
     * @dataProvider lesDeuxEcrans
     */
    public function testLaClasseDuGroupeDeCasesEstCelleQueLeScriptInterroge(string $ecran, string $champ): void
    {
        $repertoire = 'travaux' === strtolower($ecran) ? 'travaux' : 'auditEnergie';

        self::assertStringContainsString(
            "'class': 'mandatReason'",
            $this->lire('templates/BackOffice/Demande/' . $ecran . '/inc/form_instruction/Mandat_reason.html.twig'),
            "Sans cette classe, le script ne retrouve pas les cases et ne les décoche plus quand le dossier redevient conforme : des motifs resteraient enregistrés sur un mandat déclaré conforme."
        );

        self::assertStringContainsString(
            '.mandatReason input',
            $this->lire('assets/js/backOffice/demande/examineDemande/' . $repertoire . '/displayOption.js')
        );
    }

    /**
     * Le script ouvre le champ de motif libre quand la DERNIÈRE case du groupe
     * est cochée (`input:last`), en supposant que ce soit « Autre ». C'est la
     * convention des listes JP, KBIS et AI, où « Autre » porte la position 99.
     *
     * Ce test ne peut pas lire la base. Il vérifie que le script d'installation
     * crée bien ce motif, et le documente : un motif réel ajouté après
     * « Autre » prendrait sa place et déclencherait la saisie libre à tort.
     */
    public function testLeMotifAutreEstPrevuEnDernierePosition(): void
    {
        $sql = $this->lire('sql/d26013_mandat_conseiller.sql');

        self::assertStringContainsString("'MANDAT', 'Autre', 1, 99", $sql);
    }
}

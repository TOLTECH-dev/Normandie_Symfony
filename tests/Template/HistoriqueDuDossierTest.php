<?php

declare(strict_types=1);

namespace App\Tests\Template;

use App\Tests\Security\Authorization\AccessMatrix;
use PHPUnit\Framework\TestCase;

/**
 * Onglet « Historique » de la consultation d'un dossier (D26013).
 *
 * Même besoin que l'onglet « Activité » d'un compte, pris par l'autre bout :
 * là on suit une personne, ici on suit un dossier — qui a fait quoi, et quand.
 *
 * L'écran « Historique » accessible depuis la liste des dossiers existe
 * toujours : c'est un tableau ouvert à tous les profils de consultation. Le
 * nouvel onglet est une chronologie, réservée aux administrateurs.
 */
class HistoriqueDuDossierTest extends TestCase
{
    private static function source(string $relatif): string
    {
        $chemin = \dirname(__DIR__, 2) . '/' . $relatif;

        self::assertFileExists($chemin);

        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        return $contenu;
    }

    /**
     * L'onglet et son contenu sont tous deux sous la même garde : laisser le
     * panneau accessible suffirait à livrer les données dans la page.
     */
    public function testLOngletEtSonContenuSontReservesAuxAdministrateurs(): void
    {
        $source = self::source('templates/BackOffice/Demande/view.html.twig');

        self::assertSame(
            2,
            substr_count($source, "{% if is_granted('ROLE_BO_SUIVI_COMPTES') %}"),
            'Deux gardes attendues : l\'onglet, et le panneau qu\'il ouvre.'
        );

        foreach (['tab_historique', 'view_historique.html.twig'] as $marqueur) {
            $position = strpos($source, $marqueur);
            self::assertIsInt($position, $marqueur . ' introuvable.');

            $avant = substr($source, 0, $position);

            self::assertStringContainsString(
                "{% if is_granted('ROLE_BO_SUIVI_COMPTES') %}",
                substr($avant, max(0, strlen($avant) - 500)),
                $marqueur . ' doit se trouver juste après la garde.'
            );
        }
    }

    /**
     * Le droit employé est le même que pour le suivi des comptes : un seul
     * regroupement à faire évoluer le jour où la Région veut l'ouvrir.
     */
    public function testLeDroitEstCeluiDuSuiviDesComptes(): void
    {
        $matrice = new AccessMatrix(\dirname(__DIR__, 2));

        self::assertContains('ROLE_BO_SUIVI_COMPTES', $matrice->rolesAtteignables('ROLE_ADMIN'));
        self::assertNotContains('ROLE_BO_SUIVI_COMPTES', $matrice->rolesAtteignables('ROLE_INSTRUCTEUR'));
        self::assertNotContains('ROLE_BO_SUIVI_COMPTES', $matrice->rolesAtteignables('ROLE_CONSEILLER'));
    }

    public function testLHistoriqueNEstLuQuePourLesProfilsAutorises(): void
    {
        $source = self::source('src/Controller/DemandeController.php');

        self::assertStringContainsString(
            "\$historique = \$this->isGranted('ROLE_BO_SUIVI_COMPTES')",
            $source,
            'La lecture doit être conditionnée, pas seulement l\'affichage.'
        );
        self::assertStringContainsString('            : [];', $source);
    }

    /**
     * L'écran existant interroge `user` une fois par ligne : jusqu'à 87
     * requêtes pour un dossier. La chronologie résout les auteurs d'un coup.
     *
     * Par une seconde requête, et non par une jointure : `user.username` n'est
     * pas indexé — seul `username_canonical` l'est —, et la jointure se fait
     * donc par parcours complet de la table des comptes.
     */
    public function testLesAuteursSontResolusEnUneSeuleRequete(): void
    {
        $source = self::source('src/Repository/Historique_Repository.php');

        $position = strpos($source, 'public function lesActionsDuDossier');
        self::assertIsInt($position);

        $corps = substr($source, $position, 1200);

        self::assertStringContainsString('ORDER BY date_creation DESC, id DESC', $corps);
        self::assertStringContainsString('$this->avecLesAuteurs($lignes)', $corps);
        self::assertStringNotContainsString('findOneBy', $corps);
        self::assertStringNotContainsString('LEFT JOIN', $corps);
    }

    /**
     * Ce que doit porter chaque repère pour répondre à « qui fait quoi ».
     *
     * @dataProvider lesRenseignementsAttendus
     */
    public function testChaqueRepereDitQuiAFaitQuoi(string $extrait, string $pourquoi): void
    {
        self::assertStringContainsString(
            $extrait,
            self::source('templates/BackOffice/Demande/inc/view_historique.html.twig'),
            $pourquoi
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function lesRenseignementsAttendus(): array
    {
        return [
            'la date et l\'heure' => ['ligne.date_creation|date(', 'Quand.'],
            'l\'action' => ['{{ ligne.action }}', 'Quoi.'],
            'le nom de l\'auteur' => ['ligne.lastname|upper', 'Qui, en clair.'],
            'le login de repli' => ['{{ ligne.auteur_creation }}', 'Qui, quand le compte a été supprimé depuis.'],
            'le profil' => ['ligne.auteur_role', 'À quel titre.'],
            'l\'état du dossier' => ['{{ ligne.statut_slug }}', 'Où en était le dossier à ce moment-là.'],
        ];
    }

    /**
     * La chronologie est partagée avec la fiche utilisateur : une seule
     * feuille, chargée par les deux écrans.
     */
    public function testLaChronologieEstUneFeuillePartagee(): void
    {
        self::assertFileExists(\dirname(__DIR__, 2) . '/assets/styles/backOffice/core/timeline.css');

        foreach ([
            'templates/BackOffice/Demande/view.html.twig',
            'templates/BackOffice/Admin/view.html.twig',
        ] as $gabarit) {
            self::assertStringContainsString(
                "encore_entry_link_tags('backOffice_core_timeline')",
                self::source($gabarit),
                $gabarit . ' doit charger la feuille de la chronologie.'
            );
        }

        self::assertStringNotContainsString(
            '.activite-timeline {',
            self::source('assets/styles/backOffice/admin/view.css'),
            'Les règles de la chronologie ne doivent plus être dupliquées.'
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\EventListener\UserEtatHistoriqueListener;
use App\Repository\User\EtatCompteRepository;
use PHPUnit\Framework\TestCase;

/**
 * Historique des états d'un compte (D26013, point 4, première puce).
 *
 * « Adaptation de la notion d'état des comptes utilisateurs avec la date heure
 * de chaque changement d'état (création, désactivation, réactivation,
 * suppression). »
 *
 * Trois pièges guettent cette fonctionnalité, et les contrôles ci-dessous les
 * verrouillent :
 *
 *  1. ÉCOUTER L'ENTITÉ, PAS LES ÉCRANS. Un compte change aussi d'état par
 *     l'import, par les commandes de purge et par le verrouillage après cinq
 *     échecs de connexion. Un historique posé dans les contrôleurs
 *     d'administration serait muet sur ces chemins-là.
 *
 *  2. SURVIVRE À LA SUPPRESSION. Une clé étrangère vers `user` emporterait la
 *     ligne « suppression » avec le compte qu'elle documente.
 *
 *  3. NE PAS CROIRE `user.date_inactif`. Son nom trompe : sur 9 240 comptes qui
 *     la portent, 8 796 ont exactement la date de création — la colonne a servi
 *     à l'import initial. La reconstituer en désactivations produirait 8 796
 *     événements faux.
 */
class EtatsDuCompteTest extends TestCase
{
    private static function source(string $relatif): string
    {
        $chemin = \dirname(__DIR__, 2) . '/' . $relatif;

        self::assertFileExists($chemin);

        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        return $contenu;
    }

    public function testLesQuatreEtatsDemandesSontCouverts(): void
    {
        $attendus = ['creation', 'desactivation', 'reactivation', 'suppression'];

        self::assertSame($attendus, [
            UserEtatHistoriqueListener::ETAT_CREATION,
            UserEtatHistoriqueListener::ETAT_DESACTIVATION,
            UserEtatHistoriqueListener::ETAT_REACTIVATION,
            UserEtatHistoriqueListener::ETAT_SUPPRESSION,
        ]);

        self::assertSame($attendus, array_keys(EtatCompteRepository::LIBELLES), 'Chaque état doit avoir un libellé affichable.');
    }

    /**
     * L'écoute porte sur l'entité : tous les chemins sont couverts, y compris
     * ceux qui n'existent pas encore.
     */
    public function testLEcouteSeFaitSurLEntiteEtNonDansLesEcrans(): void
    {
        $source = self::source('src/EventListener/UserEtatHistoriqueListener.php');

        foreach (['postPersist', 'preUpdate', 'preRemove'] as $evenement) {
            self::assertStringContainsString(
                '#[AsEntityListener(event: Events::' . $evenement . ', entity: User::class)]',
                $source,
                'L\'événement ' . $evenement . ' doit être écouté sur l\'entité User.'
            );
        }
    }

    /**
     * Un changement de nom ou de rôle n'est pas un changement d'état : sans ce
     * garde-fou, chaque modification de fiche écrirait une ligne.
     */
    public function testSeulLeChangementDActivationEstJournalise(): void
    {
        $source = self::source('src/EventListener/UserEtatHistoriqueListener.php');

        $position = strpos($source, 'public function preUpdate');
        self::assertIsInt($position);

        $corps = substr($source, $position, 600);

        self::assertStringContainsString("if (!\$args->hasChangedField('enabled')) {", $corps);
        self::assertStringContainsString('return;', $corps);
    }

    /**
     * La suppression s'enregistre AVANT que la ligne disparaisse, et la table
     * ne référence pas `user` : sinon la trace partirait avec le compte.
     */
    public function testLaTraceSurvitALaSuppressionDuCompte(): void
    {
        $script = self::source('sql/d26013_user_etat_historique.sql');

        self::assertStringNotContainsString('FOREIGN KEY', $script, 'Aucune clé étrangère vers user.');
        self::assertStringContainsString('username       VARCHAR(255) NOT NULL', $script, 'Le login est recopié pour rester lisible après suppression.');

        $listener = self::source('src/EventListener/UserEtatHistoriqueListener.php');

        self::assertStringContainsString('public function preRemove(', $listener);
        self::assertStringNotContainsString('public function postRemove(', $listener, 'Après la suppression, l\'identifiant et le login ont disparu.');
    }

    /**
     * Le point le plus délicat de la reconstitution.
     */
    public function testLaReconstitutionNeCroitPasDateInactifSurParole(): void
    {
        $script = self::source('sql/d26013_user_etat_historique.sql');

        self::assertStringContainsString(
            'DATE(u.date_inactif) <> DATE(u.date_creation)',
            $script,
            'Seules les désactivations dont la date diffère de la création sont réelles.'
        );

        self::assertStringContainsString(
            "'reconstitue'",
            $script,
            'Les lignes déduites doivent se distinguer de celles que l\'application a constatées.'
        );
    }

    /**
     * L'état courant doit figurer à côté de la chronologie : les réactivations
     * antérieures à la mise en service n'ont laissé aucune trace, et lire la
     * seule chronologie induirait en erreur.
     */
    public function testLEcranRappelleLEtatCourantDuCompte(): void
    {
        $gabarit = self::source('templates/BackOffice/Admin/inc/view_admin/etats.html.twig');

        self::assertStringContainsString('{% if user.enabled %}', $gabarit);
        self::assertStringContainsString('Compte actif', $gabarit);
        self::assertStringContainsString('Compte d&eacute;sactiv&eacute;', $gabarit);
        self::assertStringContainsString('etat-reconstitue', $gabarit, 'Une ligne déduite doit être signalée comme telle.');
    }

    public function testLaLectureVaDuPlusRecentAuPlusAncien(): void
    {
        $source = self::source('src/Repository/User/EtatCompteRepository.php');

        self::assertStringContainsString('ORDER BY date_evenement DESC, id DESC', $source);
    }

    /**
     * Une écriture d'historique qui échoue ne doit pas empêcher un
     * administrateur de désactiver un compte.
     */
    public function testUnHistoriqueEnEchecNeBloquePasLAdministration(): void
    {
        $source = self::source('src/EventListener/UserEtatHistoriqueListener.php');

        self::assertStringContainsString('} catch (\Throwable $e) {', $source);
        self::assertStringContainsString('$this->logger->error(', $source);
    }
}

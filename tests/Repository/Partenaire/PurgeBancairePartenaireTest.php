<?php

declare(strict_types=1);

namespace App\Tests\Repository\Partenaire;

use App\Archive\InventairePieces;
use App\Repository\Partenaire\PurgeBancairePartenaireRepository;
use PHPUnit\Framework\TestCase;

/**
 * Historique d'activité et purge bancaire des partenaires (Reco-13).
 *
 * Deux choses à protéger, de nature différente :
 *
 *  - la RECONSTITUTION DES PÉRIODES, qui est la restitution demandée par la
 *    recommandation (« un historique précis des comptes actifs / inactifs ») ;
 *    une erreur ici ferait dire à l'historique l'inverse de ce qui s'est
 *    passé, sans que rien ne le signale ;
 *  - le CHEMIN DU RIB, calculé par convention et non lu en base : s'il était
 *    faux, la purge viderait la colonne sans effacer le fichier, et le RIB
 *    resterait sur le disque après qu'on a déclaré l'avoir supprimé.
 */
class PurgeBancairePartenaireTest extends TestCase
{
    /**
     * @return array{enabled: int, date_evenement: ?string, date_creation: string, auteur: ?string, origine: string}
     */
    private function changement(int $enabled, ?string $evenement, string $creation, string $origine = 'observe'): array
    {
        return [
            'enabled' => $enabled,
            'date_evenement' => $evenement,
            'date_creation' => $creation,
            'auteur' => 'test',
            'origine' => $origine,
        ];
    }

    public function testUnPartenaireToujoursActifNaQuUnePeriodeOuverte(): void
    {
        $periodes = PurgeBancairePartenaireRepository::reconstituerPeriodes([
            $this->changement(1, '2017-10-01', '2026-11-21 10:00:00', 'reconstitution'),
        ]);

        self::assertCount(1, $periodes);
        self::assertSame('actif', $periodes[0]['etat']);
        self::assertSame('2017-10-01', $periodes[0]['debut']);
        self::assertNull($periodes[0]['fin'], 'la période en cours reste ouverte');
        self::assertSame('reconstitution', $periodes[0]['origine']);
    }

    /**
     * Le cas que la recommandation vise explicitement : « un compte peut être
     * temporairement inactif ». C'est précisément ce que `partenaire_statut`
     * ne sait pas dire, puisqu'elle ne garde que la dernière désactivation.
     */
    public function testUnPartenaireDesactivePuisReactiveDonneTroisPeriodes(): void
    {
        $periodes = PurgeBancairePartenaireRepository::reconstituerPeriodes([
            $this->changement(1, '2017-10-01', '2017-10-01 09:00:00'),
            $this->changement(0, '2019-04-15', '2019-04-15 11:00:00'),
            $this->changement(1, '2021-06-01', '2021-06-01 14:00:00'),
        ]);

        self::assertCount(3, $periodes);

        self::assertSame(['actif', '2017-10-01', '2019-04-15'], [
            $periodes[0]['etat'], $periodes[0]['debut'], $periodes[0]['fin'],
        ]);
        self::assertSame(['inactif', '2019-04-15', '2021-06-01'], [
            $periodes[1]['etat'], $periodes[1]['debut'], $periodes[1]['fin'],
        ]);
        self::assertSame('actif', $periodes[2]['etat']);
        self::assertNull($periodes[2]['fin']);
    }

    /**
     * Deux enregistrements vers le même état ne décrivent qu'un changement :
     * afficher deux périodes « inactif » qui se suivent laisserait croire à
     * une réactivation entre les deux.
     */
    public function testDeuxChangementsVersLeMemeEtatSontFusionnes(): void
    {
        $periodes = PurgeBancairePartenaireRepository::reconstituerPeriodes([
            $this->changement(1, '2017-10-01', '2017-10-01 09:00:00'),
            $this->changement(0, '2019-04-15', '2019-04-15 11:00:00'),
            $this->changement(0, '2019-04-16', '2019-04-16 08:00:00'),
            $this->changement(1, '2022-01-10', '2022-01-10 08:00:00'),
        ]);

        self::assertCount(3, $periodes);
        self::assertSame('inactif', $periodes[1]['etat']);
        self::assertSame('2019-04-15', $periodes[1]['debut'], 'le premier enregistrement fait foi');
        self::assertSame('2022-01-10', $periodes[1]['fin']);
    }

    /**
     * La date métier peut manquer : on retombe alors sur la date
     * d'enregistrement, qui existe toujours.
     */
    public function testUneDateMetierAbsenteEstRemplaceeParLaDateDEnregistrement(): void
    {
        $periodes = PurgeBancairePartenaireRepository::reconstituerPeriodes([
            $this->changement(1, null, '2026-11-21 10:30:00'),
        ]);

        self::assertSame('2026-11-21 10:30:00', $periodes[0]['debut']);
    }

    /**
     * Une réactivation n'a pas de date métier : le schéma n'en porte aucune.
     * `date_rattachement` est la date de rattachement INITIALE et ne bouge
     * jamais — s'en servir datait la réactivation de plusieurs années en
     * arrière et produisait une période dont la fin précédait le début.
     * Constaté en recette le 21/11/2026, sur une bascule réelle.
     */
    public function testUneReactivationSansDateMetierResteChronologique(): void
    {
        $periodes = PurgeBancairePartenaireRepository::reconstituerPeriodes([
            $this->changement(1, '2026-10-06', '2026-10-06 09:00:00', 'reconstitution'),
            $this->changement(0, '2026-11-21', '2026-11-21 22:38:00'),
            $this->changement(1, null, '2026-11-21 22:38:33'),
        ]);

        self::assertCount(3, $periodes);
        self::assertSame('2026-11-21', $periodes[1]['debut']);
        self::assertSame('2026-11-21 22:38:33', $periodes[1]['fin']);

        foreach ($periodes as $rang => $periode) {
            if (null === $periode['fin']) {
                continue;
            }

            self::assertLessThanOrEqual(
                $periode['fin'],
                (string) $periode['debut'],
                sprintf('période %d : la fin ne peut pas précéder le début', $rang)
            );
        }
    }

    public function testUnHistoriqueVideNeDonneAucunePeriode(): void
    {
        self::assertSame([], PurgeBancairePartenaireRepository::reconstituerPeriodes([]));
    }

    /**
     * Le RIB d'un partenaire suit la même convention que les pièces de
     * dossier : répertoire du type, identifiant de la LIGNE PORTEUSE — celle
     * de `partenaire_option_auditeur`, et non celle du partenaire — puis
     * suffixe et extension.
     */
    public function testLeCheminDuRibSuitLaConventionDesPieces(): void
    {
        $depot = new PurgeBancairePartenaireRepository(
            $this->createMock(\Doctrine\DBAL\Connection::class),
            '/var/data_symfony/normandie_symfony/data/'
        );

        self::assertSame(
            '/var/data_symfony/normandie_symfony/data/uploads/partenaire/auditeur/42_rib.pdf',
            $depot->cheminDuRib(42, 'pdf')
        );
    }

    public function testSansExtensionIlNyAPasDeRibAEffacer(): void
    {
        $depot = new PurgeBancairePartenaireRepository(
            $this->createMock(\Doctrine\DBAL\Connection::class),
            '/var/data/'
        );

        self::assertNull($depot->cheminDuRib(42, null));
        self::assertNull($depot->cheminDuRib(42, ''));
    }

    /**
     * Le répertoire et le suffixe employés doivent rester ceux que le
     * téléservice utilise pour déposer les RIB de partenaires : s'ils
     * changeaient d'un côté seulement, la purge effacerait un fichier
     * inexistant et laisserait le vrai en place.
     */
    public function testLeRepertoireEtLeSuffixeSontCeuxDuTeleservice(): void
    {
        self::assertSame('partenaire/auditeur/', InventairePieces::DOSSIERS[6]);
        self::assertSame('_rib.', InventairePieces::SUFFIXES[6]);
    }
}

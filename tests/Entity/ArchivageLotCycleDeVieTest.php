<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ArchiveLot;
use App\Entity\ArchiveLotBeneficiaire;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fous du cycle de vie d'une campagne d'archivage (Reco-03).
 *
 * Ce que ces tests protègent n'est pas du confort de programmation : c'est la
 * règle qui veut qu'aucune donnée ne disparaisse avant que sa conservation soit
 * assurée ailleurs. Si l'un d'eux tombe, la procédure peut détruire des
 * dossiers que les Archives de la Région n'ont pas récupérés.
 */
class ArchivageLotCycleDeVieTest extends TestCase
{
    private function lotNeuf(): ArchiveLot
    {
        return new ArchiveLot('ARCH-2027', new \DateTime('2027-01-10 23:59:59'), 'test');
    }

    /**
     * Amène un lot jusqu'à l'état demandé en suivant le chemin normal.
     */
    private function lotDansLEtat(string $etat): ArchiveLot
    {
        $chemin = [
            ArchiveLot::ETAT_CONSTITUE,
            ArchiveLot::ETAT_VERSE,
            ArchiveLot::ETAT_RECEPTION_VALIDEE,
            ArchiveLot::ETAT_DETRUIT,
            ArchiveLot::ETAT_ATTESTE,
        ];

        $lot = $this->lotNeuf();

        if (ArchiveLot::ETAT_ANNONCE === $etat) {
            return $lot;
        }

        foreach ($chemin as $etape) {
            $lot->passerA($etape);

            if ($etape === $etat) {
                return $lot;
            }
        }

        self::fail(sprintf('État inatteignable par le chemin normal : %s', $etat));
    }

    public function testUnLotNeufEstAnnonceEtNePeutRienDetruire(): void
    {
        $lot = $this->lotNeuf();

        self::assertSame(ArchiveLot::ETAT_ANNONCE, $lot->getEtat());
        self::assertFalse($lot->destructionAutorisee());
        self::assertTrue($lot->estOuvert());
    }

    /**
     * Le verrou central : la destruction n'est autorisée QUE lorsque les
     * Archives ont confirmé la réception.
     */
    public function testLaDestructionNestAutoriseeQuApresValidationDeLaReception(): void
    {
        $etats = [
            ArchiveLot::ETAT_ANNONCE,
            ArchiveLot::ETAT_CONSTITUE,
            ArchiveLot::ETAT_VERSE,
            ArchiveLot::ETAT_RECEPTION_VALIDEE,
            ArchiveLot::ETAT_DETRUIT,
            ArchiveLot::ETAT_ATTESTE,
        ];

        foreach ($etats as $etat) {
            $autorisee = $this->lotDansLEtat($etat)->destructionAutorisee();

            self::assertSame(
                ArchiveLot::ETAT_RECEPTION_VALIDEE === $etat,
                $autorisee,
                sprintf('État « %s » : destruction autorisée à tort ou refusée à tort.', $etat)
            );
        }
    }

    /**
     * Le raccourci le plus dangereux : passer du versement à la destruction
     * sans attendre la confirmation des Archives.
     */
    public function testUnLotVerseNePeutPasEtreDetruitDirectement(): void
    {
        $lot = $this->lotDansLEtat(ArchiveLot::ETAT_VERSE);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Transition interdite/');

        $lot->passerA(ArchiveLot::ETAT_DETRUIT);
    }

    public function testUnLotAnnonceNePeutPasEtreDetruitDirectement(): void
    {
        $this->expectException(\LogicException::class);

        $this->lotNeuf()->passerA(ArchiveLot::ETAT_DETRUIT);
    }

    public function testUnLotAttesteEstTerminal(): void
    {
        $lot = $this->lotDansLEtat(ArchiveLot::ETAT_ATTESTE);

        self::assertFalse($lot->estOuvert());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/état terminal/');

        $lot->passerA(ArchiveLot::ETAT_DETRUIT);
    }

    public function testUnLotDetruitNePeutPlusEtreAbandonne(): void
    {
        $lot = $this->lotDansLEtat(ArchiveLot::ETAT_DETRUIT);

        $this->expectException(\LogicException::class);

        $lot->passerA(ArchiveLot::ETAT_ABANDONNE);
    }

    public function testLeCheminCompletEstPossible(): void
    {
        $lot = $this->lotDansLEtat(ArchiveLot::ETAT_ATTESTE);

        self::assertSame(ArchiveLot::ETAT_ATTESTE, $lot->getEtat());
    }

    /**
     * Toute transition absente de la table est refusée, sans exception : c'est
     * ce qui empêche d'inventer un chemin de contournement.
     */
    public function testAucuneTransitionHorsTableNestAcceptee(): void
    {
        $tous = [
            ArchiveLot::ETAT_ANNONCE,
            ArchiveLot::ETAT_CONSTITUE,
            ArchiveLot::ETAT_VERSE,
            ArchiveLot::ETAT_RECEPTION_VALIDEE,
            ArchiveLot::ETAT_DETRUIT,
            ArchiveLot::ETAT_ATTESTE,
            ArchiveLot::ETAT_ABANDONNE,
        ];

        foreach ($tous as $depart) {
            if (ArchiveLot::ETAT_ABANDONNE === $depart) {
                continue; // inatteignable par le chemin normal, et terminal
            }

            $autorises = ArchiveLot::TRANSITIONS[$depart] ?? [];

            foreach ($tous as $cible) {
                $lot = $this->lotDansLEtat($depart);
                $attenduAccepte = in_array($cible, $autorises, true);

                try {
                    $lot->passerA($cible);
                    $accepte = true;
                } catch (\LogicException) {
                    $accepte = false;
                }

                self::assertSame(
                    $attenduAccepte,
                    $accepte,
                    sprintf('Transition « %s » vers « %s » mal traitée.', $depart, $cible)
                );
            }
        }
    }

    public function testLePreavisCourtAPartirDeLEnvoiReelEtDureUnMois(): void
    {
        $lot = $this->lotNeuf();

        self::assertFalse($lot->preavisEchu(new \DateTime('2030-01-01')), "sans annonce, le préavis n'est jamais échu");

        $lot->annoncerLe(new \DateTime('2026-12-09 10:30:00'));

        self::assertSame('2026-12-09 10:30:00', $lot->getDateAnnonce()?->format('Y-m-d H:i:s'));
        self::assertSame('2027-01-09 10:30:00', $lot->getDateFinPreavis()?->format('Y-m-d H:i:s'));

        self::assertFalse($lot->preavisEchu(new \DateTime('2027-01-08 23:59:59')));
        self::assertTrue($lot->preavisEchu(new \DateTime('2027-01-09 10:30:00')));
        self::assertTrue($lot->preavisEchu(new \DateTime('2027-01-15')));
    }

    public function testLAnnonceNeModifiePasLaDateDePassageQuiLuiEstDonnee(): void
    {
        $lot = $this->lotNeuf();
        $envoi = new \DateTime('2026-12-09 10:30:00');

        $lot->annoncerLe($envoi);

        self::assertSame(
            '2026-12-09 10:30:00',
            $envoi->format('Y-m-d H:i:s'),
            "annoncerLe() ne doit pas décaler la date qu'on lui passe"
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fournirSituationsConnues(): iterable
    {
        yield 'travaux payé' => ['travaux paye', ArchiveLotBeneficiaire::DECLENCHEUR_TRAVAUX_PAYE];
        yield 'audit payé' => ['audit paye', ArchiveLotBeneficiaire::DECLENCHEUR_AUDIT_PAYE];
        yield 'demande refusée' => ['demande refusee', ArchiveLotBeneficiaire::DECLENCHEUR_DEMANDE_REFUSEE];
        yield 'remboursement refusé' => ['remboursement refuse', ArchiveLotBeneficiaire::DECLENCHEUR_REMBOURSEMENT_REFUSE];
        yield 'demande non finalisée' => ['demande non finalisee', ArchiveLotBeneficiaire::DECLENCHEUR_NON_FINALISEE];
    }

    /**
     * @dataProvider fournirSituationsConnues
     */
    public function testChaqueSituationConnueDonneSonDeclencheur(string $situation, string $attendu): void
    {
        self::assertSame($attendu, ArchiveLotBeneficiaire::declencheurPourSituation($situation, 2));
    }

    public function testUnCompteSansDossierADeclencheurPropre(): void
    {
        self::assertSame(
            ArchiveLotBeneficiaire::DECLENCHEUR_SANS_DEMANDE,
            ArchiveLotBeneficiaire::declencheurPourSituation(null, 0)
        );
    }

    /**
     * Les situations d'un dossier ENCORE EN VIE ne doivent jamais produire de
     * déclencheur : si la requête d'éligibilité en remontait une, c'est qu'elle
     * s'est trompée, et il vaut mieux arrêter la campagne que d'inscrire un
     * motif faux dans une attestation de destruction.
     *
     * @return iterable<string, array{string}>
     */
    public static function fournirSituationsDeDossierVivant(): iterable
    {
        yield 'remboursement en cours' => ['remboursement en cours'];
        yield 'chèque envoyé' => ['cheque envoye, remboursement non engage'];
        yield 'situation inventée' => ['dossier parti en fumee'];
    }

    /**
     * @dataProvider fournirSituationsDeDossierVivant
     */
    public function testUneSituationDeDossierVivantEstRefusee(string $situation): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Situation de dossier inconnue/');

        ArchiveLotBeneficiaire::declencheurPourSituation($situation, 3);
    }

    public function testUneSituationNulleAvecDesDossiersEstRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ArchiveLotBeneficiaire::declencheurPourSituation(null, 4);
    }
}

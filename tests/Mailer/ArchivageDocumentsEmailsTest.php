<?php

declare(strict_types=1);

namespace App\Tests\Mailer;

use App\Entity\ArchiveLot;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Les deux courriels envoyés aux administrateurs pendant une campagne
 * d'archivage (Reco-03) : le bordereau de versement, puis l'attestation de
 * destruction.
 *
 * Ce que ces tests protègent est une distinction facile à perdre de vue : le
 * bordereau est nominatif et son message le dit, tandis que l'attestation ne
 * l'est pas — et son message ne doit pas le devenir par accident, par exemple
 * en reprenant des noms dans le corps pour « rendre service ».
 *
 * Twig fonctionne en strict_variables en test : un gabarit qui référencerait
 * une variable non fournie ferait échouer le rendu.
 */
class ArchivageDocumentsEmailsTest extends KernelTestCase
{
    private function twig(): Environment
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig;
    }

    private function lot(): ArchiveLot
    {
        $lot = new ArchiveLot('ARCH-2027', new \DateTime('2027-01-10'), 'Automate');
        $lot->setNbComptes(5283)->setNbDossiers(7832);
        $lot->annoncerLe(new \DateTime('2026-12-09'));
        $lot->passerA(ArchiveLot::ETAT_CONSTITUE)
            ->passerA(ArchiveLot::ETAT_VERSE)
            ->setDateVersement(new \DateTime('2027-02-11'))
            ->passerA(ArchiveLot::ETAT_RECEPTION_VALIDEE);
        $lot->setDateReceptionValidee(new \DateTime('2027-02-16'));

        return $lot;
    }

    public function testLeCourrielDuBordereauNeDonneQueDesCompteurs(): void
    {
        $corps = $this->twig()->render('Email/archivage_bordereau.html.twig', [
            'lot' => $this->lot(),
            'compteurs' => [
                'comptes' => 5283,
                'dossiers' => 7832,
                'pieces' => 22540,
                'manquantes' => 312,
                'octets' => 27917287424,
            ],
            'fichier' => 'BORDEREAU-DE-VERSEMENT-ARCH-2027.csv',
        ]);

        self::assertStringContainsString('ARCH-2027', $corps);
        self::assertStringContainsString('5283', $corps);
        self::assertStringContainsString('7832', $corps);
        self::assertStringContainsString('312', $corps, 'les pièces manquantes sont annoncées');
        self::assertStringContainsString('BORDEREAU-DE-VERSEMENT-ARCH-2027.csv', $corps);
    }

    /**
     * Le message doit dire, noir sur blanc, que rien n'est encore supprimé :
     * c'est la première inquiétude de qui reçoit un bordereau d'archivage.
     */
    public function testLeCourrielDuBordereauPreciseQueRienNestEncoreSupprime(): void
    {
        $corps = $this->twig()->render('Email/archivage_bordereau.html.twig', [
            'lot' => $this->lot(),
            'compteurs' => ['comptes' => 1, 'dossiers' => 1, 'pieces' => 0, 'manquantes' => 0, 'octets' => 1024],
            'fichier' => 'BORDEREAU.csv',
        ]);

        self::assertMatchesRegularExpression(
            '/Aucune donn\&eacute;e n\'a encore \&eacute;t\&eacute; supprim\&eacute;e/u',
            $corps
        );
    }

    /**
     * Le bordereau est nominatif : le message doit le signaler, pour que
     * personne ne le fasse suivre sans y penser.
     */
    public function testLeCourrielDuBordereauAvertitDeSaNatureNominative(): void
    {
        $corps = $this->twig()->render('Email/archivage_bordereau.html.twig', [
            'lot' => $this->lot(),
            'compteurs' => ['comptes' => 1, 'dossiers' => 1, 'pieces' => 0, 'manquantes' => 0, 'octets' => 0],
            'fichier' => 'BORDEREAU.csv',
        ]);

        self::assertStringContainsString('nominatif', $corps);
        // Le gabarit coupe ses lignes : l'assertion doit tolérer les retours.
        self::assertMatchesRegularExpression('/ne pas le\s+diffuser/u', $corps);
    }

    public function testLeCourrielDeLAttestationAnnonceLaDestructionFaite(): void
    {
        $lot = $this->lot();
        $lot->passerA(ArchiveLot::ETAT_DETRUIT)->setDateDestruction(new \DateTime('2027-02-18'));

        $corps = $this->twig()->render('Email/archivage_attestation.html.twig', [
            'lot' => $lot,
            'compteurs' => ['comptes' => 5283, 'pieces' => 22540, 'manquantes' => 312, 'octets' => 27917287424],
            'fichier' => 'ATTESTATION-DE-DESTRUCTION-ARCH-2027.docx',
        ]);

        self::assertStringContainsString('ARCH-2027', $corps);
        self::assertStringContainsString('16/02/2027', $corps, 'la date de réception par les Archives');
        self::assertStringContainsString('18/02/2027', $corps, 'la date de destruction');
        self::assertStringContainsString('signer', $corps);
    }

    /**
     * Le contraire du bordereau : ce message affirme l'absence de donnée
     * nominative, et doit s'y tenir.
     */
    public function testLeCourrielDeLAttestationAffirmeEtRespecteLaNonNominativite(): void
    {
        $lot = $this->lot();
        $lot->passerA(ArchiveLot::ETAT_DETRUIT)->setDateDestruction(new \DateTime('2027-02-18'));

        $corps = $this->twig()->render('Email/archivage_attestation.html.twig', [
            'lot' => $lot,
            'compteurs' => ['comptes' => 2, 'pieces' => 3, 'manquantes' => 0, 'octets' => 2048],
            'fichier' => 'ATTESTATION.docx',
        ]);

        self::assertStringContainsString('aucune donn', $corps);
        self::assertStringContainsString('nominative', $corps);
        self::assertStringNotContainsString('@', $corps, 'aucune adresse de courriel dans le corps');
    }

    /**
     * Une destruction encore en cours ne doit pas produire une date fantaisiste
     * dans le message.
     */
    public function testLesDatesAbsentesSontAffichees(): void
    {
        $lot = new ArchiveLot('ARCH-2028', new \DateTime('2028-01-10'), 'Automate');
        $lot->setNbComptes(1)->setNbDossiers(1);

        $corps = $this->twig()->render('Email/archivage_attestation.html.twig', [
            'lot' => $lot,
            'compteurs' => ['comptes' => 1, 'pieces' => 0, 'manquantes' => 0, 'octets' => 0],
            'fichier' => 'ATTESTATION.docx',
        ]);

        self::assertStringContainsString('—', $corps, 'une date absente s’affiche comme telle');
    }
}

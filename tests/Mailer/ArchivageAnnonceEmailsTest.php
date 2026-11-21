<?php

declare(strict_types=1);

namespace App\Tests\Mailer;

use App\Entity\ArchiveLot;
use App\Entity\Demande_;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Les deux courriels de l'annonce d'archivage (Reco-03).
 *
 * Les vrais gabarits sont rendus, et en environnement de test Twig fonctionne
 * en « strict_variables » : un gabarit qui référencerait une variable qu'on ne
 * lui passe pas ferait échouer le rendu. Le rendu réussi est donc lui-même une
 * garantie — c'est la même approche que AuthenticationEmailsTest.
 *
 * Ce qui est vérifié ici tient à une distinction qu'il serait facile de perdre :
 * le courriel au professionnel DOIT porter les numéros de dossier, sans quoi le
 * préavis est inutilisable, alors que celui de la Région ne doit porter que des
 * compteurs.
 */
class ArchivageAnnonceEmailsTest extends KernelTestCase
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
        $lot = new ArchiveLot('ARCH-2027', new \DateTime('2027-01-10 23:59:59'), 'test');
        $lot->setNbComptes(5283)->setNbDossiers(7832);
        $lot->annoncerLe(new \DateTime('2026-12-09 10:00:00'));

        return $lot;
    }

    /**
     * @return list<array{demande_id: int, libelle: string}>
     */
    private function dossiers(int $nombre): array
    {
        $dossiers = [];

        for ($i = 0; $i < $nombre; ++$i) {
            $dossiers[] = [
                'demande_id' => 20000 + $i,
                'libelle' => Demande_::$demandeType[Demande_::DEMANDE_TRAVAUX_TYPE],
            ];
        }

        return $dossiers;
    }

    public function testLeCourrielAuProfessionnelPorteLesNumerosDeDossierEtLEcheance(): void
    {
        $corps = $this->twig()->render('Email/archivage_annonce_partenaire.html.twig', [
            'nom' => 'Structure Test',
            'profil' => 'conseiller',
            'nombre_dossiers' => 3,
            'dossiers' => $this->dossiers(3),
            'tronquee' => false,
            'date_fin_preavis' => new \DateTime('2027-01-09'),
        ]);

        self::assertStringContainsString('20000', $corps, 'le numéro de dossier doit figurer');
        self::assertStringContainsString('20002', $corps);
        self::assertStringContainsString('Travaux', $corps);
        self::assertStringContainsString('conseiller', $corps);
        self::assertStringContainsString('09/01/2027', $corps, "l'échéance du préavis doit figurer");
        self::assertStringContainsString('Structure Test', $corps);
    }

    public function testLeCourrielAuProfessionnelSansNomResteLisible(): void
    {
        $corps = $this->twig()->render('Email/archivage_annonce_partenaire.html.twig', [
            'nom' => '',
            'profil' => 'auditeur',
            'nombre_dossiers' => 1,
            'dossiers' => $this->dossiers(1),
            'tronquee' => false,
            'date_fin_preavis' => new \DateTime('2027-01-09'),
        ]);

        self::assertStringContainsString('Bonjour,', $corps);
        self::assertStringNotContainsString('Bonjour ,', $corps);
    }

    public function testLeCourrielAuProfessionnelAccordeLesPluriels(): void
    {
        $twig = $this->twig();

        $unSeul = $twig->render('Email/archivage_annonce_partenaire.html.twig', [
            'nom' => 'X',
            'profil' => 'rénovateur',
            'nombre_dossiers' => 1,
            'dossiers' => $this->dossiers(1),
            'tronquee' => false,
            'date_fin_preavis' => new \DateTime('2027-01-09'),
        ]);

        $plusieurs = $twig->render('Email/archivage_annonce_partenaire.html.twig', [
            'nom' => 'X',
            'profil' => 'rénovateur',
            'nombre_dossiers' => 4,
            'dossiers' => $this->dossiers(4),
            'tronquee' => false,
            'date_fin_preavis' => new \DateTime('2027-01-09'),
        ]);

        self::assertStringContainsString('1 dossier vous', $unSeul);
        self::assertStringContainsString('concerne en tant que', $unSeul);
        self::assertStringContainsString('4 dossiers vous', $plusieurs);
        self::assertStringContainsString('concernent en tant que', $plusieurs);
    }

    public function testLaListeTronqueeEstSignaleeAuProfessionnel(): void
    {
        $corps = $this->twig()->render('Email/archivage_annonce_partenaire.html.twig', [
            'nom' => 'X',
            'profil' => 'conseiller',
            'nombre_dossiers' => 180,
            'dossiers' => $this->dossiers(50),
            'tronquee' => true,
            'date_fin_preavis' => new \DateTime('2027-01-09'),
        ]);

        self::assertStringContainsString('180 dossiers', $corps, 'le nombre réel doit être annoncé');
        self::assertStringContainsString('50 premiers dossiers', $corps);
        self::assertStringContainsString('consultation des dossiers', $corps);
    }

    public function testLeCourrielALaRegionNePorteQueDesCompteurs(): void
    {
        $corps = $this->twig()->render('Email/archivage_annonce_region.html.twig', [
            'lot' => $this->lot(),
            'synthese' => [
                ['declencheur' => 'cheque travaux paye (RMH + 1 an)', 'comptes' => 2136, 'dossiers' => 3968],
                ['declencheur' => 'compte sans demande (+ 2 ans)', 'comptes' => 117, 'dossiers' => 0],
            ],
            'nombre_destinataires' => 222,
            'dossiers_sans_destinataire' => ['professionnel sans compte joignable' => 1566],
        ]);

        self::assertStringContainsString('ARCH-2027', $corps);
        self::assertStringContainsString('2136', $corps);
        self::assertStringContainsString('5283', $corps, 'le total des comptes du lot');
        self::assertStringContainsString('7832', $corps, 'le total des dossiers du lot');
        self::assertStringContainsString('222', $corps);
        self::assertStringContainsString('1566', $corps);
        self::assertStringContainsString('09/01/2027', $corps, 'la fin du préavis');

        // Aucune adresse de courriel, aucun numéro de dossier : que des agrégats.
        self::assertStringNotContainsString('@', $corps);
    }

    /**
     * Le message doit dire, noir sur blanc, que rien n'a encore été supprimé :
     * c'est la première question que se posera son lecteur.
     */
    public function testLeCourrielALaRegionPreciseQueRienNestEncoreDetruit(): void
    {
        $corps = $this->twig()->render('Email/archivage_annonce_region.html.twig', [
            'lot' => $this->lot(),
            'synthese' => [],
            'nombre_destinataires' => 1,
            'dossiers_sans_destinataire' => [],
        ]);

        self::assertMatchesRegularExpression(
            '/aucune donn\&eacute;e n\'a \&eacute;t\&eacute; produite ni\s+supprim\&eacute;e/u',
            $corps
        );
        self::assertStringContainsString('Apr&egrave;s cette confirmation seulement', $corps);
    }
}

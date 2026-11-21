<?php

declare(strict_types=1);

namespace App\Tests\Archive;

use App\Archive\PhotoExport;
use App\Service\DemandeServiceBO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * La photo de l'export, et son invariant (Reco-03).
 *
 * L'invariant : la photo écrite en base est DÉJÀ ANONYMISÉE. C'est elle qui
 * survit à la destruction des dossiers, et si elle conservait un nom ou un
 * numéro de téléphone, l'anonymisation promise à la Région n'aurait pas lieu.
 *
 * Le danger n'est pas théorique : les six colonnes sont désignées par leur
 * LIBELLÉ dans l'en-tête de l'export (« Nom », « Email »…). Renommer une
 * colonne de l'export — ce qui arrivera, le retrait du revenu fiscal est déjà
 * demandé — suffirait à ce que l'anonymisation ne porte plus sur elle, sans
 * aucune erreur visible. D'où l'arrêt volontaire vérifié ici.
 */
class PhotoExportTest extends TestCase
{
    /** En-tête réel de l'export, réduit à ce qui est utile au test. */
    private const ENTETE_COMPLET = [
        'Numéro dossier', 'Thématique', 'Nom', 'Prénom', 'Code postal travaux',
        'Ville travaux', 'Date demande', 'Statut demande', 'Date commission', 'Date RMH',
        'Email', 'Téléphone1', 'Téléphone2', 'Type de ménage',
        'Description du projet du particulier',
    ];

    /**
     * @param list<string>       $entete
     * @param list<list<string>> $lignes
     */
    private function photoExport(array $entete, array $lignes): PhotoExport
    {
        $service = $this->createMock(DemandeServiceBO::class);
        $service->method('fputTitleAndContentExport')
            ->willReturnCallback(static function ($flux) use ($entete, $lignes): void {
                fputcsv($flux, $entete, ';');

                foreach ($lignes as $ligne) {
                    fputcsv($flux, $ligne, ';');
                }
            });

        return new PhotoExport($service, new ParameterBag([
            'production_travauxNiveau_BBC1' => '1',
            'production_travauxNiveau_BBC2' => '2',
        ]));
    }

    /**
     * @return list<string>
     */
    private function ligneDe(int $numeroDossier): array
    {
        return [
            (string) $numeroDossier, 'Travaux', 'DUPONT', 'Jeanne', '14000',
            'CAEN', '2019-03-04', 'Chèque envoyé', '2019-05-01', '2020-01-15',
            'jeanne.dupont@example.com', '0600000000', '0233000000', 'Modeste',
            'Remplacement de la chaudière, maison de ma mère au 3 rue des Lilas',
        ];
    }

    public function testLaPhotoEstRendueDejaAnonymisee(): void
    {
        $photo = $this->photoExport(self::ENTETE_COMPLET, [$this->ligneDe(4242)])
            ->lignesDesDossiers([4242 => true]);

        self::assertArrayHasKey(4242, $photo['lignes']);
        $anonymisee = $photo['lignes'][4242]['anonymisee'];

        foreach (PhotoExport::COLONNES_ANONYMISEES as $colonne) {
            self::assertSame('', $anonymisee[$colonne], sprintf('« %s » doit être vide dans la photo', $colonne));
        }
    }

    /**
     * L'anonymisation vide les colonnes, elle ne les supprime pas : le CSV garde
     * le même format entre lignes vivantes et lignes archivées.
     */
    public function testLesColonnesAnonymiseesSubsistentVides(): void
    {
        $photo = $this->photoExport(self::ENTETE_COMPLET, [$this->ligneDe(4242)])
            ->lignesDesDossiers([4242 => true]);

        $anonymisee = $photo['lignes'][4242]['anonymisee'];

        self::assertCount(count(self::ENTETE_COMPLET), $anonymisee);

        foreach (PhotoExport::COLONNES_ANONYMISEES as $colonne) {
            self::assertArrayHasKey($colonne, $anonymisee);
        }
    }

    public function testLaLigneCompleteGardeLIdentitePourLArchive(): void
    {
        $photo = $this->photoExport(self::ENTETE_COMPLET, [$this->ligneDe(4242)])
            ->lignesDesDossiers([4242 => true]);

        $complete = $photo['lignes'][4242]['complete'];

        self::assertSame('DUPONT', $complete['Nom']);
        self::assertSame('jeanne.dupont@example.com', $complete['Email']);
        self::assertStringContainsString('chaudière', $complete['Description du projet du particulier']);
    }

    public function testLesColonnesNonIdentifiantesSontConservees(): void
    {
        $photo = $this->photoExport(self::ENTETE_COMPLET, [$this->ligneDe(4242)])
            ->lignesDesDossiers([4242 => true]);

        $anonymisee = $photo['lignes'][4242]['anonymisee'];

        self::assertSame('14000', $anonymisee['Code postal travaux'], 'le code postal reste : la Région en a besoin');
        self::assertSame('CAEN', $anonymisee['Ville travaux']);
        self::assertSame('Modeste', $anonymisee['Type de ménage']);
        self::assertSame('2020-01-15', $anonymisee['Date RMH']);
    }

    /**
     * Le garde-fou décisif : une colonne renommée dans l'export doit arrêter la
     * campagne, pas produire une photo incomplètement anonymisée.
     *
     * @dataProvider fournirColonnesAnonymisees
     */
    public function testUneColonneAAnonymiserDisparueArreteTout(string $colonneRenommee): void
    {
        $entete = array_values(array_map(
            static fn (string $en): string => $en === $colonneRenommee ? 'Libellé changé' : $en,
            self::ENTETE_COMPLET
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ne porte pas la ou les colonnes/');

        $this->photoExport($entete, [$this->ligneDe(4242)])->lignesDesDossiers([4242 => true]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fournirColonnesAnonymisees(): iterable
    {
        foreach (PhotoExport::COLONNES_ANONYMISEES as $colonne) {
            yield $colonne => [$colonne];
        }
    }

    public function testLaColonneDuNumeroDeDossierEstIndispensable(): void
    {
        $entete = array_values(array_map(
            static fn (string $en): string => PhotoExport::COLONNE_NUMERO_DOSSIER === $en ? 'Référence' : $en,
            self::ENTETE_COMPLET
        ));

        $this->expectException(\RuntimeException::class);

        $this->photoExport($entete, [$this->ligneDe(4242)])->lignesDesDossiers([4242 => true]);
    }

    public function testSeulsLesDossiersDuLotSontRetenus(): void
    {
        $photo = $this->photoExport(
            self::ENTETE_COMPLET,
            [$this->ligneDe(1), $this->ligneDe(2), $this->ligneDe(3)]
        )->lignesDesDossiers([2 => true]);

        self::assertSame(3, $photo['lues'], 'toutes les lignes sont lues');
        self::assertSame([2], array_keys($photo['lignes']), 'une seule est retenue');
    }

    public function testLeCsvDeLArchiveReprendLEnteteEtLeBom(): void
    {
        $photoExport = $this->photoExport(self::ENTETE_COMPLET, [$this->ligneDe(7)]);
        $photo = $photoExport->lignesDesDossiers([7 => true]);

        $csv = $photoExport->rendreEnCsv($photo['entetes'], [$photo['lignes'][7]['complete']]);

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv, 'le BOM UTF-8, sinon Excel casse les accents');
        // fputcsv entoure de guillemets tout champ contenant une espace : l'en-tête
        // réel de l'export est donc « "Numéro dossier";Thématique;… ».
        self::assertStringContainsString('"Numéro dossier";Thématique', $csv);
        self::assertStringContainsString('DUPONT', $csv);
        self::assertSame(2, substr_count(trim($csv), "\n") + 1, "un en-tête et une ligne");
    }

    public function testLeCsvPeutPorterPlusieursDossiersDuMemeCompte(): void
    {
        $photoExport = $this->photoExport(self::ENTETE_COMPLET, [$this->ligneDe(10), $this->ligneDe(11)]);
        $photo = $photoExport->lignesDesDossiers([10 => true, 11 => true]);

        $csv = $photoExport->rendreEnCsv($photo['entetes'], [
            $photo['lignes'][10]['complete'],
            $photo['lignes'][11]['complete'],
        ]);

        self::assertSame(3, substr_count(trim($csv), "\n") + 1, 'un en-tête et deux lignes');
    }
}

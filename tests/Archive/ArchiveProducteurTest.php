<?php

declare(strict_types=1);

namespace App\Tests\Archive;

use App\Archive\ArchiveProducteur;
use App\Archive\PhotoExport;
use App\Entity\ArchiveLot;
use App\Service\DemandeServiceBO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * L'assemblage d'une archive de bénéficiaire (Reco-03).
 *
 * Ce que ces tests protègent : l'archive est la SEULE trace qui subsistera des
 * dossiers détruits. Si elle est incomplète, muette sur ce qui manque, ou si
 * son empreinte ne correspond pas à son contenu, la destruction qui suivra sera
 * irréversible et mal documentée.
 */
class ArchiveProducteurTest extends TestCase
{
    private string $repertoire;

    /** @var list<string> */
    private array $aNettoyer = [];

    protected function setUp(): void
    {
        $this->repertoire = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'archive-test-' . bin2hex(random_bytes(6));

        if (!mkdir($this->repertoire, 0777, true) && !is_dir($this->repertoire)) {
            self::fail('répertoire de test impossible à créer');
        }

        $this->aNettoyer[] = $this->repertoire;
    }

    protected function tearDown(): void
    {
        foreach ($this->aNettoyer as $chemin) {
            if (is_dir($chemin)) {
                foreach (glob($chemin . '/*') ?: [] as $fichier) {
                    if (is_file($fichier)) {
                        unlink($fichier);
                    }
                }

                rmdir($chemin);
            }
        }

        $this->aNettoyer = [];
    }

    private function producteur(): ArchiveProducteur
    {
        $service = $this->createMock(DemandeServiceBO::class);

        return new ArchiveProducteur(new PhotoExport($service, new ParameterBag([])));
    }

    private function lot(): ArchiveLot
    {
        return new ArchiveLot('ARCH-2027', new \DateTime('2027-01-10'), 'test');
    }

    /**
     * @return array{0: list<string>, 1: array<int, array{complete: array<string, string>, anonymisee: array<string, string>}>}
     */
    private function donnees(): array
    {
        $entetes = ['Numéro dossier', 'Thématique', 'Nom', 'Ville travaux'];

        $ligne = static fn (int $id, string $thematique): array => [
            'complete' => [
                'Numéro dossier' => (string) $id,
                'Thématique' => $thematique,
                'Nom' => 'DUPONT',
                'Ville travaux' => 'CAEN',
            ],
            'anonymisee' => [
                'Numéro dossier' => (string) $id,
                'Thématique' => $thematique,
                'Nom' => '',
                'Ville travaux' => 'CAEN',
            ],
        ];

        return [$entetes, [101 => $ligne(101, 'Travaux'), 102 => $ligne(102, 'Audit')]];
    }

    private function piece(string $nom, ?string $contenu): array
    {
        if (null === $contenu) {
            return [
                'libelle' => 'piece absente',
                'nom_archive' => $nom,
                'chemin' => $this->repertoire . '/jamais-ecrit-' . $nom,
                'presente' => false,
                'taille' => 0,
            ];
        }

        $chemin = $this->repertoire . \DIRECTORY_SEPARATOR . 'src-' . $nom;
        file_put_contents($chemin, $contenu);

        return [
            'libelle' => 'piece presente',
            'nom_archive' => $nom,
            'chemin' => $chemin,
            'presente' => true,
            'taille' => strlen($contenu),
        ];
    }

    private function contenuDeLArchive(string $chemin): array
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($chemin), 'archive illisible');

        $entrees = [];

        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $nom = (string) $zip->getNameIndex($i);
            $entrees[$nom] = (string) $zip->getFromIndex($i);
        }

        $zip->close();

        return $entrees;
    }

    public function testLArchiveContientLesDonneesLHistoriqueEtLeManifeste(): void
    {
        [$entetes, $lignes] = $this->donnees();

        $resultat = $this->producteur()->produire(
            $this->lot(),
            13805,
            'cheque travaux paye (RMH + 1 an)',
            $entetes,
            $lignes,
            [101 => "Historique du dossier 101\n", 102 => "Historique du dossier 102\n"],
            [101 => [$this->piece('devis-1.pdf', 'contenu du devis')]],
            $this->repertoire
        );

        self::assertSame('BENEFICIAIRE-13805.zip', $resultat['nom']);
        self::assertFileExists($resultat['chemin']);

        $entrees = $this->contenuDeLArchive($resultat['chemin']);

        self::assertArrayHasKey('BENEFICIAIRE-13805/donnees.csv', $entrees);
        self::assertArrayHasKey('BENEFICIAIRE-13805/manifeste.txt', $entrees);
        self::assertArrayHasKey('BENEFICIAIRE-13805/DOSSIER-101/historique.txt', $entrees);
        self::assertArrayHasKey('BENEFICIAIRE-13805/DOSSIER-102/historique.txt', $entrees);
        self::assertArrayHasKey('BENEFICIAIRE-13805/DOSSIER-101/devis-1.pdf', $entrees);
        self::assertSame('contenu du devis', $entrees['BENEFICIAIRE-13805/DOSSIER-101/devis-1.pdf']);
    }

    /**
     * L'archive porte l'identité complète : c'est la base de production qui est
     * anonymisée, pas l'archive. Une archive anonyme serait inexploitable pour
     * le service des Archives.
     */
    public function testLArchivePorteLIdentiteComplete(): void
    {
        [$entetes, $lignes] = $this->donnees();

        $resultat = $this->producteur()->produire(
            $this->lot(), 13805, 'declencheur', $entetes, $lignes, [], [], $this->repertoire
        );

        $csv = $this->contenuDeLArchive($resultat['chemin'])['BENEFICIAIRE-13805/donnees.csv'];

        self::assertStringContainsString('DUPONT', $csv, "l'archive versée garde l'identité");
        self::assertSame(3, substr_count(trim($csv), "\n") + 1, 'un en-tête et deux dossiers');
    }

    /**
     * Le point qui rend l'attestation de destruction honnête : une pièce
     * référencée en base mais introuvable est déclarée, pas oubliée.
     */
    public function testUnePieceManquanteEstDeclareeDansLeManifeste(): void
    {
        [$entetes, $lignes] = $this->donnees();

        $resultat = $this->producteur()->produire(
            $this->lot(),
            13805,
            'declencheur',
            $entetes,
            $lignes,
            [],
            [101 => [
                $this->piece('present.pdf', 'xx'),
                $this->piece('absent.pdf', null),
            ]],
            $this->repertoire
        );

        self::assertSame(2, $resultat['nb_pieces']);
        self::assertSame(1, $resultat['nb_manquantes']);

        $entrees = $this->contenuDeLArchive($resultat['chemin']);
        $manifeste = $entrees['BENEFICIAIRE-13805/manifeste.txt'];

        self::assertArrayHasKey('BENEFICIAIRE-13805/DOSSIER-101/present.pdf', $entrees);
        self::assertArrayNotHasKey('BENEFICIAIRE-13805/DOSSIER-101/absent.pdf', $entrees);

        self::assertStringContainsString('absent.pdf', $manifeste);
        self::assertStringContainsString('MANQUANTE', $manifeste);
        self::assertStringContainsString('Pieces manquantes ....... 1', $manifeste);
        self::assertStringContainsString('Pieces presentes ........ 1', $manifeste);
    }

    public function testLeManifestePorteLesTroisInformationsDeLAttestation(): void
    {
        [$entetes, $lignes] = $this->donnees();

        $resultat = $this->producteur()->produire(
            $this->lot(),
            13805,
            'cheque audit paye (RMH + 5 ans)',
            $entetes,
            $lignes,
            [],
            [101 => [$this->piece('devis.pdf', str_repeat('a', 2048))]],
            $this->repertoire
        );

        $manifeste = $this->contenuDeLArchive($resultat['chemin'])['BENEFICIAIRE-13805/manifeste.txt'];

        // Description du contenu, volumétrie, date : les trois colonnes du
        // modèle d'attestation fourni par la Région.
        self::assertStringContainsString('DOSSIER-101', $manifeste);
        self::assertStringContainsString('2048 octets', $manifeste);
        self::assertStringContainsString('Date de constitution', $manifeste);
        self::assertStringContainsString('cheque audit paye (RMH + 5 ans)', $manifeste);
        self::assertStringContainsString('ARCH-2027', $manifeste);
    }

    /**
     * L'empreinte sert de verrou à la destruction : elle doit correspondre au
     * fichier réellement écrit, sans quoi la destruction refusera de s'exécuter
     * sur une archive pourtant intacte — ou, pire, acceptera sur une archive
     * corrompue.
     */
    public function testLEmpreinteEtLaTailleCorrespondentAuFichierEcrit(): void
    {
        [$entetes, $lignes] = $this->donnees();

        $resultat = $this->producteur()->produire(
            $this->lot(), 13805, 'declencheur', $entetes, $lignes, [], [], $this->repertoire
        );

        self::assertSame(filesize($resultat['chemin']), $resultat['taille']);
        self::assertSame(hash_file('sha256', $resultat['chemin']), $resultat['sha256']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $resultat['sha256']);
    }

    /**
     * Les pièces d'un dossier sont rangées sous son répertoire, et pas ailleurs :
     * un archiviste doit pouvoir ouvrir un dossier et y trouver ses propres
     * pièces, sans les mélanger avec celles d'un autre dossier du même compte.
     */
    public function testChaqueDossierGardeSesPropresPieces(): void
    {
        [$entetes, $lignes] = $this->donnees();

        $resultat = $this->producteur()->produire(
            $this->lot(),
            13805,
            'declencheur',
            $entetes,
            $lignes,
            [],
            [
                101 => [$this->piece('a.pdf', 'aaa')],
                102 => [$this->piece('b.pdf', 'bbb')],
            ],
            $this->repertoire
        );

        $entrees = $this->contenuDeLArchive($resultat['chemin']);

        self::assertArrayHasKey('BENEFICIAIRE-13805/DOSSIER-101/a.pdf', $entrees);
        self::assertArrayHasKey('BENEFICIAIRE-13805/DOSSIER-102/b.pdf', $entrees);
        self::assertArrayNotHasKey('BENEFICIAIRE-13805/DOSSIER-101/b.pdf', $entrees);
    }

    public function testUnCompteSansDossierProduitUneArchiveLisible(): void
    {
        $resultat = $this->producteur()->produire(
            $this->lot(), 777, 'compte sans demande (+ 2 ans)', ['Numéro dossier'], [], [], [], $this->repertoire
        );

        $entrees = $this->contenuDeLArchive($resultat['chemin']);
        $manifeste = $entrees['BENEFICIAIRE-777/manifeste.txt'];

        self::assertSame(0, $resultat['nb_pieces']);
        self::assertStringContainsString('Dossiers ................ 0', $manifeste);
        self::assertStringContainsString('Aucune piece referencee pour ce compte.', $manifeste);
    }

    /**
     * Relancer la production ne doit pas échouer sur une archive déjà là : la
     * commande est reprenable, et une reprise après incident réécrit l'archive.
     */
    public function testUneArchiveExistanteEstRemplacee(): void
    {
        [$entetes, $lignes] = $this->donnees();
        $producteur = $this->producteur();

        $premier = $producteur->produire(
            $this->lot(), 13805, 'declencheur', $entetes, $lignes, [], [], $this->repertoire
        );
        $second = $producteur->produire(
            $this->lot(), 13805, 'declencheur', $entetes, $lignes, [], [], $this->repertoire
        );

        self::assertSame($premier['chemin'], $second['chemin']);
        self::assertFileExists($second['chemin']);
        self::assertSame(filesize($second['chemin']), $second['taille']);
    }

    /**
     * Une pièce annoncée présente mais illisible doit faire échouer la
     * production du compte, et ne laisser aucune archive tronquée derrière
     * elle : mieux vaut un compte en erreur qu'une archive incomplète dont
     * personne ne saura qu'elle l'est.
     */
    public function testUnePieceIllisibleNeLaissePasDArchiveTronquee(): void
    {
        [$entetes, $lignes] = $this->donnees();

        $piece = [
            'libelle' => 'piece cassee',
            'nom_archive' => 'cassee.pdf',
            'chemin' => $this->repertoire . '/inexistant-mais-annonce-present.pdf',
            'presente' => true,
            'taille' => 10,
        ];

        try {
            $this->producteur()->produire(
                $this->lot(), 13805, 'declencheur', $entetes, $lignes, [], [101 => [$piece]], $this->repertoire
            );
            self::fail('une pièce illisible doit interrompre la production');
        } catch (\RuntimeException) {
            self::assertFileDoesNotExist(
                $this->repertoire . \DIRECTORY_SEPARATOR . 'BENEFICIAIRE-13805.zip',
                'aucune archive tronquée ne doit subsister'
            );
        }
    }
}

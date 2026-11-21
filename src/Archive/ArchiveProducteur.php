<?php

declare(strict_types=1);

namespace App\Archive;

use App\Entity\ArchiveLot;
use App\Entity\Demande_;

/**
 * Assemblage de l'archive d'un compte bénéficiaire (Reco-03).
 *
 * Une archive par bénéficiaire, un bénéficiaire pouvant porter plusieurs
 * dossiers — correction apportée au compte rendu du 17/11/2026, qui indiquait à
 * tort « pour chaque dossier ». Contenu :
 *
 *     BENEFICIAIRE-13805.zip
 *     ├── donnees.csv ............ l'export complet du compte, IDENTITÉ COMPRISE
 *     ├── manifeste.txt .......... déclencheur, volumétrie, empreintes, pièces manquantes
 *     ├── DOSSIER-20123/
 *     │   ├── historique.txt ..... historique, commentaires et courriels du dossier
 *     │   └── <pièces>
 *     └── DOSSIER-20456/
 *
 * Deux choix de conception méritent d'être explicités :
 *
 * 1. L'archive porte l'IDENTITÉ COMPLÈTE. C'est la base de production qui est
 *    anonymisée, pas l'archive : une archive anonyme serait inexploitable pour
 *    le service des Archives, dont le métier est précisément de retrouver le
 *    dossier d'une personne.
 *
 * 2. Les pièces introuvables sur le disque sont DÉCLARÉES dans le manifeste,
 *    jamais passées sous silence. Sur huit ans d'historique il y en aura, et
 *    c'est ce qui rend l'attestation de destruction honnête.
 */
final class ArchiveProducteur
{
    /** Nom du fichier de données dans l'archive. */
    private const FICHIER_DONNEES = 'donnees.csv';

    /** Nom du manifeste dans l'archive. */
    private const FICHIER_MANIFESTE = 'manifeste.txt';

    public function __construct(private readonly PhotoExport $photoExport)
    {
    }

    /**
     * Construit l'archive d'un compte et rend ce qu'il faut inscrire au registre.
     *
     * @param list<string>                                                                       $entetes
     * @param array<int, array{complete: array<string, string>, anonymisee: array<string, string>}> $lignesParDossier
     * @param array<int, string>                                                                 $historiqueParDossier texte déjà rendu
     * @param array<int, list<array{libelle: string, nom_archive: string, chemin: string, presente: bool, taille: int}>> $piecesParDossier
     *
     * @return array{nom: string, chemin: string, taille: int, sha256: string, nb_pieces: int, nb_manquantes: int}
     *
     * @throws \RuntimeException si l'archive ne peut pas être écrite
     */
    public function produire(
        ArchiveLot $lot,
        int $userId,
        string $declencheur,
        array $entetes,
        array $lignesParDossier,
        array $historiqueParDossier,
        array $piecesParDossier,
        string $repertoire
    ): array {
        $nom = sprintf('BENEFICIAIRE-%d.zip', $userId);
        $chemin = rtrim($repertoire, '/\\') . \DIRECTORY_SEPARATOR . $nom;
        $racine = sprintf('BENEFICIAIRE-%d', $userId);

        if (is_file($chemin) && !unlink($chemin)) {
            throw new \RuntimeException(sprintf('Archive existante impossible à remplacer : %s', $chemin));
        }

        $zip = new \ZipArchive();
        $ouverture = $zip->open($chemin, \ZipArchive::CREATE | \ZipArchive::EXCL);

        if (true !== $ouverture) {
            throw new \RuntimeException(sprintf(
                'Création de l\'archive impossible (%s) : code %s',
                $chemin,
                (string) $ouverture
            ));
        }

        $nbPieces = 0;
        $nbManquantes = 0;
        $empreintes = [];

        try {
            // Les données, identité comprise : une ligne par dossier du compte.
            $zip->addFromString(
                $racine . '/' . self::FICHIER_DONNEES,
                $this->photoExport->rendreEnCsv($entetes, array_values(array_map(
                    static fn (array $ligne): array => $ligne['complete'],
                    $lignesParDossier
                )))
            );

            foreach (array_keys($lignesParDossier) as $demandeId) {
                $dossier = sprintf('%s/DOSSIER-%d', $racine, $demandeId);

                $zip->addFromString(
                    $dossier . '/historique.txt',
                    $historiqueParDossier[$demandeId] ?? "Aucun historique enregistre pour ce dossier.\n"
                );

                foreach ($piecesParDossier[$demandeId] ?? [] as $piece) {
                    ++$nbPieces;

                    if (!$piece['presente']) {
                        ++$nbManquantes;
                        $empreintes[] = [
                            'dossier' => $demandeId,
                            'nom' => $piece['nom_archive'],
                            'libelle' => $piece['libelle'],
                            'taille' => 0,
                            'sha256' => null,
                            'chemin' => $piece['chemin'],
                        ];

                        continue;
                    }

                    if (!$zip->addFile($piece['chemin'], $dossier . '/' . $piece['nom_archive'])) {
                        throw new \RuntimeException(sprintf(
                            'Pièce impossible à ajouter à l\'archive : %s',
                            $piece['chemin']
                        ));
                    }

                    $empreintes[] = [
                        'dossier' => $demandeId,
                        'nom' => $piece['nom_archive'],
                        'libelle' => $piece['libelle'],
                        'taille' => $piece['taille'],
                        'sha256' => hash_file('sha256', $piece['chemin']) ?: null,
                        'chemin' => $piece['chemin'],
                    ];
                }
            }

            $zip->addFromString(
                $racine . '/' . self::FICHIER_MANIFESTE,
                $this->rendreManifeste($lot, $userId, $declencheur, $lignesParDossier, $empreintes, $nbPieces, $nbManquantes)
            );
        } catch (\Throwable $e) {
            $zip->close();

            if (is_file($chemin)) {
                unlink($chemin);
            }

            throw $e;
        }

        if (!$zip->close()) {
            throw new \RuntimeException(sprintf('Fermeture de l\'archive en échec : %s', $chemin));
        }

        clearstatcache(true, $chemin);
        $taille = filesize($chemin);
        $empreinte = hash_file('sha256', $chemin);

        if (false === $taille || false === $empreinte) {
            throw new \RuntimeException(sprintf('Archive illisible après écriture : %s', $chemin));
        }

        return [
            'nom' => $nom,
            'chemin' => $chemin,
            'taille' => $taille,
            'sha256' => $empreinte,
            'nb_pieces' => $nbPieces,
            'nb_manquantes' => $nbManquantes,
        ];
    }

    /**
     * Le manifeste : ce qu'un archiviste lira pour savoir ce que contient
     * l'archive, et ce qui lui manque. Les trois informations qu'attend le
     * modèle d'attestation de destruction de la Région — description du
     * contenu, volumétrie, date — se lisent directement dessus.
     *
     * @param array<int, array{complete: array<string, string>, anonymisee: array<string, string>}> $lignesParDossier
     * @param list<array{dossier: int, nom: string, libelle: string, taille: int, sha256: ?string, chemin: string}> $empreintes
     */
    private function rendreManifeste(
        ArchiveLot $lot,
        int $userId,
        string $declencheur,
        array $lignesParDossier,
        array $empreintes,
        int $nbPieces,
        int $nbManquantes
    ): string {
        $octets = array_sum(array_column($empreintes, 'taille'));

        $lignes = [
            'MANIFESTE D ARCHIVE',
            str_repeat('=', 60),
            '',
            sprintf('Campagne ................ %s', $lot->getReference()),
            sprintf('Date de constitution .... %s', (new \DateTime())->format('d/m/Y H:i:s')),
            sprintf('Eligibilite calculee au . %s', $lot->getDateReference()->format('d/m/Y')),
            sprintf('Compte beneficiaire ..... %d', $userId),
            sprintf('Declencheur ............. %s', $declencheur),
            sprintf('Dossiers ................ %d', count($lignesParDossier)),
            sprintf('Pieces attendues ........ %d', $nbPieces),
            sprintf('Pieces presentes ........ %d', $nbPieces - $nbManquantes),
            sprintf('Pieces manquantes ....... %d', $nbManquantes),
            sprintf('Volumetrie des pieces ... %d octets', $octets),
            '',
            'DOSSIERS',
            str_repeat('-', 60),
        ];

        foreach ($lignesParDossier as $demandeId => $ligne) {
            $type = (int) ($ligne['complete']['Thématique'] ?? 0);
            $lignes[] = sprintf(
                'DOSSIER-%d  %s',
                $demandeId,
                $ligne['complete']['Thématique'] ?? (Demande_::$demandeType[$type] ?? '')
            );
        }

        $lignes[] = '';
        $lignes[] = 'PIECES';
        $lignes[] = str_repeat('-', 60);

        foreach ($empreintes as $piece) {
            if (null === $piece['sha256']) {
                $lignes[] = sprintf(
                    'DOSSIER-%d  %-45s  MANQUANTE (referencee en base, absente du disque)',
                    $piece['dossier'],
                    $piece['nom']
                );

                continue;
            }

            $lignes[] = sprintf(
                'DOSSIER-%d  %-45s  %10d o  sha256:%s',
                $piece['dossier'],
                $piece['nom'],
                $piece['taille'],
                $piece['sha256']
            );
        }

        if (0 === $nbPieces) {
            $lignes[] = 'Aucune piece referencee pour ce compte.';
        }

        $lignes[] = '';
        $lignes[] = 'Les pieces declarees MANQUANTES sont referencees dans la base de la';
        $lignes[] = 'plateforme mais introuvables sur son espace de stockage. Elles sont';
        $lignes[] = 'signalees ici plutot que passees sous silence, afin que l attestation';
        $lignes[] = 'de destruction rende compte de ce qui a reellement ete conserve.';
        $lignes[] = '';
        $lignes[] = 'Le fichier donnees.csv reprend, pour chaque dossier, les colonnes de';
        $lignes[] = 'l export de la consultation des dossiers du teleservice, identite du';
        $lignes[] = 'beneficiaire comprise.';

        return implode("\n", $lignes) . "\n";
    }
}

<?php

declare(strict_types=1);

namespace App\Archive;

use App\Entity\User;
use App\Service\DemandeServiceBO;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * La photo de l'export, prise au moment de l'archivage (Reco-03).
 *
 * C'est la pièce qui rend la suite possible. Le compte rendu du 17/11/2026
 * demande que « seules les données extraites par la demande d'export de la
 * consultation des dossiers soient conservées », et par ailleurs que le contenu
 * des dossiers soit vidé. Les deux ne peuvent pas être vrais en même temps si
 * l'export reste calculé sur les tables de production : la requête d'export
 * reconstruit 79 colonnes depuis une quinzaine de tables, qui vont disparaître.
 * Il faut donc figer l'export au moment de l'archivage.
 *
 * La photo est produite en lançant LE VRAI EXPORT, dans un flux en mémoire, puis
 * en relisant son CSV. C'est volontaire : la ligne archivée est alors
 * exactement celle que la Région aurait obtenue, et toute évolution future de
 * l'export suit automatiquement, sans qu'on ait à dupliquer les transformations
 * de DemandeServiceBO. Le coût est d'un export complet par campagne, une fois
 * l'an.
 *
 * INVARIANT : la photo est rendue DÉJÀ ANONYMISÉE. Les six colonnes
 * identifiantes y sont vides. L'identité complète ne figure que dans le CSV
 * déposé à l'intérieur de l'archive versée aux Archives de la Région. Ainsi,
 * même si la destruction échouait en cours de route, la photo ne contiendrait
 * aucune donnée nominative.
 */
final class PhotoExport
{
    /**
     * Les six colonnes vidées dans la photo, par leur libellé EXACT dans
     * l'en-tête de l'export (cf. DemandeServiceBO::fputTitleAndContentExport).
     *
     * Nom, prénom et e-mail viennent du compte rendu du 17/11/2026 ; les deux
     * téléphones et la description du projet ont été ajoutés le 21/11/2026,
     * parce qu'un numéro de téléphone identifie une personne aussi sûrement
     * qu'une adresse, et que la description est un texte libre saisi par le
     * bénéficiaire, où l'on trouve en pratique des noms et des adresses.
     */
    final public const COLONNES_ANONYMISEES = [
        'Nom',
        'Prénom',
        'Email',
        'Téléphone1',
        'Téléphone2',
        'Description du projet du particulier',
    ];

    /** Libellé de la colonne qui porte l'identifiant du dossier. */
    final public const COLONNE_NUMERO_DOSSIER = 'Numéro dossier';

    public function __construct(
        private readonly DemandeServiceBO $demandeService,
        private readonly ParameterBagInterface $parametres,
    ) {
    }

    /**
     * Lance l'export complet et rend les lignes des dossiers demandés.
     *
     * @param array<int, true> $dossiersRetenus identifiants de dossier en clés
     *
     * @return array{
     *     entetes: list<string>,
     *     lignes: array<int, array{complete: array<string, string>, anonymisee: array<string, string>}>,
     *     lues: int
     * }
     *
     * @throws \RuntimeException si l'en-tête de l'export ne porte plus l'une des colonnes à anonymiser
     */
    public function lignesDesDossiers(array $dossiersRetenus): array
    {
        $flux = fopen('php://temp/maxmemory:' . (64 * 1024 * 1024), 'w+');

        if (false === $flux) {
            throw new \RuntimeException("Impossible d'ouvrir un flux temporaire pour l'export.");
        }

        try {
            $this->demandeService->fputTitleAndContentExport($flux, $this->optionAdministrateur(), []);

            rewind($flux);

            $entetes = fgetcsv($flux, 0, ';');

            if (!is_array($entetes) || [] === $entetes) {
                throw new \RuntimeException("L'export n'a produit aucun en-tête.");
            }

            $entetes = array_map(static fn ($valeur): string => (string) $valeur, $entetes);
            $this->verifierEntetes($entetes);

            $lignes = [];
            $lues = 0;

            while (false !== ($valeurs = fgetcsv($flux, 0, ';'))) {
                if (!is_array($valeurs) || [null] === $valeurs) {
                    continue;
                }

                ++$lues;
                $complete = $this->associer($entetes, $valeurs);
                $numero = (int) ($complete[self::COLONNE_NUMERO_DOSSIER] ?? 0);

                if (0 === $numero || !isset($dossiersRetenus[$numero])) {
                    continue;
                }

                $lignes[$numero] = [
                    'complete' => $complete,
                    'anonymisee' => $this->anonymiser($complete),
                ];
            }

            return ['entetes' => $entetes, 'lignes' => $lignes, 'lues' => $lues];
        } finally {
            fclose($flux);
        }
    }

    /**
     * Rend les lignes d'export d'un compte au format CSV, pour le fichier
     * déposé dans l'archive. Même séparateur, même ordre de colonnes et même
     * BOM que l'export de la consultation : le fichier s'ouvre exactement comme
     * celui que la Région connaît.
     *
     * @param list<string>                      $entetes
     * @param list<array<string, string>>       $lignes
     */
    public function rendreEnCsv(array $entetes, array $lignes): string
    {
        $flux = fopen('php://temp', 'w+');

        if (false === $flux) {
            throw new \RuntimeException("Impossible d'ouvrir un flux temporaire pour le CSV.");
        }

        try {
            // BOM UTF-8 : sans lui, Excel n'affiche pas correctement les accents.
            fwrite($flux, "\xEF\xBB\xBF");
            fputcsv($flux, $entetes, ';');

            foreach ($lignes as $ligne) {
                fputcsv($flux, array_map(static fn (string $en): string => $ligne[$en] ?? '', $entetes), ';');
            }

            rewind($flux);

            return (string) stream_get_contents($flux);
        } finally {
            fclose($flux);
        }
    }

    /**
     * @param list<string> $entetes
     *
     * @throws \RuntimeException
     */
    private function verifierEntetes(array $entetes): void
    {
        $manquantes = array_values(array_diff(
            array_merge(self::COLONNES_ANONYMISEES, [self::COLONNE_NUMERO_DOSSIER]),
            $entetes
        ));

        if ([] === $manquantes) {
            return;
        }

        // Arrêt volontaire : si une colonne a été renommée dans l'export,
        // l'anonymisation ne porterait plus sur elle, et la photo conserverait
        // des données nominatives sans que rien ne le signale. Mieux vaut
        // interrompre la campagne que produire cela.
        throw new \RuntimeException(sprintf(
            "L'en-tête de l'export ne porte pas la ou les colonnes suivantes : %s. "
            . "L'anonymisation de la photo ne peut pas être garantie : corriger "
            . 'PhotoExport::COLONNES_ANONYMISEES avant de relancer. '
            . "En-tête reçu : %s",
            implode(', ', $manquantes),
            implode(', ', $entetes)
        ));
    }

    /**
     * @param list<string>        $entetes
     * @param array<int, ?string> $valeurs
     *
     * @return array<string, string>
     */
    private function associer(array $entetes, array $valeurs): array
    {
        $ligne = [];

        foreach ($entetes as $index => $entete) {
            $ligne[$entete] = (string) ($valeurs[$index] ?? '');
        }

        return $ligne;
    }

    /**
     * @param array<string, string> $ligne
     *
     * @return array<string, string>
     */
    private function anonymiser(array $ligne): array
    {
        foreach (self::COLONNES_ANONYMISEES as $colonne) {
            if (array_key_exists($colonne, $ligne)) {
                // Vidée, et non supprimée : le format du CSV reste identique
                // entre les lignes vivantes et les lignes archivées, et les
                // tableaux de la Région continuent de s'ouvrir sans retouche.
                $ligne[$colonne] = '';
            }
        }

        return $ligne;
    }

    /**
     * L'export est lancé avec les droits d'administration, pour obtenir la
     * totalité des colonnes : DemandeServiceBO en retire une partie pour les
     * autres profils, et une photo partielle serait inexploitable.
     *
     * @return array<string, mixed>
     */
    private function optionAdministrateur(): array
    {
        return [
            'roles' => [User::PARAM_ROLE_ADMIN],
            'username' => 'ARCHIVAGE',
            'production_travauxNiveau_BBC1' => $this->parametres->get('production_travauxNiveau_BBC1'),
            'production_travauxNiveau_BBC2' => $this->parametres->get('production_travauxNiveau_BBC2'),
        ];
    }
}

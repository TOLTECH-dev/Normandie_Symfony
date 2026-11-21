<?php

declare(strict_types=1);

namespace App\Archive;

use App\Entity\ArchiveLot;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Le bordereau de versement d'une campagne d'archivage (Reco-03).
 *
 * Il accompagne les archives déposées : pour chaque dossier versé, le compte
 * bénéficiaire, son nom et son prénom, le dossier et son motif d'archivage.
 * C'est le document qui permet au service des Archives de retrouver le dossier
 * d'une personne nommée, ce qui est précisément son métier.
 *
 * Il est produit À LA CONSTITUTION, et pas plus tard : à la destruction, les
 * noms n'existent plus et les dossiers non plus. C'est aussi ce qui permet à
 * l'attestation de destruction de rester NON NOMINATIVE — elle ne porte que
 * les numéros de compte. Le nominatif part avec les archives, chez ceux dont
 * c'est le rôle de le conserver ; il ne reste pas dans le registre de la
 * plateforme.
 *
 * Format CSV, et non Word : une campagne porte plusieurs milliers de lignes,
 * qu'un tableur ouvre, trie et recherche — ce qu'un tableau Word de cette
 * taille ne permet pas.
 */
final class BordereauVersement
{
    /** @var list<string> */
    private const ENTETES = [
        'Campagne',
        'Compte beneficiaire',
        'Nom',
        'Prenom',
        'Numero de dossier',
        'Thematique',
        'Motif d archivage',
        'Archive',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Écrit le bordereau du lot et rend son chemin.
     *
     * @throws DBALException
     * @throws \RuntimeException
     */
    public function produire(ArchiveLot $lot, string $repertoire): string
    {
        $chemin = rtrim($repertoire, '/\\') . \DIRECTORY_SEPARATOR
            . sprintf('BORDEREAU-DE-VERSEMENT-%s.csv', $lot->getReference());

        $flux = fopen($chemin, 'w');

        if (false === $flux) {
            throw new \RuntimeException(sprintf('Bordereau impossible à écrire : %s', $chemin));
        }

        try {
            // BOM UTF-8, comme l'export : sans lui Excel casse les accents.
            fwrite($flux, "\xEF\xBB\xBF");
            fputcsv($flux, self::ENTETES, ';');

            $lignes = 0;

            foreach ($this->lignes($lot) as $ligne) {
                fputcsv($flux, $ligne, ';');
                ++$lignes;
            }

            if (0 === $lignes) {
                throw new \RuntimeException(sprintf(
                    'Aucune ligne à porter au bordereau du lot %s.',
                    $lot->getReference()
                ));
            }
        } catch (\Throwable $e) {
            fclose($flux);

            if (is_file($chemin)) {
                unlink($chemin);
            }

            throw $e;
        }

        fclose($flux);

        return $chemin;
    }

    /**
     * Une ligne par dossier versé.
     *
     * Les noms sont lus dans `beneficiaire`, qui existe encore à ce stade ; les
     * dossiers viennent de la photo de l'export, déjà écrite. Un compte peut
     * porter plusieurs fiches bénéficiaire : leurs noms distincts sont réunis,
     * plutôt que d'en retenir un arbitrairement.
     *
     * Un compte sans dossier — ouvert et jamais utilisé — figure quand même au
     * bordereau, avec une colonne de dossier vide : il a bien été versé.
     *
     * @return iterable<list<string>>
     *
     * @throws DBALException
     */
    private function lignes(ArchiveLot $lot): iterable
    {
        $resultat = $this->connection->executeQuery(
            <<<'SQL'
            SELECT
                alb.user_id,
                alb.declencheur,
                alb.zip_nom,
                ade.demande_id,
                ade.thematique,
                (SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(b.nom), '') ORDER BY b.nom SEPARATOR ' / ')
                   FROM beneficiaire b WHERE b.user_id = alb.user_id)    AS noms,
                (SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(b.prenom), '') ORDER BY b.prenom SEPARATOR ' / ')
                   FROM beneficiaire b WHERE b.user_id = alb.user_id)    AS prenoms
              FROM archive_lot_beneficiaire alb
              LEFT JOIN archive_demande_export ade
                     ON ade.lot_id = alb.lot_id AND ade.user_id = alb.user_id
             WHERE alb.lot_id = :lot_id
               AND alb.etat = :etat
             ORDER BY alb.user_id, ade.demande_id
            SQL,
            ['lot_id' => $lot->getId(), 'etat' => \App\Entity\ArchiveLotBeneficiaire::ETAT_PRODUITE]
        );

        while ($ligne = $resultat->fetchAssociative()) {
            yield [
                $lot->getReference(),
                (string) $ligne['user_id'],
                (string) ($ligne['noms'] ?? ''),
                (string) ($ligne['prenoms'] ?? ''),
                null === $ligne['demande_id'] ? '' : (string) $ligne['demande_id'],
                (string) ($ligne['thematique'] ?? ''),
                (string) $ligne['declencheur'],
                (string) ($ligne['zip_nom'] ?? ''),
            ];
        }
    }
}

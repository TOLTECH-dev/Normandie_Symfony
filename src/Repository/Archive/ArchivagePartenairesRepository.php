<?php

declare(strict_types=1);

namespace App\Repository\Archive;

use App\Entity\ArchiveLot;
use App\Entity\ArchiveLotBeneficiaire;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Qui prévenir avant d'archiver, et qui ne pourra pas l'être (Reco-03).
 *
 * Service de LECTURE SEULE. Le regroupement par destinataire est fait ici, en
 * PHP, et non en SQL : un courriel par professionnel portant la liste de ses
 * dossiers, et non un courriel par dossier.
 */
class ArchivagePartenairesRepository
{
    /** Libellé du profil, d'après la première lettre du nom de compte. */
    private const PROFILS = [
        'C' => 'conseiller',
        'A' => 'auditeur',
        'R' => 'rénovateur',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Les professionnels à prévenir, avec les dossiers du lot qui les concernent.
     *
     * @return list<array{
     *     user_id: int,
     *     username: string,
     *     email: string,
     *     nom: string,
     *     profil: string,
     *     dossiers: list<array{demande_id: int, demande_type: int}>
     * }>
     *
     * @throws DBALException
     */
    public function destinatairesDuLot(ArchiveLot $lot): array
    {
        $lignes = $this->connection->executeQuery(
            ArchivagePartenairesSql::destinatairesEtDossiers(),
            [
                'lot_id' => $lot->getId(),
                'etat' => ArchiveLotBeneficiaire::ETAT_A_PRODUIRE,
            ]
        )->fetchAllAssociative();

        $parDestinataire = [];

        foreach ($lignes as $ligne) {
            $userId = (int) $ligne['user_id'];

            if (!isset($parDestinataire[$userId])) {
                $parDestinataire[$userId] = [
                    'user_id' => $userId,
                    'username' => (string) $ligne['username'],
                    'email' => (string) $ligne['email'],
                    'nom' => $this->nomLisible($ligne),
                    'profil' => self::PROFILS[$ligne['prefixe']] ?? 'professionnel',
                    'dossiers' => [],
                ];
            }

            $parDestinataire[$userId]['dossiers'][] = [
                'demande_id' => (int) $ligne['demande_id'],
                'demande_type' => (int) $ligne['demande_type'],
            ];
        }

        return array_values($parDestinataire);
    }

    /**
     * Les dossiers du lot dont personne ne sera prévenu.
     *
     * Ce n'est pas une anomalie à corriger : un professionnel dont le compte
     * est désactivé ne peut pas se connecter, donc ne peut rien exporter, donc
     * l'avertir n'aurait pas d'objet. Le chiffre doit malgré tout être remonté
     * à la Région, qui seule peut décider de les joindre autrement.
     *
     * @return array<string, int> motif => nombre de dossiers
     *
     * @throws DBALException
     */
    public function dossiersSansDestinataireParMotif(ArchiveLot $lot): array
    {
        $lignes = $this->connection->executeQuery(
            ArchivagePartenairesSql::dossiersSansDestinataire(),
            [
                'lot_id' => $lot->getId(),
                'etat' => ArchiveLotBeneficiaire::ETAT_A_PRODUIRE,
            ]
        )->fetchAllAssociative();

        $parMotif = [];

        foreach ($lignes as $ligne) {
            $motif = (string) $ligne['motif'];
            $parMotif[$motif] = ($parMotif[$motif] ?? 0) + 1;
        }

        return $parMotif;
    }

    /**
     * Nom affichable d'un destinataire, sans jamais tomber sur une chaîne vide :
     * à défaut de nom et prénom, le nom de compte, qui est toujours renseigné.
     *
     * @param array<string, mixed> $ligne
     */
    private function nomLisible(array $ligne): string
    {
        $nom = trim(sprintf('%s %s', (string) ($ligne['firstname'] ?? ''), (string) ($ligne['lastname'] ?? '')));

        return '' !== $nom ? $nom : (string) $ligne['username'];
    }
}

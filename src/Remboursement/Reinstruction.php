<?php

declare(strict_types=1);

namespace App\Remboursement;

use App\Entity\Remboursement_statut;

/**
 * Reprise d'un remboursement refusé (D26013, point 1).
 *
 * Un remboursement refusé était un terminus : le partenaire ne pouvait plus
 * remplacer la pièce qu'on lui reprochait, et le dossier disparaissait même de
 * sa liste. C'est l'objet de la demande de support.
 *
 * La reprise est ouverte tant que le dossier est encore là, c'est-à-dire ni
 * anonymisé par la purge RGPD, ni versé à un lot d'archivage. Passé l'un ou
 * l'autre, les pièces n'existent plus : rouvrir le dépôt n'aurait pas de sens
 * et laisserait croire qu'une reprise est possible.
 *
 * Les conditions sont rendues en SQL parce que les écrans concernés
 * (liste des remboursements, contrôle d'accès au dépôt) sont bâtis sur des
 * requêtes écrites à la main, pas sur l'ORM.
 */
final class Reinstruction
{
    /**
     * Les deux statuts qui ferment définitivement le dépôt.
     *
     * 22 : remboursement payé — il n'y a plus rien à déposer.
     * Le 20 (refusé) n'y figure plus : c'est tout l'objet de l'évolution.
     */
    public const STATUTS_DEPOT_FERME = [Remboursement_statut::STATUS_22];

    /**
     * Le dossier est encore vivant : ni anonymisé, ni archivé.
     *
     * `rgpd` est posé par la purge (un an après le refus pour un remboursement
     * refusé) ; une ligne dans `archive_demande_export` signe le versement du
     * dossier à un lot d'archivage.
     *
     * @param string $remboursement alias de la table remboursement_
     * @param string $demande       alias de la table demande_
     */
    public static function conditionDossierVivant(string $remboursement = 'r', string $demande = 'd'): string
    {
        return sprintf(
            '(%1$s.rgpd <> 1 AND %2$s.rgpd <> 1'
            . ' AND NOT EXISTS (SELECT 1 FROM archive_demande_export ade WHERE ade.demande_id = %2$s.id))',
            $remboursement,
            $demande
        );
    }

    /**
     * Un remboursement refusé est-il repris par ce profil ?
     *
     * Seuls les profils qui disposent du dépôt le voient réapparaître : le
     * partenaire qui doit redéposer, et les comptes Région qui l'accompagnent.
     * La liste des conseillers, instructeurs et EPCI reste inchangée — ils
     * reverront le dossier une fois le redépôt fait, au statut « à
     * réinstruire ».
     *
     * @param iterable<string> $roles
     */
    public static function profilPeutReprendre(iterable $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, ['ROLE_AUDITEUR', 'ROLE_CLIENT', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], true)) {
                return true;
            }
        }

        return false;
    }
}

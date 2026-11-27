<?php

declare(strict_types=1);

namespace App\Form;

/**
 * Les motifs de non-conformité déjà retenus sur l'enregistrement en cours
 * d'édition (D26013).
 *
 * Depuis que les motifs peuvent être désactivés, les listes de choix ne
 * proposent plus que les motifs actifs. Un dossier instruit autrefois avec un
 * motif depuis retiré doit malgré tout pouvoir être rouvert : sans cela le
 * formulaire échoue, la valeur liée ne figurant plus parmi les choix. Ces
 * identifiants sont donc réintroduits dans la requête de la liste.
 *
 * Les colonnes `*_reason` ne stockent pas des identifiants mais les entités
 * SÉRIALISÉES (vérifié sur les 609 enregistrements de la base : forme
 * uniforme). On accepte néanmoins les deux formes : une donnée ancienne, un
 * import ou une évolution du stockage ne doit pas faire tomber un écran
 * d'instruction.
 */
final class MotifsDejaChoisis
{
    /**
     * @param mixed       $donnees   les données du formulaire : l'entité en cours d'édition, ou null à la création. Tout autre type est ignoré.
     * @param string      $accesseur nom du getter portant les motifs, ex. « getJPreason »
     *
     * @return list<int>
     */
    public static function ids(mixed $donnees, string $accesseur): array
    {
        if (!is_object($donnees) || !method_exists($donnees, $accesseur)) {
            return [];
        }

        $motifs = $donnees->{$accesseur}();

        if (!is_iterable($motifs)) {
            return [];
        }

        $ids = [];

        foreach ($motifs as $motif) {
            $id = self::identifiant($motif);

            if (null !== $id) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function identifiant(mixed $motif): ?int
    {
        if (is_int($motif)) {
            return $motif;
        }

        if (is_string($motif) && ctype_digit($motif)) {
            return (int) $motif;
        }

        if (is_object($motif) && method_exists($motif, 'getId')) {
            $id = $motif->getId();

            return is_int($id) ? $id : null;
        }

        return null;
    }
}

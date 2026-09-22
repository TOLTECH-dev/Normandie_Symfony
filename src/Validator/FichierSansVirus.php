<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Vulnérabilité 5 / recommandation 9 de l'audit ALMOND : le fichier déposé est analysé par
 * l'antivirus avant tout enregistrement.
 *
 * À placer à côté de la contrainte Assert\File de chaque propriété recevant un fichier.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class FichierSansVirus extends Constraint
{
    public string $message = 'Ce fichier a été reconnu comme dangereux par l\'antivirus : son dépôt est refusé.';

    public string $messageIndisponible = 'Le dépôt est momentanément impossible : le contrôle antivirus est indisponible. Merci de réessayer plus tard.';

    /**
     * Libellé de la pièce, repris dans l'alerte adressée aux administrateurs (jamais affiché à
     * l'utilisateur). Exemple : « avis d'imposition ».
     */
    public ?string $libelle = null;

    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}

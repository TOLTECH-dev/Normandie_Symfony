<?php

declare(strict_types=1);

namespace App\Security\Antivirus;

/**
 * L'analyse n'a pas pu être menée : service arrêté, injoignable, ou réponse incomprise.
 *
 * Le comportement à adopter alors est un choix d'exploitation, porté par le validateur :
 * refuser le dépôt (réglage par défaut) ou l'accepter sans analyse.
 */
final class AntivirusIndisponibleException extends \RuntimeException
{
}

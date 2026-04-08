<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Renovateur extends Constraint
{
    public $message = 'Veuillez choisir un Rénovateur.';

}

<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Règles de constitution des mots de passe choisis par les utilisateurs (Reco-15 du plan de
 * traitement des risques, ANSSI PG-078 R27) : au moins 12 caractères, une lettre minuscule, une
 * lettre majuscule, un chiffre et un caractère spécial.
 *
 * Mêmes règles que le contrôle en temps réel de assets/js/main/passwordValidation.js : les deux
 * doivent rester alignés.
 *
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class PasswordStrength extends Constraint
{
    public const MIN_LENGTH = 12;

    public $minLengthMessage = 'Le mot de passe doit contenir au moins {{ limit }} caractères.';
    public $lowercaseMessage = 'Le mot de passe doit contenir au moins une lettre minuscule.';
    public $uppercaseMessage = 'Le mot de passe doit contenir au moins une lettre majuscule.';
    public $digitMessage = 'Le mot de passe doit contenir au moins un chiffre.';
    public $specialCharacterMessage = 'Le mot de passe doit contenir au moins un caractère spécial.';
}

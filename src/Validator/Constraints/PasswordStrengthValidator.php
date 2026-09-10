<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class PasswordStrengthValidator extends ConstraintValidator
{
    /**
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordStrength) {
            throw new UnexpectedTypeException($constraint, PasswordStrength::class);
        }

        // Les valeurs vides relèvent de la contrainte NotBlank
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (mb_strlen($value) < PasswordStrength::MIN_LENGTH) {
            $this->context->buildViolation($constraint->minLengthMessage)
                ->setParameter('{{ limit }}', (string) PasswordStrength::MIN_LENGTH)
                ->addViolation();
        }

        // Classes de caractères ASCII, comme passwordValidation.js : une lettre accentuée compte
        // comme un caractère spécial, et non comme une minuscule ou une majuscule.
        if (!preg_match('/[a-z]/', $value)) {
            $this->context->buildViolation($constraint->lowercaseMessage)->addViolation();
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $this->context->buildViolation($constraint->uppercaseMessage)->addViolation();
        }

        if (!preg_match('/\d/', $value)) {
            $this->context->buildViolation($constraint->digitMessage)->addViolation();
        }

        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $this->context->buildViolation($constraint->specialCharacterMessage)->addViolation();
        }
    }
}

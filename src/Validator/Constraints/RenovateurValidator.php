<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RenovateurValidator extends ConstraintValidator
{
    /**
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Renovateur) {
            throw new UnexpectedTypeException($constraint, Renovateur::class);
        }

        // custom constraints should ignore not empty and take care other constraints
        if (true === $value) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}

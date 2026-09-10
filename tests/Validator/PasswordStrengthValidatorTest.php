<?php

namespace App\Tests\Validator;

use App\Validator\Constraints\PasswordStrength;
use App\Validator\Constraints\PasswordStrengthValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Règles de constitution des mots de passe (Reco-15) : 12 caractères, minuscule, majuscule,
 * chiffre et caractère spécial, comme le contrôle en temps réel de passwordValidation.js.
 */
class PasswordStrengthValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PasswordStrengthValidator
    {
        return new PasswordStrengthValidator();
    }

    public function testUnMotDePasseConformeEstAccepte(): void
    {
        $this->validator->validate('Robuste-Mdp-2026', new PasswordStrength());

        $this->assertNoViolation();
    }

    public function testUneSaisieVideRelèveDeNotBlank(): void
    {
        $this->validator->validate('', new PasswordStrength());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider motsDePasseNonConformes
     */
    public function testChaqueRegleManquanteEstSignalee(string $password, string $expectedMessageProperty): void
    {
        $constraint = new PasswordStrength();
        $this->validator->validate($password, $constraint);

        $messages = [];
        foreach ($this->context->getViolations() as $violation) {
            $messages[] = $violation->getMessageTemplate();
        }

        $this->assertContains($constraint->$expectedMessageProperty, $messages);
        $this->assertCount(1, $messages, 'Une seule règle est enfreinte : ' . implode(' / ', $messages));
    }

    public function motsDePasseNonConformes(): iterable
    {
        yield 'trop court' => ['Court-Mdp-1', 'minLengthMessage'];
        yield 'sans minuscule' => ['ROBUSTE-MDP-2026', 'lowercaseMessage'];
        yield 'sans majuscule' => ['robuste-mdp-2026', 'uppercaseMessage'];
        yield 'sans chiffre' => ['Robuste-Mdp-Sans', 'digitMessage'];
        yield 'sans caractère spécial' => ['RobusteMdp2026x', 'specialCharacterMessage'];
    }
}

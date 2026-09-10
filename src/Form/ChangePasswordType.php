<?php

namespace App\Form;

use App\Validator\Constraints\PasswordStrength;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Renouvellement du mot de passe par l'utilisateur connecté (Reco-15, ANSSI PG-078 R7) : saisie du
 * mot de passe actuel, puis du nouveau. Les contrôles liés au compte (données personnelles,
 * derniers mots de passe) sont faits par App\Security\Password\PasswordChangeService.
 */
class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                // UserPassword signale aussi une saisie vide : pas de NotBlank, qui doublerait le message
                'constraints' => [
                    new UserPassword(['message' => 'Le mot de passe actuel est incorrect.']),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options' => ['label' => 'Nouveau mot de passe'],
                'second_options' => ['label' => 'Confirmer le nouveau mot de passe'],
                // Contraintes portées par le champ répété : elles ne s'appliquent qu'une fois les
                // deux saisies identiques, sans message parasite sur le second champ.
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez saisir un nouveau mot de passe.']),
                    new PasswordStrength(),
                ],
            ]);
    }
}

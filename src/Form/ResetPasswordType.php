<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\Constraints\PasswordStrength;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Non mappé : l'empreinte actuelle du compte doit rester intacte jusqu'à l'enregistrement,
            // pour contrôler la non-réutilisation des derniers mots de passe (PasswordChangeService).
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'required' => true,
                'first_options'  => [
                    'label' => 'Nouveau mot de passe',
                ],
                'second_options' => [
                    'label' => 'Répéter le nouveau mot de passe',
                ],
                // Contraintes portées par le champ répété, et non par chacun des deux champs : elles
                // ne s'appliquent qu'une fois les deux saisies identiques, sans message parasite.
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez saisir un nouveau mot de passe.']),
                    new PasswordStrength(),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }

}

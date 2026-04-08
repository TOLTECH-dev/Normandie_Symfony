<?php

namespace App\Form;

use App\Entity\User;
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
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'required' => true,
                'first_options'  => [
                    'label' => 'Nouveau mot de passe',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Regex([
                            'pattern' => '/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/',
                            'message' => 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et au moins 8 caractères.'
                        ]),
                    ]
                ],
                'second_options' => [
                    'label' => 'Répéter le nouveau mot de passe',
                    'required' => true,
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Regex([
                            'pattern' => '/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/',
                            'message' => 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et au moins 8 caractères.'
                        ]),
                    ],
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
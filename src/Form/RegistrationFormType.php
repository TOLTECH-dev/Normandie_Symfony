<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\Constraints\PasswordStrength;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'required' => true,
                'label' => 'Prénom',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 180]),
                ]
            ])

            ->add('lastname', TextType::class, [
                'required' => true,
                'label' => 'Nom',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 180]),
                ]
            ])

            ->add('email', RepeatedType::class, [
                'type' => EmailType::class,
                'invalid_message' => 'Les emails ne correspondent pas.',
                'required' => true,
                'first_options'  => [
                    'label' => 'Adresse e-mail',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Email(),
                        new Assert\Length(['min' => 2, 'max' => 180]),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmation adresse e-mail',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Email(),
                        new Assert\Length(['min' => 2, 'max' => 180]),
                    ],
                ],
            ])

            ->add('username', TextType::class, [
                'required' => true,
                'label' => "Nom d'utilisateur",
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 2, 'max' => 180]),
                ]
            ])

            // Non mappé : le mot de passe en clair ne transite pas par l'entité ; il est haché par
            // UserService::registerUser() (Reco-15 : date du mot de passe et historique).
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'required' => true,
                'first_options'  => [
                    'label' => 'Mot de passe',
                ],
                'second_options' => [
                    'label' => 'Répéter le mot de passe',
                ],
                // Règles portées par le champ répété : elles ne s'appliquent qu'une fois les deux
                // saisies identiques, sans message parasite sur le second champ.
                'constraints' => [
                    new Assert\NotBlank(),
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

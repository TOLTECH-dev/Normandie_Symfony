<?php

namespace App\Form;

use App\Entity\User;
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

            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'required' => true,
                'first_options'  => [
                    'label' => 'Mot de passe',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Regex([
                            'pattern' => '/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/',
                            'message' => 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et au moins 8 caractères.'
                        ]),
                    ]
                ],
                'second_options' => [
                    'label' => 'Répéter le mot de passe',
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

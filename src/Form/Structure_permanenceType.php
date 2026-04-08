<?php

namespace App\Form;

use App\Entity\Structure_permanence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class Structure_permanenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'required' => false,
                'label' => 'Nom Permanence',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
                ],
                'attr' => [
                    'placeholder' => 'Entrez le nom de la permanence',
                    'class' => 'form-control'
                ]
            ])
            ->add('adresse', TextType::class, [
                'required' => false,
                'label' => 'Adresse',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
                ],
                'attr' => [
                    'placeholder' => "Entrez l'adresse",
                    'class' => 'form-control'
                ]
            ])
            ->add('codePostal', TextType::class, [
                'required' => false,
                'label' => 'Code Postal',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
                ],
                'attr' => [
                    'placeholder' => 'Entrez le code postal',
                    'maxlength' => 5,
                    'pattern' => '^[0-9]{4,5}$',
                    'class' => 'form-control permanence-codePostal'
                ]
            ])
            ->add('ville', TextType::class, [
                'required' => false,
                'label' => 'Commune',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
                ],
                'attr' => [
                    'placeholder' => 'Entrez la ville',
                    'class' => 'form-control'
                ]
            ])
            ->add('telephone', TextType::class, [
                'required' => false,
                'label' => 'Téléphone',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
                ],
                'attr' => [
                    'placeholder' => 'Entrez le téléphone',
                    'maxlength' => 14,
                    'pattern' => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                    'class' => 'form-control permanence-telephone',
                    'title' => '0x-xx-xx-xx-xx'
                ]
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'label' => 'Email',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
                ],
                'attr' => [
                    'placeholder' => "Entrez l'adresse email",
                    'class' => 'form-control'
                ]
            ])
            ->add('jourOuverture', TextareaType::class, [
                'required' => false,
                'label' => "Jours d'ouverture",
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
                ],
                'attr' => [
                    'placeholder' => "Entrez les jours d'ouverture (limité à 245 caractères)",
                    'class' => 'form-control',
                    'maxlength' => 245
                ]
            ])
            ->add('horaire', TextareaType::class, [
                'required' => false,
                'label' => 'Horaires',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-4 col-lg-4'
                ],
                'attr' => [
                    'placeholder' => 'Entrez les horaires (limité à 245 caractères)',
                    'class' => 'form-control',
                    'maxlength' => 245
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Structure_permanence::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_structure_permanence';
    }
}

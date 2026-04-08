<?php

namespace App\Form;

use App\Entity\Structure_conseiller;
use App\Entity\Structure_conseiller_intervention;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class Structure_conseillerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'required' => false,
                'label' => 'Nom',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-3 col-lg-3'
                ],
                'attr' => [
                    'placeholder' => 'Entrez le nom',
                    'class' => 'form-control'
                ]
            ])
            ->add('prenom', TextType::class, [
                'required' => false,
                'label' => 'Prénom',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-3 col-lg-3'
                ],
                'attr' => [
                    'placeholder' => 'Entrez le prénom',
                    'class' => 'form-control'
                ]
            ])
            ->add('telephone', TextType::class, [
                'required' => false,
                'label' => 'Téléphone',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-3 col-lg-3'
                ],
                'attr' => [
                    'placeholder' => 'Entrez le téléphone',
                    'maxlength' => 14,
                    'pattern' => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                    'class' => 'form-control conseiller-telephone',
                    'title' => '0x-xx-xx-xx-xx'
                ]
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'label' => 'Email',
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-3 col-lg-3'
                ],
                'attr' => [
                    'placeholder' => "Entrez l'email",
                    'class' => 'form-control'
                ]
            ])
            ->add('departement_intervention', EntityType::class, [
                'required' => false,
                'label' => false,
                'class' => Structure_conseiller_intervention::class,
                'choice_label' => 'slug',
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Actif',
                'label_attr' => [
                    'class' => 'label_conseiller_enabled'
                ],
                'attr' => [
                    'class' => 'value_conseiller_enabled'
                ]
            ])
            ->add('checkboxEnabled', TextType::class, [
                'mapped' => false,
                'label_attr' => [
                    'class' => 'hidden'
                ],
                'attr' => [
                    'class' => 'hidden'
                ]
            ])
            // ->add('dateInactif', TextType::class, [
            //     'required' => false,
            //     'label' => 'Date inactif',
            //     'attr' => [
            //         'placeholder' => 'DD/MM/YYYY',
            //         'readonly' => true,
            //     ]
            // ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Structure_conseiller::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_structure_conseiller';
    }
}

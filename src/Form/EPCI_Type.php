<?php

namespace App\Form;

use App\Entity\EPCI_;
use App\Form\EPCI_contactType;
use App\Form\EPCI_permanenceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EPCI_Type extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, [
                'required' => true,
                'label' => 'Nom EPCI',
                'attr' => [
                    'placeholder' => 'Entrez le nom de l\'EPCI',
                ]
            ])
            ->add('adresse1', TextType::class, [
                'required' => true,
                'label' => 'Adresse 1',
                'attr' => [
                    'placeholder' => 'Entrez l\'adresse',
                ]
            ])
            ->add('adresse2', TextType::class, [
                'required' => false,
                'label' => 'Adresse 2',
                'attr' => [
                    'placeholder' => 'Entrez l\'adresse'
                ]
            ])
            ->add('adresse3', TextType::class, [
                'required' => false,
                'label' => 'Adresse 3',
                'attr' => [
                    'placeholder' => 'Entrez l\'adresse'
                ]
            ])
            ->add('codePostal', TextType::class, [
                'required' => true,
                'label' => 'Code Postal',
                'attr' => [
                    'placeholder' => 'Entrez le code postal',
                    'maxlength' => 5,
                    'pattern' => '^[0-9]{4,5}$',
                ]
            ])
            ->add('ville', TextType::class, [
                'required' => true,
                //'disabled'  => true,
                'label' => 'Commune',
                'attr' => [
                    'placeholder' => 'Entrez la ville',
                    'readonly' => true,
                ]
            ])
            ->add('telephone', TextType::class, [
                'required' => false,
                'label' => 'Téléphone',
                'attr' => [
                    'placeholder' => 'Entrez le téléphone',
                    'maxlength' => 14,
                    'pattern' => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                ]
            ])
            ->add('siteInternet', UrlType::class, [
                'required' => false,
                'label' => 'Site internet',
                'attr' => [
                    'placeholder' => 'Entrez l\'adresse du site internet',
                ]
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'label' => 'Courriel',
                'attr' => [
                    'placeholder' => 'Entrez le courriel',
                ]
            ])
            ->add('participationSARE', CheckboxType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'onoffswitch-checkbox'
                ]
            ])
            ->add('pointEntreeStructure', CheckboxType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'onoffswitch-checkbox'
                ]
            ])
            ->add('nomAffichage', TextType::class, [
                'required' => false,
                'label' => 'Nom d\'affichage',
                'attr' => [
                    'placeholder' => 'Entrez le nom d\'affichage'
                ]
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Actif',
                'data' => true
            ])
            ->add('dateInactif', DateType::class, [
                'required' => false,
                'label' => 'Date inactif',
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'attr'     => [
                    'placeholder' => 'DD/MM/YYYY',
                    'readonly'    => true
                ]
            ])
            ->add('EPCI_contact', CollectionType::class, [
                'entry_type' => EPCI_contactType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false,
            ])
            ->add('EPCI_permanence', CollectionType::class, [
                'entry_type' => EPCI_permanenceType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false
            ])
            ->add('valider', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EPCI_::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_epci_';
    }
}

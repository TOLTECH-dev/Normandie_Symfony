<?php

namespace App\Form;

use App\Entity\Partenaire_adresse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Partenaire_adresseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
                'label' => 'Commune',
                'attr' => [
                    'placeholder' => 'Entrez la ville',
                    'readonly' => true,
                ]
            ])
            ->add('departement', TextType::class, [
                'required' => true,
                'label' => 'Département',
                'attr' => [
                    'placeholder' => 'Entrez le département',
                    'readonly' => true,
                ]
            ])
            ->add('telFixe', TextType::class, [
                'required' => false,
                'label' => 'Téléphone fixe',
                'attr' => [
                    'placeholder' => 'Entrez le téléphone fixe',
                    'maxlength' => 14,
                    'pattern' => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                ]
            ])
            ->add('telMobile', TextType::class, [
                'required' => false,
                'label' => 'Téléphone mobile',
                'attr' => [
                    'placeholder' => 'Entrez le téléphone mobile',
                    'maxlength' => 14,
                    'pattern' => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                ]
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'Email entreprise',
                'attr' => [
                    'placeholder' => 'Entrez l\'email de l\'entreprise',
                ]
            ])
            ->add('siteInternet', UrlType::class, [
                'required' => false,
                'label' => 'Site internet',
                'attr' => [
                    'placeholder' => 'Entrez l\'adresse du site internet',
                ]
            ])
            ->add('complement', TextareaType::class, [
                'required' => false,
                'label' => 'Informations complémentaires',
                'attr' => [
                    'placeholder' => 'Entrez les informations complémentaires (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenaire_adresse::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_partenaire_adresse';
    }
}

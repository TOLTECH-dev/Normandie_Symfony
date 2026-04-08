<?php

namespace App\Form;

use App\Entity\Structure_adresse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

class Structure_adresseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adresse1', TextType::class, [
                'required'  => true,
                'label'     => 'Adresse 1',
                'attr'      => [
                    'placeholder' => "Entrez l'adresse",
                ]
            ])
            ->add('adresse2', TextType::class, [
                'required'  => false,
                'label'     => 'Adresse 2',
                'attr'      => [
                    'placeholder' => "Entrez l'adresse"
                ]
            ])
            ->add('adresse3', TextType::class, [
                'required'  => false,
                'label'     => 'Adresse 3',
                'attr'      => [
                    'placeholder' => "Entrez l'adresse"
                ]
            ])
            ->add('codePostal', TextType::class, [
                'required'  => true,
                'label'     => 'Code Postal',
                'attr'      => [
                    'placeholder'   => 'Entrez le code postal',
                    'maxlength'     => 5,
                    'pattern'       => '^[0-9]{4,5}$',
                ]
            ])
            ->add('ville', TextType::class, [
                'required'  => true,
                'label'     => 'Commune',
                'attr'      => [
                    'placeholder'   => 'Entrez la ville',
                    'readonly'      => true,
                ]
            ])
            ->add('telephone', TextType::class, [
                'required'  => false,
                'label'     => 'Téléphone',
                'attr'      => [
                    'placeholder'   => 'Entrez le téléphone',
                    'maxlength'     => 14,
                    'pattern'       => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                ]
            ])
            ->add('siteInternet', UrlType::class, [
                'required'  => false,
                'label'     => 'Site internet',
                'attr'      => [
                    'placeholder' => "Entrez l'adresse du site internet",
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Structure_adresse::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_structure_adresse';
    }
}

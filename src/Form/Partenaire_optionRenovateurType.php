<?php

namespace App\Form;

use App\Entity\Partenaire_optionRenovateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Partenaire_optionRenovateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeActeur', ChoiceType::class, [
                'required' => false,
                'label' => 'Type d\'acteur',
                'placeholder' => '-- Choisir un acteur --',
                'multiple' => false,
                'empty_data' => null,
                'choices' => [
                    'Architecte' => '0 | architecte',
                    'Maître d\'oeuvre' => '1 | moe',
                    'Entreprise Générale' => '2 | entreprise',
                    'Artisan' => '3 | artisan',
                    'Coopérative' => '4 | cooperative',
                    'Autre' => '5 | autre'
                ]
            ])
            ->add('complement', TextareaType::class, [
                'required' => false,
                'label' => 'Complément identification',
                'attr' => [
                    'placeholder' => 'Entrez le complément d\'identification (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenaire_optionRenovateur::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_partenaire_optionrenovateur';
    }
}

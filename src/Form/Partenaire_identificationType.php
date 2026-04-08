<?php

namespace App\Form;

use App\Entity\Partenaire_identification;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Partenaire_identificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('raisonSociale', TextType::class, [
                'required' => true,
                'label' => 'Raison Sociale',
                'attr' => [
                    'placeholder' => 'Entrez la raison sociale',
                ]
            ])
            /*
            ->add('thematique',     ChoiceType::class,  [
                'required' => true,
                'label' => 'Thématique',
                'placeholder' => 'Toutes les thématiques',
                'multiple' => false,
                'empty_data' => null,
                'choices' => [
                    'Auditeur' => '0 | auditeur',
                    'Rénovateur' => '1 | renovateur'
                ]
            ])
            */
            ->add('siret', TextType::class, [
                'required' => true,
                'label' => 'SIRET',
                'attr' => [
                    'placeholder' => 'Entrez le SIRET',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenaire_identification::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_partenaire_identification';
    }
}

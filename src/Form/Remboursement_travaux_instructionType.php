<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_travaux_instruction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class Remboursement_travaux_instructionType extends Remboursement_instructionType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder
            ->add('remboursement_travaux_instruction_conformite', CollectionType::class, [
                'entry_type'    => Remboursement_travaux_instruction_conformiteType::class,
                'label'         => false,
                'allow_add'     => true,
                'allow_delete'  => true,
                'required'      => false,
                'prototype'     => true,
                'by_reference'  => false,
            ])
            ->add('ficheTravaux', FileType::class, [
                'required'  => false,
                'label'     => 'Téléchargement de la fiche descriptive des travaux'
            ])
            ->add('isFicheTravauxConforme', ChoiceType::class, [
                'required'      => true,
                'label'         => 'Conforme ?',
                'placeholder'   => '-- Choisir --',
                'multiple'      => false,
                'expanded'      => false,
                'empty_data'    => null,
                'choices'       => [
                    'Oui'   => '0 | oui',
                    'Non'   => '1 | non',
                ]
            ])
            ->add('ficheTravauxReason', EntityType::class, [
                'required'      => false,
                'placeholder'   => '-- Choisir un motif pour la fiche descriptive des travaux --',
                'label'         => false,
                'multiple'      => true,
                'expanded'      => true,
                'class'         => 'App\Entity\Remboursement_reason',
                'choice_label'  => function ($obj) {
                    return $obj->getSlug();
                },
                'choice_value'  => 'id',
                'query_builder' => function($r) {
                    return $r->findByFiltre('fiche_travaux');
                }
            ])
            ->add('ficheTravauxReasonAutre', TextareaType::class, [
                'required'  => false,
                'label'     => 'Autre motif de non-conformité',
                'attr'      => [
                    'placeholder' => 'Autre motif de non-conformité',
                ]
            ])
            ->add('destinataire', ChoiceType::class, [
                'required'      => false,
                'label'         => 'Remboursement à',
                'placeholder'   => '-- Choisir --',
                'multiple'      => false,
                'expanded'      => false,
                'empty_data'    => null,
                'choices'       => [
                    'Bénéficiaire'  => '1 | beneficiaire',
                    'Rénovateur'    => '2 | renovateur',
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Remboursement_travaux_instruction::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_remboursement_travaux_instruction';
    }
}

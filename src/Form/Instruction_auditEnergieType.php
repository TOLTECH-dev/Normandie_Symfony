<?php

namespace App\Form;

use App\Entity\Instruction_auditEnergie;
use App\Entity\Instruction_;
use App\Entity\Instruction_reason;
use App\Repository\Instruction_reasonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Instruction_auditEnergieType extends AbstractType
{
    public function __construct() {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('JPconformite', ChoiceType::class, [
                'required' => true,
                'label' => 'Justificatif propriété conforme',
                'placeholder' => '-- Choisir une conformité --',
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'choices' => [
                    'Oui' => '0 | oui',
                    'Non' => '1 | non',
                    'Indéterminé' => '2 | indetermine'
                ]
            ])
            ->add('JPtype', ChoiceType::class, [
                'required' => false,
                'label' => 'Type de document',
                'placeholder' => '-- Choisir un type de document --',
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'choices' => Instruction_::$conformiteJPTypeDocument
            ])
            ->add('JPreason', EntityType::class, [
                'required' => false,
                'placeholder' => '-- Choisir un motif pour le Justificatif de Propriété --',
                'label' => false,
                'multiple' => true,
                'expanded' => true,
                'class' => Instruction_reason::class,
                'choice_label' => 'slug',
                'choice_value' => 'id',
                'query_builder' => function (Instruction_reasonRepository $r) {
                    return $r->findByFiltre('JP');
                }
            ]);


        $builder
            ->add('JPreasonAutre', TextareaType::class, [
                'required' => false,
                'label' => 'Autre motif de non-conformité',
                'attr' => [
                    'placeholder' => 'Autre motif de non-conformité (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
            ->add('KBISconformite', ChoiceType::class, [
                'required' => false,
                'label' => 'KBIS conforme',
                'placeholder' => '-- Choisir une conformité --',
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'choices' => [
                    'Oui' => '0 | oui',
                    'Non' => '1 | non',
                    'Indéterminé' => '2 | indetermine'
                ]
            ])
            ->add('KBISreason', EntityType::class, [
                'required' => false,
                'placeholder' => '-- Choisir un motif pour le KBIS --',
                'label' => false,
                'multiple' => true,
                'expanded' => true,
                'class' => Instruction_reason::class,
                'choice_label'  => function ($obj) {
                    return $obj->getSlug();
                },
                'choice_value' => 'id',
                'query_builder' => function (Instruction_reasonRepository $r) {
                    return $r->findByFiltre('KBIS');
                }
            ]);


        $builder
            ->add('KBISreasonAutre', TextareaType::class, [
                'required' => false,
                'label' => 'Autre motif de non-conformité',
                'attr' => [
                    'placeholder' => 'Autre motif de non-conformité (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
            ->add('AIconformite', ChoiceType::class, [
                'required' => true,
                'label' => 'Avis imposition conforme',
                'placeholder' => '-- Choisir une conformité --',
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'choices' => [
                    'Oui' => '0 | oui',
                    'Non' => '1 | non',
                    'Indéterminé' => '2 | indetermine'
                ]
            ])
            ->add('AIreason', EntityType::class, [
                'required' => false,
                'placeholder' => '-- Choisir un motif pour l\'Avis d\'imposition --',
                'label' => false,
                'multiple' => true,
                'expanded' => true,
                'class' => Instruction_reason::class,
                'choice_label'  => function ($obj) {
                    return   $obj->getSlug();
                },
                'choice_value'  => 'id',
                'query_builder' => function (Instruction_reasonRepository $r) {
                    return $r->findByFiltre('AI');
                }
            ]);


        $builder
            ->add('AIreasonAutre', TextareaType::class, [
                'required' => false,
                'label' => 'Autre motif de non-conformité',
                'attr' => [
                    'placeholder' => 'Autre motif de non-conformité (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Instruction_auditEnergie::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_instruction_auditenergie';
    }
}

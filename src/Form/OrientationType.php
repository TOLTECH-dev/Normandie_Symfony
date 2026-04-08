<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\EPCI_;
use App\Entity\Orientation;
use App\Form\DataTransformer\EntityTransformerFactory;
use App\Repository\EPCI_Repository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class OrientationType extends AbstractType
{
    private EntityManagerInterface $entityManager;
    private EntityTransformerFactory $transformerFactory;

    public function __construct(EntityManagerInterface $entityManager, EntityTransformerFactory $transformerFactory)
    {
        $this->entityManager = $entityManager;
        $this->transformerFactory = $transformerFactory;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $traitChoices = $options['trait_choices'] ?? null;

        $builder
            ->add('ville_id', HiddenType::class, [
                'required' => false,
                'label'    => 'Commune',
                'attr'     => [
                    'placeholder' => 'Commune',
                    'readonly'    => true,
                ]
            ])
            ->add('orientation_structureInferieur', CollectionType::class, [
                'entry_type'    => Orientation_structureInferieurType::class,
                'label'         => false,
                'allow_add'     => true,
                'allow_delete'  => true,
                'required'      => false,
                'prototype'     => true,
                'by_reference'  => false,
                'entry_options' => [
                    'trait_choices' => $traitChoices
                ]
            ])
            ->add('orientation_structureSuperieur', CollectionType::class, [
                'entry_type'    => Orientation_structureSuperieurType::class,
                'label'         => false,
                'allow_add'     => true,
                'allow_delete'  => true,
                'required'      => false,
                'prototype'     => true,
                'by_reference'  => false,
                'entry_options' => [
                    'trait_choices' => $traitChoices
                ]
            ])
            ->add('EPCI_id', EntityType::class, [
                'required'      => true,
                'placeholder'   => '-- Choisir une EPCI --',
                'label'         => false,
                'class'         => EPCI_::class,
                'choice_label'  => function (EPCI_ $obj): string {
                    return $obj->getNom();
                },
                'query_builder' => function (EPCI_Repository $r) {
                    return $r->findEnabled();
                },
            ])
            ->add('valider', SubmitType::class);

        // Add model transformer to convert between EPCI_ object and ID
        $builder->get('EPCI_id')->addModelTransformer(
            $this->transformerFactory->create(EPCI_::class)
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Orientation::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_orientation';
    }
}

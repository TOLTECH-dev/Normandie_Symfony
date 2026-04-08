<?php

namespace App\Form;

use App\Entity\Structure_;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;


class Structure_Type extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('structure_identification', Structure_identificationType::class, [
                'label' => false,
            ])
            ->add('structure_adresse', Structure_adresseType::class, [
                'label' => false,
            ])
            ->add('structure_statut', Structure_statutType::class, [
                'label' => false,
            ])
            ->add('structure_contact', CollectionType::class, [
                'entry_type' => Structure_contactType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false,
            ])
            ->add('structure_conseiller', CollectionType::class, [
                'entry_type' => Structure_conseillerType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false,
            ])
            ->add('structure_permanence', CollectionType::class, [
                'entry_type' => Structure_permanenceType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false,
            ])
            ->add('valider', SubmitType::class)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Structure_::class,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_structure_';
    }
}

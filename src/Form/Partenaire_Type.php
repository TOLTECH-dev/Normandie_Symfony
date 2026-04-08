<?php

namespace App\Form;

use App\Entity\Partenaire_;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Partenaire_Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('partenaire_identification', Partenaire_identificationType::class, [
                'label' => false,
            ])
            ->add('partenaire_adresse', Partenaire_adresseType::class, [
                'label' => false,
            ])
            ->add('partenaire_statut', Partenaire_statutType::class, [
                'label' => false,
            ])
            ->add('partenaire_optionAuditeur', Partenaire_optionAuditeurType::class, [
                'label' => false,
            ])
            ->add('partenaire_optionRenovateur', Partenaire_optionRenovateurType::class, [
                'label' => false,
            ])
            ->add('partenaire_contact', CollectionType::class, [
                'entry_type' => Partenaire_contactType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false
            ])
            ->add('partenaire_agence', CollectionType::class, [
                'entry_type' => Partenaire_agenceType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false
            ])
            ->add('valider', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenaire_::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_partenaire_';
    }
}

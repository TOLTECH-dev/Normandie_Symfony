<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Admin_import;


class Admin_importType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $traitChoices = $options['trait_choices'] ?? null;
        $builder
            ->add('type', ChoiceType::class, [
                'required'      => true,
                'label'         => false,
                'placeholder'   => "-- Choisir un type d'import --",
                'empty_data'    => null,
                'choices'       => [
                    'Auditeur / Rénovateur' => '0 | partenaire',
                    'Conseiller'            => '1 | conseiller',
                    'Permanence'            => '2 | permanence',
                ],
            ])
            ->add('file', FileType::class, [
                'required'  => true,
                'label'     => 'Fichier de données',
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
            'data_class'    => Admin_import::class,
            'trait_choices' => null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_admin_import';
    }


}

<?php

namespace App\Form;

use App\Entity\Structure_statut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class Structure_statutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Actif',
                // 'data' => true
            ])
            ->add('dateInactif', DateType::class, [
                'required' => false,
                'label' => 'Date inactif',
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'attr' => [
                    'placeholder' => 'DD/MM/YYYY',
                    'readonly' => true,
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Structure_statut::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_structure_statut';
    }
}

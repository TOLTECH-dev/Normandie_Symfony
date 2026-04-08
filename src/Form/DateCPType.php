<?php

namespace App\Form;

use App\Entity\DateCP;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class DateCPType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateCP', DateType::class, [
                'required' => true,
                'label' => 'Date CP',
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'attr' => [
                    'placeholder' => 'DD/MM/YYYY',
                ]
            ])
            ->add('numeroDeliberation', TextType::class, [
                'required' => false,
                'label' => 'Numéro Délibération',
                'attr' => [
                    'placeholder' => 'Entrez le numéro de délibération',
                ]
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Actif'
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
            ->add('valider', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DateCP::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_datecp';
    }
}

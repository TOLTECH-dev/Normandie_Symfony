<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Entity\Production_;

class Production_Type extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];
        $builder
            ->add('dateExpedition', DateType::class, [
                'required' => true,
                'label' => 'Date d\'expédition',
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'attr' => [
                    'placeholder' => 'Date d\'expédition',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'required' => false,
                'placeholder' => '-- Choisir un type --',
                'label' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => Production_::$arrayProductionType,
            ])
            ->add('niveau', ChoiceType::class, [
                'required' => false,
                'placeholder' => '-- Choisir un niveau --',
                'label' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => Production_::$arrayProductionNiveau,
            ])
            ->add('valider', SubmitType::class);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Production_::class,
            'trait_choices' => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_production_';
    }
}

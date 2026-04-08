<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Historique_email;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class Historique_emailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $traitChoices = $options['trait_choices'] ?? [];
        $choices = is_array($traitChoices) && count($traitChoices) > 0 ? $traitChoices[0] : [];

        $builder
            ->add('content', TextareaType::class, [
                'required' => true,
                'label' => false,
                'attr' => [
                    'placeholder' => 'Entrez le contenu de l\'email (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
            ->add('recipient', ChoiceType::class, [
                'required' => false,
                'label' => 'Envoyer également par email à',
                'multiple' => true,
                'expanded' => true,
                'empty_data' => [],
                'choices' => $choices
            ])
            ->add('enregistrer', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Historique_email::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_historique_email';
    }
}

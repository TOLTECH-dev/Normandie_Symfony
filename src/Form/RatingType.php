<?php

namespace App\Form;

use App\Entity\Rating;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * Class RatingType
 * Form type for Rating entity
 */
class RatingType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];

        $builder
            ->add('score', TextType::class, array(
                'required' => true,
                'label' => 'Rating',
                'attr' => array(
                    'placeholder' => 'Entrez le rating',
                    'readonly' => true,
                )
            ))
            ->add('commentaire', TextareaType::class, array(
                'required' => false,
                'label' => 'Commentaire',
                'attr' => array(
                    'placeholder' => 'Entrez le commentaire',
                    'maxlength' => '245'
                )
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rating::class,
            'trait_choices' => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_rating';
    }
}

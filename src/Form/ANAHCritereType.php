<?php

namespace App\Form;

use App\Entity\ANAHCritere;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ANAHCritereType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->traitChoices = $options['trait_choices'];

        $builder
            ->add('nbPersonne', TextType::class, [
                'required' => false,
                'label' => 'Nombre de personnes',
                'attr' => [
                    'placeholder' => 'Entrez le nombre de personnes',
                    'readonly' => true
                ]
            ])
            ->add('plafondTresModeste', TextType::class, [
                'required' => true,
                'label' => 'Plafond très modeste',
                'attr' => [
                    'placeholder' => 'Entrez le plafond très modeste',
                    'pattern' => '^[0-9]+$'
                ]
            ])
            ->add('supplementTresModeste', TextType::class, [
                'required' => true,
                'label' => 'Supplément très modeste',
                'attr' => [
                    'placeholder' => 'Entrez le supplément très modeste',
                    'pattern' => '^[0-9]+$',
                    'readonly' => $this->traitChoices['isSupplementsReadOnly']
                ]
            ])
            ->add('plafondModeste', TextType::class, [
                'required' => true,
                'label' => 'Plafond modeste',
                'attr' => [
                    'placeholder' => 'Entrez le plafond modeste',
                    'pattern' => '^[0-9]+$'
                ]
            ])
            ->add('supplementModeste', TextType::class, [
                'required' => true,
                'label' => 'Supplément modeste',
                'attr' => [
                    'placeholder' => 'Entrez le supplément modeste',
                    'pattern' => '^[0-9]+$',
                    'readonly' => $this->traitChoices['isSupplementsReadOnly']
                ]
            ])
            ->add('plafondIntermediaire', TextType::class, [
                'required' => true,
                'label' => 'Plafond intermédiaire',
                'attr' => [
                    'placeholder' => 'Entrez le plafond intermédiaire',
                    'pattern' => '^[0-9]+$'
                ]
            ])
            ->add('supplementIntermediaire', TextType::class, [
                'required' => true,
                'label' => 'Supplément intermédiaire',
                'attr' => [
                    'placeholder' => 'Entrez le supplément intermédiaire',
                    'pattern' => '^[0-9]+$',
                    'readonly' => $this->traitChoices['isSupplementsReadOnly']
                ]
            ])
            ->add('valider', SubmitType::class);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ANAHCritere::class,
            'trait_choices' => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'whitelabel_backofficebundle_anahcritere';
    }

}
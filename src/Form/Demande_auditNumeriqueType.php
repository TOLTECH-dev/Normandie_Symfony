<?php

namespace App\Form;

use App\Entity\Demande_auditNumerique;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Demande_auditNumeriqueType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->traitChoices = $options['trait_choices'];

        $builder
            ->add('commitment', CheckboxType::class, array(
                'required' => true,
                'label' => 'Je m\'engage'
            ))
            ->add('structure_id', HiddenType::class, array(
                'required' => true,
                'label' => 'Votre structure',
                'attr' => array(
                    'placeholder' => 'Votre structure',
                    'readonly' => true,
                ),
                'data' => $this->traitChoices[0]
            ))
            ->add('conseiller_id', HiddenType::class, array(
                'required' => true,
                'label' => 'Votre conseiller',
                'attr' => array(
                    'placeholder' => 'Votre conseiller',
                    'readonly' => true,
                ),
                'data' => $this->traitChoices[1]
            ))
            ->add('auditeur_id', HiddenType::class, array(
                'required' => true,
                'label' => 'Votre auditeur',
                'attr' => array(
                    'placeholder' => 'Votre auditeur',
                    'readonly' => true,
                ),
                'data' => $this->traitChoices[2]
            ))
            ->add('signature', CheckboxType::class, array(
                'required' => true,
                'label' => 'Je signe'
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Demande_auditNumerique::class,
            'trait_choices' => null
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'whitelabel_frontofficebundle_demande_auditnumerique';
    }

}
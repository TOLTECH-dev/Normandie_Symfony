<?php

namespace App\Form;

use App\Entity\Demande_;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Demande_Type extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->traitChoices = $options['trait_choices'];

        $builder
            ->add('demande_auditEnergie', Demande_auditEnergieType::class, array(
                'label' => false,
                'trait_choices' => $this->traitChoices
            ))
            ->add('demande_auditNumerique', Demande_auditNumeriqueType::class, array(
                'label' => false,
                'trait_choices' => $this->traitChoices
            ))
            ->add('demande_travaux', Demande_travauxType::class, array(
                'label' => false,
                'trait_choices' => $this->traitChoices
            ))
            ->add('valider', SubmitType::class);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Demande_::class,
            'trait_choices' => null
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'whitelabel_frontofficebundle_demande_';
    }

}
<?php

namespace App\Form;

use App\Entity\Banque_statut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

/**
 * Class Banque_statutType
 * @package App\Form
 */
class Banque_statutType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Actif',
            ])
            ->add('dateInactif', DateType::class, [
                'required'  => false,
                'label'     => 'Date inactif',
                'widget'    => 'single_text',
                'format'    => 'dd/MM/yyyy',
                'html5' => false,
                'attr'      => [
                    'placeholder'   => 'DD/MM/YYYY',
                    'readonly'      => true,
                ],
            ])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Banque_statut::class,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_banque_statut';
    }
}

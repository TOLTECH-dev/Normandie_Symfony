<?php

namespace App\Form;

use App\Entity\Banque_;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class Banque_Type
 * @package App\Form
 */
class Banque_Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'required'  => true,
                'label'     => 'Nom',
                'attr'      => [
                    'placeholder' => 'Entrez le nom de la Banque',
                ],
            ])
            ->add('banque_statut', Banque_statutType::class, [
                'label' => false,
            ])
            ->add('valider', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Banque_::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_banque_';
    }
}

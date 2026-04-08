<?php

namespace App\Form;

use App\Entity\Structure_identification;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class Structure_identificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'required'  => true,
                'label'     => 'Nom Structure',
                'attr'      => [
                    'placeholder' => 'Entrez le nom de la structure',
                ]
            ])
            ->add('code', TextType::class, [
                'required'  => true,
                'label'     => 'Code',
                'attr'      => [
                    'placeholder' => 'Entrez le code',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Structure_identification::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_structure_identification';
    }
}

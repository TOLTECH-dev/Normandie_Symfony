<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Contact;

class ContactType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $traitChoices = $options['trait_choices'] ?? [];

        $builder
            ->add('type', ChoiceType::class, [
                'required'      => true,
                'label'         => 'Type',
                'placeholder'   => '-- Choisir un type --',
                'multiple'      => false,
                'empty_data'    => null,
                'choices'       => $traitChoices['optionType'] ?? []
            ])
            ->add('nom', TextType::class, [
                'required'  => true,
                'label'     => 'Nom',
                'attr'      => [
                    'placeholder'   => 'Nom'
                ]
            ])
            ->add('prenom', TextType::class, [
                'required'  => true,
                'label'     => 'Prénom',
                'attr'      => [
                    'placeholder'   => 'Prénom'
                ]
            ])
            ->add('telephone', TextType::class, [
                'required'  => false,
                'label'     => 'Téléphone',
                'attr'      => [
                    'placeholder'   => 'Téléphone',
                    'maxlength'     => 14,
                    'pattern'       => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$'
                ]
            ])
            ->add('email', EmailType::class, [
                'required'  => true,
                'label'     => 'Email',
                'attr'      => [
                    'placeholder'   => 'Email'
                ]
            ])
            ->add('valider', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Contact::class,
            'trait_choices' => null
        ]);
    }


    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_contact';
    }
}

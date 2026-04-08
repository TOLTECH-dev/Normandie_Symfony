<?php

namespace App\Form;

use App\Entity\Structure_contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class Structure_contactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('civilite', ChoiceType::class, [
                'required'      => false,
                'label'         => 'Civilité',
                'placeholder'   => '',
                'multiple'      => false,
                'empty_data'    => null,
                'choices'       => [
                    'Mme'   => '0 | madame',
                    'Mr'    => '1 | monsieur'
                ],
                'label_attr'    => [
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ],
                'attr'          => [
                    'class' => 'form-control'
                ]
            ])
            ->add('nom', TextType::class, [
                'required'  => false,
                'label'     => 'Nom',
                'attr'      => [
                    'placeholder'   => '',
                    'class'         => 'form-control'
                ],
                'label_attr'    => [
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ]
            ])
            ->add('prenom', TextType::class, [
                'required'  => false,
                'label'     => 'Prénom',
                'attr'      => [
                    'placeholder'   => '',
                    'class'         => 'form-control'
                ],
                'label_attr'    => [
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ]
            ])
            ->add('titre', TextType::class, [
                'required'  => false,
                'label'     => 'Titre',
                'attr'      => [
                    'placeholder'   => '',
                    'class'         => 'form-control'
                ],
                'label_attr'    => [
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ]
            ])
            ->add('telephone', TextType::class, [
                'required'  => false,
                'label'     => 'Téléphone',
                'attr'      => [
                    'placeholder'   => '',
                    'maxlength'     => 14,
                    'pattern'       => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                    'class'         => 'form-control contact-telephone',
                    'title'         => '0x-xx-xx-xx-xx'
                ],
                'label_attr'    => [
                    'class' => 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ]
            ])
            ->add('email', EmailType::class, [
                'required'  => false,
                'label'     => 'Email',
                'attr'      => [
                    'placeholder'   => '',
                    'class'         => 'form-control'
                ],
                'label_attr'    => [
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Structure_contact::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_structure_contact';
    }
}

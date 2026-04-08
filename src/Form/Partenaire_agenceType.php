<?php

namespace App\Form;

use App\Entity\Partenaire_agence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Partenaire_agenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom',        TextType::class,    [
                'required'  => false,
                'label'     => 'Nom',
                'attr'      => [
                    'placeholder' => '',
                    'class'       => 'form-control'
                ],
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ],
            ])
            ->add('contact',    TextType::class,    [
                'required'  => false,
                'label'     => 'Contact',
                'attr'      => [
                    'placeholder' => '',
                    'class'       => 'form-control'
                ],
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ],
            ])
            ->add('adresse',    TextType::class,    [
                'required'   => false,
                'label'      => 'Adresse',
                'attr'       => [
                    'placeholder' => '',
                    'class'       => 'form-control'
                ],
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ],
            ])
            ->add('codePostal', TextType::class,    [
                'required'   => false,
                'label'      => 'Code Postal',
                'attr'       => [
                    'placeholder' => '',
                    'maxlength'   => 5,
                    'pattern'     => '^[0-9]{4,5}$',
                    'class'       => 'form-control agence-codePostal'
                ],
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ]
            ])
            ->add('ville',      TextType::class,    [
                'required'   => false,
                'label'      => 'Commune',
                'attr'       => [
                    'placeholder' => '',
                    'class'       => 'form-control'
                ],
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ]
            ])
            ->add('telephone',  TextType::class,    [
                'required'   => false,
                'label'      => 'Téléphone',
                'attr'       => [
                    'placeholder' => '',
                    'maxlength'   => 14,
                    'pattern'     => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                    'class'       => 'form-control agence-telephone',
                    'title'       => '0x-xx-xx-xx-xx'
                ],
                'label_attr' => [
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ]
            ])
            ->add('email',      EmailType::class,   [
                'required'   => false,
                'label'      => 'Email',
                'attr'       => [
                    'placeholder' => '',
                    'class'       => 'form-control'
                ],
                'label_attr' => [
                    'class' => 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenaire_agence::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_partenaire_agence';
    }
}

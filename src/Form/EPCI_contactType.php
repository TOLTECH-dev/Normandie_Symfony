<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;


class EPCI_contactType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('civilite',   ChoiceType::class,  array(
                'required'      => false,
                'label'         => 'Civilité',
                'placeholder'   => '',
                'multiple'      => false,
                'empty_data'    => null,
                'choices'       => array(
                    'Mme'   => '0 | madame',
                    'Mr'    => '1 | monsieur'
                ),
                'label_attr'    =>  array(
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ),
                'attr'          => array(
                    'class' => 'form-control'
                )
            ))
            ->add('nom',        TextType::class,    array(
                'required'  => true,
                'label'     => 'Nom',
                'label_attr'    =>  array(
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ),
                'attr'      => array(
                    'placeholder'   => '',
                    'class'         => 'form-control'
                )
            ))
            ->add('prenom',     TextType::class,    array(
                'required'  => false,
                'label'     => 'Prénom',
                'label_attr'    =>  array(
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ),
                'attr'      => array(
                    'placeholder'   => '',
                    'class'         => 'form-control'
                )
            ))
            ->add('titre',      TextType::class,    array(
                'required'  => false,
                'label'     => 'Titre',
                'label_attr'    =>  array(
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ),
                'attr'      => array(
                    'placeholder'   => '',
                    'class'         => 'form-control'
                )
            ))
            ->add('telephone',  TextType::class,    array(
                'required'  => false,
                'label'     => 'Téléphone',
                'label_attr'    =>  array(
                    'class' => 'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ),
                'attr'      => array(
                    'placeholder'   => '',
                    'maxlength'     => 14,
                    'pattern'       => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                    'class'         => 'form-control contact-telephone',
                    'title'         => '0x-xx-xx-xx-xx'
                )
            ))
            ->add('email',      EmailType::class,   array(
                'required'  => false,
                'label'     => 'Email',
                'label_attr'    =>  array(
                    'class' =>  'col-xs-12 col-sm-12 col-md-12 col-lg-12'
                ),
                'attr'      => array(
                    'placeholder'   => '',
                    'class'         => 'form-control'
                )
            ))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'App\Entity\EPCI_contact'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'whitelabel_backofficebundle_epci_contact';
    }
}

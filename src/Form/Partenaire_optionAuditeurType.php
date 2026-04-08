<?php

namespace App\Form;

use App\Entity\Partenaire_optionAuditeur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Partenaire_optionAuditeurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rib', FileType::class, [
                'required' => false,
                'label' => 'Téléchargement du RIB'
            ])
            ->add('domicileBancaire', TextType::class, [
                'required' => false,
                'label' => 'Domicialisation bancaire',
                'attr' => [
                    'placeholder' => 'Entrez le domicile bancaire',
                ]
            ])
            ->add('titulaire', TextType::class, [
                'required' => false,
                'label' => 'Nom du titulaire',
                'attr' => [
                    'placeholder' => 'Entrez le nom du titulaire',
                ]
            ])
            ->add('iban', TextType::class, [
                'required' => false,
                'label' => 'IBAN',
                'attr' => [
                    'placeholder' => 'Entrez l\'IBAN',
                ]
            ])
            ->add('bic', TextType::class, [
                'required' => false,
                'label' => 'BIC',
                'attr' => [
                    'placeholder' => 'Entrez le BIC',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenaire_optionAuditeur::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_partenaire_optionauditeur';
    }
}

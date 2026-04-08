<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_travaux_instruction_conformite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class Remboursement_travaux_instruction_conformiteType extends AbstractType
{
    private $var;
    private $var_label;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->var = $options['var'] . ' form-control';
        $this->var_label = $options['var'];

        $builder
            ->add('document',   FileType::class,    [
                'required'  => false,
                'label'     => 'Document',
                'attr'      => [
                    'class' => 'custom-file',
                ]
            ])
            ->add('isConforme', ChoiceType::class,  [
                'required'      => true,
                'label'         => 'Conforme',
                'placeholder'   => '-- Choisir --',
                'empty_data'    => null,
                'choices'       => [
                    'Oui'   => '0 | oui',
                    'Non'   => '1 | non',
                ],
                'label_attr'  => [
                    'class' => $this->var_label,
                ],
                'attr'  => [
                    'class' => $this->var,
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Remboursement_travaux_instruction_conformite::class,
            'var'           => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_remboursement_travaux_instruction_conformite';
    }
}

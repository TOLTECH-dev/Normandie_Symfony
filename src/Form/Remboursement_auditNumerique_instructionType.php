<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_auditNumerique_instruction;
use App\Form\Remboursement_instructionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class Remboursement_auditNumerique_instructionType extends Remboursement_instructionType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder
            ->add('facture', FileType::class, [
                'required'  => false,
                'label'     => 'Téléchargement de la facture',
                'attr'      => [
                    'class' =>  'custom-file'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Remboursement_auditNumerique_instruction::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_remboursement_auditNumerique_instruction';
    }
}

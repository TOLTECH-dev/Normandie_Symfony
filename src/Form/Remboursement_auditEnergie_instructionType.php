<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_auditEnergie_instruction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class Remboursement_auditEnergie_instructionType extends Remboursement_instructionType
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
            'data_class'    => Remboursement_auditEnergie_instruction::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_remboursement_auditenergie_instruction';
    }
}

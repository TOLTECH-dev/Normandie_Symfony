<?php

namespace App\Form;

use App\Entity\Instruction_;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class Instruction_Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('instruction_auditEnergie', Instruction_auditEnergieType::class, [
                'label' => false,
            ])
            ->add('instruction_travaux', Instruction_travauxType::class, [
                'label' => false,
            ])
            ->add('valider', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Instruction_::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_instruction_';
    }
}

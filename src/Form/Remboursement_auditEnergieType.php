<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_auditEnergie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Remboursement_auditEnergieType extends AbstractType
{
    private $traitChoices;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];
        $builder
            ->add('depot',          Remboursement_auditEnergie_depotType::class,        [
                'label'         => false,
                'trait_choices' => $this->traitChoices
            ])
            ->add('instruction',    Remboursement_auditEnergie_instructionType::class,  [
                'label'         => false,
                'trait_choices' => $this->traitChoices
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Remboursement_auditEnergie::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_remboursement_auditenergie';
    }
}

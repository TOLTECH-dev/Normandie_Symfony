<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class Remboursement_Type extends AbstractType
{
    private $traitChoices;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];
        $builder
            ->add('remboursement_auditEnergie',     Remboursement_auditEnergieType::class,      [
                'label'         => false,
                'trait_choices' => $this->traitChoices
            ])
            ->add('remboursement_auditNumerique',   Remboursement_auditNumeriqueType::class,    [
                'label'         => false,
                'trait_choices' => $this->traitChoices
            ])
            ->add('remboursement_travaux',          Remboursement_travauxType::class,           [
                'label'         => false,
                'trait_choices' => $this->traitChoices
            ])
            ->add('valider',                        SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Remboursement_::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_remboursement_';
    }
}

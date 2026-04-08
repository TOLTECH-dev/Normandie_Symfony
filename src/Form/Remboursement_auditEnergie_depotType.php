<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_auditEnergie_depot;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class Remboursement_auditEnergie_depotType extends AbstractType
{
    private $traitChoices;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];
        $builder
            ->add('audit', FileType::class, [
                'required' => $this->traitChoices['optionDepot'][0],
                'label' => "Téléchargement de l'audit réalisé"
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Remboursement_auditEnergie_depot::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_remboursement_auditenergie_depot';
    }
}

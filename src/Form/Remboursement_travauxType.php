<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_travaux;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class Remboursement_travauxType
 * @package whiteLabel\BackOfficeBundle\Form
 */
class Remboursement_travauxType extends AbstractType
{
    private $traitChoices;

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];

        $builder
            ->add('ficheTechnique', FicheTechniqueType::class, [
                'label'         => false,
                'trait_choices' => $this->traitChoices
            ])
            ->add('instruction', Remboursement_travaux_instructionType::class, [
                'label'         => false,
                'trait_choices' => $this->traitChoices
            ])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Remboursement_travaux::class,
            'trait_choices' => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_remboursement_travaux';
    }
}

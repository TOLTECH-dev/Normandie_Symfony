<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\FicheTechnique;

class FicheTechniqueType extends AbstractType
{
    private $traitChoices;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fielCEPUbatBase = 'CEPUbatBase';
        $this->traitChoices = $options['trait_choices'];

        $examineFicheTechniquePart = !empty($this->traitChoices['optionFicheTechnique'][5]) ? $this->traitChoices['optionFicheTechnique'][5] : null;
        $validateNumericFieldsBase = [
            'surfaceHabitable',
            'surfaceSRT',
            'toitureSurface',
            'toitureR',
            'mursSurface',
            'mursR',
            'menuiseriesExterieuresSurface',
            'menuiseriesExterieuresUW',
            'plancherBasSurface',
            'plancherBasR',
            'CEP',
            'CEPGES',
            'CEPUbat',
            'CEPQ4Pa_surf'
        ];
        $validateNumericFieldsInitial = [];
        $validateNumericFieldsBBC = [];
        $validateNumericFieldsPrescription = [];
        $validateNumericFieldsFinChantier = [];

        $validateAdditionalRequiredFieldsDemande = [];
        $validateAdditionalRequiredFieldsFinChantier = [];

        if (FicheTechnique::EXAMINE_FICHE_TECHNIQUE_PART_DEMANDE === $examineFicheTechniquePart) {
            $validateNumericFieldsInitial = array_merge($validateNumericFieldsBase, [$fielCEPUbatBase]);
            $validateNumericFieldsBBC = $validateNumericFieldsBase;
            $validateNumericFieldsPrescription = $validateNumericFieldsBase;
        } elseif (FicheTechnique::EXAMINE_FICHE_TECHNIQUE_PART_REMBOURSEMENT === $examineFicheTechniquePart) {
            $validateNumericFieldsFinChantier = $validateNumericFieldsBase;
            $validateAdditionalRequiredFieldsFinChantier = ['CEP', 'CEPGES', 'CEPUbat', 'CEPQ4Pa_surf',
                'menuiseriesExterieuresUW', 'mursR', 'plancherBasR', 'toitureR'];
        }

        $builder
            ->add('ficheTechnique_initial',     FicheTechniqueFieldType::class, [
                'label' => false,
                'trait_choices' => $this->traitChoices,
                'validate_numeric_fields' => $validateNumericFieldsInitial,
                'validate_additional_required_fields' => $validateAdditionalRequiredFieldsDemande
            ])
            ->add('ficheTechnique_BBC',         FicheTechniqueFieldType::class, [
                'label' => false,
                'trait_choices' => $this->traitChoices,
                'validate_numeric_fields' => $validateNumericFieldsBBC,
                'validate_additional_required_fields' => $validateAdditionalRequiredFieldsDemande
            ])
            ->add('ficheTechnique_prescription', FicheTechniqueFieldType::class, [
                'label' => false,
                'trait_choices' => $this->traitChoices,
                'validate_numeric_fields' => $validateNumericFieldsPrescription,
                'validate_additional_required_fields' => $validateAdditionalRequiredFieldsDemande
            ])
            ->add('ficheTechnique_finChantier', FicheTechniqueFieldType::class, [
                'label' => false,
                'trait_choices' => $this->traitChoices,
                'validate_numeric_fields' => $validateNumericFieldsFinChantier,
                'validate_additional_required_fields' => $validateAdditionalRequiredFieldsFinChantier
            ]);

        if (!empty($this->traitChoices['optionFicheTechnique'][4])) {
            $builder
                ->add('isValidationConseiller', CheckboxType::class, [
                    'required' => false,
                    'attr' => [
                        'class' => 'onoffswitch-checkbox'
                    ]
                ]);
        }

        $builder->add('valider', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FicheTechnique::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_fichetechnique';
    }
}

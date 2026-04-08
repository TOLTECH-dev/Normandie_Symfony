<?php

declare(strict_types=1);

namespace App\Form;

use App\Utils\DefaultServiceUtils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\FicheTechniqueField;

class FicheTechniqueFieldType extends AbstractType
{
    private $traitChoices;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];
        $isReexamine = (isset($this->traitChoices['optionFicheTechnique'][3]) && $this->traitChoices['optionFicheTechnique'][3] === true);

        $builder
            ->add('type', HiddenType::class, [
                'required' => false,
                'label'    => 'Type',
                'attr'     => [
                    'placeholder' => 'Type'
                ]
            ])
            ->add('ficheTechniqueDocument', FileType::class, [
                'required' => false,
                'label'    => false,
            ])
            ->add('surfaceHabitable', TextType::class, [
                'required' => true,
                'label'    => 'Surface habitable (SHAB en m²)',
                'attr'     => [
                    'placeholder' => 'Surface habitable (SHAB en m²)',
                    'oninvalid'   => 'this.setCustomValidity("Pour enregistrer, veuillez renseigner les champs marqués d\'une astérisque")',
                    'oninput'     => 'this.setCustomValidity("")'
                ]
            ])
            ->add('surfaceSRT', TextType::class, [
                'required' => false,
                'label'    => 'SRT (m²)',
                'attr'     => [
                    'placeholder' => 'SRT (m²)',
                    'oninvalid'   => 'this.setCustomValidity("Pour enregistrer, veuillez renseigner les champs marqués d\'une astérisque")',
                    'oninput'     => 'this.setCustomValidity("")'
                ]
            ])
            ->add('surfacePathologies', ChoiceType::class, [
                'required'    => false,
                'placeholder' => '-- Choisir des pathologies --',
                'label'       => false,
                'multiple'    => true,
                'expanded'    => true,
                'choices'     => $this->traitChoices['optionFicheTechnique'][0]
            ])
            ->add('surfacePathologiesAutre', TextType::class, [
                'required' => false,
                'label'    => false,
                'attr'     => [
                    'placeholder' => 'Autre pathologie',
                    'maxlength'   => '245'
                ]
            ])
            ->add('toitureSurface', TextType::class, [
                'required' => false,
                'label'    => 'Surface (m²)',
                'attr'     => [
                    'placeholder' => 'Surface (m²)'
                ]
            ])
            ->add('toitureR', TextType::class, [
                'required' => true,
                'label'    => 'R (m².K/W)',
                'attr'     => [
                    'placeholder' => 'R (m².K/W)'
                ],
                'constraints' => array_filter([
                    in_array('toitureR', $options['validate_numeric_fields'])
                        ? new Assert\Regex([
                        'pattern' => DefaultServiceUtils::DECIMAL_FIELD_FORM_PATTERN_WITH_DELIMITER,
                        'message' => DefaultServiceUtils::DECIMAL_FIELD_FORM_TEXT,
                    ]) : null,
                    in_array('toitureR', $options['validate_additional_required_fields'])
                        ? new Assert\NotBlank([
                        'message' => 'La saisie est obligatoire'
                    ]) : null,
                ])
            ])
            ->add('toitureEtancheite', TextareaType::class, [
                'required' => false,
                'label'    => 'Information étanchéité à l\'air et migration vapeur d\'eau',
                'attr'     => [
                    'placeholder' => 'Information étanchéité à l\'air et migration vapeur d\'eau'
                ]
            ])
            ->add('mursSurface', TextType::class, [
                'required' => false,
                'label'    => 'Surface (m²)',
                'attr'     => [
                    'placeholder' => 'Surface (m²)'
                ]
            ])
            ->add('mursR', TextType::class, [
                'required' => true,
                'label'    => 'R (m².K/W)',
                'attr'     => [
                    'placeholder' => 'R (m².K/W)'
                ],
                'constraints' => array_filter([
                    in_array('mursR', $options['validate_numeric_fields'])
                        ? new Assert\Regex([
                        'pattern' => DefaultServiceUtils::DECIMAL_FIELD_FORM_PATTERN_WITH_DELIMITER,
                        'message' => DefaultServiceUtils::DECIMAL_FIELD_FORM_TEXT,
                    ]) : null,
                    in_array('mursR', $options['validate_additional_required_fields'])
                        ? new Assert\NotBlank([
                        'message' => 'La saisie est obligatoire'
                    ]) : null,
                ])
            ])
            ->add('mursEtancheite', TextareaType::class, [
                'required' => false,
                'label'    => 'Information étanchéité à l\'air et migration vapeur d\'eau',
                'attr'     => [
                    'placeholder' => 'Information étanchéité à l\'air et migration vapeur d\'eau'
                ]
            ])
            ->add('mursJonctionMursPlanchers', TextareaType::class, [
                'required' => false,
                'label'    => 'Jonction murs / planchers y compris intermédiaires',
                'attr'     => [
                    'placeholder' => 'Jonction murs / planchers y compris intermédiaires',
                    'maxlength'   => 255
                ]
            ])
            ->add('menuiseriesExterieuresSurface', TextType::class, [
                'required' => false,
                'label'    => 'Surface (m²)',
                'attr'     => [
                    'placeholder' => 'Surface (m²)'
                ]
            ])
            ->add('menuiseriesExterieuresUW', TextType::class, [
                'required' => true,
                'label'    => 'Uw (W/m².K)',
                'attr'     => [
                    'placeholder' => 'Uw (W/m².K)'
                ],
                'constraints' => array_filter([
                    in_array('menuiseriesExterieuresUW', $options['validate_numeric_fields'])
                        ? new Assert\Regex([
                        'pattern' => DefaultServiceUtils::DECIMAL_FIELD_FORM_PATTERN_WITH_DELIMITER,
                        'message' => DefaultServiceUtils::DECIMAL_FIELD_FORM_TEXT,
                    ]) : null,
                    in_array('menuiseriesExterieuresUW', $options['validate_additional_required_fields'])
                        ? new Assert\NotBlank([
                        'message' => 'La saisie est obligatoire'
                    ]) : null,
                ])
            ])
            ->add('menuiseriesModePose', TextareaType::class, [
                'required' => false,
                'label'    => 'Information sur mode de pose et traitement jonction isolant / retour de tableau',
                'attr'     => [
                    'placeholder' => 'Information sur mode de pose et traitement jonction isolant / retour de tableau'
                ]
            ])
            ->add('menuiseriesTypeProtectionsSolaires', TextareaType::class, [
                'required' => false,
                'label'    => 'Type de protections solaires',
                'attr'     => [
                    'placeholder' => 'Type de protections solaires',
                    'maxlength'   => 255
                ]
            ])
            ->add('plancherBasSurface', TextType::class, [
                'required' => false,
                'label'    => 'Surface (m²)',
                'attr'     => [
                    'placeholder' => 'Surface (m²)'
                ]
            ])
            ->add('plancherBasR', TextType::class, [
                'required' => true,
                'label'    => 'R (m².K/W)',
                'attr'     => [
                    'placeholder' => 'R (m².K/W)'
                ],
                'constraints' => array_filter([
                    in_array('plancherBasR', $options['validate_numeric_fields'])
                        ? new Assert\Regex([
                        'pattern' => DefaultServiceUtils::DECIMAL_FIELD_FORM_PATTERN_WITH_DELIMITER,
                        'message' => DefaultServiceUtils::DECIMAL_FIELD_FORM_TEXT,
                    ]) : null,
                    in_array('plancherBasR', $options['validate_additional_required_fields'])
                        ? new Assert\NotBlank([
                        'message' => 'La saisie est obligatoire'
                    ]) : null,
                ])
            ])
            ->add('plancherBasEtancheite', TextareaType::class, [
                'required' => false,
                'label'    => 'Gestion des points singuliers',
                'attr'     => [
                    'placeholder' => 'Gestion des points singuliers'
                ]
            ])
            ->add('chauffageEnergie', ChoiceType::class, [
                'required'    => false,
                'label'       => 'Energie',
                'placeholder' => '-- Choisir une énergie --',
                'multiple'    => true,
                'expanded'    => true,
                'choices'     => $this->traitChoices['optionFicheTechnique'][1]
            ])
            ->add('chauffageEquipement', TextareaType::class, [
                'required' => false,
                'label'    => 'Equipement',
                'attr'     => [
                    'placeholder' => 'Equipement'
                ]
            ])
            ->add('ECSEnergie', ChoiceType::class, [
                'required'    => false,
                'label'       => 'Energie',
                'placeholder' => '-- Choisir une énergie --',
                'multiple'    => true,
                'expanded'    => true,
                'choices'     => $this->traitChoices['optionFicheTechnique'][1]
            ])
            ->add('ECSEquipement', TextareaType::class, [
                'required' => false,
                'label'    => 'Equipement',
                'attr'     => [
                    'placeholder' => 'Equipement'
                ]
            ])
            ->add('climatisation', TextareaType::class, [
                'required' => false,
                'label'    => 'Climatisation',
                'attr'     => [
                    'placeholder' => 'Climatisation'
                ]
            ])
            ->add('climatisationTypeVentilation', ChoiceType::class, [
                'required'    => false,
                'label'       => 'Ventilation',
                'placeholder' => '-- Choisir une ventilation --',
                'multiple'    => false,
                'empty_data'  => null,
                'choices'     => $this->traitChoices['optionFicheTechnique'][2]
            ])
            ->add('CEP', TextType::class, [
                'required' => false,
                'label'    => 'CEP (kWh/m².an)',
                'attr'     => [
                    'placeholder' => 'CEP (kWh/m².an)',
                    'oninvalid'   => 'this.setCustomValidity("Pour enregistrer, veuillez renseigner les champs marqués d\'une astérisque")',
                    'oninput'     => 'this.setCustomValidity("")'
                ]
            ])
            ->add('CEPUbat', TextType::class, [
                'required' => false,
                'label'    => 'Ubât (W/m².K)',
                'attr'     => [
                    'placeholder' => 'Ubât (W/m².K)'
                ]
            ])
            ->add('CEPUbatBase', TextType::class, [
                'required' => false,
                'label'    => 'Ubât base (W/m².K)',
                'attr'     => [
                    'placeholder' => 'Ubât base (W/m².K)'
                ]
            ])
            ->add('CEPQ4Pa_surf', TextType::class, [
                'required' => false,
                'label'    => 'Q4 (m³/h.m²)',
                'attr'     => [
                    'placeholder' => 'Q4 (m³.h/m²)'
                ]
            ])
            ->add('CEPGES', TextType::class, [
                'required' => false,
                'label'    => 'GES (Tonne CO² /m²)',
                'attr'     => [
                    'placeholder' => 'GES (Tonne CO² /m²)'
                ]
            ])
             ->add('CEPEtiquetteEnergetique', TextType::class, [
                'required' => false,
                'label'    => 'Etiquette énergétique',
                'attr'     => [
                    'placeholder' => 'Etiquette énergétique',
                    'readonly'    => true,
                ]
            ])
            ->add('informationControleurChantier', TextareaType::class, [
                'required' => false,
                'label'    => 'Contrôleur de fin de chantier : infiltrométrie et ventilation',
                'attr'     => [
                    'placeholder' => 'Nom des sociétés de contrôles : infiltrométrie et ventilation',
                    'readonly'    => true,
                    'rows'        => 3
                ]
            ])
            ->add('infiltrometrieDocument', FileType::class, [
                'required' => (!$isReexamine && isset($this->traitChoices['optionFicheTechnique'][3])),
                'label'    => false,
            ])
            ->add('isValeurQ4CalculeeConforme', CheckboxType::class, [
                'required' => false,
                'attr'     => [
                    'class' => 'onoffswitch-checkbox'
                ]
            ])
            ->add('ventilationDocument', FileType::class, [
                'required' => (!$isReexamine && isset($this->traitChoices['optionFicheTechnique'][3])),
                'label'    => false,
            ])
            ->add('isSystemeVentilationConforme', CheckboxType::class, [
                'required' => false,
                'attr'     => [
                    'class' => 'onoffswitch-checkbox'
                ]
            ])
            ->add('auditApresTravauxDocument', FileType::class, [
                'required' => (!$isReexamine && isset($this->traitChoices['optionFicheTechnique'][3])),
                'label'    => false,
            ])
            ->add('isValoriserRenovation', CheckboxType::class, [
                'required' => false,
                'attr'     => [
                    'class' => 'onoffswitch-checkbox'
                ]
            ])
            ->add('valoriserRenovationJustification', TextareaType::class, [
                'required' => false,
                'label'    => false,
                'attr'     => [
                    'placeholder' => 'Champs libre pour justifier',
                    'rows'        => 3
                ]
            ])
            ->add('informationValidation', CheckboxType::class, [
                'required' => false,
                'attr'     => [
                    'class' => 'onoffswitch-checkbox'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FicheTechniqueField::class,
            'trait_choices' => null,
            'validate_numeric_fields' => [],
            'validate_additional_required_fields' => []
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_fichetechniquefield';
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Demande_travaux_devis_upload;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContext;


class Demande_travaux_devis_uploadType extends AbstractType
{

    /**
     * @var null
     */
    protected $devisCustomColumnsAttrRequired = null;


    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!empty($options['var']['isShowDevisCustomColumns'])) {
            // WE SHOW CUSTOM COLUMNS
            $devisCustomColumnsLabelAttrClass = 'required';
            $devisCustomColumnsAttrClass = 'form-control';
            $this->devisCustomColumnsAttrRequired = true;
        } else {
            // WE HIDE CUSTOM COLUMNS
            $devisCustomColumnsLabelAttrClass = 'hidden';
            $devisCustomColumnsAttrClass = 'hidden';
            $this->devisCustomColumnsAttrRequired = false;
        }

        $arrayDemandeTypeTravauxForm = Demande_travaux_devis_upload::$arrayDemandeTypeTravaux;
        // Element à afficher seulement en consulation
        unset($arrayDemandeTypeTravauxForm[Demande_travaux_devis_upload::TYPE_TRAVAUX_ISOLATION_THERMIQUE_MURS_KEY]);

        $builder
            ->add('type', ChoiceType::class, array(
                'required' => true,
                'label' => 'Type de travaux',
                'placeholder' => '-- Type de travaux --',
                'empty_data' => null,
                'choices' => array_flip($arrayDemandeTypeTravauxForm),
                'label_attr' => [
                    'class' => 'required'
                ],
                'attr' => [
                    'class' => 'form-control',
                    'required' => true
                ]
            ))
            ->add('biosource', ChoiceType::class, [
                'required' => true,
                'label' => 'Biosourcé',
                'placeholder' => '-- Choisir une valeur --',
                'empty_data' => null,
                'choices' => Demande_travaux_devis_upload::$ARRAY_BIOSOURCE,
                'label_attr' => [
                    'class' => $devisCustomColumnsLabelAttrClass
                ],
                'attr' => [
                    'class' => $devisCustomColumnsAttrClass,
                    'required' => $this->devisCustomColumnsAttrRequired
                ]
            ])
            ->add('montant', IntegerType::class, [
                'required' => true,
                'label' => 'Montant total TTC',
                'label_attr' => [
                    'class' => 'required'
                ],
                'attr' => [
                    'class' => 'form-control montantDevis',
                    'placeholder' => 'Montant total TTC',
                    'required' => true
                ],
            ])
            ->add('entrepriseRGE', ChoiceType::class, [
                'required' => true,
                'label' => 'Entreprise RGE',
                'placeholder' => '-- Choisir une valeur --',
                //'data'          => '0 | oui',
                'empty_data' => null,
                'choices' => [
                    'OUI' => '0 | oui',
                    'NON' => '1 | non',
                    'En cours' => '3 | en_cours',
                ],
                'label_attr' => [
                    'class' => $devisCustomColumnsLabelAttrClass
                ],
                'attr' => [
                    'class' => $devisCustomColumnsAttrClass,
                    'required' => $this->devisCustomColumnsAttrRequired
                ]
            ])
            ->add('bonification', ChoiceType::class, [
                'required' => true,
                'label' => 'Bonification',
                'placeholder' => '-- Choisir une valeur --',
                'empty_data' => null,
                'choices' => array_flip(Demande_travaux_devis_upload::$arrayBonification),
                'label_attr' => [
                    'class' => $devisCustomColumnsLabelAttrClass
                ],
                'attr' => [
                    'class' => $devisCustomColumnsAttrClass,
                    'required' => $this->devisCustomColumnsAttrRequired
                ]
            ])
            ->add('devisDocument', FileType::class, [
                'required' => true,
                'label' => 'Devis',
                'label_attr' => [
                    'class' => 'required'
                ],
                'attr' => [
                    'class' => 'custom-file custom-file-devis',
                    'required' => true
                ]
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {

        $resolver->setDefaults([
            'data_class' => Demande_travaux_devis_upload::class,
            'var' => null,
            'constraints' => [
                new Callback(['callback' => function (Demande_travaux_devis_upload $data = null, ExecutionContext $context) {
                    if (!empty($data) && $this->devisCustomColumnsAttrRequired) {
                        if (empty($data->getBiosource())) {
                            $context
                                ->buildViolation('Cette valeur ne doit pas être vide.')
                                ->atPath('biosource')
                                ->addViolation();
                        }
                        if (empty($data->getEntrepriseRGE())) {
                            $context
                                ->buildViolation('Cette valeur ne doit pas être vide.')
                                ->atPath('entrepriseRGE')
                                ->addViolation();
                        }
                        if (empty($data->getBonification())) {
                            $context
                                ->buildViolation('Cette valeur ne doit pas être vide.')
                                ->atPath('bonification')
                                ->addViolation();
                        }
                    }
                }])
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'whitelabel_frontofficebundle_demande_travaux_devis_upload';
    }
}

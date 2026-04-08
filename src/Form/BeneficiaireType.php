<?php

namespace App\Form;

use App\Entity\Banque_;
use App\Entity\Partenaire_;
use App\Entity\Structure_;
use App\Form\DataTransformer\EntityTransformerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Repository\Structure_Repository;
use App\Repository\Partenaire_Repository;
use App\Repository\Banque_Repository;
use App\Entity\Beneficiaire;


class BeneficiaireType extends AbstractType
{
    protected EntityManagerInterface $EM;
    protected EntityTransformerFactory $transformerFactory;

    public function __construct(EntityManagerInterface $EM, EntityTransformerFactory $transformerFactory)
    {
        $this->EM = $EM;
        $this->transformerFactory = $transformerFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];

        $readonly = false;
        $classCivility = 'form-control';
        if (isset($this->traitChoices['isFranceConnect']) && $this->traitChoices['isFranceConnect']) {
            $readonly = true;
            $classCivility .= ' isReadonly';
        }

        $builder
            ->add('type', ChoiceType::class, array(
                'required' => true,
                'label' => 'Type',
                'placeholder' => '',
                'multiple' => false,
                'expanded' => true,
                'empty_data' => null,
                'choices' => array(
                    'Particulier' => '0 | particulier',
                    'SCI' => '1 | sci'
                )
            ))
            ->add('nomSCI', TextType::class, array(
                'required' => false,
                'label' => 'Nom SCI',
                'attr' => array(
                    'placeholder' => 'Nom SCI',
                )
            ))
            ->add('civilite', ChoiceType::class, array(
                'required' => true,
                'label' => 'Civilité',
                'attr' => array(
                    'readonly' => $readonly,
                    'class' => $classCivility
                ),
                'placeholder' => '-- Choisir une civilité --',
                'multiple' => false,
                'empty_data' => null,
                'choices' => array(
                    'Madame' => '0 | madame',
                    'Monsieur' => '1 | monsieur'
                )
            ))
            ->add('nom', TextType::class, array(
                'required' => true,
                'label' => 'Nom',
                'attr' => array(
                    'placeholder' => 'Nom',
                    'readonly' => $readonly
                )
            ))
            ->add('prenom', TextType::class, array(
                'required' => true,
                'label' => 'Prénom',
                'attr' => array(
                    'placeholder' => 'Prénom',
                    'readonly' => $readonly
                )
            ))
            ->add('codePostal', TextType::class, array(
                'required' => true,
                'label' => 'Code Postal',
                'attr' => array(
                    'placeholder' => 'Code Postal',
                    'maxlength' => 5,
                    'pattern' => '^[0-9]{4,5}$',
                )
            ))
            ->add('ville', TextType::class, array(
                'required' => true,
                'label' => 'Commune',
                'attr' => array(
                    'placeholder' => 'Commune',
                    'readonly' => true,
                )
            ))
            ->add('villeId', HiddenType::class, array(
                'required' => true
            ))
            ->add('numeroRue', TextType::class, array(
                'required' => true,
                'label' => 'Numéro de rue',
                'attr' => array(
                    'placeholder' => 'Numéro de rue',
                )
            ))
            ->add('complementNumeroRue', ChoiceType::class, array(
                'required' => false,
                'label' => 'Complément numéro de rue',
                'placeholder' => '-- Choisir un complément numéro de rue --',
                'multiple' => false,
                'empty_data' => null,
                'choices' => array(
                    'BIS' => '0 | bis',
                    'TER' => '1 | ter'
                )
            ))
            ->add('INSEE', HiddenType::class, array(
                'required' => false,
                'label' => 'Code INSEE',
                'attr' => array(
                    'placeholder' => 'Code INSEE',
                    'readonly' => true,
                )
            ))
            ->add('nomRue', HiddenType::class, array(
                'required' => true,
                'label' => 'Nom de rue',
                'attr' => array(
                    'placeholder' => 'Nom de rue',
                    'readonly' => true,
                )
            ))
            ->add('nomRueNotFound', CheckboxType::class, array(
                'required' => false,
                'label' => 'Adresse non trouvée',
            ))
            ->add('complement1', TextType::class, array(
                'required' => false,
                'label' => 'Complément 1',
                'attr' => array(
                    'placeholder' => 'Complément 1',
                )
            ))
            ->add('complement2', TextType::class, array(
                'required' => false,
                'label' => 'Complément 2',
                'attr' => array(
                    'placeholder' => 'Complément 2',
                )
            ))
            ->add('email', EmailType::class, array(
                'required' => true,
                'label' => 'Email',
                'attr' => array(
                    'placeholder' => 'Email',
                    'readonly' => true,
                )
            ))
            ->add('tel1', TextType::class, array(
                'required' => false,
                'label' => 'Téléphone 1',
                'attr' => array(
                    'placeholder' => 'Téléphone 1',
                    'maxlength' => 14,
                    'pattern' => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                )
            ))
            ->add('tel2', TextType::class, array(
                'required' => false,
                'label' => 'Téléphone 2',
                'attr' => array(
                    'placeholder' => 'Téléphone 2',
                    'maxlength' => 14,
                    'pattern' => '^0\d(\s|-)?(\d{2}(\s|-)?){4}$',
                )
            ))
            ->add('situationFamille', ChoiceType::class, array(
                'required' => false,
                'label' => 'Situation familiale',
                'placeholder' => '-- Choisir une situation familiale --',
                'multiple' => false,
                'empty_data' => null,
                'choices' => array_flip(Beneficiaire::$ARRAY_SITUATION_FAMILIALE)
            ))
            ->add('nomConjoint', TextType::class, array(
                'required' => false,
                'label' => 'Nom du conjoint',
                'attr' => array(
                    'placeholder' => 'Nom du conjoint',
                )
            ))
            ->add('prenomConjoint', TextType::class, array(
                'required' => false,
                'label' => 'Prénom du conjoint',
                'attr' => array(
                    'placeholder' => 'Prénom du conjoint',
                )
            ))
            ->add('nbPersFoyer', TextType::class, array(
                'required' => true,
                'label' => 'Nombre de personnes dans le foyer',
                'attr' => array(
                    'placeholder' => 'Nombre de personnes dans le foyer',
                )
            ))
            ->add('revenuFiscalRef', TextType::class, array(
                'required' => true,
                'label' => 'Revenu fiscal de référence',
                'attr' => array(
                    'placeholder' => 'Revenu fiscal de référence',
                )
            ))
            ->add('knownByMedia', CheckboxType::class, array(
                'required' => false,
                'label' => 'Média/Evènement/Presse',
            ))
            ->add('structure_id', EntityType::class, array(
                'required' => false,
                'placeholder' => '-- Choisir une structure --',
                'label' => false,
                'class' => Structure_::class,
                'choice_label' => function ($obj) {
                    return $obj->getStructureIdentification()->getNom();
                },
                'choice_value' => function ($obj) {
                    return $obj ? $obj->getId() : '';
                },
                'query_builder' => function (Structure_Repository $r) {
                    return $r->findByStructureEnabled('1');
                }
            ))
            ->add('auditeur_id', EntityType::class, array(
                'required' => false,
                'placeholder' => '-- Choisir un auditeur --',
                'label' => false,
                'class' => Partenaire_::class,
                'choice_label' => function ($obj) {
                    return $obj->getPartenaireIdentification()->getRaisonSociale();
                },
                'choice_value' => function ($obj) {
                    return $obj ? $obj->getId() : '';
                },
                'query_builder' => function (Partenaire_Repository $r) {
                    return $r->findByThematiqueEnabled('%auditeur%', '1');
                }
            ))
            ->add('renovateur_id', EntityType::class, array(
                'required' => false,
                'placeholder' => '-- Choisir un rénovateur --',
                'label' => false,
                'class' => Partenaire_::class,
                'choice_label' => function ($obj) {
                    return $obj->getPartenaireIdentification()->getRaisonSociale();
                },
                'choice_value' => function ($obj) {
                    return $obj ? $obj->getId() : '';
                },
                'query_builder' => function (Partenaire_Repository $r) {
                    return $r->findByThematiqueEnabled('%renovateur%', '1');
                }
            ))
            ->add('financeur_id', EntityType::class, array(
                'required' => false,
                'placeholder' => '-- Choisir une banque --',
                'label' => false,
                'class' => Banque_::class,
                'choice_label' => function ($obj) {
                    return $obj->getNom();
                },
                'choice_value' => function ($obj) {
                    return $obj ? $obj->getId() : '';
                },
                'query_builder' => function (Banque_Repository $r) {
                    return $r->findByEnabled('1');
                }
            ))
            ->add('knownByOther', CheckboxType::class, array(
                'required' => false,
                'label' => 'Autre',
            ))
            ->add('structure_rattachement_id', EntityType::class, array(
                'required' => true,
                'placeholder' => '-- Choisir une structure --',
                'label' => false,
                'class' => Structure_::class,
                'choice_label' => function ($obj) {
                    return $obj->getStructureIdentification()->getNom();
                },
                'choice_value' => function ($obj) {
                    return $obj ? $obj->getId() : '';
                },
                'query_builder' => function (Structure_Repository $r) {
                    return $r->findByStructureEnabled('1');
                }
            ))
            ->add('conseiller_rattachement_id', HiddenType::class, array(
                'required' => false,
                'data' => $this->traitChoices['conseillerRattachementId']
            ))
            ->add('valider', SubmitType::class);

        // Add model transformers to convert between entity objects and ID values
        $builder->get('structure_id')->addModelTransformer(
            $this->transformerFactory->create(Structure_::class)
        );
        $builder->get('auditeur_id')->addModelTransformer(
            $this->transformerFactory->create(Partenaire_::class)
        );
        $builder->get('renovateur_id')->addModelTransformer(
            $this->transformerFactory->create(Partenaire_::class)
        );
        $builder->get('financeur_id')->addModelTransformer(
            $this->transformerFactory->create(Banque_::class)
        );
        $builder->get('structure_rattachement_id')->addModelTransformer(
            $this->transformerFactory->create(Structure_::class)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Beneficiaire::class,
            'trait_choices' => null,
            'constraints' => [
                new Callback(['callback' => function (Beneficiaire $data, ExecutionContext $context) {
                    if (in_array($data->getSituationFamille(), [
                        Beneficiaire::SITUATION_FAMILIALE_MARIE_KEY,
                        Beneficiaire::SITUATION_FAMILIALE_UNION_LIBRE_KEY,
                        Beneficiaire::SITUATION_FAMILIALE_PACSE_KEY
                    ])) {
                        if (empty($data->getNomConjoint())) {
                            $context
                                ->buildViolation('Cette valeur ne doit pas être vide.')
                                ->atPath('nomConjoint')
                                ->addViolation();
                        }
                        if (empty($data->getPrenomConjoint())) {
                            $context
                                ->buildViolation('Cette valeur ne doit pas être vide.')
                                ->atPath('prenomConjoint')
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
        return 'whitelabel_frontofficebundle_beneficiaire';
    }
}

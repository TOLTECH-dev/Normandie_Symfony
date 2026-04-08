<?php

namespace App\Form;

use App\Entity\Demande_auditEnergie;
use App\Form\DataTransformer\EntityTransformerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Partenaire_;
use App\Entity\Structure_;
use App\Entity\Structure_conseiller;
use App\Repository\Partenaire_Repository;
use App\Repository\Structure_Repository;

class Demande_auditEnergieType extends AbstractType
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
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->traitChoices = $options['trait_choices'];

        $builder
            ->add('justificatifPropriete', FileType::class, [
                'required' => false,
                'label' => 'Justificatif de propriété',
            ])
            ->add('pieceComplement', FileType::class, [
                'required' => false,
                'label' => 'Document complémentaire',
            ])
            // DESACTIVE TEMPORAIREMENT
//            ->add('carnetNumerique',        CheckboxType::class,    array(
//                                                                        'required'  => false,
//                                                                        'label'     => 'J\'accepte'
//                                                                    ))
            ->add('avisImposition', FileType::class, [
                'required' => false,
                'label' => 'Avis d\'imposition du demandeur',
            ])
            ->add('avisImpositionConjoint', FileType::class, [
                'required' => false,
                'label' => 'Avis d\'imposition du conjoint',
            ])
            ->add('nbPersFoyer', TextType::class, [
                'required' => true,
                'label' => 'Nombre de personnes dans le foyer',
                'attr' => [
                    'placeholder' => 'Nombre de personnes dans le foyer',
                ]
            ])
            ->add('revenu1', TextType::class, [
                'required' => true,
                'label' => 'Revenu fiscal de référence du demandeur',
                'attr' => [
                    'placeholder' => 'Revenu fiscal du demandeur',
                ]
            ])
            ->add('revenu2', TextType::class, [
                'required' => false,
                'label' => 'Revenu fiscal de référence du conjoint',
                'attr' => [
                    'placeholder' => 'Revenu fiscal du conjoint',
                ]
            ])
            ->add('revenu3', TextType::class, [
                'required' => false,
                'label' => 'Revenu fiscal de référence du foyer',
                'attr' => [
                    'placeholder' => 'Revenu fiscal du foyer',
                    'readonly' => true,
                ]
            ])
            ->add('structure_id', EntityType::class, [
                'required' => true,
                'placeholder' => '-- Choisir une structure --',
                'label' => false,
                'class' => Structure_::class,
                'choice_label' => function ($obj) {
                    return $obj->getStructureIdentification()->getNom();
                },
                'choice_value' => 'id',
                'query_builder' => function (Structure_Repository $r) {
                    return $r->findByStructureEnabled('1');
                }
            ])
            ->add('conseiller_id', EntityType::class, [
                'required' => true,
                'placeholder' => '-- Choisir un conseiller --',
                'label' => false,
                'class' => Structure_conseiller::class
            ])
            ->add('auditeur_id', EntityType::class, [
                'required' => false,
                'placeholder' => '-- Choisir un auditeur --',
                'label' => false,
                'class' => Partenaire_::class,
                'choice_label' => function ($obj) {
                    return $obj->getPartenaireIdentification()->getRaisonSociale();
                },
                'choice_value' => 'id',
                'query_builder' => function (Partenaire_Repository $r) {
                    return $r->findByThematiqueEnabled('%auditeur%', '1');
                }
            ])
            ->add('CGV', CheckboxType::class, [
                'required' => true,
                'label' => 'Je m\'engage'
            ])
            ->add('isAccompagneStructure', CheckboxType::class, [
                'required' => true,
                'label' => 'J\'accepte'
            ])
            ->add('signature', CheckboxType::class, [
                'required' => true,
                'label' => 'Je signe'
            ]);

        $builder->get('structure_id')->addModelTransformer(
            $this->transformerFactory->create(Structure_::class)
        );
        $builder->get('conseiller_id')->addModelTransformer(
            $this->transformerFactory->create(Structure_conseiller::class)
        );
        $builder->get('auditeur_id')->addModelTransformer(
            $this->transformerFactory->create(Partenaire_::class)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Demande_auditEnergie::class,
            'trait_choices' => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'whitelabel_frontofficebundle_demande_auditenergie';
    }

}

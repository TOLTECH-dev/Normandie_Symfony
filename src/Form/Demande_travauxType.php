<?php

namespace App\Form;

use App\Form\DataTransformer\EntityTransformerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Structure_;
use App\Entity\Structure_conseiller;
use App\Repository\Structure_Repository;
use App\Entity\Demande_travaux;

class Demande_travauxType extends AbstractType
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
            ->add('audit', ChoiceType::class, [
                'required' => true,
                'label' => 'Avez-vous précédemment bénéficié d\'un Chèque Audit Région Normandie?',
                'placeholder' => false,
                'multiple' => false,
                'expanded' => true,
                //'data'      => '0',
                'empty_data' => null,
                'choices' => [
                    'Oui' => '1',
                    'Non' => '0'
                ]
            ])
            ->add('justificatifPropriete', FileType::class, [
                'required' => false,
                'label' => 'Justificatif de propriété',
            ])
            ->add('pieceComplement', FileType::class, [
                'required' => false,
                'label' => 'Document complémentaire',
            ])
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
                'placeholder' => 'Référence Structure',
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
                'label' => 'Nom du Conseiller H&E',
                'placeholder' => '-- Votre Conseiller H&E --',
                'class' => Structure_conseiller::class
            ])
            ->add('signature', CheckboxType::class, [
                'required' => true,
                'label' => 'Je m\'engage'
            ])
            ->add('isAccompagneStructure', CheckboxType::class, [
                'required' => true,
                'label' => 'J\'accepte'
            ]);

        $builder->get('structure_id')->addModelTransformer(
            $this->transformerFactory->create(Structure_::class)
        );
        $builder->get('conseiller_id')->addModelTransformer(
            $this->transformerFactory->create(Structure_conseiller::class)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Demande_travaux::class,
            'trait_choices' => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'whitelabel_frontofficebundle_demande_travaux';
    }

}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Logement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LogementType extends AbstractType
{
    private array $traitChoices = [];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];

        $builder
            ->add('nom', TextType::class, [
                'required' => true,
                'label' => 'Nom du logement',
                'attr' => [
                    'placeholder' => 'Nom du logement (50 caractères maximum)',
                    'maxlength' => 50,
                ]
            ])
            ->add('isDifferent', CheckboxType::class, [
                'required' => false,
                'label' => 'isDifferent',
            ])
            ->add('codePostal', TextType::class, [
                'required' => true,
                'label' => 'Code Postal',
                'attr' => [
                    'placeholder' => 'Code Postal',
                    'maxlength' => 5,
                    'pattern' => '^[0-9]{4,5}$',
                    'readonly' => true,
                ]
            ])
            ->add('ville', TextType::class, [
                'required' => true,
                'label' => 'Commune',
                'attr' => [
                    'placeholder' => 'Commune',
                    'readonly' => true,
                ]
            ])
            ->add('villeId', HiddenType::class, [
                'required' => true
            ])
            ->add('numeroRue', TextType::class, [
                'required' => true,
                'label' => 'Numéro de rue',
                'attr' => [
                    'placeholder' => 'Numéro de rue',
                    'readonly' => true,
                ]
            ])
            ->add('complementRue', TextType::class, [
                'required' => false,
                'label' => 'Complément numéro de rue',
                'attr' => [
                    'placeholder' => 'Complément numéro de rue',
                    'readonly' => true,
                ]
            ])
            ->add('INSEE', HiddenType::class, [
                'required' => false,
                'label' => 'Code INSEE',
                'attr' => [
                    'placeholder' => 'Code INSEE',
                    'readonly' => true,
                ]
            ])
            ->add('adresse', HiddenType::class, [
                'required' => true,
                'label' => 'Nom de rue',
                'attr' => [
                    'placeholder' => 'Nom de rue',
                    'readonly' => true,
                ]
            ])
            ->add('nomRueNotFound', CheckboxType::class, [
                'required' => false,
                'label' => 'Adresse non trouvée',
            ])
            ->add('complement1', TextType::class, [
                'required' => false,
                'label' => 'Complément 1',
                'attr' => [
                    'placeholder' => 'Complément 1',
                    'readonly' => true,
                ]
            ])
            ->add('complement2', TextType::class, [
                'required' => false,
                'label' => 'Complément 2',
                'attr' => [
                    'placeholder' => 'Complément 2',
                    'readonly' => true,
                ]
            ])
            ->add('situation', ChoiceType::class, [
                'required' => true,
                'label' => 'Votre situation',
                'placeholder' => '',
                'multiple' => false,
                'expanded' => true,
                'empty_data' => null,
                'choices' => [
                    'Propriétaire occupant ou en cours d\'acquisition' => '0 | proprietaire_occupant',
                    'Propriétaire bailleur' => '1 | proprietaire_bailleur',
                    'Locataire' => '2 | locataire'
                ]
            ])
            ->add('typeLogement', ChoiceType::class, [
                'required' => true,
                'label' => 'Type de logement',
                'placeholder' => '',
                'multiple' => false,
                'expanded' => true,
                'empty_data' => null,
                'choices' => [
                    'Maison individuelle' => '0 | maison_individuelle',
                    'Copropriété' => '1 | copropriete'
                ]
            ])
            ->add('typeHabitation', ChoiceType::class, [
                'required' => false,
                'label' => 'Type d\'habitation',
                'placeholder' => false,
                'multiple' => false,
                'expanded' => true,
                'empty_data' => null,
                'choices' => [
                    'Principale' => '0 | principale',
                    'Secondaire' => '1 | secondaire'
                ]
            ])
            ->add('anneeConstruction', ChoiceType::class, [
                'required' => true,
                'label' => 'Type',
                'placeholder' => '-- Choisir une année de construction --',
                'multiple' => false,
                'empty_data' => null,
                'choices' => $this->traitChoices['optionAnneeConstruction']
            ])
            ->add('descriptionProjet', TextareaType::class, [
                'required' => true,
                'label' => 'Description succincte du projet de rénovation',
                'attr' => [
                    'placeholder' => 'Description succincte du projet de rénovation (limité à 245 caractères)',
                    'readonly' => false,
                    'maxlength' => '245'
                ]
            ])
            ->add('valider', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Logement::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_frontofficebundle_logement';
    }
}

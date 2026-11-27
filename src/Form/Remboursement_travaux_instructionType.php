<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_travaux_instruction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class Remboursement_travaux_instructionType extends Remboursement_instructionType
{
    /**
     * Les listes de motifs de non-conformité (D26013).
     *
     * Déclarées ici parce qu'elles ne sont PAS ajoutées avec les autres
     * champs : voir l'écoute de PRE_SET_DATA dans buildForm().
     *
     * @var array<string, array{filtre: string, accesseur: string, invite: string}>
     */
    private const LISTES_DE_MOTIFS = [
        'ficheTravauxReason' => ['filtre' => 'fiche_travaux', 'accesseur' => 'getFicheTravauxReason', 'invite' => '-- Choisir un motif pour la fiche descriptive des travaux --'],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder
            ->add('remboursement_travaux_instruction_conformite', CollectionType::class, [
                'entry_type'    => Remboursement_travaux_instruction_conformiteType::class,
                'label'         => false,
                'allow_add'     => true,
                'allow_delete'  => true,
                'required'      => false,
                'prototype'     => true,
                'by_reference'  => false,
            ])
            ->add('ficheTravaux', FileType::class, [
                'required'  => false,
                'label'     => 'Téléchargement de la fiche descriptive des travaux'
            ])
            ->add('isFicheTravauxConforme', ChoiceType::class, [
                'required'      => true,
                'label'         => 'Conforme ?',
                'placeholder'   => '-- Choisir --',
                'multiple'      => false,
                'expanded'      => false,
                'empty_data'    => null,
                'choices'       => [
                    'Oui'   => '0 | oui',
                    'Non'   => '1 | non',
                ]
            ])
            ->add('ficheTravauxReasonAutre', TextareaType::class, [
                'required'  => false,
                'label'     => 'Autre motif de non-conformité',
                'attr'      => [
                    'placeholder' => 'Autre motif de non-conformité',
                ]
            ])
            ->add('destinataire', ChoiceType::class, [
                'required'      => false,
                'label'         => 'Remboursement à',
                'placeholder'   => '-- Choisir --',
                'multiple'      => false,
                'expanded'      => false,
                'empty_data'    => null,
                'choices'       => [
                    'Bénéficiaire'  => '1 | beneficiaire',
                    'Rénovateur'    => '2 | renovateur',
                ]
            ]);

        // D26013 : les listes ne proposent que les motifs ACTIFS, auxquels
        // s'ajoutent ceux déjà retenus sur ce dossier.
        //
        // Ces champs sont ajoutés ici, et non dans la chaîne ci-dessus, parce
        // qu'ils ont besoin des données du dossier. Un sous-formulaire ne reçoit
        // PAS l'option « data » — seul le formulaire racine la porte, et celui-ci
        // est un enfant. La donnée n'est connue qu'au moment où elle est posée,
        // d'où PRE_SET_DATA, et non buildForm : le constructeur de requête d'un
        // EntityType est figé dès la création du formulaire, bien avant.
        //
        // Sans cela, un motif désactivé après l'instruction disparaîtrait de la
        // liste : sa case ne serait plus cochée, et le motif serait perdu au
        // premier enregistrement suivant, sans la moindre erreur.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $evenement): void {
            $donnees = $evenement->getData();
            $formulaire = $evenement->getForm();

            foreach (self::LISTES_DE_MOTIFS as $champ => $liste) {
                $dejaChoisis = MotifsDejaChoisis::ids($donnees, $liste['accesseur']);

                $formulaire->add($champ, EntityType::class, [
                    'required' => false,
                    'placeholder' => $liste['invite'],
                    'label' => false,
                    'multiple' => true,
                    'expanded' => true,
                    'class' => 'App\Entity\Remboursement_reason',
                    'choice_label' => function ($motif) {
                        return $motif->getSlug();
                    },
                    'choice_value' => 'id',
                    'query_builder' => function ($depot) use ($liste, $dejaChoisis) {
                        return $depot->findByFiltre($liste['filtre'], $dejaChoisis);
                    },
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Remboursement_travaux_instruction::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_remboursement_travaux_instruction';
    }
}

<?php

namespace App\Form;

use App\Entity\Instruction_auditEnergie;
use App\Entity\Instruction_;
use App\Entity\Instruction_reason;
use App\Repository\Instruction_reasonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Instruction_auditEnergieType extends AbstractType
{
    public function __construct() {}

    /**
     * Les listes de motifs de non-conformité (D26013).
     *
     * Déclarées ici parce qu'elles ne sont PAS ajoutées avec les autres
     * champs : voir l'écoute de PRE_SET_DATA dans buildForm().
     *
     * @var array<string, array{filtre: string, accesseur: string, invite: string}>
     */
    private const LISTES_DE_MOTIFS = [
        'JPreason' => ['filtre' => 'JP', 'accesseur' => 'getJPreason', 'invite' => '-- Choisir un motif pour le Justificatif de Propriété --'],
        'KBISreason' => ['filtre' => 'KBIS', 'accesseur' => 'getKBISreason', 'invite' => '-- Choisir un motif pour le KBIS --'],
        'mandatReason' => ['filtre' => 'MANDAT', 'accesseur' => 'getMandatReason', 'invite' => '-- Choisir un motif pour le Mandat --'],
        'AIreason' => ['filtre' => 'AI', 'accesseur' => 'getAIreason', 'invite' => '-- Choisir un motif pour l\'Avis d\'imposition --'],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('JPconformite', ChoiceType::class, [
                'required' => true,
                'label' => 'Justificatif propriété conforme',
                'placeholder' => '-- Choisir une conformité --',
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'choices' => [
                    'Oui' => '0 | oui',
                    'Non' => '1 | non',
                    'Indéterminé' => '2 | indetermine'
                ]
            ])
            ->add('JPtype', ChoiceType::class, [
                'required' => false,
                'label' => 'Type de document',
                'placeholder' => '-- Choisir un type de document --',
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'choices' => Instruction_::$conformiteJPTypeDocument
            ]);


        $builder
            ->add('JPreasonAutre', TextareaType::class, [
                'required' => false,
                'label' => 'Autre motif de non-conformité',
                'attr' => [
                    'placeholder' => 'Autre motif de non-conformité (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
            ->add('KBISconformite', ChoiceType::class, [
                'required' => false,
                'label' => 'KBIS conforme',
                'placeholder' => '-- Choisir une conformité --',
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'choices' => [
                    'Oui' => '0 | oui',
                    'Non' => '1 | non',
                    'Indéterminé' => '2 | indetermine'
                ]
            ]);


        $builder
            ->add('KBISreasonAutre', TextareaType::class, [
                'required' => false,
                'label' => 'Autre motif de non-conformité',
                'attr' => [
                    'placeholder' => 'Autre motif de non-conformité (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
            ->add('mandatConformite', ChoiceType::class, [
                'required' => true,
                'label' => 'Mandat conforme',
                'placeholder' => '-- Choisir une conformité --',
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'choices' => [
                    'Oui' => '0 | oui',
                    'Non' => '1 | non',
                    'Indéterminé' => '2 | indetermine'
                ]
            ])
            ->add('AIconformite', ChoiceType::class, [
                'required' => true,
                'label' => 'Avis imposition conforme',
                'placeholder' => '-- Choisir une conformité --',
                'multiple' => false,
                'expanded' => false,
                'empty_data' => null,
                'choices' => [
                    'Oui' => '0 | oui',
                    'Non' => '1 | non',
                    'Indéterminé' => '2 | indetermine'
                ]
            ]);


        $builder
            ->add('mandatReasonAutre', TextareaType::class, [
                'required' => false,
                'label' => 'Autre motif de non-conformité',
                'attr' => [
                    'placeholder' => 'Autre motif de non-conformité (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
            ->add('AIreasonAutre', TextareaType::class, [
                'required' => false,
                'label' => 'Autre motif de non-conformité',
                'attr' => [
                    'placeholder' => 'Autre motif de non-conformité (limité à 245 caractères)',
                    'maxlength' => '245'
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
                    'class' => Instruction_reason::class,
                    'choice_label' => function ($motif) {
                        return $motif->getSlug();
                    },
                    'choice_value' => 'id',
                    'query_builder' => function (Instruction_reasonRepository $depot) use ($liste, $dejaChoisis) {
                        return $depot->findByFiltre($liste['filtre'], $dejaChoisis);
                    },
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Instruction_auditEnergie::class
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_instruction_auditenergie';
    }
}

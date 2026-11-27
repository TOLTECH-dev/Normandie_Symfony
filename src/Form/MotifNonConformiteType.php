<?php

declare(strict_types=1);

namespace App\Form;

use App\Motif\FamilleDeMotifs;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création et modification d'un motif de non-conformité (D26013).
 *
 * Le formulaire n'est lié à aucune entité : les deux familles de motifs sont
 * portées par deux classes distinctes, de forme identique mais sans ancêtre
 * commun. Le contrôleur reporte les valeurs sur l'entité voulue.
 *
 * LE DOCUMENT NE SE CHOISIT PAS DANS CE FORMULAIRE. Il vient de l'onglet
 * d'où l'on arrive, et le champ est désactivé — au niveau du formulaire, de
 * sorte que Symfony ignore toute valeur soumise et conserve l'original. Deux
 * raisons :
 *
 *  - en modification, déplacer un motif détacherait silencieusement les
 *    dossiers qui le citent. Les colonnes `*_reason` conservent une copie
 *    sérialisée du motif, mais la case à cocher est retrouvée par son
 *    identifiant DANS la liste du document. Changé de document, le motif sort
 *    de cette liste, sa case n'est plus cochée, et la non-conformité disparaît
 *    du dossier au premier enregistrement suivant ;
 *
 *  - les positions proposées dépendent du document. Les laisser choisir tous
 *    les deux obligerait à recalculer la liste des places libres à chaque
 *    changement, donc à un aller-retour avec le serveur, pour un gain nul : la
 *    création part toujours de l'onglet d'un document.
 *
 * Pour corriger un motif rattaché au mauvais document, il faut le désactiver et
 * en créer un nouveau au bon endroit : les dossiers déjà instruits gardent
 * alors le leur.
 */
class MotifNonConformiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        [$famille, $filtre] = FamilleDeMotifs::separerLeChoix($options['document']);

        $positions = [];

        foreach ($options['positions'] as $position) {
            $positions[(string) $position] = $position;
        }

        $builder
            ->add('document', ChoiceType::class, [
                'required' => true,
                'disabled' => true,
                'label' => 'Document concerné',
                'choices' => [FamilleDeMotifs::libelleDuDocument($famille, $filtre) => $options['document']],
            ])
            ->add('libelle', TextType::class, [
                'required' => true,
                'label' => 'Libellé du motif',
                'attr' => [
                    'maxlength' => (string) FamilleDeMotifs::LONGUEUR_MAX_LIBELLE,
                    'placeholder' => 'Par exemple : Le document déposé est illisible',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le libellé est obligatoire.'),
                    new Assert\Length(
                        max: FamilleDeMotifs::LONGUEUR_MAX_LIBELLE,
                        maxMessage: 'Le libellé ne doit pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])
            // Une liste, et non une saisie libre : seules les places libres y
            // figurent, ce qui rend le doublon de position impossible à saisir
            // plutôt que simplement refusé après coup.
            ->add('position', ChoiceType::class, [
                'required' => true,
                'label' => 'Position dans la liste',
                'placeholder' => '-- Choisir une position --',
                'choices' => $positions,
                'constraints' => [new Assert\NotNull(message: 'La position est obligatoire.')],
            ])
            ->add('actif', CheckboxType::class, [
                'required' => false,
                'label' => 'Motif actif',
            ])
            ->add('valider', SubmitType::class, ['label' => 'Enregistrer'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);

        // Le document du motif, au format « famille|code ». Fixé par le
        // contrôleur d'après l'onglet d'origine, jamais choisi ici.
        $resolver->setRequired('document');
        $resolver->setAllowedTypes('document', 'string');

        // Les positions encore libres dans la liste de ce document.
        $resolver->setRequired('positions');
        $resolver->setAllowedTypes('positions', 'array');

        $resolver->setNormalizer('positions', static function ($resolveur, array $positions): array {
            return array_values(array_map(static fn ($position): int => (int) $position, $positions));
        });
    }

    public function getBlockPrefix(): string
    {
        return 'motif_non_conformite';
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_instruction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class Remboursement_instructionType extends AbstractType
{
    private $traitChoices;

    /**
     * Les listes de motifs de non-conformité (D26013).
     *
     * Déclarées ici parce qu'elles ne sont PAS ajoutées avec les autres
     * champs : voir l'écoute de PRE_SET_DATA dans buildForm().
     *
     * @var array<string, array{filtre: string, accesseur: string, invite: string}>
     */
    private const LISTES_DE_MOTIFS = [
        'chequeReason' => ['filtre' => 'cheque', 'accesseur' => 'getChequeReason', 'invite' => '-- Choisir un motif pour le chèque --'],
        'factureReason' => ['filtre' => 'facture', 'accesseur' => 'getFactureReason', 'invite' => '-- Choisir un motif pour la facture --'],
        'ribReason' => ['filtre' => 'rib', 'accesseur' => 'getRibReason', 'invite' => '-- Choisir un motif pour le RIB --'],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->traitChoices = $options['trait_choices'];
        $builder
            ->add('dateCheque', DateType::class, [
                'required'  => false,
                'label'     => 'Date de réception du chèque',
                'widget'    => 'single_text',
                'format'    => 'dd/MM/yyyy',
                'html5'     => false,
                'attr'      => [
                    'placeholder'   => 'DD/MM/YYYY',
                ]
            ])
            ->add('numeroRemiseRSI',            TextType::class,    [
                'required'  => false,
                'label'     => 'N° remise RSI',
                'attr'      => [
                    'placeholder'   => 'Entrez le N° remise RSI',
                ]
            ])
            ->add('rectoCheque',               FileType::class,    [
                'required'  => false,
                'label'     => 'Téléchargement du recto du chèque',
                'attr'      => [
                    'class' => 'custom-file'
                ]
            ])
            ->add('versoCheque',                FileType::class,    [
                'required'  => false,
                'label'     => 'Téléchargement du verso du chèque',
                'attr'      => [
                    'class' => 'custom-file'
                ]
            ])
            ->add('isChequeConforme',           ChoiceType::class,  [
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
            ->add('chequeReasonAutre',          TextareaType::class,[
                'required'  => false,
                'label'     => 'Autre motif de non-conformité',
                'attr'      => [
                    'placeholder' => 'Autre motif de non-conformité',
                ]
            ])
            ->add('montantFacture',             TextType::class,    [
                'required'  =>  false,
                'label'     =>  'Montant de la facture',
                'attr'      =>  [
                    'placeholder'   => 'Entrez le montant de la facture',
                    'pattern'       => '^\d+(?:\.\d+)?$'
                ]
            ])
            ->add('isFactureConforme',          ChoiceType::class,  [
                'required'      => true,
                'label'         => 'Conforme',
                'placeholder'   => '-- Choisir --',
                'multiple'      => false,
                'expanded'      => false,
                'empty_data'    => null,
                'choices'       => [
                    'Oui'   => '0 | oui',
                    'Non'   => '1 | non',
                ]
            ])
            ->add('factureReasonAutre',         TextareaType::class,[
                'required'  => false,
                'label'     => 'Autre motif de non-conformité',
                'attr'      => [
                    'placeholder' => 'Autre motif de non-conformité',
                ]
            ])
            ->add('destinataire',               ChoiceType::class,  [
                'required'      => false,
                'label'         => 'Remboursement à',
                'placeholder'   => '-- Choisir --',
                'multiple'      => false,
                'expanded'      => false,
                'empty_data'    => null,
                'choices'       => [
                    'Auditeur'      => '0 | auditeur',
                    'Bénéficiaire'  => '1 | beneficiaire',
                ]
            ])
            ->add('rib',                        FileType::class,    [
                'required'  => false,
                'label'     => 'Téléchargement du RIB',
                'attr'      => [
                    'class' => 'custom-file'
                ]
            ])
            ->add('iban',                       TextType::class,    [
                'required'  => false,
                'label'     => 'IBAN',
                'attr'      => [
                    'placeholder'   => 'Entrez l\'IBAN',
                    'readonly'      => $this->traitChoices['optionDepot'][1]
                ]
            ])
            ->add('bic',                        TextType::class,    [
                'required'  => false,
                'label'     => 'BIC',
                'attr'      => [
                    'placeholder'   => 'Entrez le BIC',
                    'readonly'      => $this->traitChoices['optionDepot'][1]
                ]
            ])
            ->add('domiciliationBancaire',      TextType::class,    [
                'required'  => false,
                'label'     => 'Domiciliation bancaire',
                'attr'      => [
                    'placeholder'   => 'Entrez la domiciliation bancaire',
                    'readonly'      => $this->traitChoices['optionDepot'][1]
                ]
            ])
            ->add('isRibConforme',              ChoiceType::class,  [
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
            ->add('ribReasonAutre',             TextareaType::class,[
                'required'  => false,
                'label'     => 'Autre motif de non-conformité',
                'attr'      => [
                    'placeholder' => 'Autre motif de non-conformité',
                ]
            ])
        ;
        // Ajout du DataTransformer pour dateCheque (format français)
//        $builder->get('dateCheque')->addModelTransformer(new FrenchDateStringToDateTimeTransformer());

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
            'data_class'    => Remboursement_instruction::class,
            'trait_choices' => null
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_Remboursement_instruction';
    }
}

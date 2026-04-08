<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Remboursement_instruction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
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
            ->add('chequeReason',               EntityType::class,  [
                'required'      => false,
                'placeholder'   => '-- Choisir un motif pour le chèque --',
                'label'         => false,
                'multiple'      => true,
                'expanded'      => true,
                'class'         => 'App\Entity\Remboursement_reason',
                'choice_label'  => function ($obj) {
                    return $obj->getSlug();
                },
                'choice_value'  => 'id',
                'query_builder' => function($r) {
                    return $r->findByFiltre('cheque');
                }
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
            ->add('factureReason',              EntityType::class,  [
                'required'      => false,
                'placeholder'   => '-- Choisir un motif pour la facture --',
                'label'         => false,
                'multiple'      => true,
                'expanded'      => true,
                'class'         => 'App\Entity\Remboursement_reason',
                'choice_label'  => function ($obj) {
                        return $obj->getSlug();
                },
                'choice_value'  => 'id',
                'query_builder' => function($r) {
                    return $r->findByFiltre('facture');
                }
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
            ->add('ribReason',                  EntityType::class,  [
                'required'      => false,
                'placeholder'   => '-- Choisir un motif pour le RIB --',
                'label'         => false,
                'multiple'      => true,
                'expanded'      => true,
                'class'         => 'App\Entity\Remboursement_reason',
                'choice_label'  => function ($obj) {
                        return $obj->getSlug();
                },
                'choice_value'  => 'id',
                'query_builder' => function($r) {
                    return $r->findByFiltre('rib');
                }
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

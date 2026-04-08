<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Banque_;
use App\Entity\Demande_travaux_devis;
use App\Entity\Partenaire_;
use App\Entity\PlanFinancementType;
use App\Form\DataTransformer\EntityTransformerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContext;
use App\Repository\Banque_Repository;
use App\Repository\Partenaire_Repository;
use App\Repository\PlanFinancementTypeRepository;
use App\Service\DemandeTravauxDevisService;


/**
 * Class Demande_travaux_devisType
 * @package App\Form
 */
class Demande_travaux_devisType extends AbstractType
{
    protected EntityManagerInterface $EM;
    protected Banque_Repository $repo_banque;
    protected PlanFinancementTypeRepository $planFinancementTypeRepository;
    protected bool $isDemandeTravauxAudit;
    protected EntityTransformerFactory $transformerFactory;

    /**
     * Demande_travaux_devisType constructor.
     * @param EntityManagerInterface $EM
     * @param EntityTransformerFactory $transformerFactory
     */
    public function __construct(EntityManagerInterface $EM, EntityTransformerFactory $transformerFactory)
    {
        $this->EM = $EM;
        $this->transformerFactory = $transformerFactory;
        $this->repo_banque = $this->EM->getRepository(Banque_::class);
        $this->planFinancementTypeRepository = $this->EM->getRepository(PlanFinancementType::class);
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->var = $options['trait_choices'];
        $this->isDemandeTravauxAudit = $this->var['isDemandeTravauxAudit'];

        $isAuditFileRequired = empty($this->isDemandeTravauxAudit) && empty($this->var['auditAlt']);

        $arrayChoicesTypeDemandeNiveau = Demande_travaux_devis::$arrayDemandeTypeNiveauForForm;

        $niveauChoicePlaceholder = '-- Choisir un niveau d\'aide --';
        if (true === $this->var['isLastDemandeTravauxRembourseSortieDePassoire']) {
            // Cas 1ere demande travaux remboursée est sortie de passoire => On laisser seulement item "renovation globale BBC"
            foreach ($arrayChoicesTypeDemandeNiveau as $keyNiveau => $valueNiveau) {
                if (Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_VALUE != $valueNiveau) {
                    unset($arrayChoicesTypeDemandeNiveau[$keyNiveau]);
                }
            }
            // On retire le select vide pour imposer seulement le niveau Renovation globale BBC
            $niveauChoicePlaceholder = null;
        } else {
            if (!$this->isDemandeTravauxAudit) {
                unset($arrayChoicesTypeDemandeNiveau[Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_ETAPE1_BBC_RENOVATEUR_KEY]);
                unset($arrayChoicesTypeDemandeNiveau[Demande_travaux_devis::DEMANDE_TYPE_NIVEAU_RENOVATION_GLOBALE_BBC_KEY]);
            }
            foreach (Demande_travaux_devis::$arrayDemandeTypeNiveauForFormToHide as $keyNiveau => $valueNiveau) {
                if (empty($this->var['niveau']) || $this->var['niveau'] != $valueNiveau) {
                    unset($arrayChoicesTypeDemandeNiveau[$keyNiveau]);
                }
            }
        }

        $isRequiredNiveauFieldOption = (true === $this->var['isRequiredNiveauFieldOption'] && !empty($arrayChoicesTypeDemandeNiveau));

        $builder
            ->add('auditeur_id', HiddenType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'placeholder' => 'Auditeur',
                ]
            ])
            ->add('audit', FileType::class, [
                'required' => $isAuditFileRequired,
                'label' => 'Audit Région Normandie ou Audit énergétique national',
                'attr' => [
                    'class' => 'custom-file'
                ]
            ])
            ->add('demande_travaux_devis_upload', CollectionType::class, [
                'entry_type' => Demande_travaux_devis_uploadType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false,
                'entry_options' => [
                    'var' => $this->var
                ]
            ])
            ->add('totalDevis', TextType::class, [
                'required' => false,
                'label' => 'Total',
                'attr' => [
                    'placeholder' => 'Total',
                    'readonly' => true,
                ]
            ])
            ->add('isBonificationAide', CheckboxType::class, [
                'required' => false,
                'label' => 'Je souhaite obtenir la bonification de l\'aide'
            ])
            ->add('niveau', ChoiceType::class, [
                'required' => $isRequiredNiveauFieldOption,
                'label' => 'Niveau d\'aide',
                'placeholder' => $niveauChoicePlaceholder,
                //'data'          => '0 | niveau1',
                'empty_data' => null,
                'choices' => $arrayChoicesTypeDemandeNiveau
            ])
            ->add('renovateur_id', EntityType::class, [
                'required' => false,
                'placeholder' => 'Choisir un rénovateur',
                'label' => false,
                'class' => Partenaire_::class,
                'choice_label' => function ($obj) {
                    return $obj->getPartenaireIdentification()->getRaisonSociale();
                },
                'choice_value' => 'id',
                'query_builder' => function (Partenaire_Repository $r) {
                    return $r->findByThematiqueEnabled('%renovateur%', '1');
                }
            ])
            ->add('aideAnah', IntegerType::class, [
                'required' => false,
                'label' => 'Aide de l\'Anah',
                'attr' => [
                    'placeholder' => 'Aide de l\'Anah',
                ]
            ])
//            ->add('aideHabiterMieux',               IntegerType::class,     [
//                                                                                'required'      => false,
//                                                                                'label'         => 'MaPrimeRénov’ Sérénité',
//                                                                                'attr'          => [
//                                                                                    'placeholder' => 'MaPrimeRénov’ Sérénité',
//                                                                                ]
//                                                                            ])
//            ->add('typeMaPrimeRenovSereniteNom',     EntityType::class,      [
//                                                                                'required'      => false,
//                                                                                'placeholder'   => '-- Choisir un type --',
//                                                                                'label'         => false,
//                                                                                'class'         => PlanFinancementType::class,
//                                                                                'choice_label'  => function ($obj) {
//                                                                                    return   $obj->getNom();
//                                                                                },
//                                                                                'choice_value'  => 'nom',
//                                                                                'query_builder' => function(PlanFinancementTypeRepository $r) {
//                                                                                    return $r->findCustomQbByCategoryId(PlanFinancementType::CATEGORY_MAPRIMERENOV_SERENITE_ID);
//                                                                                },
//                                                                                'data' => $this->planFinancementTypeRepository->findOneBy(['nom' => $this->var['typeMaPrimeRenovSereniteNom']])
//                                                                            ])
            ->add('creditImpot', IntegerType::class, [
                'required' => false,
                'label' => 'MaPrimeRénov\'',
                'attr' => [
                    'placeholder' => 'MaPrimeRénov\'',
                ]
            ])
            ->add('typeMaPrimeRenovNom', EntityType::class, [
                'required' => false,
                'placeholder' => '-- Choisir un type --',
                'label' => false,
                'class' => PlanFinancementType::class,
                'choice_label' => function ($obj) {
                    return $obj->getNom();
                },
                'choice_value' => 'nom',
                'query_builder' => function (PlanFinancementTypeRepository $r) {
                    return $r->findCustomQbByCategoryId(PlanFinancementType::CATEGORY_MAPRIMERENOV_ID);
                },
                'data' => $this->planFinancementTypeRepository->findOneBy(['nom' => $this->var['typeMaPrimeRenovNom']])
            ])
            ->add('aideRegion', IntegerType::class, [
                'required' => false,
                'label' => 'Aide Région',
                'attr' => [
                    'placeholder' => 'Aide Région',
                    'readonly' => true,
                ]
            ])
            ->add('CEE', IntegerType::class, [
                'required' => false,
                'label' => 'CEE',
                'attr' => [
                    'placeholder' => 'CEE',
                ]
            ])
            ->add('EcoPTZ', IntegerType::class, [
                'required' => false,
                'label' => 'EcoPTZ',
                'attr' => [
                    'placeholder' => 'EcoPTZ',
                ]
            ])
            ->add('EcoPTZBanque', EntityType::class, [
                'required' => false,
                'placeholder' => '-- Choisir une banque --',
                'label' => false,
                'class' => Banque_::class,
                'choice_label' => function ($obj) {
                    return $obj->getNom();
                },
                'choice_value' => 'id',
                'query_builder' => function (Banque_Repository $r) {
                    return $r->findByEnabled('1');
                },
                'data' => $this->repo_banque->findOneBy(['id' => $this->var['ecoPTZBanque']])
            ])
            ->add('fondsPropres', IntegerType::class, [
                'required' => false,
                'label' => 'Fonds propres',
                'attr' => [
                    'placeholder' => 'Fonds propres',
                ]
            ])
            ->add('aideDepartement', IntegerType::class, [
                'required' => false,
                'label' => 'Aide Département',
                'attr' => [
                    'placeholder' => 'Aide Département',
                ]
            ])
            ->add('aideDepartementOrigine', TextType::class, [
                'required' => false,
                'label' => 'Origine aide Département',
                'attr' => [
                    'placeholder' => 'Origine aide Département',
                ]
            ])
            ->add('aideIntercommunalite', IntegerType::class, [
                'required' => false,
                'label' => 'Aide Intercommunalité',
                'attr' => [
                    'placeholder' => 'Aide Intercommunalité',
                ]
            ])
            ->add('aideIntercommunaliteOrigine', TextType::class, [
                'required' => false,
                'label' => 'Origine aide Intercommunalité',
                'attr' => [
                    'placeholder' => 'Origine aide Intercommunalité',
                ]
            ])
            ->add('autreAide', IntegerType::class, [
                'required' => false,
                'label' => 'Autres aides',
                'attr' => [
                    'placeholder' => 'Autres aides',
                ]
            ])
            ->add('autreAideOrigine', TextType::class, [
                'required' => false,
                'label' => 'Origine autres aides',
                'attr' => [
                    'placeholder' => 'Origine autres aides',
                ]
            ])
            ->add('autrePret', IntegerType::class, [
                'required' => false,
                'label' => 'Autre prêt',
                'attr' => [
                    'placeholder' => 'Autre prêt',
                ]
            ])
            ->add('autrePretBanque', EntityType::class, [
                'required' => false,
                'placeholder' => '-- Choisir une banque --',
                'label' => false,
                'class' => Banque_::class,
                'choice_label' => function ($obj) {
                    return $obj->getNom();
                },
                'choice_value' => 'id',
                'query_builder' => function (Banque_Repository $r) {
                    return $r->findByEnabled('1');
                },
                'data' => $this->repo_banque->findOneBy(['id' => $this->var['autrePretBanque']])
            ])
            ->add('totalPlan', TextType::class, [
                'required' => false,
                'label' => 'Total',
                'attr' => [
                    'placeholder' => 'Total',
                    'readonly' => true,
                ]
            ])
            ->add('acteEngagement', FileType::class, [
                'required' => false,
                'label' => 'Votre acte d\'engagement',
                'attr' => [
                    'class' => 'custom-file'
                ]
            ])
            ->add('instructionDossierConforme', ChoiceType::class, [
                'required' => true,
                'label' => 'Dossier conforme',
                'placeholder' => '-- Choisir une valeur --',
                //'data'          => '0 | oui',
                'empty_data' => null,
                'choices' => [
                    'OUI' => '0 | oui',
                    'EN COURS DE VALIDATION' => '1 | en_cours',
                ]
            ])
            ->add('timestamp', HiddenType::class, [
                'mapped' => false,
                'attr' => [
                    'value' => time()
                ],
            ])
            ->add('isBanqueAccess', HiddenType::class, [
                'required' => false,
                'label' => false,
                'data' => $this->var['isBanqueAccess']
            ])
            ->add('valider', SubmitType::class);

        // Add data transformer for renovateur_id (stores int, displays Partenaire_ entity)
        $builder->get('renovateur_id')->addModelTransformer(
            $this->transformerFactory->create(Partenaire_::class)
        );

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $data = $event->getData();
            $form = $event->getForm();

            // Données minimales requises
            if (!isset($data['niveau'])) {
                return;
            }

            // Calcul serveur officiel
            $montantAttendu = DemandeTravauxDevisService::findMontantRegionByNiveauAndBonification(
                $data['niveau'],
                !empty($data['isBonificationAide'])
            );

            // Valeur envoyée par le client
            if (isset($data['aideRegion'])) {
                $montantSoumis = (int) $data['aideRegion'];

                // Tentative de modification détectée
                if ($montantSoumis !== $montantAttendu) {
                    // ERREUR FORMULAIRE (bloque isValid)
                    $form->addError(
                        new FormError(
                            'Incohérence détectée dans le calcul de l’aide régionale.'
                        )
                    );

                    // On ne modifie pas $data
                    return;
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();

            // Si le formulaire est déjà invalide, on ne continue pas
            if (!$form->isValid()) {
                return;
            }

            /** @var Demande_travaux_devis $devis */
            $devis = $form->getData();

            $totalCalcule = 0;
            foreach ($devis->getDemandeTravauxDevisUpload() as $ligneDevis) {
                if (!empty($ligneDevis->getMontant())) {
                    $totalCalcule += (int) $ligneDevis->getMontant();
                }
            }

            $totalSoumis = (int) $devis->getTotalDevis();

            // Incohérence détectée : total soumis != total calcule (somme des montants saisis)
            if ($totalSoumis !== $totalCalcule) {
                $form->addError(
                    new FormError(
                        'Le total des devis ne correspond pas à la somme des montants saisis.'
                    )
                );
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Demande_travaux_devis::class,
            'trait_choices' => null,
            'constraints' => [
                new Callback(['callback' => function (Demande_travaux_devis $data, ExecutionContext $context) {

                    // Si le champ doit etre obligatoire => on controle si un fichier existe deja (en edition)
                    // ou que un nouveau fichier vient d'etre uploadé (cas creation)
                    if (empty($this->isDemandeTravauxAudit) && (empty($data->getAuditAlt()) && empty($data->getAudit()))) {
                        $context
                            ->buildViolation('Le document dans l\'Audit doit être selectionné')
                            ->atPath('audit')
                            ->addViolation();
                    }

                    // Controle des Devis
                    if ($data->getDemandeTravauxDevisUpload()->count() < 1) {
                        $context
                            ->buildViolation('Au moins un devis doît être ajouté')
                            ->atPath('demande_travaux_devis_upload')
                            ->addViolation();
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
        return 'whitelabel_frontofficebundle_demande_travaux_devis';
    }
}

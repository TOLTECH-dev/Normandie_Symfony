<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Demande_travaux_devis;
use App\Entity\Partenaire_;
use App\Entity\Structure_;
use App\Entity\Structure_conseiller;
use App\Form\DataTransformer\EntityTransformerFactory;
use App\Form\RatingType;
use App\Repository\Partenaire_Repository;
use App\Repository\Structure_Repository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class AdminFormService
{
    public function __construct(
        private FormFactoryInterface  $formFactory,
        private Partenaire_Repository $partenaireRepository,
        private Structure_Repository  $structureRepository,
        private EntityManagerInterface $entityManager,
        private EntityTransformerFactory $transformerFactory,
    ) {}



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @return FormInterface
     */
    public function denyDemandeType(): FormInterface
    {
        return $this->formFactory->createBuilder()
            ->add('motifRefus', TextareaType::class, [
                'required' => true,
                'label' => 'Motif de refus',
                'attr' => [
                    'placeholder' => 'Motif de refus (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
            ->add('valider', SubmitType::class)
            ->getForm();
    }

    /**
     * @param array $option
     * @param string $data
     * @return FormInterface
     */
    public function assignDateCPType(array $option = [], string $data = ''): FormInterface
    {
        return $this->formFactory->createBuilder()
            ->add('dateCP', ChoiceType::class, [
                'required' => true,
                'label' => false,
                'placeholder' => 'Choisir une date',
                'empty_data' => null,
                'choices' => $option,
                'data' => $data
            ])
            ->add('valider', SubmitType::class)
            ->getForm();
    }

    /**
     * @return FormInterface
     */
    public function assignStructureType(): FormInterface
    {
        return $this->formFactory->createBuilder()
            ->add('structure_rattachement_id', EntityType::class, [
                'required' => false,
                'placeholder' => '-- Choisir une structure --',
                'label' => false,
                'class' => Structure_::class,
                'choice_label' => function ($obj) {
                    return $obj->getStructureIdentification()->getNom();
                },
                'choice_value' => 'id',
                'query_builder' => function (Structure_Repository $r) {
                    return $r->findByStructureEnabled('1');
                },
            ])
            ->add('conseiller_rattachement_id', HiddenType::class, [
                'required' => false,
            ])
            ->add('beneficiaire_id', HiddenType::class, [
                'required' => false,
            ])
            ->add('nbPersFoyer', HiddenType::class, [
                'required' => false,
            ])
            ->add('revenuFiscalRef', HiddenType::class, [
                'required' => false,
            ])
            ->add('INSEE', HiddenType::class, [
                'required' => false,
            ])
            ->add('valider', SubmitType::class)
            ->getForm();
    }

    /**
     * @param array $option
     * @param string $data
     * @return FormInterface
     */
    public function assignDateRMHType(array $option = [], string $data = ''): FormInterface
    {
        return $this->formFactory->createBuilder()
            ->add('dateRMH', ChoiceType::class, [
                'required' => true,
                'label' => false,
                'placeholder' => 'Choisir une date',
                'empty_data' => null,
                'choices' => $option,
                'data' => $data
            ])
            ->add('rating', RatingType::class, [
                'label' => false
            ])
            ->add('valider', SubmitType::class)
            ->getForm();
    }

    /**
     * @return FormInterface
     */
    public function cancelDateRMHType(): FormInterface
    {
        return $this->formFactory->createBuilder()
            ->add('dateRMH_id', HiddenType::class, [
                'required' => true,
            ])
            ->add('valider', SubmitType::class)
            ->getForm();
    }

    /**
     * @return FormInterface
     */
    public function denyRemboursementType(): FormInterface
    {
        $form = $this->formFactory->createBuilder()
            ->add('motifRefus', TextareaType::class, [
                'required' => true,
                'label' => 'Motif de refus',
                'attr' => [
                    'placeholder' => 'Motif de refus (limité à 245 caractères)',
                    'maxlength' => '245'
                ]
            ])
            ->add('valider', SubmitType::class)
            ->getForm();

        return $form;
    }

    /**
     * @param bool $showAuditeur
     * @param bool $showRenovateur
     * @param int|null $auditeurId
     * @param int|null $renovateurId
     * @param string|null $dataBeneficiaireEmail
     * @param int|null $structureId
     * @return FormInterface
     */
    public function assignContactsType(
        bool    $showAuditeur = false,
        bool    $showRenovateur = false,
        ?int    $auditeurId = null,
        ?int    $renovateurId = null,
        ?string $dataBeneficiaireEmail = null,
        ?int $structureId = null
    ): FormInterface {
        $objectAuditeur = ($auditeurId) ? $this->partenaireRepository->find($auditeurId) : null;
        $objectRenovateur = ($renovateurId) ? $this->partenaireRepository->find($renovateurId) : null;
        $objectStructure = ($structureId) ? $this->structureRepository->find($structureId) : null;

        return $this->formFactory->createNamedBuilder('form_assign_contacts')
            ->add('beneficiaireEmail', EmailType::class, [
                'required' => true,
                'label' => 'Email du Bénéficiaire',
                'attr' => [
                    'placeholder' => 'Entrez l\'email du Bénéficiaire',
                ],
                'data' => $dataBeneficiaireEmail
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
                },
                'data' => $objectStructure
            ])
            ->add('conseiller_id', EntityType::class, [
                'required' => true,
                'label' => 'Nom du Conseiller H&E',
                'placeholder' => '-- Votre Conseiller H&E --',
                'class' => Structure_conseiller::class
            ])
            ->add('auditeur_id', EntityType::class, [
                'required' => $showAuditeur,
                'placeholder' => '-- Choisir un auditeur --',
                'label' => false,
                'class' => Partenaire_::class,
                'choice_label' => function ($obj) {
                    return $obj->getPartenaireIdentification()->getRaisonSociale();
                },
                'choice_value' => 'id',
                'query_builder' => function (Partenaire_Repository $r) {
                    return $r->findByThematiqueEnabled('%auditeur%', '1');
                },
                'data' => $objectAuditeur
            ])
            ->add('renovateur_id', EntityType::class, [
                'required' => $showRenovateur,
                'placeholder' => '-- Choisir un rénovateur --',
                'label' => false,
                'class' => Partenaire_::class,
                'choice_label' => function ($obj) {
                    return $obj->getPartenaireIdentification()->getRaisonSociale();
                },
                'choice_value' => 'id',
                'query_builder' => function (Partenaire_Repository $r) {
                    return $r->findByThematiqueEnabled('%renovateur%', '1');
                },
                'data' => $objectRenovateur
            ])
            ->add('valider', SubmitType::class)
            ->getForm();
    }

    /**
     * @param string|null $devisNiveau
     * @param int|null $renovateurId
     * @return FormInterface
     */
    public function travauxDevisUpdateNiveauAideType(?string $devisNiveau = null, ?int $renovateurId = null): FormInterface
    {
        $builder = $this->formFactory->createNamedBuilder('form_travaux_devis_update_niveau_aide')
            ->add('niveau', ChoiceType::class, [
                'required' => true,
                'label' => 'Niveau d\'aide',
                'placeholder' => '-- Choisir un niveau d\'aide --',
                'empty_data' => null,
                'choices' => Demande_travaux_devis::$arrayDemandeTypeNiveauForForm,
                'data' => $devisNiveau

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
            ->add('valider', SubmitType::class);

        $builder->get('renovateur_id')->addModelTransformer($this->transformerFactory->create(Partenaire_::class));

        return $builder->getForm();
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/
}

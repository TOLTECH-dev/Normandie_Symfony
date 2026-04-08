<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Newsletter;

class NewsletterType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $traitChoices = $options['trait_choices'] ?? [];

        $builder
            ->add('file', FileType::class, [
                'required'  => false,
                'label'     => 'Téléchargement de la newsletter (format HTML)',
            ])
            ->add('subject', TextType::class, [
                'required'  => true,
                'label'     => 'Saisir l\'objet de l\'email',
                'attr'      => [
                    'placeholder'   => 'Objet de l\'email',
                ]
            ])
            ->add('email', TextareaType::class, [
                'required'  => false,
                'label'     => 'Saisir le texte de l\'email',
                'attr'      => [
                    'placeholder'   => 'Texte de l\'email',
                ]
            ])
            ->add('isSentToClient', CheckboxType::class, [
                'required'  => false,
                'label'     => 'Région'
            ])
            ->add('isSentToAuditeur', CheckboxType::class, [
                'required'  => false,
                'label'     => 'Auditeurs'
            ])
            ->add('isSentToRenovateur', CheckboxType::class, [
                'required'  => false,
                'label'     => 'Rénovateurs'
            ])
            ->add('isSentToConseiller', CheckboxType::class, [
                'required'  => false,
                'label'     => 'Conseiller H&E'
            ])
            ->add('isSentToEPCI', CheckboxType::class, [
                'required'  => false,
                'label'     => 'EPCI'
            ])
            ->add('isSentToBeneficiaire', CheckboxType::class, [
                'required'  => false,
                'label'     => 'Bénéficiaires'
            ])
            ->add('isSentToAdministrateur', CheckboxType::class, [
                'required'  => false,
                'label'     => 'Administrateurs'
            ])
            ->add('isSentToInstructeur', CheckboxType::class, [
                'required'  => false,
                'label'     => 'Instructeurs UP'
            ])
            ->add('isSentToTechnique', CheckboxType::class, [
                'required'  => false,
                'label'     => 'Technique'
            ])
            ->add('partenaireType', ChoiceType::class, [
                'required'  => false,
                'label'     => false,
                'multiple'  => true,
                'expanded'  => true,
                'choices'   => $traitChoices['optionType'] ?? []
            ])
            ->add('envoyer', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Newsletter::class,
            'trait_choices' => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_newsletter';
    }
}

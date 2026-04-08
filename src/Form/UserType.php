<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use App\Entity\User;

class UserType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $traitChoices = $options['trait_choices'];
        if ($traitChoices) {
            if (in_array('ROLE_ADMIN', $traitChoices[0], true)) {
                $var = [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Automate'       => 'ROLE_AUTOMATE',
                    'Client'         => 'ROLE_CLIENT',
                    'Instructeur'    => 'ROLE_INSTRUCTEUR',
                    'Instructeur UP' => 'ROLE_INSTRUCTEUR_UP',
                    'Technique'      => 'ROLE_TECHNIQUE',
                ];
            } else {
                $var = [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Auditeur'       => 'ROLE_AUDITEUR',
                    'Automate'       => 'ROLE_AUTOMATE',
                    'Bénéficiaire'   => 'ROLE_MEMBER',
                    'Client'         => 'ROLE_CLIENT',
                    'Conseiller'     => 'ROLE_CONSEILLER',
                    'EPCI'           => 'ROLE_EPCI',
                    'Instructeur'    => 'ROLE_INSTRUCTEUR',
                    'Instructeur UP' => 'ROLE_INSTRUCTEUR_UP',
                    'Rénovateur'     => 'ROLE_RENOVATEUR',
                    'Technique'      => 'ROLE_TECHNIQUE',
                ];
            }
        } else {
            $var = [];
            $traitChoices[1][0] = '';
        }

        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'translation_domain' => 'FOSUserBundle',
                'required' => true,
            ])
            ->add('username', TextType::class, [
                'label' => 'form.username',
                'translation_domain' => 'FOSUserBundle',
                'required' => true,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'options' => ['translation_domain' => 'FOSUserBundle'],
                'first_options'  => [
                    'label' => 'Mot de passe'
                ],
                'second_options' => [
                    'label' => 'Confirmation'
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'required' => true,
            ])
            ->add('firstname', TextType::class, [
                'required'  => true,
                'label'     => 'Prénom',
                'attr'      => [ 'placeholder' => 'Prénom' ],
            ])
            ->add('lastname', TextType::class, [
                'required'  => true,
                'label'     => 'Nom',
                'attr'      => [ 'placeholder' => 'Nom' ],
            ])
            ->add('roles', ChoiceType::class, [
                'required'      => true,
                'multiple'      => false,
                'expanded'      => false,
                'placeholder'   => 'Choisir un profil',
                'choices'       => $var,
                'data'          => $traitChoices[1][0] ?? null,
            ])
            ->add('valider', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => User::class,
            'trait_choices' => null,
//            'csrf_token_id' => 'registration',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_mainbundle_user';
    }

}

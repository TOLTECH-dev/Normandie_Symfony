<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Orientation_structureSuperieur;
use App\Entity\Structure_;
use App\Repository\Structure_Repository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class Orientation_structureSuperieurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $traitChoices = $options['trait_choices'] ?? null;
        $builder
            ->add('structure', EntityType::class, [
                'required'      => true,
                'placeholder'   => '-- Choisir une structure --',
                'label'         => 'Structure H&E',
                'attr'          => [
                    'class'    => 'form-control',
                    'required' => true,
                ],
                'class'         => Structure_::class,
                'choice_label'  => function (Structure_ $obj): string {
                    return $obj->getStructureIdentification()?->getNom() ?? '';
                },
                'choice_value'  => 'id',
                'query_builder' => function (Structure_Repository $r) {
                    return $r->findByStructureEnabled('1');
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Orientation_structureSuperieur::class,
            'trait_choices' => null,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whitelabel_backofficebundle_orientation_structure_superieur';
    }
}

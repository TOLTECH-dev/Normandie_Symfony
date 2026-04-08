<?php

namespace App\Form\DataTransformer;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\DataTransformerInterface;

class EntityToIdTransformer implements DataTransformerInterface
{
    private string $entityClass;

    public function __construct(
        private readonly ManagerRegistry $registry,
        string                           $entityClass
    ) {
        $this->entityClass = $entityClass;
    }

    public function transform($value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_object($value)) {
            return $value;
        }

        $manager = $this->registry->getManagerForClass($this->entityClass);
        $entity = $manager->getRepository($this->entityClass)->find($value);

        if ($entity === null) {
            return null;
        }

        return $entity;
    }

    public function reverseTransform($value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'getId')) {
            return $value->getId();
        }

        return $value;
    }
}

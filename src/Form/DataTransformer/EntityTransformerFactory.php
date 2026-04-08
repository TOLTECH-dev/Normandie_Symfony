<?php

namespace App\Form\DataTransformer;

use Doctrine\Persistence\ManagerRegistry;

class EntityTransformerFactory
{
    public function __construct(
        private readonly ManagerRegistry $registry
    ) {}

    public function create(string $entityClass): EntityToIdTransformer
    {
        return new EntityToIdTransformer($this->registry, $entityClass);
    }
}

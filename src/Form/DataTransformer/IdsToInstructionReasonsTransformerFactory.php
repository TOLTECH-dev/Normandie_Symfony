<?php

namespace App\Form\DataTransformer;

use Doctrine\Persistence\ManagerRegistry;

class IdsToInstructionReasonsTransformerFactory
{
    public function __construct(
        private readonly ManagerRegistry $registry
    ) {}

    public function create(): IdsToInstructionReasonsTransformer
    {
        return new IdsToInstructionReasonsTransformer($this->registry);
    }
}

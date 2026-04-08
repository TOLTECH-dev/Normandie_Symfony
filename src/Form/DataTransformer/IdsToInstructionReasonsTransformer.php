<?php

namespace App\Form\DataTransformer;

use App\Entity\Instruction_reason;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transformer pour convertir entre IDs (BD) et entités Instruction_reason (Form)
 */
class IdsToInstructionReasonsTransformer implements DataTransformerInterface
{
    public function __construct(private readonly ManagerRegistry $registry) {}

    /**
     * Affichage du form: IDs → Entités
     */
    public function transform(mixed $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        if (!is_array($ids)) {
            return [];
        }

        $manager = $this->registry->getManagerForClass(Instruction_reason::class);
        $reasons = [];
        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                continue;
            }
            $reason = $manager->getRepository(Instruction_reason::class)->find($id);
            if ($reason) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * Soumission du form: Entités → IDs
     */
    public function reverseTransform(mixed $reasons): ?array
    {
        if (empty($reasons)) {
            return null;
        }

        if (!is_array($reasons)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $ids = [];
        foreach ($reasons as $reason) {
            if (!$reason instanceof Instruction_reason) {
                throw new TransformationFailedException('Expected an Instruction_reason instance.');
            }
            $ids[] = $reason->getId();
        }

        return $ids;
    }
}

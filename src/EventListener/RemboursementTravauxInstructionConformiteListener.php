<?php

namespace App\EventListener;

use App\Entity\Remboursement_travaux_instruction_conformite;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Remboursement_travaux_instruction_conformite::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Remboursement_travaux_instruction_conformite::class)]
#[AsEntityListener(event: Events::preRemove, entity: Remboursement_travaux_instruction_conformite::class)]
#[AsEntityListener(event: Events::postRemove, entity: Remboursement_travaux_instruction_conformite::class)]
class RemboursementTravauxInstructionConformiteListener extends AbstractFileUploadListener
{
    public function postPersist(Remboursement_travaux_instruction_conformite $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Remboursement_travaux_instruction_conformite $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Remboursement_travaux_instruction_conformite $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Remboursement_travaux_instruction_conformite $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Remboursement_travaux_instruction_conformite $entity): void
    {
        $this->handleFileUpload($entity, 'getDocument', 'getDocumentUrl', 'document', 'document_getUploadDir');
    }

    private function processPreRemove(Remboursement_travaux_instruction_conformite $entity): void
    {
        $this->handleFilePreRemove($entity, 'getDocumentUrl', 'document', 'document_getUploadDir');
    }
}
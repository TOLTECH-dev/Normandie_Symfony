<?php

namespace App\EventListener;

use App\Entity\Remboursement_auditEnergie_instruction;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Remboursement_auditEnergie_instruction::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Remboursement_auditEnergie_instruction::class)]
#[AsEntityListener(event: Events::preRemove, entity: Remboursement_auditEnergie_instruction::class)]
#[AsEntityListener(event: Events::postRemove, entity: Remboursement_auditEnergie_instruction::class)]
class Remboursement_auditEnergie_instructionListener extends AbstractFileUploadListener
{

    public function postPersist(Remboursement_auditEnergie_instruction $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Remboursement_auditEnergie_instruction $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Remboursement_auditEnergie_instruction $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Remboursement_auditEnergie_instruction $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Remboursement_auditEnergie_instruction $entity): void
    {
        $this->handleFileUpload($entity, 'getRib', 'getRibUrl', 'rib','ribGetUploadDir');
        $this->handleFileUpload($entity, 'getFacture', 'getFactureUrl', 'facture','factureGetUploadDir');
        $this->handleFileUpload($entity, 'getRectoCheque', 'getRectoChequeUrl', 'recto_cheque','rectoChequeGetUploadDir');
        $this->handleFileUpload($entity, 'getVersoCheque', 'getVersoChequeUrl', 'verso_cheque','versoChequeGetUploadDir');
    }

    private function processPreRemove(Remboursement_auditEnergie_instruction $entity): void
    {
        $this->handleFilePreRemove($entity, 'getRib', 'rib','ribGetUploadDir');
        $this->handleFilePreRemove($entity, 'getFacture', 'facture','factureGetUploadDir');
        $this->handleFilePreRemove($entity, 'getRectoCheque', 'recto_cheque','rectoChequeGetUploadDir');
        $this->handleFilePreRemove($entity, 'getVersoCheque', 'verso_cheque','versoChequeGetUploadDir');
    }
}

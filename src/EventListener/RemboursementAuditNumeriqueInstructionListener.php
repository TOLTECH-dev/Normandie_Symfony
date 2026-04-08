<?php

namespace App\EventListener;

use App\Entity\Remboursement_auditNumerique_instruction;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Remboursement_auditNumerique_instruction::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Remboursement_auditNumerique_instruction::class)]
#[AsEntityListener(event: Events::preRemove, entity: Remboursement_auditNumerique_instruction::class)]
#[AsEntityListener(event: Events::postRemove, entity: Remboursement_auditNumerique_instruction::class)]
class RemboursementAuditNumeriqueInstructionListener extends AbstractFileUploadListener
{
    public function postPersist(Remboursement_auditNumerique_instruction $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Remboursement_auditNumerique_instruction $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Remboursement_auditNumerique_instruction $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Remboursement_auditNumerique_instruction $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Remboursement_auditNumerique_instruction $entity): void
    {
        $this->handleFileUpload($entity, 'getRectoCheque', 'getRectoChequeUrl', 'recto_cheque', 'rectoCheque_getUploadDir');
        $this->handleFileUpload($entity, 'getVersoCheque', 'getVersoChequeUrl', 'verso_cheque', 'versoCheque_getUploadDir');
        $this->handleFileUpload($entity, 'getFacture', 'getFactureUrl', 'facture', 'facture_getUploadDir');
        $this->handleFileUpload($entity, 'getRib', 'getRibUrl', 'rib', 'rib_getUploadDir');
    }

    private function processPreRemove(Remboursement_auditNumerique_instruction $entity): void
    {
        $this->handleFilePreRemove($entity, 'getRectoChequeUrl', 'recto_cheque','rectoCheque_getUploadDir');
        $this->handleFilePreRemove($entity, 'getVersoChequeUrl', 'verso_cheque', 'versoCheque_getUploadDir');
        $this->handleFilePreRemove($entity, 'getFactureUrl', 'facture', 'facture_getUploadDir');
        $this->handleFilePreRemove($entity, 'getRibUrl', 'rib', 'rib_getUploadDir');
    }

}
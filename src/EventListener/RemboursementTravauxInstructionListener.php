<?php

namespace App\EventListener;

use App\Entity\Remboursement_travaux_instruction;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Remboursement_travaux_instruction::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Remboursement_travaux_instruction::class)]
#[AsEntityListener(event: Events::preRemove, entity: Remboursement_travaux_instruction::class)]
#[AsEntityListener(event: Events::postRemove, entity: Remboursement_travaux_instruction::class)]
class RemboursementTravauxInstructionListener extends AbstractFileUploadListener
{
    public function postPersist(Remboursement_travaux_instruction $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Remboursement_travaux_instruction $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Remboursement_travaux_instruction $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Remboursement_travaux_instruction $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Remboursement_travaux_instruction $entity): void
    {
        $this->handleFileUpload($entity, 'getRectoCheque', 'getRectoChequeUrl', 'recto_cheque', 'rectoCheque_getUploadDir');
        $this->handleFileUpload($entity, 'getVersoCheque', 'getVersoChequeUrl', 'verso_cheque', 'versoCheque_getUploadDir');
        $this->handleFileUpload($entity, 'getFicheTravaux', 'getFicheTravauxUrl', 'fiche_travaux', 'ficheTravaux_getUploadDir');
        $this->handleFileUpload($entity, 'getRib', 'getRibUrl', 'rib', 'rib_getUploadDir');
    }

    private function processPreRemove(Remboursement_travaux_instruction $entity): void
    {
        $this->handleFilePreRemove($entity, 'getRectoChequeUrl', 'recto_cheque','rectoCheque_getUploadDir');
        $this->handleFilePreRemove($entity, 'getVersoChequeUrl', 'verso_cheque', 'versoCheque_getUploadDir');
        $this->handleFilePreRemove($entity, 'getFicheTravauxUrl', 'fiche_travaux', 'ficheTravaux_getUploadDir');
        $this->handleFilePreRemove($entity, 'getRibUrl', 'rib', 'rib_getUploadDir');
    }

}
<?php

namespace App\EventListener;

use App\Entity\Remboursement_auditEnergie_depot;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Remboursement_auditEnergie_depot::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Remboursement_auditEnergie_depot::class)]
#[AsEntityListener(event: Events::preRemove, entity: Remboursement_auditEnergie_depot::class)]
#[AsEntityListener(event: Events::postRemove, entity: Remboursement_auditEnergie_depot::class)]
class Remboursement_auditEnergie_depotListener extends AbstractFileUploadListener
{

    public function postPersist(Remboursement_auditEnergie_depot $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Remboursement_auditEnergie_depot $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Remboursement_auditEnergie_depot $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Remboursement_auditEnergie_depot $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Remboursement_auditEnergie_depot $entity): void
    {
        $this->handleFileUpload($entity, 'getAudit', 'getAuditUrl', 'audit','auditGetUploadDir');
    }

    private function processPreRemove(Remboursement_auditEnergie_depot $entity): void
    {
        $this->handleFilePreRemove($entity, 'getAuditUrl', 'audit','auditGetUploadDir');
    }
}

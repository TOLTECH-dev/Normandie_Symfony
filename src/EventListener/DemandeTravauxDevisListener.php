<?php

namespace App\EventListener;

use App\Entity\Demande_travaux_devis;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Demande_travaux_devis::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Demande_travaux_devis::class)]
#[AsEntityListener(event: Events::preRemove, entity: Demande_travaux_devis::class)]
#[AsEntityListener(event: Events::postRemove, entity: Demande_travaux_devis::class)]
class DemandeTravauxDevisListener extends AbstractFileUploadListener
{
    public function postPersist(Demande_travaux_devis $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Demande_travaux_devis $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Demande_travaux_devis $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Demande_travaux_devis $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Demande_travaux_devis $entity): void
    {
        $this->handleFileUpload($entity, 'getAudit', 'getAuditUrl', 'audit', 'audit_getUploadDir');
        $this->handleFileUpload($entity, 'getActeEngagement', 'getActeEngagementUrl', 'acte_engagement', 'acteEngagement_getUploadDir');
    }

    private function processPreRemove(Demande_travaux_devis $entity): void
    {
        $this->handleFilePreRemove($entity, 'getAuditUrl', 'audit', 'audit_getUploadDir');
        $this->handleFilePreRemove($entity, 'getActeEngagementUrl', 'acte_engagement', 'acteEngagement_getUploadDir');
    }
}

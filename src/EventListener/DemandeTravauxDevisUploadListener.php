<?php

namespace App\EventListener;

use App\Entity\Demande_travaux_devis_upload;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Demande_travaux_devis_upload::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Demande_travaux_devis_upload::class)]
#[AsEntityListener(event: Events::preRemove, entity: Demande_travaux_devis_upload::class)]
#[AsEntityListener(event: Events::postRemove, entity: Demande_travaux_devis_upload::class)]
class DemandeTravauxDevisUploadListener extends AbstractFileUploadListener
{
    public function postPersist(Demande_travaux_devis_upload $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Demande_travaux_devis_upload $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Demande_travaux_devis_upload $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Demande_travaux_devis_upload $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Demande_travaux_devis_upload $entity): void
    {
        $this->handleFileUpload($entity, 'getDevisDocument', 'getDevisDocumentUrl', 'devis_document', 'devisDocument_getUploadDir');
    }

    private function processPreRemove(Demande_travaux_devis_upload $entity): void
    {
        $this->handleFilePreRemove($entity, 'getDevisDocumentUrl', 'devis_document', 'devisDocument_getUploadDir');
    }
}

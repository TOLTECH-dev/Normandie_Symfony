<?php

namespace App\EventListener;

use App\Entity\FicheTechniqueField;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: FicheTechniqueField::class)]
#[AsEntityListener(event: Events::postUpdate, entity: FicheTechniqueField::class)]
#[AsEntityListener(event: Events::preRemove, entity: FicheTechniqueField::class)]
#[AsEntityListener(event: Events::postRemove, entity: FicheTechniqueField::class)]
class FicheTechniqueFieldListener extends AbstractFileUploadListener
{
    public function postPersist(FicheTechniqueField $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(FicheTechniqueField $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(FicheTechniqueField $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(FicheTechniqueField $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(FicheTechniqueField $entity): void
    {
        $this->handleFileUpload($entity, 'getFicheTechniqueDocument', 'getFicheTechniqueDocumentUrl', 'xml_document','ficheTechniqueDocument_getUploadDir');
        $this->handleFileUpload($entity, 'getInfiltrometrieDocument', 'getInfiltrometrieDocumentUrl', 'infiltrometrie_document','infiltrometrieDocument_getUploadDir');
        $this->handleFileUpload($entity, 'getVentilationDocument', 'getVentilationDocumentUrl', 'ventilation_document','ventilationDocument_getUploadDir');
        $this->handleFileUpload($entity, 'getAuditApresTravauxDocument', 'getAuditApresTravauxDocumentUrl', 'audit_apres_travaux_document','auditApresTravauxDocument_getUploadDir');
    }

    private function processPreRemove(FicheTechniqueField $entity): void
    {
        $this->handleFilePreRemove($entity, 'getFicheTechniqueDocument', 'xml_document','ficheTechniqueDocument_getUploadDir');
        $this->handleFilePreRemove($entity, 'getInfiltrometrieDocument', 'infiltrometrie_document','infiltrometrieDocument_getUploadDir');
        $this->handleFilePreRemove($entity, 'getVentilationDocument', 'ventilation_document','ventilationDocument_getUploadDir');
        $this->handleFilePreRemove($entity, 'getAuditApresTravauxDocument', 'audit_apres_travaux_document','auditApresTravauxDocument_getUploadDir');
    }
}

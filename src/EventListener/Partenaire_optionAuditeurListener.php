<?php

namespace App\EventListener;

use App\Entity\Partenaire_optionAuditeur;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Partenaire_optionAuditeur::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Partenaire_optionAuditeur::class)]
#[AsEntityListener(event: Events::preRemove, entity: Partenaire_optionAuditeur::class)]
#[AsEntityListener(event: Events::postRemove, entity: Partenaire_optionAuditeur::class)]
class Partenaire_optionAuditeurListener extends AbstractFileUploadListener
{
    public function postPersist(Partenaire_optionAuditeur $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Partenaire_optionAuditeur $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Partenaire_optionAuditeur $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Partenaire_optionAuditeur $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Partenaire_optionAuditeur $entity): void
    {
        $this->handleFileUpload($entity, 'getRib', 'getRibUrl', 'rib','rib_getUploadDir');
    }

    private function processPreRemove(Partenaire_optionAuditeur $entity): void
    {
        $this->handleFilePreRemove($entity, 'getRib', 'rib','rib_getUploadDir');
    }
}

<?php

namespace App\EventListener;

use App\Entity\Admin_import;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Admin_import::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Admin_import::class)]
#[AsEntityListener(event: Events::preRemove, entity: Admin_import::class)]
#[AsEntityListener(event: Events::postRemove, entity: Admin_import::class)]
class AdminImportListener extends AbstractFileUploadListener
{
    public function postPersist(Admin_import $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Admin_import $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Admin_import $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Admin_import $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Admin_import $entity): void
    {
        $this->handleFileUpload($entity, 'getFile', 'getFileUrl', $this->getFilePrefix($entity), 'file_getUploadDir');
    }

    private function processPreRemove(Admin_import $entity): void
    {
        $this->handleFilePreRemove($entity, 'getFileUrl', $this->getFilePrefix($entity), 'file_getUploadDir');
    }

    private function getFilePrefix(Admin_import $entity): string
    {
        $typeKey = explode(' | ', $entity->getType());

        return $typeKey[1];
    }

}
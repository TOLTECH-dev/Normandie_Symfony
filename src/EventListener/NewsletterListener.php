<?php

namespace App\EventListener;

use App\Entity\Newsletter;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Newsletter::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Newsletter::class)]
#[AsEntityListener(event: Events::preRemove, entity: Newsletter::class)]
#[AsEntityListener(event: Events::postRemove, entity: Newsletter::class)]
class NewsletterListener extends AbstractFileUploadListener
{
    public function postPersist(Newsletter $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Newsletter $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Newsletter $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Newsletter $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Newsletter $entity): void
    {
        $this->handleFileUpload($entity, 'getFile', 'getFileUrl', 'file', 'fileGetUploadDir');
    }

    private function processPreRemove(Newsletter $entity): void
    {
        $this->handleFilePreRemove($entity, 'getFileUrl', 'file', 'fileGetUploadDir');
    }
}
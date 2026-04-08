<?php

namespace App\EventListener;

use App\Entity\Demande_travaux;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Demande_travaux::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Demande_travaux::class)]
#[AsEntityListener(event: Events::preRemove, entity: Demande_travaux::class)]
#[AsEntityListener(event: Events::postRemove, entity: Demande_travaux::class)]
class DemandeTravauxListener extends AbstractFileUploadListener
{
    public function postPersist(Demande_travaux $entity): void
    {
        $this->processUpload($entity);
    }

    public function postUpdate(Demande_travaux $entity): void
    {
        $this->processUpload($entity);
    }

    public function preRemove(Demande_travaux $entity): void
    {
        $this->processPreRemove($entity);
    }

    public function postRemove(Demande_travaux $entity): void
    {
        $this->handlePostRemove($entity);
    }

    private function processUpload(Demande_travaux $entity): void
    {
        $this->handleFileUpload($entity, 'getJustificatifPropriete', 'getJustificatifProprieteUrl', 'justificatif_propriete', 'justificatifPropriete_getUploadDir');
        $this->handleFileUpload($entity, 'getPieceComplement', 'getPieceComplementUrl', 'piece_complement', 'pieceComplement_getUploadDir');
        $this->handleFileUpload($entity, 'getAvisImpositionConjoint', 'getAvisImpositionConjointUrl', 'avis_imposition_conjoint', 'avisImpositionConjoint_getUploadDir');
        $this->handleFileUpload($entity, 'getAvisImposition', 'getAvisImpositionUrl', 'avis_imposition', 'avisImposition_getUploadDir');
    }

    private function processPreRemove(Demande_travaux $entity): void
    {
        $this->handleFilePreRemove($entity, 'getJustificatifProprieteUrl', 'justificatif_propriete', 'justificatifPropriete_getUploadDir');
        $this->handleFilePreRemove($entity, 'getPieceComplementUrl', 'piece_complement', 'pieceComplement_getUploadDir');
        $this->handleFilePreRemove($entity, 'getAvisImpositionConjointUrl', 'avis_imposition_conjoint', 'avisImpositionConjoint_getUploadDir');
        $this->handleFilePreRemove($entity, 'getAvisImpositionUrl', 'avis_imposition', 'avisImposition_getUploadDir');
    }
}

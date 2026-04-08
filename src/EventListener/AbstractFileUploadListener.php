<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

abstract class AbstractFileUploadListener
{
    protected ParameterBagInterface $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    protected function handleFileUpload(object $entity, string $getter, string $urlGetter, string $filePrefix, string $uploadDirGetter): void
    {
        if (null === $entity->$getter()) {
            return;
        }

        $uploadRootDir = rtrim(str_replace('\\', '/', $this->getUploadRootDir()), '/');
        $uploadSubDir = ltrim(str_replace('\\', '/', $entity->$uploadDirGetter()), '/');
        $uploadDir = $uploadRootDir . '/' . $uploadSubDir;

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new \RuntimeException(sprintf('Impossible de créer le dossier de destination "%s".', $uploadDir));
            }
        }

        if (null !== $entity->getTempFilename()) {
            $oldFile = $uploadDir . '/' . $entity->getId() . '_' . $filePrefix . '.' . $entity->getTempFilename();
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        $extension = $entity->$urlGetter();
        $filename = $entity->getId() . '_' . $filePrefix . ($extension ? ('.' . $extension) : '');
        try {
            $entity->$getter()->move($uploadDir, $filename);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Erreur lors du déplacement du fichier vers "%s/%s" : %s', $uploadDir, $filename, $e->getMessage()));
        }
    }

    protected function handleFilePreRemove(object $entity, string $urlGetter, string $filePrefix, string $uploadDirGetter): void
    {
        $entity->setTempFilename($this->getUploadRootDir() .$entity->$uploadDirGetter (). '/' . $entity->getId() . '_' . $filePrefix . '.' . $entity->$urlGetter());
    }

    protected function handlePostRemove(object $entity): void
    {
        if (file_exists($entity->getTempFilename())) {
            unlink($entity->getTempFilename());
        }
    }

    private function getUploadRootDir(): string
    {
        return $this->parameterBag->get('app_root_dossier_data_symfony') ;
    }
}

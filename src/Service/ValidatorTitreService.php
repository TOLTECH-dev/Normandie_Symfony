<?php

namespace App\Service;

use App\Entity\Titre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service de validation pour les fichiers Titre
 */
class ValidatorTitreService
{
    final public const FILE_CODE_TXT = 'txt';
    final public const FILE_ENCODAGE_UTF = 'utf-8';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {}




    /**
     * @param $listFile
     * @param $listDemande
     * @param $listProduction
     * @return array
     * @throws \Exception
     */
    public function validate($listFile, $listDemande, $listProduction): array
    {
        $return = array();
        if (!empty($listFile)) {
            foreach ($listFile as $item) {

                $errorContent = "Nom du fichier du retour de Production : " . $item['basename'] . "\r\n";
                $errorContentInit = $errorContent;

                // Validate content
                if (0 == strcasecmp($item['extension'], self::FILE_CODE_TXT)) {
                    $pathFile = $item['dirname'] . '/' . $item['basename'];
                    $fileEncodage = exec('file --mime-encoding -b "' . $pathFile . '"');
                    $arrayValidateContent = $this->validateContentTXT(file($pathFile, FILE_IGNORE_NEW_LINES), $item, $listDemande, $listProduction, $fileEncodage);
                    $errorContent .= $arrayValidateContent['errorContent'];
                } else {
                    throw new \Exception('Extension non valide');
                }

                // Get isValid variable
                $isValid = ($errorContentInit != $errorContent) ? false : true;

                $return[$item['filename']] = array(
                    'isValid'       => $isValid,
                    'reportContent' => $errorContent,
                    'pathInfo'      => $item
                );
            }
        } else {
            $errorContent = "Aucun fichier du retour de Production correspondant\r\n";

            $return['filename'] = array(
                'isValid'       => false,
                'reportContent' => $errorContent,
                'pathInfo'      => null
            );
        }

        return $return;
    }


    /**
     * @param $listData
     * @param $pathInfo
     * @param $listDemande
     * @param $listProduction
     * @param $fileEncodage
     * @return array
     */
    private function validateContentTXT($listData, $pathInfo, $listDemande, $listProduction, $fileEncodage): array
    {
        // Force encodage UTF-8
        if (0 != strcasecmp($fileEncodage, self::FILE_ENCODAGE_UTF)) {
            $listData = array_map(
                fn($item) => mb_convert_encoding($item, 'UTF-8', $fileEncodage),
                $listData
            );
        }

        $listNumeroChequier = array();
        $listNumeroCheque = array();
        $errorContent = '';
        $line = 1;

        foreach ($listData as $row) {

            $errorMessage = "Ligne " . $line . " => Motif: ";

            // CHECK ROW LENGTH
            if (strlen($row) != Titre::ROW_LENGTH_RETOUR_PRODUCTION) {
                $errorContent .= $errorMessage . "La taille de la ligne est incorrecte (taille correcte: " . Titre::ROW_LENGTH_RETOUR_PRODUCTION . " caractères) \r\n";
            }

            // Init variable
            $dataNumeroOperation = (int)substr($row, 0, 5);
            $dataDemandeId = (int)substr($row, 5, 12);
            $dataProductionId = (int)substr($row, 17, 12);
            $dataNumeroChequier = (int)substr($row, 29, 9);
            $dataNumeroCheque = (int)substr($row, 38, 9);
            $dataDateFormatEmissionTitre = substr($row, 57, 10);
            $dataDateFormatValiditeTitre = substr($row, 67, 10);

            // Init doublon
            $listNumeroChequier[$dataDemandeId][$dataNumeroChequier][] = $dataNumeroChequier;
            $listNumeroCheque[] = $dataNumeroCheque;

            // NUMERO OPE
            if (!preg_match(Titre::PATTERN_FILE_AS400 . $dataNumeroOperation . "#", $pathInfo['filename'])) {
                $errorContent .= $errorMessage . "Le numéro OPE " . $dataNumeroOperation . " ne correspond pas au fichier joint\r\n";
            }

            // ID DEMANDE
            if (!array_key_exists($dataDemandeId, $listDemande)) {
                $errorContent .= $errorMessage . "L'Id Demande " . $dataDemandeId . " n'a pas de correspondance en base de données\r\n";
            }

            // Couple ID PRODUCTION / ID DEMANDE
            if (!array_key_exists($dataProductionId . " | " . $dataDemandeId, $listProduction)) {
                $errorContent .= $errorMessage . "L'Id Production " . $dataProductionId . " et l'Id Demande " . $dataDemandeId . " n'ont pas de correspondance en base de données\r\n";
            }

            // NUMERO CHEQUIER
            if (count($listNumeroChequier[$dataDemandeId][$dataNumeroChequier]) > 1) {
                $errorContent .= $errorMessage . "Le numéro de Chèquier " . $dataNumeroChequier . " doit être unique pour chaque Demande\r\n";
            }

            // NUMERO CHEQUE
            if (count(array_keys($listNumeroCheque, $dataNumeroCheque)) > 1) {
                $errorContent .= $errorMessage . "Le numéro de Chèque " . $dataNumeroCheque . " doit être unique\r\n";
            }

            // DATE EMISSION
            if (!preg_match(Titre::PATTERN_DATE, $dataDateFormatEmissionTitre)) {
                $errorContent .= $errorMessage . "La date d'émission " . $dataDateFormatEmissionTitre . " doit être de type dd.mm.yyyy et valide\r\n";
            }

            // DATE VALIDITE
            if (!preg_match(Titre::PATTERN_DATE, $dataDateFormatValiditeTitre)) {
                $errorContent .= $errorMessage . "La date de validité " . $dataDateFormatValiditeTitre . " doit être de type dd.mm.yyyy et valide\r\n";
            }

            $line++;
        }

        $return = array(
            'errorContent' => $errorContent
        );

        return $return;
    }
}

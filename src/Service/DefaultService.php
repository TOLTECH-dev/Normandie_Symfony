<?php

namespace App\Service;

class DefaultService
{
    const GROUPCONCAT_SEPARATOR_ITEMS = "##";
    const GROUPCONCAT_SEPARATOR_ITEMS_ID_LABEL = "__";
    const PREFIX_PATHNAME_ASSISTANT_BENEFICIAIRE = 'conseiller';

    const PATHNAME_CONSEILLER_BENEFICIAIRE_LIST = 'conseiller_beneficiaire_list';
    const PATHNAME_CONSEILLER_BENEFICIAIRE_ADD  = 'conseiller_beneficiaire_add';

    const DECIMAL_FIELD_FORM_PATTERN = '^[0-9]+([,.][0-9]+)?$';
    const DECIMAL_FIELD_FORM_PATTERN_WITH_DELIMITER = '/^[0-9]+([,.][0-9]+)?$/';
    const DECIMAL_FIELD_FORM_TEXT = 'La valeur saisie doit être numérique';

    public static $arrayDocumentManquant = [
        "justificatif_propriete" => "Un justificatif de propriété:
                                        <ul>
                                            <li>taxe foncière</li>
                                            <li>ou, à défaut, l'attestation notariale</li>
                                            <li>ou le compromis de vente en cas d'acquisition en cours</li>
                                    </ul>",
        "statut_sci"             => "Statut de la SCI",
        "avis_imposition"        => "L'avis d'imposition du foyer (revenus de N-1 ou N-2)",
        "rib"                    => "RIB",
        "facture"                => "Facture",
        "cheque"                 => "Chèque éco-énergie",
        "fiche_travaux"          => "Fiche de liaison"
    ];

    public static $routesToIgnoreForMenuConseiller = [
        self::PATHNAME_CONSEILLER_BENEFICIAIRE_LIST,
        self::PATHNAME_CONSEILLER_BENEFICIAIRE_ADD
    ];


    public static function getDataSymfonyPath(): string
    {
        // Retourne le chemin racine des fichiers uploadés (à adapter selon votre config)
        return __DIR__ . '/../../public/';
    }

    /**
     * @param $remboursementId
     * @param $remboursementStatutDescription
     * @param $dataMotifStatutRemboursement
     * @return array|mixed|string|string[]|null
     */
    public static function getStatutDescriptionByRemboursementAndMotif(
        $remboursementId = null,
        $remboursementStatutDescription = null,
        $dataMotifStatutRemboursement = null
    ) {

        $documentManquantTag = null;
        $documentManquantList = [];
        $documentNonConformeList = [];
        $documentNonConformeTag = null;
        $refusText = null;
        $documentRefusTag = null;
        $explicationMotifStatut = '';
        $documentNonConformeContent = '';

        if (!empty($remboursementId)) {

            $explicationMotifStatut = $remboursementStatutDescription;

            $documentManquantList = !empty($dataMotifStatutRemboursement['documentManquantList']) ? $dataMotifStatutRemboursement['documentManquantList'] : null;
            $documentManquantTag = !empty($dataMotifStatutRemboursement['documentManquantTag']) ? $dataMotifStatutRemboursement['documentManquantTag'] : null;
            $documentNonConformeList = !empty($dataMotifStatutRemboursement['nonConformeList']) ? $dataMotifStatutRemboursement['nonConformeList'] : null;
            $documentNonConformeTag = !empty($dataMotifStatutRemboursement['documentNonConformeTag']) ? $dataMotifStatutRemboursement['documentNonConformeTag'] : null;
            $refusText = !empty($dataMotifStatutRemboursement['refusText']) ? $dataMotifStatutRemboursement['refusText'] : null;
            $documentRefusTag = !empty($dataMotifStatutRemboursement['documentRefusTag']) ? $dataMotifStatutRemboursement['documentRefusTag'] : null;

            // DOCUMENT NON CONFORME TAG
            if ($documentNonConformeTag) {
                if ($documentNonConformeList) {
                    $arrayRibReasonSlug = $documentNonConformeList['arrayRibReasonSlug'];
                    $arrayFactureReasonSlug = $documentNonConformeList['arrayFactureReasonSlug'];
                    $arrayChequeReasonSlug = $documentNonConformeList['arrayChequeReasonSlug'];
                    $arrayFicheTravauxReasonSlug = $documentNonConformeList['arrayFicheTravauxReasonSlug'];

                    $conformiteRib = $documentNonConformeList['conformiteRib'];
                    $conformiteFacture = $documentNonConformeList['conformiteFacture'];
                    $conformiteCheque = $documentNonConformeList['conformiteCheque'];
                    $conformiteFicheTravaux = $documentNonConformeList['conformiteFicheTravaux'];

                    $reasonAutreRib = $documentNonConformeList['reasonAutreRib'];
                    $reasonAutreFacture = $documentNonConformeList['reasonAutreFacture'];
                    $reasonAutreCheque = $documentNonConformeList['reasonAutreCheque'];
                    $reasonAutreFicheTravaux = $documentNonConformeList['reasonAutreFicheTravaux'];

                    if ("1" === $conformiteCheque && $arrayChequeReasonSlug) {
                        $documentNonConformeContent .= "<p>Ch&egrave;que :</p>"
                            . "<ul>";

                        foreach ($arrayChequeReasonSlug as $rowChequeReasonSlug) {
                            if ("Autre" == $rowChequeReasonSlug && $reasonAutreCheque) {
                                $rowSlug = $rowChequeReasonSlug . " : " . $reasonAutreCheque;
                            } else {
                                $rowSlug = $rowChequeReasonSlug;
                            }
                            $documentNonConformeContent .= "<li>" . $rowSlug . "</li>";
                        }
                        $documentNonConformeContent .= "</ul>";
                    }

                    if ("1" === $conformiteFicheTravaux && $arrayFicheTravauxReasonSlug) {
                        $documentNonConformeContent .= "<p>Fiche descriptive des travaux :</p>"
                            . "<ul>";

                        foreach ($arrayFicheTravauxReasonSlug as $rowFicheTravauxReasonSlug) {
                            if ("Autre" == $rowFicheTravauxReasonSlug && $reasonAutreFicheTravaux) {
                                $rowSlug = $rowFicheTravauxReasonSlug . " : " . $reasonAutreFicheTravaux;
                            } else {
                                $rowSlug = $rowFicheTravauxReasonSlug;
                            }
                            $documentNonConformeContent .= "<li>" . $rowSlug . "</li>";
                        }
                        $documentNonConformeContent .= "</ul>";
                    }

                    if ("1" === $conformiteFacture && $arrayFactureReasonSlug) {
                        $documentNonConformeContent .= "<p>Facture :</p>"
                            . "<ul>";
                        foreach ($arrayFactureReasonSlug as $rowFactureReasonSlug) {
                            if ("Autre" == $rowFactureReasonSlug && $reasonAutreFacture) {
                                $rowSlug = $rowFactureReasonSlug . " : " . $reasonAutreFacture;
                            } else {
                                $rowSlug = $rowFactureReasonSlug;
                            }
                            $documentNonConformeContent .= "<li>" . $rowSlug . "</li>";
                        }
                        $documentNonConformeContent .= "</ul>";
                    }

                    if ('1' === $conformiteRib && $arrayRibReasonSlug) {
                        $documentNonConformeContent .= "<p>RIB :</p>"
                            . "<ul>";
                        foreach ($arrayRibReasonSlug as $rowRibReasonSlug) {
                            if ("Autre" == $rowRibReasonSlug && $reasonAutreRib) {
                                $rowSlug = $rowRibReasonSlug . " : " . $reasonAutreRib;
                            } else {
                                $rowSlug = $rowRibReasonSlug;
                            }
                            $documentNonConformeContent .= "<li>" . $rowSlug . "</li>";
                        }
                        $documentNonConformeContent .= "</ul>";
                    }
                }
            }
        }

        //DOCUMENT MANQUANT TAG
        if ($documentManquantTag) {
            if ($documentManquantList) {
                $documentManquantContent = "<ul>";
                foreach ($documentManquantList as $rowDocumentManquant) {
                    $documentManquantContent .= "<li>" . self::$arrayDocumentManquant[$rowDocumentManquant] . "</li>";
                }
                $documentManquantContent .= "</ul>";
            }

            $explicationMotifStatut = str_replace("##MOTIF_DOC_MANQUANT##", $documentManquantContent, $explicationMotifStatut);
        }

        // REFUS TAG
        if ($documentRefusTag) {
            $explicationMotifStatut = str_replace("##MOTIF_REFUS##", $refusText, $explicationMotifStatut);
        }

        // DOCUMENT NON CONFORME TAG
        if ($explicationMotifStatut) {
            $explicationMotifStatut = str_replace("##MOTIF_NON_CONFORME##", $documentNonConformeContent, $explicationMotifStatut);
        }

        return $explicationMotifStatut;
    }

    /**
     * @param $demandeId
     * @param $demandeStatutDescription
     * @param $beneficiaireType
     * @param $dataMotifStatutDemande
     * @param $options
     * @return array|string|string[]
     */
    public static function getStatutDescriptionByDemandeAndMotif(
        $demandeId,
        $demandeStatutDescription,
        $beneficiaireType,
        $dataMotifStatutDemande = null,
        $options = []
    ) {

        $documentManquantTag = null;
        $documentManquantList = [];
        $documentNonConformeList = [];
        $documentNonConformeTag = null;
        $refusText = null;
        $documentRefusTag = null;
        $explicationMotifStatut = '';
        $documentNonConformeContent = '';

        if (!empty($demandeId)) {
            $explicationMotifStatut = $demandeStatutDescription;

            $documentManquantList = !empty($dataMotifStatutDemande['documentManquantList']) ? $dataMotifStatutDemande['documentManquantList'] : null;
            $documentManquantTag = !empty($dataMotifStatutDemande['documentManquantTag']) ? $dataMotifStatutDemande['documentManquantTag'] : null;
            $documentNonConformeList = !empty($dataMotifStatutDemande['nonConformeList']) ? $dataMotifStatutDemande['nonConformeList'] : null;
            $documentNonConformeTag = !empty($dataMotifStatutDemande['documentNonConformeTag']) ? $dataMotifStatutDemande['documentNonConformeTag'] : null;
            $refusText = !empty($dataMotifStatutDemande['refusText']) ? $dataMotifStatutDemande['refusText'] : null;
            $documentRefusTag = !empty($dataMotifStatutDemande['documentRefusTag']) ? $dataMotifStatutDemande['documentRefusTag'] : null;

            // DOCUMENT NON CONFORME TAG
            if ($documentNonConformeTag) {
                if ($documentNonConformeList) {
                    $arrayJPreasonSlug = $documentNonConformeList['arrayJPreasonSlug'];
                    $arrayKBISreasonSlug = $documentNonConformeList['arrayKBISreasonSlug'];
                    $arrayAIreasonSlug = $documentNonConformeList['arrayAIreasonSlug'];

                    $conformiteJP = $documentNonConformeList['conformiteJP'];
                    $conformiteKBIS = $documentNonConformeList['conformiteKBIS'];
                    $conformiteAI = $documentNonConformeList['conformiteAI'];

                    $reasonAutreJP = $documentNonConformeList['reasonAutreJP'];
                    $reasonAutreKBIS = $documentNonConformeList['reasonAutreKBIS'];
                    $reasonAutreAI = $documentNonConformeList['reasonAutreAI'];

                    if ("1" === $conformiteJP && $arrayJPreasonSlug) {
                        $documentNonConformeContent .= "<p>Justificatif de propri&eacute;t&eacute; :</p>"
                            . "<ul>";

                        foreach ($arrayJPreasonSlug as $rowJPreasonSlug) {
                            if ("Autre" == $rowJPreasonSlug && $reasonAutreJP) {
                                $rowSlug = $rowJPreasonSlug . " : " . $reasonAutreJP;
                            } else {
                                $rowSlug = $rowJPreasonSlug;
                            }
                            $documentNonConformeContent .= "<li>" . $rowSlug . "</li>";
                        }
                        $documentNonConformeContent .= "</ul>";
                    }

                    if ("1" === $conformiteKBIS && $arrayKBISreasonSlug) {
                        if ('1 | sci' === $beneficiaireType) {
                            $textDisplay = 'Statut de la SCI :';
                        } else {
                            $textDisplay = 'Pi&egrave;ce compl&eacute;mentaire :';
                        }
                        $documentNonConformeContent .= "<p>" . $textDisplay . "</p>"
                            . "<ul>";

                        foreach ($arrayKBISreasonSlug as $rowKBISreasonSlug) {
                            if ('Autre' === $rowKBISreasonSlug && $reasonAutreKBIS) {
                                $rowSlug = $rowKBISreasonSlug . " : " . $reasonAutreKBIS;
                            } else {
                                $rowSlug = $rowKBISreasonSlug;
                            }
                            $documentNonConformeContent .= "<li>" . $rowSlug . "</li>";
                        }
                        $documentNonConformeContent .= "</ul>";
                    }

                    if ("1" === $conformiteAI && $arrayAIreasonSlug) {
                        $documentNonConformeContent .= "<p>Avis Imposition :</p>"
                            . "<ul>";

                        foreach ($arrayAIreasonSlug as $rowAIreasonSlug) {
                            if ("Autre" === $rowAIreasonSlug && $reasonAutreAI) {
                                $rowSlug = $rowAIreasonSlug . " : " . $reasonAutreAI;
                            } else {
                                $rowSlug = $rowAIreasonSlug;
                            }
                            $documentNonConformeContent .= "<li>" . $rowSlug . "</li>";
                        }
                        $documentNonConformeContent .= "</ul>";
                    }
                }
            }
        }

        //DOCUMENT MANQUANT TAG
        if ($documentManquantTag) {
            if ($documentManquantList) {
                $documentManquantContent = "<ul>";
                foreach ($documentManquantList as $rowDocumentManquant) {
                    $documentManquantContent .= "<li>" . self::$arrayDocumentManquant[$rowDocumentManquant] . "</li>";
                }
                $documentManquantContent .= "</ul>";
            }

            $explicationMotifStatut = str_replace("##MOTIF_DOC_MANQUANT##", $documentManquantContent, $explicationMotifStatut);
        }

        // REFUS TAG
        if ($documentRefusTag) {
            $explicationMotifStatut = str_replace("##MOTIF_REFUS##", $refusText, $explicationMotifStatut);
        }

        // DOCUMENT NON CONFORME TAG
        if ($explicationMotifStatut) {
            $explicationMotifStatut = (!empty($options['isMotifNonConformeOnly'])) ? $documentNonConformeContent : str_replace("##MOTIF_NON_CONFORME##", $documentNonConformeContent, $explicationMotifStatut);
        }

        return $explicationMotifStatut;
    }

    /**
     * Move files from remote directory to local directory, by pattern
     *
     * @param $remoteDir
     * @param $localDir
     * @param $arrayPattern
     * @return array|void
     */
    public static function getFilesByPattern(
        $remoteDir,
        $localDir,
        $arrayPattern
    ) {
        $return = [];

        // open remote directory
        if (!file_exists($remoteDir)) {
            return [];
        }

        // retrieve files
        $files = scandir($remoteDir);
        if (empty($files)) {
            die("Error listing directory ".$remoteDir);
        }

        foreach($arrayPattern as $pattern) {
            $return[$pattern] = [];

            // match pattern
            $matches = preg_grep('/' . $pattern . '/', $files);

            if (!empty($matches)) {
                if (strpos($remoteDir, '/') === false) {
                    $remoteDir = $remoteDir . '/';
                }
                $i = 0;
                foreach($matches as $file) {

                    $file = basename($file);
                    $remoteFile = $remoteDir . '/' . $file;
                    $localFile = $localDir . '/' . $file;

                    if ($file != "." && $file != "..") {
                        $resultGet = copy($remoteFile, $localFile);
                        if ($resultGet === true) {
                            // We delete the remote file
                            $delete = unlink($remoteFile);
                            if (!$delete) {
                                echo "Impossible d'effacer le fichier $file\n";
                            }
                        }

                        $return[$pattern][] = $file;
                        $i++;
                    }
                }

                echo "Pattern: " . $pattern . " => " . $i . " fichier(s) téléchargé(s)\n";
            }
        }

        return $return;
    }

    /**
     * @param $data
     * @return array|string|string[]
     */
    public static function formatSearchForMontant($data)
    {
        return str_replace([',', ' '], ['.', ''], trim($data));
    }

    /**
     * @param \DateTime $date
     * @param int $expiration
     *
     * @return bool
     */
    public static function checkItemValidityByDate(\DateTime $date, $expiration = 172800)
    {
        $diff = $date->diff(new \DateTime('now'));

        $daysInSecs = $diff->format('%r%a') * 24 * 60 * 60;
        $hoursInSecs = $diff->h * 60 * 60;
        $minsInSecs = $diff->i * 60;

        $seconds = $daysInSecs + $hoursInSecs + $minsInSecs + $diff->s;

        return $seconds <= $expiration;
    }
}
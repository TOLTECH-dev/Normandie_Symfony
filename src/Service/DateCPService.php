<?php

namespace App\Service;

use App\Repository\Demande_Repository;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DateCPService
{
    public function __construct(
        private Demande_Repository $demandeRepository,
        private string $dateUsPassageMontantAuditEnergie,
        private string $datePassageMontantAuditRegion
    ) {}

    /**
     * @throws Exception
     */
    public function export(int $dateCPId): StreamedResponse
    {
        $data = $this->demandeRepository->findByDateCp(
            $dateCPId,
            $this->dateUsPassageMontantAuditEnergie,
            $this->datePassageMontantAuditRegion
        );

        $response = new StreamedResponse();

        $response->setCallback(function () use ($data) {
            $handle = fopen('php://output', 'r+');
            fputcsv(
                $handle,
                [
                    'NUMERO DOSSIER',
                    'NOM',
                    'PRENOM',
                    'CP',
                    'VILLE',
                    'TYPE DU CHEQUE ',
                    'MONTANT',
                    'PARTENAIRE',
                    'DATE DE PRISE EN COMPTE DES DEPENSES'
                ],
                ';'
            );

            foreach ($data as $row) {
                $beneficiaireNom = iconv("UTF-8", "Windows-1252//TRANSLIT", $row["beneficiaireNom"]);
                $beneficiaireNomSCI = iconv("UTF-8", "Windows-1252//TRANSLIT", $row["beneficiaireNomSCI"]);
                $beneficiaireNomComplet = $row["beneficiaireNomSCI"] ? $beneficiaireNomSCI . ' - ' . $beneficiaireNom : $beneficiaireNom;

                $demandeType = $row["demandeType"];
                if ($row['demandeTravauxDevisIsBonificationAide'] == 1) {
                    $demandeType .= ' + Bonification';
                }

                fputcsv(
                    $handle,
                    [
                        $row["demandeId"],
                        $beneficiaireNomComplet,
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row["beneficiairePrenom"]),
                        $row["logementCodePostal"],
                        $row["logementVille"],
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $demandeType),
                        $row["montant"],
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row["professionnel"]),
                        date_format(date_create($row["demandeDateCreation"]), 'Y-m-d')
                    ],
                    ';'
                );
            }
            fclose($handle);
        });

        $filename = "export_demande_datecp_" . date("YmdHis") . ".csv";
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename);

        return $response;
    }
}

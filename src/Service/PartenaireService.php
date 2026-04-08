<?php

namespace App\Service;

use App\Entity\Partenaire_;
use Doctrine\ORM\EntityManagerInterface;
use Spipu\Html2Pdf\Html2Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PartenaireService
{
    private EntityManagerInterface $em;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $em,
        Environment $twig
    ) {
        $this->em = $em;
        $this->twig = $twig;
    }



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param int $type
     * @return StreamedResponse
     */
    public function export(int $type): StreamedResponse
    {
        if (0 === $type) {
            $data = $this->em->getRepository(Partenaire_::class)->findBy([
                'type' => '0 | auditeur'
            ]);
        } elseif (1 === $type) {
            $data = $this->em->getRepository(Partenaire_::class)->findBy([
                'type' => '1 | renovateur'
            ]);
        }

        $response = new StreamedResponse();
        $response->setCallback(function() use ($data, $type)
        {
            $handle = fopen('php://output', 'r+');

            // Header Auditeur - Rénovateur
            if (0 === $type) {
                $header = [
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Numéro auditeur'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Raison sociale'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Siret'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Domiciliation bancaire'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Titulaire'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'IBAN'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'BIC'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Adresse 1'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Adresse 2'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Code postal'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Ville'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Département'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Téléphone fixe'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Téléphone mobile'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Email entreprise'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Site internet'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Information complémentaire'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Date rattachement'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Statut (Actif/Inactif)')
                ];
            } elseif (1 == $type) {
                $header = [
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Numéro rénovateur'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Raison sociale'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Siret'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Type d\'acteur'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Complément d\'identification'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Adresse 1'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Adresse 2'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Code postal'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Ville'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Département'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Téléphone fixe'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Téléphone mobile'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Email entreprise'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Site internet'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Information complémentaire'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Date rattachement'),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",'Statut (Actif/Inactif)')
                ];
            }

            // Header Contact
            $arrayNombreContact = array();
            foreach ($data as $row){
                array_push($arrayNombreContact, count($row->getPartenaireContact()));
            }

            for ($index=0; $index<max($arrayNombreContact); $index++) {
                $indice = $index+1;
                array_push($header,
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Contact_civilité_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Contact_nom_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Contact_prénom_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Contact_titre_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Contact_téléphone_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Contact_email_$indice")
                );
            }

            // Header Agence
            $arrayNombreAgence = array();
            foreach ($data as $row) {
                array_push($arrayNombreAgence, count($row->getPartenaireAgence()));
            }

            for ($index=0; $index<max($arrayNombreAgence); $index++) {
                $indice = $index+1;
                array_push($header,
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Agence_nom_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Agence_adresse_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Agence_code_postal_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Agence_ville_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Agence_téléphone_$indice"),
                    iconv("UTF-8", "Windows-1252//TRANSLIT","Agence_email_$indice")
                );
            }

            fputcsv($handle, $header, ';');



            foreach ($data as $row) {
                $adresse2 = (!empty($row->getPartenaireAdresse()->getAdresse2())) ? $row->getPartenaireAdresse()->getAdresse2() : '';
                $tel_fixe = (!empty($row->getPartenaireAdresse()->getTelFixe())) ? $row->getPartenaireAdresse()->getTelFixe() : '';
                $tel_mobile = (!empty($row->getPartenaireAdresse()->getTelMobile())) ? $row->getPartenaireAdresse()->getTelMobile() : '';
                $internet = (!empty($row->getPartenaireAdresse()->getSiteInternet()))? $row->getPartenaireAdresse()->getSiteInternet() : '';
                $info_complement = (!empty($row->getPartenaireAdresse()->getComplement()))? $row->getPartenaireAdresse()->getComplement() : '';
                if (0 == $row->getPartenaireStatut()->getEnabled()) $statut = 'Inactif';
                else $statut = 'Actif';

                // Body Auditeur - Rénovateur
                if (0 == $type) {
                    $body = array(
                        $row->getId(),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireIdentification()->getRaisonSociale()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireIdentification()->getSiret()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireOptionAuditeur()->getDomicileBancaire()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireOptionAuditeur()->getTitulaire()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireOptionAuditeur()->getIban()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireOptionAuditeur()->getBic()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireAdresse()->getAdresse1()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $adresse2),
                        $row->getPartenaireAdresse()->getCodePostal(),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireAdresse()->getVille()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireAdresse()->getDepartement()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $tel_fixe),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $tel_mobile),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireAdresse()->getEmail()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $internet),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $info_complement),
                        $row->getPartenaireStatut()->getDateRattachement()->format('d/m/Y'),
                        $statut
                    );
                } elseif (1 == $type) {
                    $type_acteur = explode(" | ", $row->getPartenaireOptionRenovateur()->getTypeActeur())[1];
                    $complement = (!empty($row->getPartenaireOptionRenovateur()->getComplement())) ? $row->getPartenaireOptionRenovateur()->getComplement() : '';

                    $body = array(
                        $row->getId(),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireIdentification()->getRaisonSociale()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireIdentification()->getSiret()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", ucfirst($type_acteur)),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $complement),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireAdresse()->getAdresse1()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $adresse2),
                        $row->getPartenaireAdresse()->getCodePostal(),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireAdresse()->getVille()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireAdresse()->getDepartement()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $tel_fixe),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $tel_mobile),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $row->getPartenaireAdresse()->getEmail()),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $internet),
                        iconv("UTF-8", "Windows-1252//TRANSLIT", $info_complement),
                        $row->getPartenaireStatut()->getDateRattachement()->format('d/m/Y'),
                        $statut
                    );
                }

                // Body Contact
                $body = array_merge($body, $this->getBodyContact($row->getPartenaireContact(), max($arrayNombreContact)));

                // Body Agence
                $body = array_merge($body, $this->getBodyAgence($row->getPartenaireAgence()));

                fputcsv($handle, $body, ';' );
            }

            fclose($handle);
        });

        if (0 === $type) {
            $filename = "export_auditeur_" . date("YmdHis") . ".csv";
        } elseif (1 === $type) {
            $filename = "export_renovateur_" . date("YmdHis") . ".csv";
        }

        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

        return $response;
    }

    /**
     * @param $data
     * @param $maxContact
     * @return array
     */
    public function getBodyContact($data, $maxContact): array
    {
        $body = array();
        if (!empty($data)) {
            $k = 0;
            foreach ($data as $row) {
                $civilite = (!empty($row->getCivilite())) ? explode(" | ", $row->getCivilite())[1] : '';
                $nom = (!empty($row->getNom())) ? $row->getNom() : '';
                $prenom = (!empty($row->getPrenom())) ? $row->getPrenom() : '';
                $titre = (!empty($row->getTitre())) ? $row->getTitre() : '';
                $telephone = (!empty($row->getTelephone())) ? $row->getTelephone() : '';
                $email = (!empty($row->getEmail()))? $row->getEmail() : '';

                array_push($body,
                    iconv("UTF-8", "Windows-1252//TRANSLIT", ucfirst($civilite)),
                    iconv("UTF-8", "Windows-1252//TRANSLIT", $nom),
                    iconv("UTF-8", "Windows-1252//TRANSLIT", $prenom),
                    iconv("UTF-8", "Windows-1252//TRANSLIT", $titre),
                    iconv("UTF-8", "Windows-1252//TRANSLIT", $telephone),
                    iconv("UTF-8", "Windows-1252//TRANSLIT", $email)
                );

                $k++;
            }

            if ($k<$maxContact) {
                for ($i=$k; $i<$maxContact; $i++) {
                    array_push($body,
                        "",
                        "",
                        "",
                        "",
                        "",
                        ""
                    );
                }
            }
        } else {
            for ($i=0; $i<$maxContact; $i++) {
                array_push($body,
                    "",
                    "",
                    "",
                    "",
                    "",
                    ""
                );
            }
        }

        return $body;
    }

    /**
     * @param $data
     * @return array
     */
    public function getBodyAgence($data): array
    {
        $body = array();
        if (!empty($data)) {
            foreach ($data as $row) {
                $nom = (!empty($row->getNom())) ? $row->getNom() : '';
                $adresse = (!empty($row->getAdresse())) ? $row->getAdresse() : '';
                $code_postal = (!empty($row->getCodePostal())) ? $row->getCodePostal() : '';
                $ville = (!empty($row->getVille())) ? $row->getVille() : '';
                $telephone= (!empty($row->getTelephone())) ? $row->getTelephone() : '';
                $email = (!empty($row->getEmail()))? $row->getEmail() : '';

                array_push($body,
                    iconv("UTF-8", "Windows-1252//TRANSLIT",$nom),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",$adresse),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",$code_postal),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",$ville),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",$telephone),
                    iconv("UTF-8", "Windows-1252//TRANSLIT",$email)
                );
            }
        }

        return $body;
    }

    /**
     * @param $partenaireId
     * @param $partenaireType
     * @param $partenaireRaisonSociale
     * @param $score
     * @param $listCommentaire
     * @return Html2Pdf
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function createPdfRating(
        $partenaireId,
        $partenaireType,
        $partenaireRaisonSociale,
        $score,
        $listCommentaire
    ): Html2Pdf
    {
        $html2pdf = new Html2Pdf(
          'P',
          'A4',
          'fr',
          true,
          'UTF-8'
        );

        $content = $this->twig->render(
            'BackOffice/Rating/export.html.twig',
            [
                'partenaireId'              => $partenaireId,
                'partenaireType'            => $partenaireType,
                'partenaireRaisonSociale'   => $partenaireRaisonSociale,
                'score'                     => $score,
                'listCommentaire'           => $listCommentaire,
            ]
        );

        $html2pdf->writeHTML($content);

        return $html2pdf;
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/
}

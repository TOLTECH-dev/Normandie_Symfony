<?php

namespace App\Service;

use App\Entity\Admin_coordonnee;
use App\Entity\EPCI_;
use App\Entity\Partenaire_;
use App\Entity\Structure_;
use App\Entity\Logement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Environment;

class AdminCoordonneeService extends AbstractController
{
    private EntityManagerInterface $em;

    private const PATH = 'https://api-adresse.data.gouv.fr/search/';

    public const TYPE_PARTENAIRE_AGENCE_CODE = 0;
    public const TYPE_PARTENAIRE_AGENCE_SLUG = '0 | partenaire_agence';
    public const TYPE_STRUCTURE_PERMANENCE_CODE = 1;
    public const TYPE_STRUCTURE_PERMANENCE_SLUG = '1 | structure_permanence';
    public const TYPE_LOGEMENT_CODE = 2;
    public const TYPE_LOGEMENT_SLUG = '2 | logement';
    public const TYPE_EPCI_PERMANENCE_CODE = 3;
    public const TYPE_EPCI_PERMANENCE_SLUG = '3 | epci_permanence';
    public const TYPE_ALL_CODE = 99;

    /**
     * @var array<int, string>
     */
    public static array $arrayType = [
        self::TYPE_PARTENAIRE_AGENCE_CODE => self::TYPE_PARTENAIRE_AGENCE_SLUG,
        self::TYPE_STRUCTURE_PERMANENCE_CODE => self::TYPE_STRUCTURE_PERMANENCE_SLUG,
        self::TYPE_LOGEMENT_CODE => self::TYPE_LOGEMENT_SLUG,
        self::TYPE_EPCI_PERMANENCE_CODE => self::TYPE_EPCI_PERMANENCE_SLUG
    ];

    public function __construct(
        EntityManagerInterface $em
    ) {
        $this->em = $em;
    }



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    public function createCoordonnee(int $id, int|string $type, array $arrayBefore = []): void
    {
        $list = $this->getData($id, $type);
        $this->upgradeCoordonnee($list, $arrayBefore);
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    private function getData(int $id, int|string $type): array
    {
        // 0 => Partenaire Agence
        // 1 => Structure Permanence
        // 2 => Logement
        // 3 => EPCI Permanence

        $repo_EPCI = $this->em->getRepository(EPCI_::class);
        $repo_logement = $this->em->getRepository(Logement::class);
        $repo_partenaire = $this->em->getRepository(Partenaire_::class);
        $repo_structure = $this->em->getRepository(Structure_::class);

        switch ($type) {
            case self::TYPE_PARTENAIRE_AGENCE_CODE:
                $list_partenaire = [$repo_partenaire->find($id)];
                $list_structure = [];
                $list_logement = [];
                $list_EPCI = [];
                break;
            case self::TYPE_STRUCTURE_PERMANENCE_CODE:
                $list_partenaire = [];
                $list_structure = [$repo_structure->find($id)];
                $list_logement = [];
                $list_EPCI = [];
                break;
            case self::TYPE_LOGEMENT_CODE:
                $list_partenaire = [];
                $list_structure = [];
                $list_logement = [$repo_logement->find($id)];
                $list_EPCI = [];
                break;
            case self::TYPE_EPCI_PERMANENCE_CODE:
                $list_partenaire = [];
                $list_structure = [];
                $list_logement = [];
                $list_EPCI = [$repo_EPCI->find($id)];
                break;
            default:
                $list_partenaire = [];
                $list_structure = [];
                $list_logement = [];
                $list_EPCI = [];
                break;
        }

        return [
            'partenaire' => $list_partenaire,
            'structure' => $list_structure,
            'logement' => $list_logement,
            'EPCI' => $list_EPCI
        ];
    }

    private function upgradeCoordonnee(array $list, array $arrayBefore): void
    {
        $repo_coordonnee = $this->em->getRepository(Admin_coordonnee::class);

        $listCoordonnee = $this->getCoordonneeFromAPI($list);
        foreach ($listCoordonnee as $item) {
            if (!empty($item)) {
                foreach ($item as $row) {
                    $coordonnee = $repo_coordonnee->findOneBy([
                        'objectId' => $row['ID'],
                        'type' => $row['TYPE']
                    ]);

                    if ($coordonnee) {
                        $coordonnee->setDateModif(new \DateTime());
                        $coordonnee->setAuteurModif('AUTOMATE');
                        $coordonnee->setLatitude($row['LAT']);
                        $coordonnee->setLongitude($row['LONG']);
                    } else {
                        $coordonnee = new Admin_coordonnee();
                        $coordonnee->setObjectId($row['ID']);
                        $coordonnee->setType($row['TYPE']);
                        $coordonnee->setLatitude($row['LAT']);
                        $coordonnee->setLongitude($row['LONG']);
                    }

                    $this->em->persist($coordonnee);

                    // Remove Object from initial array
                    if (!empty($arrayBefore)) {
                        unset($arrayBefore[$row['ID']]);
                    }
                }
            }
        }

        $this->em->flush();
        $this->em->clear();

        // Delete Object coordonnee
        if (!empty($arrayBefore)) {
            foreach ($arrayBefore as $key => $value) {
                $coordonnee = $repo_coordonnee->findOneBy([
                    'objectId' => $key,
                    'type' => $value
                ]);

                if ($coordonnee) {
                    $this->em->remove($coordonnee);
                }
            }

            $this->em->flush();
            $this->em->clear();
        }
    }

    private function getCoordonneeFromAPI(array $list): array
    {
        $data_partenaire = [];
        $data_structure = [];
        $data_logement = [];
        $data_EPCI = [];

        foreach ($list as $key => $item) {
            if ('partenaire' === $key && !empty($item)) {
                foreach ($item as $row) {
                    foreach ($row->getPartenaireAgence() as $value) {
                        $url = self::PATH . "?q=" . urlencode($value->getAdresse() . ',' . $value->getVille()) . "&limit=" . urlencode('1') . "&postcode=" . urlencode($value->getCodePostal());

                        $result = $this->getRequest($url);
                        if ($result) {
                            $line = [
                                'ID' => $value->getId(),
                                'TYPE' => self::TYPE_PARTENAIRE_AGENCE_SLUG,
                                'LAT' => $result[1],
                                'LONG' => $result[0],
                            ];
                            $data_partenaire[] = $line;
                        }
                    }
                }
            }

            if ('structure' === $key && !empty($item)) {
                foreach ($item as $row) {
                    foreach ($row->getStructurePermanence() as $value) {
                        $url = self::PATH . "?q=" . urlencode($value->getAdresse() . ',' . $value->getVille()) . "&limit=" . urlencode('1') . "&postcode=" . urlencode($value->getCodePostal());

                        $result = $this->getRequest($url);
                        if ($result) {
                            $line = [
                                'ID' => $value->getId(),
                                'TYPE' => self::TYPE_STRUCTURE_PERMANENCE_SLUG,
                                'LAT' => $result[1],
                                'LONG' => $result[0],
                            ];
                            $data_structure[] = $line;
                        }
                    }
                }
            }

            if ('logement' === $key && !empty($item)) {
                foreach ($item as $value) {
                    $url = self::PATH . "?q=" . urlencode($value->getNumeroRue() . ' ' . $value->getAdresse() . ',' . $value->getVille()) . "&limit=" . urlencode('1') . "&postcode=" . urlencode($value->getCodePostal());

                    $result = $this->getRequest($url);
                    if ($result) {
                        $line = [
                            'ID' => $value->getId(),
                            'TYPE' => self::TYPE_LOGEMENT_SLUG,
                            'LAT' => $result[1],
                            'LONG' => $result[0],
                        ];
                        $data_logement[] = $line;
                    }
                }
            }

            if ('EPCI' === $key && !empty($item)) {
                foreach ($item as $row) {
                    foreach ($row->getEPCIPermanence() as $value) {
                        $url = self::PATH . "?q=" . urlencode($value->getAdresse() . "," . $value->getVille()) . "&limit=" . urlencode('1') . "&postcode=" . urlencode($value->getCodePostal());

                        $result = $this->getRequest($url);
                        if ($result) {
                            $line = [
                                'ID' => $value->getId(),
                                'TYPE' => self::TYPE_EPCI_PERMANENCE_SLUG,
                                'LAT' => $result[1],
                                'LONG' => $result[0],
                            ];
                            $data_EPCI[] = $line;
                        }
                    }
                }
            }
        }

        return [
            'partenaire' => $data_partenaire,
            'structure' => $data_structure,
            'logement' => $data_logement,
            'EPCI' => $data_EPCI
        ];
    }

    private function getRequest(string $url): array|null
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $returnData = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $returnData = json_decode($returnData, true);
        if (isset($returnData['features'][0]['geometry']['coordinates'])) {
            return $returnData['features'][0]['geometry']['coordinates'];
        }

        return null;
    }
}

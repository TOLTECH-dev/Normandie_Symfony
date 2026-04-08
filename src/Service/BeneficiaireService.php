<?php

namespace App\Service;

use App\Utils\DefaultUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Structure_;
use App\Repository\Structure_Repository;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Logement;
use App\Repository\BeneficiaireRepository;
use App\Repository\Demande_Repository;
use App\Repository\LogementRepository;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

class BeneficiaireService
{
    /**
     * @var DemandeServiceFO
     */
    private $demandeService;

    /**
     * @var EntityManagerInterface
     */
    private $EM = null;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var BeneficiaireRepository
     */
    private $beneficiaireRepository;

    /**
     * @var Demande_Repository
     */
    private $demande_Repository;

    /**
     * @var LogementRepository
     */
    private $logementRepository;

    /**
     * @var Structure_Repository
     */
    private $structureRepository;


    public function __construct(
        EntityManagerInterface $EM,
        DemandeServiceFO       $demandeServiceFO,
        UserService            $userService,
        RequestStack           $requestStack,
    ) {
        $this->demandeService = $demandeServiceFO;
        $this->EM = $EM;
        $this->userService = $userService;
        $this->requestStack = $requestStack;
        $this->beneficiaireRepository = $this->EM->getRepository(Beneficiaire::class);
        $this->demande_Repository = $this->EM->getRepository(Demande_::class);
        $this->logementRepository = $this->EM->getRepository(Logement::class);
        $this->structureRepository = $this->EM->getRepository(Structure_::class);
    }



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $beneficiaireNom
     * @param $beneficiairePrenom
     * @param $beneficiaireCodePostal
     * @param $beneficiaireVille
     * @param null $beneficiaireId
     * @return array
     * @throws Exception
     */
    public function checkDuplicate(
        $beneficiaireNom,
        $beneficiairePrenom,
        $beneficiaireCodePostal,
        $beneficiaireVille,
        $beneficiaireId = null
    )
    {
        $key = null;

        /* /////////////////////////////////////////////////////////////////
                             CREATE DUPLICATE KEY
        ///////////////////////////////////////////////////////////////// */
        $key = $beneficiaireNom . $beneficiairePrenom . $beneficiaireCodePostal . $beneficiaireVille;
        $key = DefaultUtils::formatString($key, $charset = 'utf-8');
        $key = preg_replace('/\s/', '', $key);

        /* /////////////////////////////////////////////////////////////////
                             CHECK DUPLICATED BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $rowDoublon = $this->beneficiaireRepository->searchDuplicate($key, $beneficiaireId);
        $isDuplicateKey = count($rowDoublon) > 0;

        return [
            'duplicateKey' => $key,
            'isDuplicateKey' => $isDuplicateKey
        ];
    }

    /**
     * @param $rue
     * @return mixed|null|string|string[]
     */
    public function getFormattedNomRue($rue)
    {
        $nomRue = preg_replace('/^(\d+) (bis|ter|TER|BIS|Ter|Bis)*/', '', $rue);
        $nomRue = DefaultUtils::formatString($nomRue);

        return $nomRue;
    }

    /**
     * @return array
     */
    public function getDataForListAjax()
    {
        $arrayColumnAndWhere = [];
        $andWhere = '';

        /* START of $_POST variables coming from datatable */
        $draw = $_POST["draw"]; //Counter used by DataTables to ensure that the Ajax returns from server-side processing requests are drawn in sequence
        $orderByColumnIndex = $_POST['order'][0]['column']; //Index of the sorting column (0 index based)
        $orderBy = $_POST['columns'][$orderByColumnIndex]['data']; //Get name of the sorting column from its index
        $orderType = $_POST['order'][0]['dir']; //ASC or DESC
        $start = $_POST["start"]; //Paging first record indicator
        $length = $_POST['length']; //Number of records that the table can display in the current draw
        /* END of $_POST variables */

        $globalSearch = trim(strtolower($_POST['search']['value']));

        if (!empty($globalSearch)) {
            /* /////////////////////////////////////////////////////////////////
                                    GLOBAL SEARCH
            ///////////////////////////////////////////////////////////////// */

            // CONSTUCTION DU FILTRE WHERE
            $globalSearchArray = explode(' ', $globalSearch);
            foreach ($globalSearchArray as $globalSearchWord) {
                $globalSearchWord = trim($globalSearchWord);
                for ($i = 0; $i < count($_POST['columns']); $i++) {

                    switch ($_POST['columns'][$i]['data']) {
                        case 'beneficiaireNomPrenom':
                            $arrayColumnAndWhere[] = " LCASE(b.nom) LIKE '%" . $globalSearchWord . "%' OR LCASE(b.prenom) LIKE '%" . $globalSearchWord . "%'";
                            $arrayColumnAndWhere[] = " LCASE(b.nom_SCI) LIKE '%" . $globalSearchWord . "%'";
                            break;
                        case 'beneficiaireType':
                            $arrayColumnAndWhere[] = " LCASE(SUBSTRING(b.type, 4)) LIKE '%" . $globalSearchWord . "%'";
                            break;
                        case 'beneficiaireCodePostalVille':
                            $arrayColumnAndWhere[] = " b.code_postal LIKE '%" . $globalSearchWord . "%' OR LCASE(b.ville) LIKE '%" . $globalSearchWord . "%'";
                            break;
                        case 'beneficiaireEmail':
                            $arrayColumnAndWhere[] = " LCASE(b.email) LIKE '%" . $globalSearchWord . "%'";
                            break;
                        case 'beneficiaireStructureConseiller':
                            $arrayColumnAndWhere[] = " LCASE(scr.nom) LIKE '%" . $globalSearchWord . "%' OR LCASE(scr.prenom) LIKE '%" . $globalSearchWord . "%'";
                            $arrayColumnAndWhere[] = " LCASE(sir.nom) LIKE '%" . $globalSearchWord . "%'";
                            break;
//                        case 'nombreLogement':
                        // pas de recherche faite sur le nombre de logement
//                            break;
                    }
                }
            }

            if (!empty($arrayColumnAndWhere)) {
                $andWhere = ' AND (' . implode(' OR ', $arrayColumnAndWhere) . ')';
            }
        }

        return [
            'draw' => $draw,
            'orderBy' => $orderBy,
            'orderType' => $orderType,
            'start' => $start,
            'length' => $length,
            'andWhere' => $andWhere
        ];
    }

    /**
     * @return array
     */
    public function getDataForListAjaxAssistanceBeneficiaire(Request $request)
    {
        $arrayColumnAndWhere = [];
        $andWhere = '';

        /* START of $_POST variables coming from datatable */
        $postData = $request->request->all();
        $draw = $postData["draw"] ?? 0; //Counter used by DataTables to ensure that the Ajax returns from server-side processing requests are drawn in sequence
        $orderByColumnIndex = $postData['order'][0]['column'] ?? 0; //Index of the sorting column (0 index based)
        $orderByDataColumn = $postData['columns'][$orderByColumnIndex]['data'] ?? ''; //Get name of the sorting column from its index
        $orderBy = $columnMapping[$orderByDataColumn] ?? 'b.id'; // Map to real column
        $orderType = $postData['order'][0]['dir'] ?? 'ASC'; //ASC or DESC
        $start = $postData["start"] ?? 0; //Paging first record indicator
        $length = $postData['length'] ?? 25; //Number of records that the table can display in the current draw
        /* END of $_POST variables */

        $globalSearch = trim(strtolower($postData['search']['value'] ?? ''));

        if (!empty($globalSearch)) {
            /* /////////////////////////////////////////////////////////////////
                                    GLOBAL SEARCH
            ///////////////////////////////////////////////////////////////// */

            // CONSTUCTION DU FILTRE WHERE
            $globalSearchArray = explode(' ', $globalSearch);
            foreach ($globalSearchArray as $globalSearchWord) {
                $globalSearchWord = trim($globalSearchWord);
                for ($i = 0; $i < count($postData['columns']); $i++) {

                    switch ($postData['columns'][$i]['data']) {
                        case 'beneficiaireNomPrenom':
                            $arrayColumnAndWhere[] = " LCASE(b.nom) LIKE '%" . $globalSearchWord . "%' OR LCASE(b.prenom) LIKE '%" . $globalSearchWord . "%'";
                            $arrayColumnAndWhere[] = " LCASE(b.nom_SCI) LIKE '%" . $globalSearchWord . "%'";
                            break;
                        case 'beneficiaireEmail':
                            $arrayColumnAndWhere[] = " LCASE(b.email) LIKE '%" . $globalSearchWord . "%'";
                            break;
                        case 'beneficiaireAuteurCreation':
                            $arrayColumnAndWhere[] = " b.auteur_creation LIKE '%" . $globalSearchWord . "%'";
                            break;
                    }
                }
            }

            if (!empty($arrayColumnAndWhere)) {
                $andWhere = ' AND (' . implode(' OR ', $arrayColumnAndWhere) . ')';
            }
        }

        return [
            'draw' => $draw,
            'orderBy' => $orderBy,
            'orderType' => $orderType,
            'start' => $start,
            'length' => $length,
            'andWhere' => $andWhere
        ];
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param User|null $user
     * @param $userIdParam
     * @return array
     * @throws Exception
     */
    public function getDataForAddAction(
        Request $request,
        $isFrontOffice,
        User    $user = null,
        $userIdParam = null
    ) {

        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'beneficiaire' => null,
            'userNom' => null,
            'userPrenom' => null,
            'userEmail' => null,
        ];

        if ($isFrontOffice) {
            /* *****************************************************************
                        S E C U R I T Y   R E T O U R   A R R I E R E
            ***************************************************************** */
            if (true == $request->getSession()->get('timestamp_beneficiaire')) {
                $returnData['isRedirectToRoute'] = true;
                $returnData['routeName'] = 'fo_dashboard';
                $returnData['routeParams'] = [];

                return $returnData;
            }

            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            $this->userService->checkUserSecurity($user->getId(), $userIdParam);
        }

        $beneficiaire = new Beneficiaire();
        $beneficiaire->setNom('');
        $beneficiaire->setPrenom('');
        $beneficiaire->setAuteurCreation($user->getUsername());
        $beneficiaire->setAuteurModif($user->getUsername());
        $isFranceConnect = false;

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */

            $returnData['userNom'] = $user->getLastname();
            $returnData['userPrenom'] = $user->getFirstname();
            $returnData['userEmail'] = $user->getEmail();

            // If FranceConnect =>Fill up "civilite" and "adresse"
            // (in addition to last name, first name, email)
            $session = $this->requestStack->getSession();
            $identity = $session->get('identity');

            if (!empty($identity)) {
                $isFranceConnect = true;

                $beneficiaire->setType('0 | particulier');

                if ($identity['gender']) {
                    if ('female' == $identity['gender']) {
                        $beneficiaire->setCivilite('0 | madame');
                    } else {
                        $beneficiaire->setCivilite('1 | monsieur');
                    }
                }

                // address is optional in FranceConnect
                if (!empty($identity['address'])) {
                    $listVille = $this->beneficiaireRepository->searchByCodePostal($identity['address']['postal_code']);

                    if (1 == count($listVille)) {
                        $ville = $listVille[0];
                    } else {
                        foreach ($listVille as $row) {
                            if ($identity['address']['locality'] == $row['nomVille']) {
                                $ville = $row;
                            }
                        }
                    }

                    if (!empty($ville)) {
                        $beneficiaire->setINSEE($ville['codeINSEE']);
                        $beneficiaire->setCodePostal($ville['codePostal']);
                        $beneficiaire->setVille($ville['nomVille']);

                        $formattedNomRue = $this->getFormattedNomRue($identity['address']['street_address']);
                        $listRue = $this->beneficiaireRepository->searchByCodeINSEENomRue($ville['codeINSEE'], $formattedNomRue);
                        if (!empty($listRue)) {
                            $beneficiaire->setNomRue($listRue['nomRue']);

                            $nomRueArray = explode(' ', $identity['address']['street_address']);
                            if (is_numeric($nomRueArray[0])) {
                                $beneficiaire->setNumeroRue($nomRueArray[0]);
                            }
                        }
                    }
                }
            }
        } else {
            /* /////////////////////////////////////////////////////////////////
                                    COTE BACK OFFICE
            ///////////////////////////////////////////////////////////////// */
            $conseillerId = (int)substr($user->getUsername(), 1);
            if (0 != $conseillerId) {
                $structureId = $this->structureRepository->findByConseillerId($conseillerId);
                $beneficiaire->setStructureRattachementId($structureId['id']);
                $beneficiaire->setConseillerRattachementId($conseillerId);
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $returnData['formOption'] = [
            'structureId' => null,
            'auditeurId' => null,
            'renovateurId' => null,
            'financeurId' => null,
            'structureRattachementId' => null,
            'conseillerRattachementId' => null,
            'isFranceConnect' => $isFranceConnect
        ];

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $returnData['beneficiaire'] = $beneficiaire;

        return $returnData;
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @param $userId
     * @return array
     * @throws Exception
     */
    public function manageAndGetDataForAddActionSubmitted(
        Request      $request,
        $isFrontOffice,
        Beneficiaire $beneficiaire,
        $userId
    ) {
        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => []
        ];

        $beneficiaireData = $this->checkDuplicate(
            $beneficiaire->getNom(),
            $beneficiaire->getPrenom(),
            $beneficiaire->getCodePostal(),
            $beneficiaire->getVille()
        );

        $prefixFlashBag = ($isFrontOffice) ? 'Votre' : 'La';
        if (true === $beneficiaireData['isDuplicateKey']) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                $prefixFlashBag . ' fiche Bénéficiaire existe déjà.'
            );

            if ($isFrontOffice) {
                /* /////////////////////////////////////////////////////////////////
                                        COTE FRONT OFFICE
                ///////////////////////////////////////////////////////////////// */
                $returnData['isRedirectToRoute'] = true;
                $returnData['routeName'] = 'beneficiaire_add';
                $returnData['routeParams'] = ['userId' => $userId];

                return $returnData;
            } else {
                return DefaultUtils::getDataRedirectConseillerBeneficaireListBO();
            }
        } else {
            $beneficiaire->setUserId($userId);
            $beneficiaire->setDuplicateKey($beneficiaireData['duplicateKey']);

            $this->EM->persist($beneficiaire);
            $this->EM->flush();

            if ($isFrontOffice) {
                /* /////////////////////////////////////////////////////////////////
                                        COTE FRONT OFFICE
                ///////////////////////////////////////////////////////////////// */
                $session = $this->requestStack->getSession();
                $session->set('identity', null);
                $request->getSession()->set('timestamp_beneficiaire', true);
            }

            $request->getSession()->getFlashBag()->add(
                'success',
                $prefixFlashBag . ' fiche Bénéficiaire ' . $beneficiaire->getNom() . ' a bien été créée.'
            );

            if ($isFrontOffice) {
                /* /////////////////////////////////////////////////////////////////
                                        COTE FRONT OFFICE
                ///////////////////////////////////////////////////////////////// */
                return DefaultUtils::getDataRedirectLogementListFO($beneficiaire->getId());
            } else {
                return DefaultUtils::getDataRedirectConseillerBeneficaireListBO();
            }
        }
    }

    /**
     * @param $beneficiaireId
     * @return array
     * @throws Exception
     */
    public function getDataForViewAction($beneficiaireId)
    {
        $beneficiaire = $this->beneficiaireRepository->findAllCustomById($beneficiaireId);
        $nombreDemandeForEditDenied = $this->demande_Repository->findCountByBeneficiaireAndLogementForEditDenied($beneficiaireId);
        $isEditBeneficiaire = empty($nombreDemandeForEditDenied);
        $isDeleteBeneficiaire = (true === $this->isBeneficiaireWithoutLogement($beneficiaireId));

        return [
            'beneficiaire' => $beneficiaire,
            'isEditBeneficiaire' => $isEditBeneficiaire,
            'isDeleteBeneficiaire' => $isDeleteBeneficiaire
        ];
    }

    /**
     * @param $isFrontOffice
     * @param $beneficiaireId
     * @param User|null $user
     * @return array
     */
    public function getDataForEditAction(
        $isFrontOffice,
        $beneficiaireId,
        User $user = null
    ) {

        $returnData = [
            'isRedirectToRoute' => false,
            'routeName' => '',
            'routeParams' => [],
            'formOption' => [],
            'beneficiaire' => null,
            'userNom' => null,
            'userPrenom' => null,
            'userEmail' => null,
        ];
        /* *****************************************************************
                       S E C U R I T Y   B E N E F I C I A I R E
        ***************************************************************** */
        $this->demandeService->checkEditLogementBeneficiaire($beneficiaireId, null);


        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $beneficiaire = $this->beneficiaireRepository->find($beneficiaireId);
        $isFranceConnect = false;

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */

            /* *****************************************************************
                                  U S E R   S E C U R I T Y
            ***************************************************************** */
            $this->userService->checkUserSecurity($user->getId(), $beneficiaire->getUserId());

            $returnData['userNom'] = $user->getLastname();
            $returnData['userPrenom'] = $user->getFirstname();
            $returnData['userEmail'] = $user->getEmail();

            if (!empty($user)) {
                $isFranceConnect = $user->IsFranceConnect();
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                GET DATA FORM
        ///////////////////////////////////////////////////////////////// */
        $returnData['formOption'] = [
            'structureId' => $beneficiaire->getStructureId(),
            'auditeurId' => $beneficiaire->getAuditeurId(),
            'renovateurId' => $beneficiaire->getRenovateurId(),
            'financeurId' => $beneficiaire->getFinanceurId(),
            'structureRattachementId' => $beneficiaire->getStructureRattachementId(),
            'conseillerRattachementId' => $beneficiaire->getConseillerRattachementId(),
            'isFranceConnect' => $isFranceConnect
        ];

        /* /////////////////////////////////////////////////////////////////
                                GET BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        $returnData['beneficiaire'] = $beneficiaire;

        return $returnData;
    }

    /**
     * @param Request $request
     * @param $isFrontOffice
     * @param Beneficiaire $beneficiaire
     * @return array
     * @throws Exception
     */
    public function manageAndGetDataForEditActionSubmitted(
        Request      $request,
        $isFrontOffice,
        Beneficiaire $beneficiaire
    ) {
        $beneficiaireData = $this->checkDuplicate(
            $beneficiaire->getNom(),
            $beneficiaire->getPrenom(),
            $beneficiaire->getCodePostal(),
            $beneficiaire->getVille(),
            $beneficiaire->getId()
        );

        $prefixFlashBag = ($isFrontOffice) ? 'Votre' : 'La';
        if (true === $beneficiaireData['isDuplicateKey']) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                $prefixFlashBag . ' fiche Bénéficiaire existe déjà.'
            );
        } else {
            $beneficiaire->setDateModif(new \Datetime());
            $beneficiaire->setAuteurModif($_SESSION['login']->getUsername());

            $this->EM->persist($beneficiaire);
            $this->EM->flush();

            /* /////////////////////////////////////////////////////////////////
                        CLEAN INSTRUCTION ADMINISTRATIVE / TECHNIQUE
            ///////////////////////////////////////////////////////////////// */
            $demandeIdList = $this->demande_Repository->findByBeneficiaireAndLogementForEditActionSubmitted(
                $beneficiaire->getId()
            );
            $this->demandeService->cleanInstructionRestoreStatutAndHistorise($demandeIdList, $beneficiaire, null);

            $request->getSession()->getFlashBag()->add(
                'success',
                $prefixFlashBag . ' fiche Bénéficiaire ' . $beneficiaire->getNom() . ' a été modifiée avec succès.'
            );
        }

        if ($isFrontOffice) {
            /* /////////////////////////////////////////////////////////////////
                                    COTE FRONT OFFICE
            ///////////////////////////////////////////////////////////////// */
            $request->getSession()->set('timestamp_beneficiaire', true);

            return DefaultUtils::getDataRedirectLogementListFO($beneficiaire->getId());
        } else {
            return DefaultUtils::getDataRedirectConseillerBeneficaireListBO();
        }
    }

    /**
     * @param $beneficiaireId
     * @return bool
     */
    public function isBeneficiaireWithoutLogement($beneficiaireId)
    {
        $logementList = $this->logementRepository->findBy([
            'beneficiaire_id' => $beneficiaireId
        ]);

        return empty($logementList);
    }

    /**
     * @param $beneficiaireId
     * @return object|Beneficiaire|null
     */
    public function findBeneficiaireById($beneficiaireId)
    {
        return $this->beneficiaireRepository->find($beneficiaireId);
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/
}

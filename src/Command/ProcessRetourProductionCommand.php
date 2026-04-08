<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Production_;
use App\Entity\Titre;
use App\Entity\User;
use App\Repository\Demande_Repository;
use App\Repository\Demande_statutRepository;
use App\Repository\Production_Repository;
use App\Repository\UserRepository;
use App\Service\DemandeServiceFO;
use App\Service\HistoriqueService;
use App\Service\TitreService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'normandie:processRetourProduction',
    description: 'Lancer l\'intégration du retour de Production'
)]
class ProcessRetourProductionCommand extends Command
{

    private EntityManagerInterface $entityManager;
    private DemandeServiceFO $demandeServiceFO;
    private TitreService $titreService;
    private UserRepository $userRepository;
    private Demande_Repository $demandeRepository;
    private ParameterBagInterface $parameterBag;
    private Demande_statutRepository $demandeStatutRepository;
    private HistoriqueService $historiqueService;
    private Production_Repository $productionRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        DemandeServiceFO       $demandeServiceFO,
        TitreService           $titreService,
        ParameterBagInterface  $parameterBag,
        HistoriqueService $historiqueService,
        string                 $name = null
    ) {
        parent::__construct($name);
        $this->entityManager = $entityManager;
        $this->demandeServiceFO = $demandeServiceFO;
        $this->titreService = $titreService;
        $this->parameterBag = $parameterBag;
        $this->userRepository = $entityManager->getRepository(User::class);
        $this->demandeRepository = $entityManager->getRepository(Demande_::class);
        $this->demandeStatutRepository = $entityManager->getRepository(Demande_Statut::class);
        $this->productionRepository = $entityManager->getRepository(Production_::class);
        $this->historiqueService = $historiqueService;
    }

    protected function configure(): void
    {
        $this->addArgument('userId', null, 'ID de l\'utilisateur');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('userId');
        $output->writeln('--- Start retour de Production ---');
        $this->runCustom($userId, $output);
        $output->writeln('--- End retour de Production ---');
        return Command::SUCCESS;
    }

    /**
     * @throws \Exception
     */
    private function runCustom(?int $userId, OutputInterface $output): void
    {
        $titreObject = new Titre();

        $returnGetData = $this->getData($userId);

        $isProduction = $this->parameterBag->get('is_production');

        if ($isProduction) {
            /* /////////////////////////////////////////////////////////////////
                                            GET ALL FILE
            ///////////////////////////////////////////////////////////////// */
            $this->titreService->getFilesRetourProductionAS400($returnGetData['listOPE']);
        }

        /* /////////////////////////////////////////////////////////////////
                                    VALIDATE FILE
        ///////////////////////////////////////////////////////////////// */
        $path = $this->parameterBag->get('app_root_dossier_data_symfony');
        $fluxRetourProductionDir = $path . Titre::$filenameRetourProduction;
        $returnValidateFile = $this->titreService->validateFile(
            $fluxRetourProductionDir,
            $returnGetData['listOPE'],
            $returnGetData['listDemande'],
            $returnGetData['listProduction']
        );
        /* /////////////////////////////////////////////////////////////////
                                   PERSIST FILE
       ///////////////////////////////////////////////////////////////// */
        $this->persistFile(
            $returnValidateFile,
            $titreObject,
            $returnGetData['userInfo'],
            $returnGetData['listEmailCc'],
            $output
        );
    }

    /**
     * @throws Exception
     */
    private function getData(?int $userId = null): array
    {
        $userInfo = [
            'emailTo' => null,
            'auteurCreation' => null,
            'roles' => ['ROLE_AUTOMATE']
        ];
        // Get email user / admin
        $listUser = $this->userRepository->findBy([
            'enabled' => true
        ]);
        $listEmailCc = [];

        foreach ($listUser as $row) {
            if (in_array('ROLE_ADMIN', $row->getRoles())) {
                $listEmailCc[] = $row->getEmail();
            }

            // Is connected User
            if ($userId && ($userId == $row->getId())) {
                $userInfo['emailTo'] = $row->getEmail();
                $userInfo['auteurCreation'] = $row->getUsername();
                $userInfo['roles'] = $row->getRoles();
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                GET LIST DEMANDE
        ///////////////////////////////////////////////////////////////// */
        $listDemande = $this->demandeRepository->findForTitre();

        $arrayDemande = [];
        $arrayProduction = [];
        foreach ($listDemande as $item) {
            $arrayDemande[$item['demandeId']] = $item;
            $arrayProduction[$item['productionId'] . ' | ' . $item['demandeId']] = $item;
        }
        $listOPE = [
            $this->parameterBag->get('production_auditEnergie'),
            $this->parameterBag->get('production_auditNumerique'),
            $this->parameterBag->get('production_travauxNiveau1_2'),
            $this->parameterBag->get('production_travauxNiveau_BBC1'),
            $this->parameterBag->get('production_travauxNiveau_BBC2'),
        ];
        return [
            'userInfo' => $userInfo,
            'listEmailCc' => $listEmailCc,
            'listOPE' => $listOPE,
            'listDemande' => $arrayDemande,
            'listProduction' => $arrayProduction
        ];
    }

    /**
     * @throws \Exception
     */
    private function persistFile(array $returnValidateFile, Titre $titreObject, array $userInfo, array $emailCc, OutputInterface $output): void
    {
        $statutForTitre = $this->demandeServiceFO->searchStatutForTitre();
        $statutForProduction = $this->demandeServiceFO->searchStatutForProduction();

        $demandeStatutForTitre = $this->demandeStatutRepository->findOneByStatut($statutForTitre);

        $arrayBBC = [
            $this->parameterBag->get('production_travauxNiveau_BBC1'),
            $this->parameterBag->get('production_travauxNiveau_BBC2')
        ];
        $checkPersistDemandeStatut = [];

        if (!isset($returnValidateFile['filename'])) {
            foreach ($returnValidateFile as $file) {
                if (true == $file['isValid']) {
                    // Persist file into database
                    $returnPersistFile = $this->titreService->persistFile($file['pathInfo']);
                    $demandeIdLast = 0;
                    $productionIdLast = 0;
                    foreach ($returnPersistFile['listTitre'] as $key => $value) {
                        if ($key) {
                            if ($value['productionId']) {
                                // Get Titre object
                                $titre = new Titre($userInfo['auteurCreation']);
                                $titre->setNumeroOperation($value['numeroOperation']);
                                $titre->setDemandeId($value['demandeId']);
                                $titre->setProductionId($value['productionId']);
                                $titre->setNumeroChequier($value['numeroChequier']);
                                $titre->setNumeroCheque($key);
                                $titre->setTypeCheque((string)$value['typeCheque']);
                                $titre->setValeurTitre((string)$value['valeurTitre']);
                                $titre->setDateEmission($value['dateFormatEmissionTitre']);
                                $titre->setDateValidite($value['dateFormatValiditeTitre']);
                                $titre->setCodeEtat($value['codeEtatTitre']);

                                $this->entityManager->persist($titre);

                                // Get Production object
                                if ($productionIdLast != $value['productionId']) {
                                    $production = $this->productionRepository->find($value['productionId']);
                                    $production->setDateProduction(new \DateTime());
                                    $this->entityManager->persist($production);
                                }

                                $persistStatut = true;
                                if (in_array($value['numeroOperation'], $arrayBBC)) {
                                    $checkPersistDemandeStatut[$value['demandeId']][] = $value['numeroOperation'];
                                    $checkPersistDemandeStatut[$value['demandeId']] = array_unique($checkPersistDemandeStatut[$value['demandeId']]);

                                    $persistStatut = count($checkPersistDemandeStatut[$value['demandeId']]) == 2;
                                }
                                // Get Demande object
                                if ($demandeIdLast != $value['demandeId']) {

                                    $demande = $this->demandeRepository->findOneBy([
                                        'id' => $value['demandeId'],
                                        'statut_id' => $statutForProduction
                                    ]);
                                    if ($demande && $persistStatut) {
                                        $demande->setStatutId($statutForTitre);

                                        // MISE A JOUR DEMANDE STATUT DESCRIPTION
                                        $demande->setStatutDescription($demandeStatutForTitre->getDescription());

                                        $this->entityManager->persist($demande);
                                    }

                                    /* /////////////////////////////////////////////////////////////////
                                                        FILL UP HISTORIQUE
                                    ///////////////////////////////////////////////////////////////// */
                                    $this->historiqueService->save(
                                        $value['demandeId'],
                                        $statutForTitre,
                                        $demande->getType(),
                                        $userInfo['roles'],
                                        false,
                                        'Lancement du retour de Production'
                                    );
                                }

                                // Avoid doublon
                                $productionIdLast = $value['productionId'];
                            } else {
                                throw new \Exception("Production Id incorrecte.");
                            }
                            // Avoid doublon
                            $demandeIdLast = $value['demandeId'];
                        } else {
                            throw new \Exception("Demande Id incorrecte.");
                        }
                    }
                    $this->entityManager->flush();


                    // Generate report
                    $this->titreService->generateReport(
                        $titreObject,
                        [
                            'reportKey' => 1,
                            'content' => $returnPersistFile['reportContent'],
                            'filename' => $file['pathInfo']['filename']
                        ],
                        [
                            'subject' => 'Normandie: Lancement du retour de Production',
                            'templatePath' => 'BackOffice/Titre/email/reportLancement.html.twig',
                            'emailTo' => $userInfo['emailTo'],
                            'listEmailBcc' => null,
                            'listEmailCc' => $emailCc
                        ],
                        [
                            'fluxDir' => Titre::$filenameRetourProduction
                        ]
                    );
                } elseif (false == $file['isValid']) {
                    // Generate report
                    $this->titreService->generateReport(
                        $titreObject,
                        [
                            'reportKey' => 0,
                            'content' => $file['reportContent'],
                            'filename' => $file['pathInfo']['filename']
                        ],
                        [
                            'subject' => 'Normandie: Lancement du retour de Production',
                            'templatePath' => 'BackOffice/Titre/email/reportLancement.html.twig',
                            'emailTo' => $userInfo['emailTo'],
                            'listEmailBcc' => null,
                            'listEmailCc' => $emailCc
                        ],
                        [
                            'fluxDir' => Titre::$filenameRetourProduction
                        ]
                    );
                } else {
                    throw new \Exception('Erreur interne.');
                }
            }
            $this->entityManager->clear();
        } else {
            $output->writeln('--- Aucun retour de Production à lancer ---');
        }
    }
}

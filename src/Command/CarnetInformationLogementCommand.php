<?php

namespace App\Command;

use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use App\Repository\Remboursement_Repository;
use App\Entity\Demande_;
use App\Repository\Demande_Repository;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\APIService;
use App\Service\CarnetInformationLogementService;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:carnetLogement',
    description: 'Créer un Carnet d\'Information du Logement et ses relations',
)]
class CarnetInformationLogementCommand extends Command
{
    private APIService $APIService;
    private EntityManagerInterface $entityManager;
    private CarnetInformationLogementService $carnetInformationLogementService;
    private Demande_Repository $demandeRepository;
    private Remboursement_Repository $remboursementRepository;
    private UserRepository $userRepository;
    private MailerService $mailerService;
    private int $productionTravauxNiveauBBC2;


    public function __construct(
        APIService                       $APIService,
        EntityManagerInterface           $entityManager,
        CarnetInformationLogementService $carnetInformationLogementService,
        Demande_Repository               $demandeRepository,
        Remboursement_Repository         $remboursementRepository,
        UserRepository                   $userRepository,
        MailerService                    $mailerService,
        int                              $productionTravauxNiveauBBC2
    )
    {
        parent::__construct();
        $this->APIService = $APIService;
        $this->entityManager = $entityManager;
        $this->carnetInformationLogementService = $carnetInformationLogementService;
        $this->demandeRepository = $demandeRepository;
        $this->remboursementRepository = $remboursementRepository;
        $this->userRepository = $userRepository;
        $this->mailerService = $mailerService;
        $this->productionTravauxNiveauBBC2 = $productionTravauxNiveauBBC2;
    }


    /* *****************************************************************
    ********************************************************************
                            PROTECTED FUNCTION
    ********************************************************************
    *******************************************************************/

    protected function configure(): void
    {
        $this->addOption(
            'demandeId',
            null,
            InputOption::VALUE_REQUIRED
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('--- Begin Carnet Information Logement Process ---');
        try {
            $this->process($input, $io);
            $io->info('--- End Carnet Information Logement Process ---');

            return Command::SUCCESS;
        } catch (\Throwable $th) {
            $io->error($th->getMessage());

            return Command::FAILURE;
        }
    }

    /* *****************************************************************
    ********************************************************************
                            PRIVATE FUNCTION
    ********************************************************************
    *******************************************************************/

    private function process(InputInterface $input, SymfonyStyle $io): void
    {
        if (!$input->getOption('demandeId')) {
            throw new \Exception('Manque demandeId');
        }

        /**
         * @var Demande_ $demande
         */
        $demande = $input->getOption('demandeId') ? $this->demandeRepository->find($input->getOption('demandeId')) : null;

        if (empty($demande))
            return;

        // ETAPE
        if (empty($demande->getCarnetInformationCLEAEtapeCode())) {
            $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_GET_AUTH_TOKEN_ETAPE_CODE);
        }

        $returnTokenArray = $this->APIService->callApiCLEAGetAuthToken();
        if (!empty($returnTokenArray['access_token'])) {

            // ETAPE
            if (APIService::API_CLEA_GET_AUTH_TOKEN_ETAPE_CODE == $demande->getCarnetInformationCLEAEtapeCode()) {
                $demande->setCarnetInformationCLEAEtapeCode(APIService::API_CLEA_CARNET_CREATE_ETAPE_CODE);
            }

            $remboursementRow = $this->remboursementRepository->findByDemandeAndRemboursementTermine($demande->getId(), $this->productionTravauxNiveauBBC2);
            $etape = $demande->getCarnetInformationCLEAEtapeCode();

            $status = APIService::API_CLEA_CODE_STATUS_SUCCESS;
            while ($etape != APIService::API_CLEA_FINISHED_SUCCESS_ETAPE_CODE && $status == APIService::API_CLEA_CODE_STATUS_SUCCESS) {

                $dataEtapeCLEA = $this->carnetInformationLogementService->doEtapeCLEAAndGetData(
                    $demande,
                    $remboursementRow['remboursementId'],
                    $remboursementRow['remboursementStatutId'],
                    $returnTokenArray['access_token'],
                    $etape,
                    $demande->getCarnetInformationCLEAId()
                );

                $etape = $dataEtapeCLEA['newEtape'];

                if ($dataEtapeCLEA['CLEAStatus'] != APIService::API_CLEA_CODE_STATUS_SUCCESS) {
                    // ENVOI EMAIL ERREUR AUX ADMIN
                    $CLEAMessageComplet = !empty($dataEtapeCLEA['CLEAMessage']) ? $dataEtapeCLEA['CLEAMessage'] : '';
                    $CLEAMessageComplet .= '<br>Erreur sur l\'étape ' . APIService::$API_CLEA_ETAPE[$demande->getCarnetInformationCLEAEtapeCode()];
                    $CLEAMessageComplet .= '<br><br>Détails erreur:<br>' . (!empty($dataEtapeCLEA['CLEADetail']) ? $dataEtapeCLEA['CLEADetail'] : '');

                    $bodyEmail = 'Référence dossier : ' . $demande->getId() . '<br><br>Bonjour,<br>Une erreur est survenue lors des appels API CLEA: ' . $CLEAMessageComplet;
                    $this->sendRapportErrorAdmin('Carnet d\'information CLEA - Erreur appel API', $bodyEmail, $io);

                    break;
                }
            }

        } else {
            // ENVOI EMAIL ERREUR AUX ADMIN
            $CLEAMessage = !empty($returnTokenArray['message']) ? $returnTokenArray['message'] : 'Erreur';
            $bodyEmail = 'Référence dossier : ' . $demande->getId() . '<br><br>Bonjour,<br>Une erreur est survenue lors des appels API CLEA: ' . $CLEAMessage;
            $this->sendRapportErrorAdmin('Carnet d\'information CLEA - Erreur appel API', $bodyEmail, $io);
        }

        $io->info('carnetInformationCLEAId = ' . $demande->getCarnetInformationCLEAId());
        $io->info('Etape: ' . APIService::$API_CLEA_ETAPE[$demande->getCarnetInformationCLEAEtapeCode()]);

        $this->entityManager->flush();
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    private function sendRapportErrorAdmin(string $subject, string $body, SymfonyStyle $io): bool
    {
        $listEmailCc = $this->userRepository->getList(
            [
                User::PARAM_ROLE_ADMIN
            ],
            true,
            true,
            true
        );

        $result = $this->mailerService->sendGeneriqueEmail(
            $subject,
            $body,
            null,
            null,
            null,
            'text/html',
            'UTF-8',
            null,
            null,
            $listEmailCc,
        );

        if ($result !== 1) {
            $io->error('Erreur envoi email sujet ' . $subject);
        }

        return $result === 1;
    }
}

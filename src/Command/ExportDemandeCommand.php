<?php

namespace App\Command;

use App\Service\MailerService;
use App\Utils\DefaultUtils;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use App\Entity\ExportDemande;
use App\Repository\ExportDemandeRepository;
use App\Service\DemandeServiceBO;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class ExportDemandeCommand extends Command
{
    protected static $defaultName = 'normandie:exportDemande';
    protected static $defaultDescription = 'Lancer l\'export des demandes';

    private ExportDemandeRepository $exportDemandeRepository;
    private UserRepository $userRepository;
    private DemandeServiceBO $demandeService;
    private MailerService $mailerService;
    private ParameterBagInterface $parameterBag;

    public function __construct(
        ExportDemandeRepository $exportDemandeRepository,
        UserRepository          $userRepository,
        DemandeServiceBO        $demandeService,
        MailerService           $mailerService,
        ParameterBagInterface   $parameterBag
    )
    {
        parent::__construct();
        $this->exportDemandeRepository = $exportDemandeRepository;
        $this->userRepository = $userRepository;
        $this->demandeService = $demandeService;
        $this->mailerService = $mailerService;
        $this->parameterBag = $parameterBag;
    }


    /* *****************************************************************
    ********************************************************************
                            PROTECTED FUNCTION
    ********************************************************************
    *******************************************************************/

    protected function configure(): void
    {
        $this
            ->addOption(
                'exportId',
                null,
                InputOption::VALUE_REQUIRED
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Start Export Demande');

        try {
            $data = $this->process($input);
            $result = $this->sendMail($data);

            if ($result > 0) {
                $io->success(sprintf(
                    'L\'export Demande a été envoyé à %s',
                    $data['recipient']
                ));
            } else {
                $io->error('L\'export Demande a échoué');
            }

            $io->success('End Export Demande');
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /* *****************************************************************
    ********************************************************************
                            PRIVATE FUNCTION
    ********************************************************************
    *******************************************************************/


    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Exception
     */
    private function process(InputInterface $input): array
    {
        $exportId = $input->getOption('exportId');

        if (!$exportId) {
            throw new \Exception('Manque exportId');
        }


        /* /////////////////////////////////////////////////////////////////
                                OBJECT - EXPORT DEMANDE
        ///////////////////////////////////////////////////////////////// */
        /**
         * @var ExportDemande $exportDemande
         */
        $exportDemande = $this->exportDemandeRepository->find($exportId);
        if (!$exportDemande) {
            throw new \RuntimeException('ExportDemande introuvable');
        }

        /**
         * @var User $user
         */
        $user = $this->userRepository->find($exportDemande->getDestinataireUserId());

        /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                                    GENERATE FILE
        +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $exportDir = $this->parameterBag->get('app_root_dossier_data_symfony') . $exportDemande::$filename;
        DefaultUtils::createDirectory($exportDir, 0775, true);
        $fileName = "Demande-" . $exportDemande->getId() . "-" . $exportDemande->getDateCreate()->format('Ymd') . "." . DefaultUtils::FILE_CODE_CSV;
        $filePath = $exportDir . "/" . $fileName;

        $handle = fopen($filePath, 'w+');
        //$BOM = "\xEF\xBB\xBF"; // UTF-8 with BOM
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $option = [
            'roles' => $user->getRoles(),
            'username' => $user->getUsername(),
            'production_travauxNiveau_BBC1' => $this->parameterBag->get('production_travauxNiveau_BBC1'),
            'production_travauxNiveau_BBC2' => $this->parameterBag->get('production_travauxNiveau_BBC2')
        ];

        $this->demandeService->fputTitleAndContentExport(
            $handle,
            $option,
            $exportDemande->getWhereQuery()
        );

        fclose($handle);

        chmod($filePath, 0775);
        chown($filePath, 'www-data');

        /* /////////////////////////////////////////////////////////////////
                                    GENERATE ZIP
        ///////////////////////////////////////////////////////////////// */
        $zipName = "Demande-" . $exportDemande->getId() . "-" . $exportDemande->getDateCreate()->format('Ymd') . "." . DefaultUtils::FILE_CODE_ZIP;
        $zipPath = $exportDir . "/" . $zipName;

        if (!file_exists($zipPath)) {
            $zipObject = new \ZipArchive();

            if ($zipObject->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
                exit("Echec lors de la création de l'archive <$zipPath>\n");
            }

            $fileName_ = "Demande-" . $exportDemande->getId() . "-";
            $listFile = glob($exportDir . '/' . $fileName_ . '*');
            if (!empty($listFile)) {
                foreach ($listFile as $item) {
                    $file = basename($item);
                    $zipObject->addFile($exportDir . '/' . $file, $file);
                }
            }
            $zipObject->close();
            unlink($filePath);
        }
        chmod($zipPath, 0775);
        chown($zipPath, 'www-data');

        return [
            'isValid' => true,
            'zipPath' => $zipPath,
            'recipient' => $user->getEmail()
        ];
    }

    private function sendMail(array $data): int
    {
        return $this->mailerService->sendGeneriqueEmail(
            'Export Demande : ' . basename($data['zipPath']),
            'Export Demande',
            null,
            $data['recipient'],
            null,
            'text/html',
            'UTF-8',
            null,
            null,
            null,
            null,
            $data['zipPath'],
            [],
            true
        );
    }
}

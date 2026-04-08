<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\ExportADEME;
use App\Service\DemandeServiceBO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Utils\DefaultUtils;

/**
 * Class ExportADEMECommand
 */
class ExportADEMECommand extends Command
{

    private UserRepository $userRepository;
    private DemandeServiceBO $demandeService;
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private string $mailerAddressFrom;
    private string $appRootDossierDataSymfony;

    public function __construct(
        UserRepository $userRepository,
        DemandeServiceBO $demandeService,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        string $mailerAddressFrom,
        string $appRootDossierDataSymfony
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->demandeService = $demandeService;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->mailerAddressFrom = $mailerAddressFrom;
        $this->appRootDossierDataSymfony = $appRootDossierDataSymfony;
    }



    /* *****************************************************************
    ********************************************************************
                            PROTECTED FUNCTION
    ********************************************************************
    *******************************************************************/

    protected function configure(): void
    {
        $this
            ->setName('normandie:exportADEME')
            ->setDescription('Lancer l\'export ADEME')
            ->addOption(
                'date',
                null,
                InputOption::VALUE_OPTIONAL
            )
        ;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        echo '--- Start Export ADEME ---' . "\n";

        $returnGetData = $this->process($input);

        if (!$returnGetData['isValid']) {

            echo $returnGetData['msgError'] . "\n";
            echo "--- L'Export ADEME a échoué ---" . "\n";

            return Command::FAILURE;

        } else {

            $returnSendMail = $this->sendMail($returnGetData['arrayDataEmails']);

            foreach ($returnGetData['arrayDataEmails'] as $dataEmail) {
                // Delete export file
                if (file_exists($dataEmail['filePath'])) {
                    unlink($dataEmail['filePath']);
                }
            }

            if (empty($returnSendMail['txtErrorEmail'])) {
                echo "--- L'Export ADEME a été envoyé à " . implode(', ', $returnSendMail['listEmailTo']) . " ---" . "\n";
            } else {
                echo $returnSendMail['txtErrorEmail'] . "\n";
                echo "--- L'Export ADEME a échoué ---" . "\n";

                return Command::FAILURE;
            }
        }

        echo '--- End Export ADEME ---' . "\n";

        return Command::SUCCESS;
    }

    /* *****************************************************************
    ********************************************************************
                            PRIVATE FUNCTION
    ********************************************************************
    *******************************************************************/

    /**
     * @throws \Exception
     */
    private function process(InputInterface $input): array
    {
        /* /////////////////////////////////////////////////////////////////
                    GET DEMANDE SERVICE
        ///////////////////////////////////////////////////////////////// */
        $dateReferenceParam = !empty($input->getOption('date')) ? $input->getOption('date') : date('Y-m-d');

        if (!empty($input->getOption('date'))) {

            $month = substr($dateReferenceParam, 5, 2);
            $day = substr($dateReferenceParam, 8, 2);
            $year = substr($dateReferenceParam, 0, 4);

            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                return [
                    'isValid'  => false,
                    'filePath' => null,
                    'msgError' => 'Date ou format de date non valide (format attendu: YYYY-MM-DD)'
                ];
            }
        }

        $dateReferenceDateTime = \DateTime::createFromFormat('Y-m-d', $dateReferenceParam);

        /* /////////////////////////////////////////////////////////////////
                            OBJECT - EXPORT ADEME
        ///////////////////////////////////////////////////////////////// */
        $exportADEME = new ExportADEME();
        $exportADEME->setDateReference($dateReferenceDateTime);
        $this->entityManager->persist($exportADEME);
        $this->entityManager->flush();
        $this->entityManager->clear();


        $exportDir = $this->appRootDossierDataSymfony . $exportADEME::$filename;
        DefaultUtils::createDirectory($exportDir, 0775, true);
        /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                                GENERATE FILE ADEME A03
        +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $fileNameADEMEA03 = 'SARE_DonnéesIndicateurs_Normandie-A3_' . $exportADEME->getDateReference()->format('Ymd') . "." . DefaultUtils::FILE_CODE_DATA;
        $filePathADEMEA03 = $exportDir . "/" . $fileNameADEMEA03;
        $isValidADEMEA03 = $this->demandeService->fputCSVContentExportADEMEA03($filePathADEMEA03, $dateReferenceParam);

        /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                                GENERATE FILE ADEME A05
        +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $fileNameADEMEA05 = 'SARE_DonnéesIndicateurs_Normandie-A5_' . $exportADEME->getDateReference()->format('Ymd') . "." . DefaultUtils::FILE_CODE_DATA;
        $filePathADEMEA05 = $exportDir . "/" . $fileNameADEMEA05;
        $isValidADEMEA05 = $this->demandeService->fputCSVContentExportADEMEA05($filePathADEMEA05, $dateReferenceParam);

        $arrayDataEmails = [
            0 => [
                    'filePath'             => $filePathADEMEA03,
                    'prefixSubjectAndBody' => 'Export ADEME A03'
                ],
            1 => [
                    'filePath'             => $filePathADEMEA05,
                    'prefixSubjectAndBody' => 'Export ADEME A05'
            ]
        ];

        return [
            'isValid'         => ($isValidADEMEA03 && $isValidADEMEA05),
            'arrayDataEmails' => $arrayDataEmails,
            'msgError'        => null
        ];
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendMail(array $arrayDataEmails = []): array
    {
        $listEmailTo = $this->userRepository->getList(
            [
                User::PARAM_ROLE_CLIENT
            ],
            true,
            true,
            true
        );
        $listEmailBcc = $this->userRepository->getList(
            [
                User::PARAM_ROLE_ADMIN
            ],
            true,
            true,
            true
        );

        $txtErrorEmail = '';

        foreach ($arrayDataEmails as $dataEmail) {

            $email = (new Email())
                ->from($this->mailerAddressFrom)
                ->to(...$listEmailTo)
                ->cc(...$listEmailBcc)
                ->subject($dataEmail['prefixSubjectAndBody'] . ' : ' . basename($dataEmail['filePath']))
                ->html($dataEmail['prefixSubjectAndBody']);

            if ($dataEmail['filePath'] && file_exists($dataEmail['filePath'])) {
                $email->attachFromPath($dataEmail['filePath']);
            }

            try {
                $this->mailer->send($email);
            } catch (\Exception $e) {
                $txtErrorEmail .= 'Erreur envoi email sujet ' . $dataEmail['prefixSubjectAndBody'] . ': ' . $e->getMessage() . "\n";
            }
        }

        return [
            'txtErrorEmail' => $txtErrorEmail,
            'listEmailTo'   => $listEmailTo
        ];
    }
}

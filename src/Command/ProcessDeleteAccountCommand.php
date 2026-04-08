<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Entity\Beneficiaire;
use App\Entity\Logement;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:processDeleteAccount',
    description: 'Lancer la suppression des comptes',
)]
class ProcessDeleteAccountCommand extends Command
{
    private EntityManagerInterface $em;
    private LoggerInterface $commandDeleteAccountLogger;
    private UserRepository $userRepository;
    
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $commandDeleteAccountLogger,
        UserRepository $userRepository
    ) {
        parent::__construct();
        $this->em = $em;
        $this->commandDeleteAccountLogger = $commandDeleteAccountLogger;
        $this->userRepository = $userRepository;
    }


    /* *****************************************************************
    ********************************************************************
                            PROTECTED FUNCTION
    ********************************************************************
    *******************************************************************/
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->commandDeleteAccountLogger->info('--- ProcessDeleteAccountCommand: Start ---', []);
        try {
            $this->runCustom();
            $this->commandDeleteAccountLogger->info('--- ProcessDeleteAccountCommand: End ---', []);

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
     */
    private function runCustom(): void
    {
        /* /////////////////////////////////////////////////////////////////
                                GET LIST USER
        ///////////////////////////////////////////////////////////////// */
        $listUserData = $this->userRepository->findDataDeleteAccountCommand();

        $arrayUserData = [
            'user'         => [],
            'beneficiaire' => [],
            'logement'     => []
        ];
        foreach ($listUserData as $userData) {
            /* /////////////////////////////////////////////////////////////////
                                        FORMAT DATA
            ///////////////////////////////////////////////////////////////// */
            if (!empty($userData)) {
                if (User::class == get_class($userData)) {
                    $userId = $userData->getId();
                    $arrayUserData['user'][] = $userData;
                }
                if (Beneficiaire::class == get_class($userData) && $userId == $userData->getUserId()) {
                    $beneficiaireId = $userData->getId();
                    $arrayUserData['beneficiaire'][] = $userData;
                }
                if (Logement::class == get_class($userData) && $beneficiaireId == $userData->getBeneficiaireId()) {
                    $arrayUserData['logement'][] = $userData;
                }
            }
        }

        /* /////////////////////////////////////////////////////////////////
                                DELETE LOGEMENT
        ///////////////////////////////////////////////////////////////// */
        if ($arrayUserData['logement']) {
            $this->deleteLogement($arrayUserData['logement']);
        }

        /* /////////////////////////////////////////////////////////////////
                                DELETE BENEFICIAIRE
        ///////////////////////////////////////////////////////////////// */
        if ($arrayUserData['beneficiaire']) {
            $this->deleteBeneficiaire($arrayUserData['beneficiaire']);
        }

        /* /////////////////////////////////////////////////////////////////
                                DELETE USER
        ///////////////////////////////////////////////////////////////// */
        if ($arrayUserData['user']) {
            $this->deleteUser($arrayUserData['user']);
        }
    }

    private function deleteLogement(array $arrayLogement): void
    {
        if ($arrayLogement) {
            /**
             * @var Logement $logement
             */
            foreach ($arrayLogement as $logement) {
                $contextLogger = [
                    'Type'          => 'Logement',
                    'IsSuccess'     => null,
                    'ErrorMessage'  => '',
                    'DetailMessage' => 'Suppression logement ' . $logement->getNom() . ' (' . $logement->getCodePostal() . ' ' . $logement->getVille() . ')'
                ];

                try {
                    $this->em->remove($logement);
                    $this->em->flush();

                    $contextLogger['IsSuccess'] = true;
                    $this->commandDeleteAccountLogger->info('', $contextLogger);
                } catch(Exception $e) {
                    $contextLogger['IsSuccess'] = true;
                    $contextLogger['ErrorMessage'] = 'Erreur suppression du logement ' . $logement->getId() . ' : ' . $e->getMessage();
                    $this->commandDeleteAccountLogger->error('', $contextLogger);
                }
            }
        }
    }

    private function deleteBeneficiaire(array $arrayBeneficiaire): void
    {
        if ($arrayBeneficiaire) {
            /**
             * @var Beneficiaire $beneficiaire
             */
            foreach ($arrayBeneficiaire as $beneficiaire) {
                $contextLogger = [
                    'Type'          => 'Beneficiaire',
                    'IsSuccess'     => null,
                    'ErrorMessage'  => '',
                    'DetailMessage' => 'Suppression Bénéficiaire ' . $beneficiaire->getPrenom() . ' ' . $beneficiaire->getNom() . ' (' . $beneficiaire->getCodePostal() . ' ' . $beneficiaire->getVille() . ')'
                ];

                try {
                    $this->em->remove($beneficiaire);
                    $this->em->flush();

                    $contextLogger['IsSuccess'] = true;
                    $this->commandDeleteAccountLogger->info('', $contextLogger);
                } catch(Exception $e) {
                    $contextLogger['IsSuccess'] = true;
                    $contextLogger['ErrorMessage'] = 'Erreur suppression du bénéficiaire ' . $beneficiaire->getId() . ' : ' . $e->getMessage();
                    $this->commandDeleteAccountLogger->error('', $contextLogger);
                }
            }
        }
    }

    private function deleteUser(array $arrayUser): void
    {
        if ($arrayUser) {
            /**
             * @var User $user
             */
            foreach ($arrayUser as $user) {
                $contextLogger = [
                    'Type'          => 'User',
                    'IsSuccess'     => null,
                    'ErrorMessage'  => '',
                    'DetailMessage' => 'Suppression User ' . $user->getFirstname() . ' ' . $user->getLastname()
                ];

                try {
                    $this->em->remove($user);
                    $this->em->flush();

                    $contextLogger['IsSuccess'] = true;
                    $this->commandDeleteAccountLogger->info('', $contextLogger);
                } catch(Exception $e) {
                    $contextLogger['IsSuccess'] = true;
                    $contextLogger['ErrorMessage'] = 'Erreur suppression du User ' . $user->getId() . ' : ' . $e->getMessage();
                    $this->commandDeleteAccountLogger->error('', $contextLogger);
                }
            }
        }
    }
}

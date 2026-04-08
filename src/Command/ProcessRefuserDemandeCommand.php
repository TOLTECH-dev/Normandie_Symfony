<?php

namespace App\Command;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\HistoriqueService;
use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Repository\Demande_Repository;
use App\Service\DemandeServiceFO;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:processRefuserDemande',
    description: 'Refuser l\'ensemble des demandes qui ont été créées il y a 2 ans',
)]
class ProcessRefuserDemandeCommand extends Command
{
    private EntityManagerInterface $em;
    private DemandeServiceFO $demandeServiceFO;
    private HistoriqueService $historiqueService;
    private Demande_Repository $demandeRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        DemandeServiceFO       $demandeServiceFO,
        HistoriqueService      $historiqueService,
        Demande_Repository     $demandeRepository
    )
    {
        parent::__construct();
        $this->em = $entityManager;
        $this->demandeServiceFO = $demandeServiceFO;
        $this->historiqueService = $historiqueService;
        $this->demandeRepository = $demandeRepository;
    }


    /* *****************************************************************
    ********************************************************************
                            PROTECTED FUNCTION
    ********************************************************************
    *******************************************************************/
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('--- Start Refuser demande ---');
        try {
            $this->runCustom();
            $io->info('--- End Refuser demande ---');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Error: ' . $e->getMessage());

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
        $arrayDemande = $this->demandeRepository->findForProcessRefuser();

        $demande_statutRefusee = Demande_statut::STATUS_15;

        foreach ($arrayDemande as $demandeId) {
            /**
             * @var Demande_ $demande
             */
            $demande = $this->demandeRepository->find($demandeId);
            $demande->setStatutId($demande_statutRefusee);
            $demande->setDateModif(new \Datetime());
            $demande->setAuteurModif('AUTOMATE');
            $demande->setMotifRefus('Demande d\'aide non aboutie dans les 2 ans suivant sa création');

            $this->em->persist($demande);
            $this->em->flush();

            // MISE A JOUR DEMANDE STATUT DESCRIPTION
            $demande->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demande->getId()));
            $this->em->persist($demande);
            $this->em->flush();

            /* /////////////////////////////////////////////////////////////////
                                FILL UP HISTORIQUE
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $demande->getId(),
                $demande_statutRefusee,
                $demande->getType(),
                ['ROLE_AUTOMATE'],
                false,
                'Process de refus des demandes'
            );
        }
    }
}

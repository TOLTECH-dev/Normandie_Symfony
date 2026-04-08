<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Repository\Demande_Repository;
use App\Repository\Demande_statutRepository;
use App\Service\DemandeServiceFO;
use App\Service\HistoriqueService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeDemandeCommand extends Command
{
    private DemandeServiceFO $demandeServiceFO;
    private EntityManagerInterface $em;
    private HistoriqueService $historiqueService;
    private Demande_Repository $demandeRepository;
    private Demande_statutRepository $demandeStatutRepository;

    public function __construct(
        DemandeServiceFO $demandeServiceFO,
        EntityManagerInterface $em,
        HistoriqueService $historiqueService,
        Demande_Repository $demandeRepository,
        Demande_statutRepository $demandeStatutRepository,
        string $name = null
    ) {
        parent::__construct($name);
        $this->demandeServiceFO = $demandeServiceFO;
        $this->em = $em;
        $this->historiqueService = $historiqueService;
        $this->demandeRepository = $demandeRepository;
        $this->demandeStatutRepository = $demandeStatutRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName('normandie:upgradeDemande')
            ->setDescription('Upgrade demandes en attente de production vers le statut Date CP');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->runCustom();
        return Command::SUCCESS;
    }

    /**
     * @return void
     * @throws Exception
     */
    private function runCustom(): void
    {
        $listDemande = $this->getData();
        $this->upgradeDemande($listDemande);
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getData(): array
    {
        return $this->demandeRepository->findForAttenteProduction();
    }

    /**
     * @param array $listDemande
     * @return void
     */
    private function upgradeDemande(array $listDemande): void
    {
        $statutForDateCP = $this->demandeServiceFO->searchStatutForDateCP();
        /**
         * @var Demande_statut $demandeStatutForDateCP
         */
        $demandeStatutForDateCP = $this->demandeStatutRepository->findOneByStatut($statutForDateCP);

        if (!empty($listDemande)) {
            foreach ($listDemande as $row) {
                $demande = $this->demandeRepository->find($row['demandeId']);

                $demande->setStatutId($statutForDateCP);

                // MISE A JOUR DEMANDE STATUT DESCRIPTION
                $demande->setStatutDescription($demandeStatutForDateCP->getDescription());

                $this->em->persist($demande);
            }
            $this->em->flush();

            $this->persistHistorique($listDemande, (string)$statutForDateCP);
        }
    }

    private function persistHistorique(array $listDemande, string $statutForDateCP): void
    {
        foreach ($listDemande as $row) {
            $this->historiqueService->save(
                $row['demandeId'],
                $statutForDateCP,
                $row['demandeType'],
                [],
                true,
                'Commission passée',
                $row['beneficiaireEmail'],
                $row['beneficiaireType'],
                $row['documentJPAlt'],
                $row['documentKBISAlt'],
                $row['documentAIAlt']
            );
        }
    }
}

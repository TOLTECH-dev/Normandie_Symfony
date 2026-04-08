<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Demande_;
use App\Repository\Demande_Repository;
use App\Service\DemandeServiceFO;
use App\Service\HistoriqueService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessUpdateTypeMenageCommand extends Command
{

    private EntityManagerInterface $EM;

    private DemandeServiceFO $demandeServiceFO;

    private Demande_Repository $demandeRepository;

    const CURRENT_YEAR = '';

    /**
     * ANAH Critère par nombre de personne
     *
     * @var int[][]
     */
    protected static array $ANAHCritere = [];
    /*
    protected static $ANAHCritere = [
        1 => [
            ANAHCritere::ANAHCritere_PLAFOND_TRES_MODESTE_KEY     => 17009,
            ANAHCritere::ANAHCritere_SUPPLEMENT_TRES_MODESTE_KEY  => 0,
            ANAHCritere::ANAHCritere_PLAFOND_MODESTE_KEY          => 21805,
            ANAHCritere::ANAHCritere_SUPPLEMENT_MODESTE_KEY       => 0,
            ANAHCritere::ANAHCritere_PLAFOND_INTERMEDIAIRE_KEY    => 30549,
            ANAHCritere::ANAHCritere_SUPPLEMENT_INTERMEDIAIRE_KEY => 0
        ],
        2 => [
            ANAHCritere::ANAHCritere_PLAFOND_TRES_MODESTE_KEY     => 28875,
            ANAHCritere::ANAHCritere_SUPPLEMENT_TRES_MODESTE_KEY  => 0,
            ANAHCritere::ANAHCritere_PLAFOND_MODESTE_KEY          => 31889,
            ANAHCritere::ANAHCritere_SUPPLEMENT_MODESTE_KEY       => 0,
            ANAHCritere::ANAHCritere_PLAFOND_INTERMEDIAIRE_KEY    => 44907,
            ANAHCritere::ANAHCritere_SUPPLEMENT_INTERMEDIAIRE_KEY => 0
        ],
        3 => [
            ANAHCritere::ANAHCritere_PLAFOND_TRES_MODESTE_KEY     => 29917,
            ANAHCritere::ANAHCritere_SUPPLEMENT_TRES_MODESTE_KEY  => 0,
            ANAHCritere::ANAHCritere_PLAFOND_MODESTE_KEY          => 38349,
            ANAHCritere::ANAHCritere_SUPPLEMENT_MODESTE_KEY       => 0,
            ANAHCritere::ANAHCritere_PLAFOND_INTERMEDIAIRE_KEY    => 54071,
            ANAHCritere::ANAHCritere_SUPPLEMENT_INTERMEDIAIRE_KEY => 0
        ],
        4 => [
            ANAHCritere::ANAHCritere_PLAFOND_TRES_MODESTE_KEY     => 34948,
            ANAHCritere::ANAHCritere_SUPPLEMENT_TRES_MODESTE_KEY  => 0,
            ANAHCritere::ANAHCritere_PLAFOND_MODESTE_KEY          => 44802,
            ANAHCritere::ANAHCritere_SUPPLEMENT_MODESTE_KEY       => 0,
            ANAHCritere::ANAHCritere_PLAFOND_INTERMEDIAIRE_KEY    => 63235,
            ANAHCritere::ANAHCritere_SUPPLEMENT_INTERMEDIAIRE_KEY => 0
        ],
        5 => [
            ANAHCritere::ANAHCritere_PLAFOND_TRES_MODESTE_KEY     => 40002,
            ANAHCritere::ANAHCritere_SUPPLEMENT_TRES_MODESTE_KEY  => 5045,
            ANAHCritere::ANAHCritere_PLAFOND_MODESTE_KEY          => 51281,
            ANAHCritere::ANAHCritere_SUPPLEMENT_MODESTE_KEY       => 6462,
            ANAHCritere::ANAHCritere_PLAFOND_INTERMEDIAIRE_KEY    => 72400,
            ANAHCritere::ANAHCritere_SUPPLEMENT_INTERMEDIAIRE_KEY => 9165
        ]
    ];
    */

    /**
     * ProcessUpdateTypeMenageCommand constructor.
     */
    public function __construct(
        EntityManagerInterface $EM,
        DemandeServiceFO $demandeServiceFO,
        Demande_Repository $demandeRepository,
    )
    {
        parent::__construct();
        $this->EM = $EM;
        $this->demandeServiceFO = $demandeServiceFO;
        $this->demandeRepository = $demandeRepository;
    }

    /* *****************************************************************
    ********************************************************************
                        PROTECTED FUNCTION
    ********************************************************************
    *******************************************************************/

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('normandie:processUpdateTypeMenage')
            ->setDescription('Mettre à jour le champ type menage de la table demande')
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('--- Start Update demande type menage ---');
        $this->runCustom();
        $output->writeln('--- End Update demande type menage ---');
        return Command::SUCCESS;
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
        $currentYear = !empty(self::CURRENT_YEAR) ? self::CURRENT_YEAR : (new \DateTime())->format('Y');
        $arrayDemandeCreatedForProcessUpdateTypeMenage = $this->demandeRepository->findCreatedForProcessUpdateTypeMenage($currentYear);
        $arrayDemandeUpdatedForProcessUpdateTypeMenage = $this->demandeRepository->findUpdatedForProcessUpdateTypeMenage($currentYear);
        $arrayDemandeForProcessUpdateTypeMenage = array_merge_recursive($arrayDemandeCreatedForProcessUpdateTypeMenage, $arrayDemandeUpdatedForProcessUpdateTypeMenage);
        foreach ($arrayDemandeForProcessUpdateTypeMenage as $demandeId) {
            /**
             * @var Demande_ $demande
             */
            $demande = $this->demandeRepository->find($demandeId);

            $demande->setDateModif(new \Datetime());
            $demande->setAuteurModif('AUTOMATE');
            // MISE A JOUR DEMANDE TYPE MENAGE
            $this->demandeServiceFO->setDemandeTypeMenage($demande, null, self::$ANAHCritere);

            $this->EM->persist($demande);
            $this->EM->flush();
        }
    }
}

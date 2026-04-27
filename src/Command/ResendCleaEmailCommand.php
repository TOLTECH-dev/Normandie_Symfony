<?php

namespace App\Command;

use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Remboursement_statut;
use App\Entity\User;
use App\Repository\BeneficiaireRepository;
use App\Repository\Demande_Repository;
use App\Repository\Remboursement_Repository;
use App\Service\HistoriqueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:resendCleaEmail',
    description: 'Renvoie au bénéficiaire l\'invitation à créer un Carnet d\'Information CLÉA',
)]
class ResendCleaEmailCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private HistoriqueService $historiqueService;
    private Demande_Repository $demandeRepository;
    private Remboursement_Repository $remboursementRepository;
    private BeneficiaireRepository $beneficiaireRepository;
    private int $productionTravauxNiveauBBC2;

    public function __construct(
        EntityManagerInterface   $entityManager,
        HistoriqueService        $historiqueService,
        Demande_Repository       $demandeRepository,
        Remboursement_Repository $remboursementRepository,
        BeneficiaireRepository   $beneficiaireRepository,
        int                      $productionTravauxNiveauBBC2
    )
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->historiqueService = $historiqueService;
        $this->demandeRepository = $demandeRepository;
        $this->remboursementRepository = $remboursementRepository;
        $this->beneficiaireRepository = $beneficiaireRepository;
        $this->productionTravauxNiveauBBC2 = $productionTravauxNiveauBBC2;
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
                'demandeId',
                null,
                InputOption::VALUE_REQUIRED,
                'ID de la demande pour laquelle renvoyer l\'invitation CLÉA'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('--- Begin Resend CLEA Email Process ---');

        try {
            $demandeId = $input->getOption('demandeId');
            if (empty($demandeId)) {
                throw new \Exception('Option --demandeId requise.');
            }
            $demandeId = (int) $demandeId;

            /**
             * @var Demande_|null $demande
             */
            $demande = $this->demandeRepository->find($demandeId);
            if (empty($demande)) {
                throw new \Exception(sprintf('Demande #%d introuvable.', $demandeId));
            }

            if (Demande_::DEMANDE_TRAVAUX_TYPE !== $demande->getType()) {
                throw new \Exception(sprintf(
                    'Demande #%d n\'est pas de type Travaux (type = %s) — CLÉA non applicable.',
                    $demandeId,
                    $demande->getType()
                ));
            }

            $remboursementRow = $this->remboursementRepository->findByDemandeAndRemboursementTermine(
                $demandeId,
                $this->productionTravauxNiveauBBC2
            );
            if (empty($remboursementRow)) {
                throw new \Exception(sprintf(
                    'Aucun remboursement terminé éligible CLÉA pour la demande #%d.',
                    $demandeId
                ));
            }

            /**
             * @var Beneficiaire|null $beneficiaire
             */
            $beneficiaire = $this->beneficiaireRepository->find($demande->getBeneficiaireId());
            if (empty($beneficiaire) || empty($beneficiaire->getEmail())) {
                throw new \Exception(sprintf(
                    'Bénéficiaire ou email introuvable pour la demande #%d.',
                    $demandeId
                ));
            }
            $emailBeneficiaire = $beneficiaire->getEmail();

            /* /////////////////////////////////////////////////////////////////
                RESET DES CHAMPS BLOQUANTS AVANT RENVOI
                - carnetInformationValidatedAt : sinon emailRedirect rejette
                - carnetInformationCLEAEtapeCode / carnetInformationCLEAId :
                    au cas où une tentative précédente aurait échoué en cours de route
            ///////////////////////////////////////////////////////////////// */
            $demande->setCarnetInformationValidatedAt(null);
            $demande->setCarnetInformationCLEAEtapeCode(null);
            $demande->setCarnetInformationCLEAId(null);
            $this->entityManager->flush();

            /* /////////////////////////////////////////////////////////////////
                DÉCLENCHE L'ENVOI DU MAIL CLÉA
                - statutId = STATUS_22 + remboursementId => branche CLÉA
                - envoiEmail = true => envoie réellement le mail
                - destinataire = email bénéficiaire
                HistoriqueService met à jour carnetInformationRequestedAt
                et carnetInformationToken automatiquement à l'envoi.
            ///////////////////////////////////////////////////////////////// */
            $this->historiqueService->save(
                $demandeId,
                Remboursement_statut::STATUS_22,
                Demande_::DEMANDE_TRAVAUX_TYPE,
                [User::PARAM_ROLE_ADMIN],
                true,
                'Renvoi manuel de l\'invitation CLÉA au bénéficiaire',
                $emailBeneficiaire,
                null,
                null,
                null,
                null,
                false,
                $remboursementRow['remboursementId']
            );

            $io->success(sprintf(
                'Mail CLÉA renvoyé à %s pour la demande #%d (remboursement #%d).',
                $emailBeneficiaire,
                $demandeId,
                $remboursementRow['remboursementId']
            ));

            $io->info('--- End Resend CLEA Email Process ---');
            return Command::SUCCESS;

        } catch (\Throwable $th) {
            $io->error($th->getMessage());
            return Command::FAILURE;
        }
    }
}

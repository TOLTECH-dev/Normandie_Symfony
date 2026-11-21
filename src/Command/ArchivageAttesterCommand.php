<?php

declare(strict_types=1);

namespace App\Command;

use App\Archive\AttestationDestruction;
use App\Archive\DiffusionArchivage;
use App\Entity\ArchiveLot;
use App\Entity\ArchiveLotBeneficiaire;
use App\Repository\ArchiveLotBeneficiaireRepository;
use App\Repository\ArchiveLotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dernière étape de la campagne d'archivage (Reco-03) : l'attestation de
 * destruction.
 *
 * Le service d'archivage de la Région attend une attestation sur l'honneur —
 * et non un bordereau SEDA — portant trois informations : description du
 * contenu, volumétrie, date de destruction. La trame est fournie par la Région
 * et remplie telle quelle.
 *
 * Les chiffres viennent du registre, pas d'une saisie : ils décrivent ce qui a
 * réellement été détruit, compte par compte, y compris les pièces que la
 * production avait déclarées manquantes.
 */
#[AsCommand(
    name: 'normandie:archivage:attester',
    description: "Produire l'attestation de destruction d'un lot (Reco-03)",
)]
class ArchivageAttesterCommand extends Command
{
    public function __construct(
        private readonly ArchiveLotRepository $lotRepository,
        private readonly ArchiveLotBeneficiaireRepository $lotBeneficiaireRepository,
        private readonly AttestationDestruction $attestation,
        private readonly DiffusionArchivage $diffusion,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $loggerArchivage,
        private readonly string $repertoireArchivage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('lot', null, InputOption::VALUE_REQUIRED, 'Référence du lot. Par défaut : la campagne en cours.')
            ->addOption('sortie', null, InputOption::VALUE_REQUIRED, "Chemin du fichier produit. Par défaut : dans le répertoire du lot.")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $lot = $this->resoudreLot($input);

            $io->title(sprintf('Archivage — attestation de destruction du lot %s', $lot->getReference()));

            $lignes = $this->lignesDeLAttestation($lot);

            if ([] === $lignes) {
                throw new \RuntimeException(sprintf(
                    'Aucun compte détruit dans le lot %s : il n\'y a rien à attester.',
                    $lot->getReference()
                ));
            }

            $io->table(
                ['Description du contenu', 'Volumétrie', 'Date de destruction'],
                array_map(
                    static fn (array $ligne): array => [$ligne['description'], $ligne['volumetrie'], $ligne['date']],
                    $lignes
                )
            );

            $destination = $this->destination($input, $lot);
            $this->attestation->produire($lot, $lignes, $destination);

            if (ArchiveLot::ETAT_DETRUIT === $lot->getEtat()) {
                $lot->passerA(ArchiveLot::ETAT_ATTESTE)->setDateAttestation(new \DateTime());
                $this->em->flush();
            }

            $io->success(sprintf('Attestation produite : %s', $destination));
            $this->diffuser($io, $lot, $destination);
            $io->text([
                'Elle est à signer, puis à transmettre à la Région, qui la remet au service Archives.',
                sprintf('Le lot %s est à l\'état « %s ».', $lot->getReference(), $lot->getEtat()),
            ]);

            $this->loggerArchivage->warning('Attestation produite', [
                'reference' => $lot->getReference(),
                'fichier' => $destination,
                'lignes' => count($lignes),
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Attestation : echec', ['message' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }

    /**
     * Envoie l'attestation aux administrateurs.
     *
     * Comme pour le bordereau, un échec d'envoi ne remet pas la campagne en
     * cause : le document est écrit sur le serveur. Il est signalé, et le lot
     * reste attesté.
     */
    private function diffuser(SymfonyStyle $io, ArchiveLot $lot, string $chemin): void
    {
        $comptes = $this->lotBeneficiaireRepository->findParLotEtEtat($lot, ArchiveLotBeneficiaire::ETAT_SUPPRIMEE);

        $compteurs = [
            'comptes' => count($comptes),
            'pieces' => 0,
            'manquantes' => 0,
            'octets' => 0,
        ];

        foreach ($comptes as $compte) {
            $compteurs['pieces'] += $compte->getNbPieces() ?? 0;
            $compteurs['manquantes'] += $compte->getNbPiecesManquantes() ?? 0;
            $compteurs['octets'] += $compte->getZipTaille() ?? 0;
        }

        try {
            $diffusion = $this->diffusion->diffuserAttestation($lot, $chemin, $compteurs);

            if (0 === $diffusion['destinataires']) {
                $io->warning('Aucun administrateur à prévenir : l\'attestation n\'a été envoyée à personne.');

                return;
            }

            if ($diffusion['echecs'] > 0) {
                $io->warning(sprintf(
                    'Attestation envoyée à %d administrateurs, %d envois en échec.',
                    $diffusion['envoyes'],
                    $diffusion['echecs']
                ));

                return;
            }

            $io->text(sprintf('Attestation envoyée à %d administrateurs.', $diffusion['envoyes']));
        } catch (\Throwable $e) {
            $io->warning(sprintf('Envoi de l\'attestation impossible : %s', $e->getMessage()));
            $this->loggerArchivage->error('Attestation : diffusion en echec', [
                'reference' => $lot->getReference(),
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resoudreLot(InputInterface $input): ArchiveLot
    {
        $reference = $input->getOption('lot');

        $lot = (null !== $reference && '' !== $reference)
            ? $this->lotRepository->findParReference((string) $reference)
            : $this->lotRepository->findLotOuvert();

        if (null === $lot) {
            throw new \RuntimeException('Aucun lot à traiter.');
        }

        if (!in_array($lot->getEtat(), [ArchiveLot::ETAT_DETRUIT, ArchiveLot::ETAT_ATTESTE], true)) {
            throw new \RuntimeException(sprintf(
                'Le lot %s est à l\'état « %s ». On n\'atteste que ce qui a été détruit.',
                $lot->getReference(),
                $lot->getEtat()
            ));
        }

        return $lot;
    }

    /**
     * Une ligne par COMPTE détruit, désigné par son seul numéro.
     *
     * L'attestation est volontairement NON NOMINATIVE : ni nom, ni prénom, ni
     * numéro de dossier. Elle circule — elle est signée, transmise à la Région
     * puis au service Archives — et rien n'oblige à y faire voyager l'identité
     * des personnes pour prouver une destruction.
     *
     * Le détail nominatif existe, mais ailleurs et pour ceux à qui il sert :
     * le bordereau de versement, produit à la constitution et déposé avec les
     * archives, porte les noms, les prénoms et les dossiers de chaque compte
     * (cf. App\Archive\BordereauVersement).
     *
     * @return list<array{description: string, volumetrie: string, date: string}>
     */
    private function lignesDeLAttestation(ArchiveLot $lot): array
    {
        $comptes = $this->lotBeneficiaireRepository->findParLotEtEtat($lot, ArchiveLotBeneficiaire::ETAT_SUPPRIMEE);

        if ([] === $comptes) {
            return [];
        }

        $lignes = [];

        foreach ($comptes as $compte) {
            $pieces = $compte->getNbPieces() ?? 0;
            $manquantes = $compte->getNbPiecesManquantes() ?? 0;
            $dossiers = $compte->getNbDossiers();

            $lignes[] = [
                'description' => sprintf(
                    'Compte bénéficiaire n° %d du téléservice Chèque éco-énergie Normandie, '
                    . 'campagne %s — motif : %s. Données du compte, %d dossier%s et leurs pièces '
                    . 'justificatives, historique et commentaires.',
                    $compte->getUserId(),
                    $lot->getReference(),
                    ArchiveLotBeneficiaire::LIBELLES[$compte->getDeclencheur()] ?? $compte->getDeclencheur(),
                    $dossiers,
                    $dossiers > 1 ? 's' : ''
                ),
                'volumetrie' => sprintf(
                    '%d pièce%s%s, %s',
                    $pieces,
                    $pieces > 1 ? 's' : '',
                    $manquantes > 0
                        ? sprintf(
                            ' (dont %d introuvable%s sur l’espace de stockage)',
                            $manquantes,
                            $manquantes > 1 ? 's' : ''
                        )
                        : '',
                    $this->lisible($compte->getZipTaille() ?? 0)
                ),
                'date' => $compte->getDateSuppression()?->format('d/m/Y') ?? '',
            ];
        }

        return $lignes;
    }


    private function destination(InputInterface $input, ArchiveLot $lot): string
    {
        $sortie = $input->getOption('sortie');

        if (null !== $sortie && '' !== $sortie) {
            return (string) $sortie;
        }

        if ('' === $this->repertoireArchivage) {
            throw new \RuntimeException(
                'Aucun répertoire configuré (ARCHIVAGE_REPERTOIRE_DEPOT) : préciser --sortie.'
            );
        }

        $repertoire = rtrim($this->repertoireArchivage, '/\\') . \DIRECTORY_SEPARATOR . $lot->getReference();

        if (!is_dir($repertoire) && !mkdir($repertoire, 0750, true) && !is_dir($repertoire)) {
            throw new \RuntimeException(sprintf('Répertoire introuvable : %s', $repertoire));
        }

        return $repertoire . \DIRECTORY_SEPARATOR
            . sprintf('ATTESTATION-DE-DESTRUCTION-%s.docx', $lot->getReference());
    }

    private function lisible(int $octets): string
    {
        if ($octets < 1024 * 1024) {
            return sprintf('%.1f Kio', $octets / 1024);
        }

        return sprintf('%.1f Mio', $octets / 1048576);
    }
}

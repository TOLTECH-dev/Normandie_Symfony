<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ArchiveLot;
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
 * Quatrième étape de la campagne d'archivage (Reco-03) : enregistrer que les
 * Archives de la Région ont confirmé avoir récupéré les archives.
 *
 * C'est le geste le plus court de toute la procédure, et le plus lourd de
 * conséquences : il est la condition unique de la destruction. Tant qu'il n'a
 * pas été posé, `normandie:archivage:detruire` refuse de s'exécuter.
 *
 * Il est volontairement MANUEL. L'accusé de réception est un courriel du pôle
 * Archives, pas un signal technique : personne ne peut le constater à la place
 * d'un humain. La commande demande donc une confirmation, et enregistre qui l'a
 * donnée.
 */
#[AsCommand(
    name: 'normandie:archivage:reception',
    description: "Enregistrer la confirmation de réception des Archives, qui autorise la destruction (Reco-03)",
)]
class ArchivageReceptionCommand extends Command
{
    public function __construct(
        private readonly ArchiveLotRepository $lotRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $loggerArchivage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('lot', null, InputOption::VALUE_REQUIRED, 'Référence du lot. Par défaut : la campagne en cours.')
            ->addOption('par', null, InputOption::VALUE_REQUIRED, 'Qui enregistre la confirmation.', 'Automate')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date de la confirmation (AAAA-MM-JJ). Par défaut : aujourd\'hui.')
            ->setHelp(<<<'AIDE'
                À lancer UNIQUEMENT après réception du courriel du pôle Archives confirmant
                qu'il a bien récupéré les archives du lot. Ce geste autorise la destruction,
                qui est irréversible.

                  <info>php bin/console normandie:archivage:reception --par="prenom.nom"</info>
                AIDE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $reference = $input->getOption('lot');

            $lot = (null !== $reference && '' !== $reference)
                ? $this->lotRepository->findParReference((string) $reference)
                : $this->lotRepository->findLotOuvert();

            if (null === $lot) {
                throw new \RuntimeException('Aucun lot à traiter.');
            }

            if (ArchiveLot::ETAT_VERSE !== $lot->getEtat()) {
                throw new \RuntimeException(sprintf(
                    'Le lot %s est à l\'état « %s ». La réception ne se constate que sur un lot « %s », '
                    . 'c\'est-à-dire déposé et à disposition des Archives.',
                    $lot->getReference(),
                    $lot->getEtat(),
                    ArchiveLot::ETAT_VERSE
                ));
            }

            $date = $this->lireDate($input);

            $io->title(sprintf('Archivage — réception du lot %s', $lot->getReference()));
            $io->definitionList(
                ['Comptes versés' => (string) $lot->getNbComptes()],
                ['Dossiers' => (string) $lot->getNbDossiers()],
                ['Déposé le' => $lot->getDateVersement()?->format('d/m/Y H:i') ?? '?'],
                ['Confirmation datée du' => $date->format('d/m/Y')],
            );

            $io->warning(
                'Enregistrer cette confirmation AUTORISE la destruction définitive des dossiers de ce lot. '
                . 'À ne faire qu\'après réception du courriel du pôle Archives.'
            );

            if (!$io->confirm('Le pôle Archives a-t-il confirmé avoir récupéré ces archives ?', false)) {
                $io->text('Rien n\'a été enregistré.');

                return Command::SUCCESS;
            }

            $lot->passerA(ArchiveLot::ETAT_RECEPTION_VALIDEE)->setDateReceptionValidee($date);
            $lot->setCommentaire(trim(sprintf(
                "%s\nReception confirmee le %s, enregistree par %s.",
                $lot->getCommentaire() ?? '',
                $date->format('d/m/Y'),
                (string) $input->getOption('par')
            )));

            $this->em->flush();

            $io->success(sprintf(
                'Lot %s à l\'état « %s ». La destruction est désormais possible : '
                . 'normandie:archivage:detruire.',
                $lot->getReference(),
                $lot->getEtat()
            ));

            $this->loggerArchivage->warning('Reception validee : la destruction est autorisee', [
                'reference' => $lot->getReference(),
                'date_reception' => $date->format('c'),
                'par' => (string) $input->getOption('par'),
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Reception : echec', ['message' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }

    private function lireDate(InputInterface $input): \DateTimeInterface
    {
        $valeur = $input->getOption('date');

        if (null === $valeur || '' === $valeur) {
            return new \DateTime();
        }

        $date = \DateTime::createFromFormat('Y-m-d', (string) $valeur);

        if (false === $date) {
            throw new \InvalidArgumentException(sprintf(
                'Date illisible : « %s ». Format attendu : AAAA-MM-JJ.',
                (string) $valeur
            ));
        }

        return $date->setTime(12, 0);
    }
}

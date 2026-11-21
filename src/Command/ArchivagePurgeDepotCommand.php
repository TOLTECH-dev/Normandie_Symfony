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
 * Dernier geste d'une campagne d'archivage (Reco-03) : retirer du serveur les
 * archives déposées.
 *
 * Le répertoire de dépôt est une ZONE DE TRANSIT. Les archives y restent le
 * temps que les Archives de la Région les récupèrent — ce que l'état
 * « réception validée » enregistre — puis que la destruction ait lieu et que
 * l'attestation soit produite. Passé ce point, les conserver reviendrait à
 * garder, sous une autre forme, les données dont on vient précisément
 * d'attester la destruction : chaque archive porte le nom, l'adresse et les
 * pièces justificatives d'une personne.
 *
 * Ce que la commande efface : les archives `BENEFICIAIRE-*.zip` et le
 * bordereau de versement, tous deux nominatifs.
 *
 * Ce qu'elle conserve : l'attestation de destruction, qui ne porte que des
 * numéros de compte et constitue la preuve de l'opération.
 *
 * Elle n'agit que sur un lot ATTESTÉ : tant que l'attestation n'est pas
 * produite, les archives doivent rester disponibles, ne serait-ce que pour
 * vérifier leur empreinte.
 */
#[AsCommand(
    name: 'normandie:archivage:purgeDepot',
    description: "Retirer du serveur les archives déposées d'une campagne attestée (Reco-03)",
)]
class ArchivagePurgeDepotCommand extends Command
{
    /** Ce qui est conservé dans le répertoire du lot. */
    private const A_CONSERVER = 'ATTESTATION-DE-DESTRUCTION-';

    public function __construct(
        private readonly ArchiveLotRepository $lotRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $loggerArchivage,
        private readonly string $repertoireArchivage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('lot', null, InputOption::VALUE_REQUIRED, 'Référence du lot. Par défaut : tous les lots attestés non encore purgés.')
            ->addOption('purger', null, InputOption::VALUE_NONE, 'Effacer réellement. Sans cette option, la commande se contente de lister.')
            ->setHelp(<<<'AIDE'
                Liste ce qui serait effacé :

                  <info>php bin/console normandie:archivage:purgeDepot</info>

                Efface les archives et le bordereau, conserve l'attestation :

                  <info>php bin/console normandie:archivage:purgeDepot --purger</info>

                À ne lancer qu'une fois certain que les Archives de la Région ont bien
                récupéré les fichiers : après cette commande, la plateforme n'en détient
                plus aucune copie.
                AIDE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $lots = $this->lotsAPurger($input);

            if ([] === $lots) {
                $io->success('Aucun lot attesté en attente de purge de son dépôt.');

                return Command::SUCCESS;
            }

            $purger = (bool) $input->getOption('purger');
            $io->title('Archivage — purge du répertoire de dépôt');

            $total = ['fichiers' => 0, 'octets' => 0, 'echecs' => 0];

            foreach ($lots as $lot) {
                $total = $this->traiter($io, $lot, $purger, $total);
            }

            if (!$purger) {
                $io->section('Rien n\'a été effacé');
                $io->text([
                    sprintf(
                        '%d fichiers seraient effacés, soit %.1f Mio.',
                        $total['fichiers'],
                        $total['octets'] / 1048576
                    ),
                    'Les attestations de destruction seraient conservées.',
                    'Relancer avec <info>--purger</info> pour exécuter.',
                ]);

                return Command::SUCCESS;
            }

            $io->success(sprintf(
                '%d fichiers effacés (%.1f Mio), %d échecs. Les attestations sont conservées.',
                $total['fichiers'],
                $total['octets'] / 1048576,
                $total['echecs']
            ));

            return $total['echecs'] > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Purge du depot : echec', ['message' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }

    /**
     * @return list<ArchiveLot>
     */
    private function lotsAPurger(InputInterface $input): array
    {
        if ('' === $this->repertoireArchivage) {
            throw new \RuntimeException('Aucun répertoire de dépôt configuré (ARCHIVAGE_REPERTOIRE_DEPOT).');
        }

        $reference = $input->getOption('lot');

        if (null !== $reference && '' !== $reference) {
            $lot = $this->lotRepository->findParReference((string) $reference);

            if (null === $lot) {
                throw new \RuntimeException(sprintf('Aucun lot ne porte la référence %s.', (string) $reference));
            }

            if (ArchiveLot::ETAT_ATTESTE !== $lot->getEtat()) {
                throw new \RuntimeException(sprintf(
                    'Le lot %s est à l\'état « %s ». Le dépôt ne se purge qu\'après l\'attestation : '
                    . 'jusque-là, les archives doivent rester disponibles.',
                    $lot->getReference(),
                    $lot->getEtat()
                ));
            }

            return [$lot];
        }

        return array_values(array_filter(
            $this->lotRepository->findTousDuPlusRecent(),
            static fn (ArchiveLot $lot): bool => ArchiveLot::ETAT_ATTESTE === $lot->getEtat()
                && null === $lot->getDatePurgeDepot()
        ));
    }

    /**
     * @param array{fichiers: int, octets: int, echecs: int} $total
     *
     * @return array{fichiers: int, octets: int, echecs: int}
     */
    private function traiter(SymfonyStyle $io, ArchiveLot $lot, bool $purger, array $total): array
    {
        $repertoire = rtrim($this->repertoireArchivage, '/\\') . \DIRECTORY_SEPARATOR . $lot->getReference();

        if (!is_dir($repertoire)) {
            $io->text(sprintf('<comment>%s : répertoire absent, rien à faire.</comment>', $lot->getReference()));

            if ($purger) {
                $lot->setDatePurgeDepot(new \DateTime());
                $this->em->flush();
            }

            return $total;
        }

        $aEffacer = [];
        $conserves = [];

        foreach (glob($repertoire . \DIRECTORY_SEPARATOR . '*') ?: [] as $fichier) {
            if (!is_file($fichier)) {
                continue;
            }

            if (str_starts_with(basename($fichier), self::A_CONSERVER)) {
                $conserves[] = basename($fichier);

                continue;
            }

            $aEffacer[] = $fichier;
        }

        $octets = array_sum(array_map(static fn (string $f): int => (int) filesize($f), $aEffacer));

        $io->section($lot->getReference());
        $io->text(sprintf(
            '%d fichiers à effacer (%.1f Mio), %d conservés : %s',
            count($aEffacer),
            $octets / 1048576,
            count($conserves),
            [] === $conserves ? 'aucun' : implode(', ', $conserves)
        ));

        if ([] === $conserves && [] !== $aEffacer) {
            // L'attestation est la seule preuve qui doit survivre : si elle
            // n'est pas dans le répertoire, mieux vaut ne rien effacer et le
            // signaler que de se retrouver sans archive ni attestation.
            $io->warning(sprintf(
                'Aucune attestation trouvée dans %s : purge refusée pour ce lot.',
                $repertoire
            ));

            return $total;
        }

        if (!$purger) {
            $total['fichiers'] += count($aEffacer);
            $total['octets'] += $octets;

            return $total;
        }

        foreach ($aEffacer as $fichier) {
            if (unlink($fichier)) {
                ++$total['fichiers'];
                $total['octets'] += 0; // la taille a déjà été relevée avant effacement
            } else {
                ++$total['echecs'];
                $io->warning(sprintf('Effacement impossible : %s', $fichier));
            }
        }

        $total['octets'] += $octets;

        $lot->setDatePurgeDepot(new \DateTime());
        $this->em->flush();

        $this->loggerArchivage->warning('Purge du depot', [
            'reference' => $lot->getReference(),
            'fichiers_effaces' => count($aEffacer),
            'octets' => $octets,
            'conserves' => $conserves,
        ]);

        return $total;
    }
}

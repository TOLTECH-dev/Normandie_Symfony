<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Security\Csp\ViolationsCspRepository;
use App\Service\MailerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;

/**
 * Lecture et alerte des violations de la politique de sécurité du contenu
 * (Reco-04, étape « observation »).
 *
 * La recommandation demande « un mode observation qui remonte les blocages
 * sans impacter les utilisateurs, un système d'alerte, et une période de
 * mesure pour fiabiliser la politique avant durcissement ». Cette commande
 * sert les trois : elle lit la mesure, et, planifiée quotidiennement avec
 * --alerter, prévient les administrateurs.
 *
 * Deux natures de violation, à ne pas confondre :
 *
 *  - celles de la politique EN VIGUEUR sont des ressources RÉELLEMENT
 *    BLOQUÉES pour les utilisateurs. C'est un incident, et c'est le seul cas
 *    qui déclenche une alerte ;
 *  - celles de la politique en OBSERVATION disent ce que le durcissement
 *    casserait. C'est la mesure attendue, et elle n'a rien d'alarmant — elle
 *    se lit, elle ne se signale pas chaque matin.
 */
#[AsCommand(
    name: 'normandie:csp:violations',
    description: "Lire les violations de la politique de sécurité du contenu et alerter (Reco-04)",
)]
class CspViolationsCommand extends Command
{
    private const TEMPLATE = 'Email/csp_alerte.html.twig';

    /**
     * @param list<string> $rolesAlertes
     */
    public function __construct(
        private readonly ViolationsCspRepository $depot,
        private readonly UserRepository $userRepository,
        private readonly MailerService $mailerService,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly array $rolesAlertes,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('appliquees', null, InputOption::VALUE_NONE, 'N\'afficher que les violations de la politique en vigueur, c\'est-à-dire les blocages réels.')
            ->addOption('depuis', null, InputOption::VALUE_REQUIRED, 'Ne retenir que ce qui a été revu depuis (ex. « 24 hours », « 7 days »).')
            ->addOption('limite', null, InputOption::VALUE_REQUIRED, 'Nombre de cas détaillés.', '30')
            ->addOption('alerter', null, InputOption::VALUE_NONE, 'Prévenir les administrateurs s\'il existe des blocages réels sur la période.')
            ->addOption('purger', null, InputOption::VALUE_REQUIRED, 'Supprimer les violations plus revues depuis (ex. « 90 days »).')
            ->setHelp(<<<'AIDE'
                Ce que le durcissement casserait, sur les sept derniers jours :

                  <info>php bin/console normandie:csp:violations --depuis="7 days"</info>

                Les blocages réels, et alerte des administrateurs s'il y en a :

                  <info>php bin/console normandie:csp:violations --appliquees --depuis="24 hours" --alerter</info>

                Cette dernière forme est celle à planifier quotidiennement.
                AIDE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if (null !== $input->getOption('purger')) {
                return $this->purger($io, (string) $input->getOption('purger'));
            }

            $appliquees = (bool) $input->getOption('appliquees');
            $depuis = $this->lireDepuis($input->getOption('depuis'));
            $limite = max(1, (int) $input->getOption('limite'));

            $io->title($appliquees
                ? 'CSP — blocages réels (politique en vigueur)'
                : 'CSP — ce que le durcissement casserait (politique en observation)');

            if (null !== $depuis) {
                $io->text(sprintf('Revus depuis le %s.', $depuis->format('d/m/Y H:i')));
            }

            $synthese = $this->depot->syntheseParDirective($appliquees, $depuis);

            if ([] === $synthese) {
                $io->success($appliquees
                    ? 'Aucun blocage réel : la politique en vigueur ne gêne personne.'
                    : 'Aucune violation observée. Attention : cela peut aussi vouloir dire que le '
                      . 'point de collecte ne reçoit rien — à vérifier avant d\'en conclure que la '
                      . 'politique stricte est applicable.');

                return Command::SUCCESS;
            }

            $io->table(
                ['Directive', 'Cas distincts', 'Occurrences'],
                array_map(
                    static fn (array $l): array => [$l['directive'], $l['cas'], $l['occurrences']],
                    $synthese
                )
            );

            $violations = $this->depot->violations($appliquees, $depuis, $limite);

            $io->section(sprintf('Les %d cas les plus fréquents', count($violations)));
            $io->table(
                ['Directive', 'Bloqué', 'Page', 'Occurrences', 'Dernière'],
                array_map(
                    static fn (array $v): array => [
                        $v['directive'],
                        mb_strimwidth($v['source'], 0, 40, '…'),
                        mb_strimwidth($v['page'], 0, 50, '…'),
                        $v['occurrences'],
                        (new \DateTime($v['derniere']))->format('d/m/Y H:i'),
                    ],
                    $violations
                )
            );

            if ($input->getOption('alerter')) {
                $this->alerter($io, $appliquees, $synthese, $violations, $depuis);
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->logger->error('CSP : lecture des violations en echec', ['message' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }

    /**
     * @param list<array{directive: string, cas: int, occurrences: int}> $synthese
     * @param list<array{directive: string, source: string, page: string, occurrences: int, premiere: string, derniere: string}> $violations
     */
    private function alerter(
        SymfonyStyle $io,
        bool $appliquees,
        array $synthese,
        array $violations,
        ?\DateTimeInterface $depuis
    ): void {
        if (!$appliquees) {
            // Ce que le durcissement casserait n'a rien d'une alerte : c'est
            // la mesure qu'on a demandée. L'annoncer chaque matin ferait
            // perdre de vue les vrais blocages.
            $io->note(
                'Alerte non envoyée : seules les violations de la politique en vigueur en '
                . 'déclenchent une. Relancer avec --appliquees.'
            );

            return;
        }

        $destinataires = $this->destinataires();

        if ([] === $destinataires) {
            $io->warning('Aucun administrateur à prévenir.');

            return;
        }

        $corps = $this->twig->render(self::TEMPLATE, [
            'synthese' => $synthese,
            'violations' => array_slice($violations, 0, 20),
            'depuis' => $depuis,
        ]);

        $envoyes = 0;

        foreach ($destinataires as $adresse) {
            if (1 === $this->mailerService->sendGeneriqueEmail(
                'Chèque Éco-Énergie Normandie — ressources bloquées par la politique de sécurité',
                $corps,
                null,
                $adresse
            )) {
                ++$envoyes;
            }
        }

        $io->text(sprintf('Alerte envoyée à %d administrateurs sur %d.', $envoyes, count($destinataires)));

        $this->logger->warning('CSP : blocages reels signales aux administrateurs', [
            'directives' => array_column($synthese, 'directive'),
            'occurrences' => array_sum(array_column($synthese, 'occurrences')),
            'destinataires' => count($destinataires),
        ]);
    }

    private function purger(SymfonyStyle $io, string $depuis): int
    {
        $limite = new \DateTime();
        $limite->modify('-' . ltrim($depuis, '-'));

        $supprimees = $this->depot->purger($limite);

        $io->success(sprintf(
            '%d violations plus revues depuis le %s ont été supprimées du journal.',
            $supprimees,
            $limite->format('d/m/Y')
        ));

        return Command::SUCCESS;
    }

    private function lireDepuis(mixed $valeur): ?\DateTimeInterface
    {
        if (null === $valeur || '' === $valeur) {
            return null;
        }

        $date = new \DateTime();
        $modifiee = @$date->modify('-' . ltrim((string) $valeur, '-'));

        if (false === $modifiee) {
            throw new \InvalidArgumentException(sprintf(
                'Durée illisible : « %s ». Exemples : « 24 hours », « 7 days ».',
                (string) $valeur
            ));
        }

        return $modifiee;
    }

    /**
     * @return list<string>
     */
    private function destinataires(): array
    {
        $adresses = [];

        foreach ($this->rolesAlertes as $role) {
            foreach ($this->userRepository->findByRole($role) as $compte) {
                $adresse = $compte->getEmail();

                if (null !== $adresse && '' !== $adresse) {
                    $adresses[strtolower($adresse)] = $adresse;
                }
            }
        }

        return array_values($adresses);
    }
}

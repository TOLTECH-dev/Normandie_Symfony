<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ArchiveLot;
use App\Entity\Demande_;
use App\Repository\Archive\ArchivageEligibiliteRepository;
use App\Repository\Archive\ArchivagePartenairesRepository;
use App\Repository\ArchiveLotRepository;
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
 * Deuxième étape de la campagne annuelle d'archivage (Reco-03) : prévenir les
 * professionnels, et faire partir le préavis.
 *
 * La proposition commerciale engage un « avertissement des conseillers,
 * auditeurs et rénovateurs des dossiers qui auront été archivés », et le
 * courriel Région du 13/05/2026 en donne le motif : leur permettre de
 * « réaliser un export complet avant mise en place de l'archivage ». Ce n'est
 * donc pas une politesse mais un délai utile, d'un mois, décompté à partir de
 * l'envoi RÉEL du courriel.
 *
 * Cette commande ne touche à aucune donnée de dossier et ne change pas l'état
 * du lot : elle envoie des courriels et enregistre deux dates. Tant que le
 * préavis n'est pas échu, la constitution des archives refusera de s'exécuter.
 */
#[AsCommand(
    name: 'normandie:archivage:annonce',
    description: "Prévenir les professionnels des dossiers à archiver et faire partir le préavis (Reco-03)",
)]
class ArchivageAnnonceCommand extends Command
{
    /**
     * Au-delà de ce nombre, la liste des dossiers est tronquée dans le
     * courriel : un conseiller peut en porter plusieurs centaines, et un
     * courriel de cette taille n'est ni lisible ni toujours délivré.
     */
    private const MAX_DOSSIERS_LISTES = 50;

    public function __construct(
        private readonly ArchiveLotRepository $lotRepository,
        private readonly ArchivagePartenairesRepository $partenairesRepository,
        private readonly ArchivageEligibiliteRepository $eligibiliteRepository,
        private readonly MailerService $mailerService,
        private readonly Environment $twig,
        private readonly LoggerInterface $loggerArchivage,
        private readonly string $emailRegionArchivage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'envoyer',
                null,
                InputOption::VALUE_NONE,
                'Envoyer réellement les courriels. Sans cette option, la commande se contente de lister.'
            )
            ->addOption(
                'lot',
                null,
                InputOption::VALUE_REQUIRED,
                "Référence du lot. Par défaut : la campagne en cours."
            )
            ->addOption(
                'relancer',
                null,
                InputOption::VALUE_NONE,
                "Renvoyer l'annonce d'un lot déjà annoncé. Le préavis repart de zéro."
            )
            ->setHelp(<<<'AIDE'
                Liste les professionnels à prévenir, sans rien envoyer :

                  <info>php bin/console normandie:archivage:annonce</info>

                Envoie l'annonce et fait partir le préavis d'un mois :

                  <info>php bin/console normandie:archivage:annonce --envoyer</info>

                En environnement de développement, tous les courriels sont redirigés vers
                l'adresse de config/packages/dev/mailer.yaml : aucun message ne part vers un
                professionnel réel.
                AIDE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $lot = $this->resoudreLot($input);
            $envoyer = (bool) $input->getOption('envoyer');

            $io->title(sprintf('Archivage — annonce du lot %s', $lot->getReference()));

            $this->loggerArchivage->info('Annonce : debut', [
                'reference' => $lot->getReference(),
                'lot_id' => $lot->getId(),
                'envoyer' => $envoyer,
            ]);

            $destinataires = $this->partenairesRepository->destinatairesDuLot($lot);
            $sansDestinataire = $this->partenairesRepository->dossiersSansDestinataireParMotif($lot);

            $this->afficherPerimetre($io, $destinataires, $sansDestinataire, $lot);

            if ([] === $destinataires) {
                $io->warning('Aucun professionnel joignable : il n\'y a personne à prévenir.');

                return Command::SUCCESS;
            }

            if (!$envoyer) {
                $io->section('Aucun courriel envoyé');
                $io->text([
                    sprintf('%d courriels seraient envoyés.', count($destinataires)),
                    'Relancer avec <info>--envoyer</info> pour les envoyer et faire partir le préavis.',
                ]);

                return Command::SUCCESS;
            }

            // Les dates sont posées AVANT l'envoi, parce que les courriels
            // annoncent la fin du préavis. Elles ne seront enregistrées que si
            // au moins un message est parti : sans destinataire prévenu, le
            // préavis n'a pas commencé.
            $lot->annoncerLe(new \DateTime());

            $resultat = $this->envoyerAuxProfessionnels($io, $lot, $destinataires);

            if (0 === $resultat['envoyes']) {
                $io->error(sprintf(
                    'Aucun courriel n\'est parti sur %d tentatives : le préavis n\'a pas été enregistré.',
                    count($destinataires)
                ));

                return Command::FAILURE;
            }

            $this->envoyerALaRegion($io, $lot, count($destinataires), $sansDestinataire);

            $this->lotRepository->enregistrer($lot);

            $io->success(sprintf(
                '%d courriels envoyés, %d en échec. Préavis jusqu\'au %s.',
                $resultat['envoyes'],
                $resultat['echecs'],
                $lot->getDateFinPreavis()?->format('d/m/Y') ?? '?'
            ));

            $this->loggerArchivage->info('Annonce : terminee', [
                'reference' => $lot->getReference(),
                'envoyes' => $resultat['envoyes'],
                'echecs' => $resultat['echecs'],
                'date_annonce' => $lot->getDateAnnonce()?->format('c'),
                'date_fin_preavis' => $lot->getDateFinPreavis()?->format('c'),
            ]);

            return $resultat['echecs'] > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $this->loggerArchivage->error('Annonce : echec', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return Command::FAILURE;
        }
    }

    private function resoudreLot(InputInterface $input): ArchiveLot
    {
        $reference = $input->getOption('lot');

        if (null !== $reference && '' !== $reference) {
            $lot = $this->lotRepository->findParReference((string) $reference);

            if (null === $lot) {
                throw new \RuntimeException(sprintf('Aucun lot ne porte la référence %s.', (string) $reference));
            }
        } else {
            $lot = $this->lotRepository->findLotOuvert();

            if (null === $lot) {
                throw new \RuntimeException(
                    'Aucune campagne en cours. Lancer normandie:archivage:selection --creer au préalable.'
                );
            }
        }

        if (ArchiveLot::ETAT_ANNONCE !== $lot->getEtat()) {
            throw new \RuntimeException(sprintf(
                'Le lot %s est à l\'état « %s » : l\'annonce ne concerne que les lots à l\'état « %s ».',
                $lot->getReference(),
                $lot->getEtat(),
                ArchiveLot::ETAT_ANNONCE
            ));
        }

        if (null !== $lot->getDateAnnonce() && !$input->getOption('relancer')) {
            throw new \RuntimeException(sprintf(
                'Le lot %s a déjà été annoncé le %s, préavis jusqu\'au %s. Utiliser --relancer pour renvoyer '
                . "l'annonce, ce qui fait repartir le préavis de zéro.",
                $lot->getReference(),
                $lot->getDateAnnonce()->format('d/m/Y'),
                $lot->getDateFinPreavis()?->format('d/m/Y') ?? '?'
            ));
        }

        return $lot;
    }

    /**
     * @param list<array{profil: string, dossiers: array<int, mixed>}> $destinataires
     * @param array<string, int>                                      $sansDestinataire
     */
    private function afficherPerimetre(
        SymfonyStyle $io,
        array $destinataires,
        array $sansDestinataire,
        ArchiveLot $lot
    ): void {
        $parProfil = [];

        foreach ($destinataires as $destinataire) {
            $profil = $destinataire['profil'];
            $parProfil[$profil]['destinataires'] = ($parProfil[$profil]['destinataires'] ?? 0) + 1;
            $parProfil[$profil]['dossiers'] = ($parProfil[$profil]['dossiers'] ?? 0) + count($destinataire['dossiers']);
        }

        ksort($parProfil);

        $io->table(
            ['Profil', 'Destinataires', 'Dossiers concernés'],
            array_map(
                static fn (string $profil, array $compteurs): array => [
                    $profil,
                    $compteurs['destinataires'],
                    $compteurs['dossiers'],
                ],
                array_keys($parProfil),
                $parProfil
            )
        );

        $io->text(sprintf(
            'Lot de %d comptes et %d dossiers.',
            $lot->getNbComptes(),
            $lot->getNbDossiers()
        ));

        foreach ($sansDestinataire as $motif => $nombre) {
            $io->text(sprintf(
                '<comment>%d dossiers sans destinataire — %s.</comment>',
                $nombre,
                $motif
            ));
        }

        if ([] !== $sansDestinataire) {
            $io->text(
                'Un compte désactivé ne peut pas se connecter, donc ne peut rien exporter : '
                . 'ces dossiers ne sont pas annoncés, et le chiffre est remonté à la Région.'
            );
        }
    }

    /**
     * @param list<array{user_id: int, username: string, email: string, nom: string, profil: string, dossiers: list<array{demande_id: int, demande_type: int}>}> $destinataires
     *
     * @return array{envoyes: int, echecs: int}
     */
    private function envoyerAuxProfessionnels(SymfonyStyle $io, ArchiveLot $lot, array $destinataires): array
    {
        $envoyes = 0;
        $echecs = 0;

        $io->progressStart(count($destinataires));

        foreach ($destinataires as $destinataire) {
            $tous = $destinataire['dossiers'];
            $listes = array_slice($tous, 0, self::MAX_DOSSIERS_LISTES);

            $corps = $this->twig->render('Email/archivage_annonce_partenaire.html.twig', [
                'nom' => $destinataire['nom'],
                'profil' => $destinataire['profil'],
                'nombre_dossiers' => count($tous),
                'dossiers' => array_map(
                    static fn (array $dossier): array => [
                        'demande_id' => $dossier['demande_id'],
                        'libelle' => Demande_::$demandeType[$dossier['demande_type']] ?? 'Dossier',
                    ],
                    $listes
                ),
                'tronquee' => count($tous) > count($listes),
                'date_fin_preavis' => $lot->getDateFinPreavis(),
            ]);

            $resultat = $this->mailerService->sendGeneriqueEmail(
                'Chèque Éco-Énergie Normandie — dossiers à archiver : exportez avant le '
                    . ($lot->getDateFinPreavis()?->format('d/m/Y') ?? ''),
                $corps,
                null,
                $destinataire['email'],
                $destinataire['nom']
            );

            if (1 === $resultat) {
                ++$envoyes;
            } else {
                ++$echecs;
                $this->loggerArchivage->error('Annonce : envoi en echec', [
                    'reference' => $lot->getReference(),
                    'username' => $destinataire['username'],
                    'nb_dossiers' => count($tous),
                ]);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        return ['envoyes' => $envoyes, 'echecs' => $echecs];
    }

    /**
     * @param array<string, int> $sansDestinataire
     */
    private function envoyerALaRegion(
        SymfonyStyle $io,
        ArchiveLot $lot,
        int $nombreDestinataires,
        array $sansDestinataire
    ): void {
        if ('' === $this->emailRegionArchivage) {
            $io->warning(
                "Aucune adresse d'archivage configurée (EMAIL_REGION_ARCHIVAGE) : "
                . "le courriel de confirmation à la Région n'a pas été envoyé."
            );

            return;
        }

        $corps = $this->twig->render('Email/archivage_annonce_region.html.twig', [
            'lot' => $lot,
            'synthese' => $this->eligibiliteRepository->syntheseParDeclencheur($lot->getDateReference()),
            'nombre_destinataires' => $nombreDestinataires,
            'dossiers_sans_destinataire' => $sansDestinataire,
        ]);

        $resultat = $this->mailerService->sendGeneriqueEmail(
            sprintf('Chèque Éco-Énergie Normandie — campagne d\'archivage %s lancée', $lot->getReference()),
            $corps,
            null,
            $this->emailRegionArchivage
        );

        if (1 !== $resultat) {
            $io->warning("Le courriel de confirmation à la Région n'a pas pu être envoyé.");
            $this->loggerArchivage->error('Annonce : confirmation Region en echec', [
                'reference' => $lot->getReference(),
            ]);
        }
    }
}

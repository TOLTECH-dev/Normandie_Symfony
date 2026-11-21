<?php

declare(strict_types=1);

namespace App\Archive;

use App\Entity\ArchiveLot;
use App\Repository\UserRepository;
use App\Service\MailerService;
use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * Envoi aux administrateurs des deux documents d'une campagne d'archivage
 * (Reco-03) : le bordereau de versement, puis l'attestation de destruction.
 *
 * Les destinataires sont les comptes portant les rôles configurés, résolus au
 * moment de l'envoi — même mécanisme que l'alerte antivirus
 * (App\Security\Antivirus\AlerteAntivirusMailer), pour qu'un administrateur
 * ajouté ou retiré soit pris en compte sans intervention.
 *
 * Règle de contenu, et elle diffère entre les deux documents :
 *
 *  - le BORDEREAU est nominatif par nature, puisque c'est sa fonction. Il part
 *    donc en PIÈCE JOINTE, et le corps du message n'en reprend que des
 *    compteurs, avec un avertissement sur la nature du fichier ;
 *  - l'ATTESTATION ne porte que des numéros de compte : ni le corps ni la
 *    pièce jointe ne contiennent de donnée nominative.
 *
 * Un envoi en échec n'interrompt jamais la campagne : les documents sont déjà
 * écrits sur le serveur, et un courriel perdu se renvoie. L'échec est
 * journalisé et remonté à l'appelant, qui le signale.
 */
final class DiffusionArchivage
{
    private const TEMPLATE_BORDEREAU = 'Email/archivage_bordereau.html.twig';
    private const TEMPLATE_ATTESTATION = 'Email/archivage_attestation.html.twig';

    /**
     * @param list<string> $rolesDestinataires rôles dont les comptes reçoivent les documents
     */
    public function __construct(
        private readonly MailerService $mailerService,
        private readonly UserRepository $userRepository,
        private readonly Environment $twig,
        private readonly LoggerInterface $loggerArchivage,
        private readonly array $rolesDestinataires,
    ) {
    }

    /**
     * @param array{comptes: int, dossiers: int, pieces: int, manquantes: int, octets: int} $compteurs
     *
     * @return array{destinataires: int, envoyes: int, echecs: int}
     */
    public function diffuserBordereau(ArchiveLot $lot, string $chemin, array $compteurs): array
    {
        return $this->diffuser(
            $lot,
            $chemin,
            self::TEMPLATE_BORDEREAU,
            sprintf(
                'Chèque Éco-Énergie Normandie — bordereau de versement de la campagne d\'archivage %s',
                $lot->getReference()
            ),
            ['lot' => $lot, 'compteurs' => $compteurs, 'fichier' => basename($chemin)],
            'bordereau'
        );
    }

    /**
     * @param array{comptes: int, pieces: int, manquantes: int, octets: int} $compteurs
     *
     * @return array{destinataires: int, envoyes: int, echecs: int}
     */
    public function diffuserAttestation(ArchiveLot $lot, string $chemin, array $compteurs): array
    {
        return $this->diffuser(
            $lot,
            $chemin,
            self::TEMPLATE_ATTESTATION,
            sprintf(
                'Chèque Éco-Énergie Normandie — attestation de destruction de la campagne %s',
                $lot->getReference()
            ),
            ['lot' => $lot, 'compteurs' => $compteurs, 'fichier' => basename($chemin)],
            'attestation'
        );
    }

    /**
     * @param array<string, mixed> $contexte
     *
     * @return array{destinataires: int, envoyes: int, echecs: int}
     */
    private function diffuser(
        ArchiveLot $lot,
        string $chemin,
        string $gabarit,
        string $sujet,
        array $contexte,
        string $nature
    ): array {
        if (!is_file($chemin) || !is_readable($chemin)) {
            throw new \RuntimeException(sprintf('Document à diffuser introuvable : %s', $chemin));
        }

        $destinataires = $this->destinataires();
        $corps = $this->twig->render($gabarit, $contexte);

        $envoyes = 0;
        $echecs = 0;

        foreach ($destinataires as $adresse) {
            // La pièce jointe est passée par son chemin ; surtout pas de
            // suppression après envoi : les documents restent sur le serveur,
            // à disposition des Archives et pour un renvoi éventuel.
            $resultat = $this->mailerService->sendGeneriqueEmail(
                $sujet,
                $corps,
                null,
                $adresse,
                null,
                'text/html',
                'UTF-8',
                null,
                null,
                null,
                null,
                $chemin
            );

            if (1 === $resultat) {
                ++$envoyes;
            } else {
                ++$echecs;
            }
        }

        $this->loggerArchivage->info(sprintf('Diffusion du %s', $nature), [
            'reference' => $lot->getReference(),
            'fichier' => basename($chemin),
            'destinataires' => count($destinataires),
            'envoyes' => $envoyes,
            'echecs' => $echecs,
        ]);

        return ['destinataires' => count($destinataires), 'envoyes' => $envoyes, 'echecs' => $echecs];
    }

    /**
     * Adresses distinctes des comptes à prévenir.
     *
     * Les comptes désactivés sont exclus par le dépôt ; une adresse vide l'est
     * ici. L'absence totale de destinataire est journalisée : la campagne se
     * poursuit, mais personne n'aurait reçu les documents, et cela doit se
     * voir.
     *
     * @return list<string>
     */
    public function destinataires(): array
    {
        $adresses = [];

        foreach ($this->rolesDestinataires as $role) {
            foreach ($this->userRepository->findByRole($role) as $compte) {
                $adresse = $compte->getEmail();

                if (null !== $adresse && '' !== $adresse) {
                    $adresses[strtolower($adresse)] = $adresse;
                }
            }
        }

        if ([] === $adresses) {
            $this->loggerArchivage->warning(
                'Aucun administrateur à qui envoyer les documents d\'archivage.',
                ['roles' => $this->rolesDestinataires]
            );
        }

        return array_values($adresses);
    }
}

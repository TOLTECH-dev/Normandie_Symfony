<?php

declare(strict_types=1);

namespace App\Security\Antivirus;

use App\Repository\UserRepository;
use App\Service\MailerService;
use Psr\Log\LoggerInterface;

/**
 * Prévient les administrateurs qu'un fichier infecté a été refusé au dépôt.
 *
 * Le courriel ne contient ni identifiant, ni nom, ni numéro de dossier : il signale l'événement et
 * renvoie aux journaux du serveur, où le détail est conservé. Un message intercepté ou transféré
 * ne doit renseigner personne (même règle que les courriels d'authentification).
 */
final class AlerteAntivirusMailer implements AlerteAntivirus
{
    private const TEMPLATE = 'Email/antivirus_alerte.html.twig';

    /**
     * @param list<string> $rolesAlertes rôles dont les comptes reçoivent l'alerte
     */
    public function __construct(
        private readonly MailerService $mailerService,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
        private readonly array $rolesAlertes,
        private readonly bool $alerteActive
    ) {
    }

    public function signalerFichierInfecte(string $menace, string $champ): void
    {
        if (!$this->alerteActive) {
            return;
        }

        $contexte = [
            'menace' => $menace,
            'champ' => $champ,
            'date' => new \DateTimeImmutable(),
        ];

        foreach ($this->destinataires() as $adresse) {
            $this->mailerService->sendTemplateEmail(
                $adresse,
                'Chèque Éco-Énergie : fichier infecté refusé',
                self::TEMPLATE,
                $contexte
            );
        }
    }

    /**
     * @return list<string> adresses distinctes des comptes à prévenir
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

        if ([] === $adresses) {
            $this->logger->warning('Aucun administrateur à prévenir du dépôt d\'un fichier infecté.', [
                'roles' => $this->rolesAlertes,
            ]);
        }

        return array_values($adresses);
    }
}

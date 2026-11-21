<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Service\MailerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;

/**
 * Détourne le courrier des environnements qui travaillent sur une copie de la production.
 *
 * Recette et pré-production rejouent des données réelles : la moindre action y expédierait un
 * message à un véritable bénéficiaire. La parade retenue jusqu'ici — « MAILER_DSN=null://null » —
 * évitait bien l'accident, mais au prix d'un angle mort : le parcours « mot de passe oublié »
 * devenait invérifiable, alors que c'est précisément celui que des milliers de comptes vont
 * emprunter après la mise en production.
 *
 * Cet écouteur remplace donc les destinataires de l'enveloppe par une boîte de contrôle, sans
 * toucher au « À : » affiché : le message part réellement, on en vérifie la mise en forme et les
 * liens, et l'on voit à qui il était destiné.
 *
 * **Le code de double authentification échappe à la redirection**, faute de quoi plus personne ne
 * pourrait se connecter à l'environnement. Il se reconnaît au marquage MailerService::ENTETE_VOIE,
 * et non à « X-Transport » : ce dernier est retiré du message par Transports::send() avant que le
 * transport ne diffuse l'évènement traité ici.
 *
 * En production, SECURITY_MAILER_REDIRECT_TO est vide et l'écouteur ne fait rien.
 */
#[AsEventListener(event: MessageEvent::class, priority: -256)]
final class RedirectionCourrielListener
{
    /** @var list<string> */
    private readonly array $destinataires;

    public function __construct(
        string $destinataires,
        private readonly LoggerInterface $logger
    ) {
        $this->destinataires = array_values(array_filter(
            array_map('trim', explode(',', $destinataires)),
            static fn (string $adresse): bool => '' !== $adresse
        ));
    }

    public function __invoke(MessageEvent $event): void
    {
        if ([] === $this->destinataires) {
            return;
        }

        // L'évènement « mis en file » porte un CLONE du message : Mailer::send() diffuse une copie
        // pour que les écouteurs puissent observer sans modifier. Y réécrire l'enveloppe serait
        // sans effet. Le passage utile est celui du transport, juste avant l'envoi.
        if ($event->isQueued()) {
            return;
        }

        $message = $event->getMessage();

        if ($message instanceof Message && $this->estLeCodeDeConnexion($message)) {
            return;
        }

        $envoiPrevu = array_map(
            static fn (Address $adresse): string => $adresse->getAddress(),
            $event->getEnvelope()->getRecipients()
        );

        if ($envoiPrevu === $this->destinataires) {
            return;
        }

        $event->getEnvelope()->setRecipients(array_map(
            static fn (string $adresse): Address => new Address($adresse),
            $this->destinataires
        ));

        $this->logger->info('Courriel détourné (environnement de test)', [
            'destinataires_prevus' => $envoiPrevu,
            'destinataires_reels'  => $this->destinataires,
        ]);
    }

    private function estLeCodeDeConnexion(Message $message): bool
    {
        $entetes = $message->getHeaders();

        if (!$entetes->has(MailerService::ENTETE_VOIE)) {
            return false;
        }

        return MailerService::TRANSPORT_MFA === $entetes->get(MailerService::ENTETE_VOIE)->getBody();
    }
}

<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Journalise les changements d'état d'un compte (D26013, point 4).
 *
 * La demande veut « la date heure de chaque changement d'état : création,
 * désactivation, réactivation, suppression ». La table `user` n'en garde
 * aucune trace exploitable : `enabled` est un booléen sans mémoire, une
 * réactivation n'écrit rien, et la suppression efface la ligne.
 *
 * L'écoute se fait sur l'ENTITÉ et non dans les écrans d'administration : un
 * compte change aussi d'état par l'import, par les commandes de purge, et par
 * le verrouillage après cinq échecs de connexion. Un historique qui dépendrait
 * du chemin emprunté serait faux sans qu'on s'en aperçoive.
 *
 * L'écriture passe par DBAL, dans la transaction en cours : si l'opération
 * échoue, la ligne d'historique disparaît avec elle. Passer par l'ORM depuis un
 * `preUpdate` demanderait de manipuler l'unité de travail en cours de vidage,
 * source d'ennuis connue — c'est le choix déjà fait pour les partenaires
 * (PartenaireStatutHistoriqueListener, Reco-13).
 */
#[AsEntityListener(event: Events::postPersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
#[AsEntityListener(event: Events::preRemove, entity: User::class)]
class UserEtatHistoriqueListener
{
    public const ETAT_CREATION = 'creation';
    public const ETAT_DESACTIVATION = 'desactivation';
    public const ETAT_REACTIVATION = 'reactivation';
    public const ETAT_SUPPRESSION = 'suppression';

    /** Changement constaté par l'application, par opposition à la reconstitution initiale. */
    private const ORIGINE_OBSERVE = 'observe';

    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(User $user, PostPersistEventArgs $args): void
    {
        $this->journaliser($user, self::ETAT_CREATION);
    }

    /**
     * Seul le passage de `enabled` nous intéresse : un changement de nom ou de
     * rôle n'est pas un changement d'état.
     */
    public function preUpdate(User $user, PreUpdateEventArgs $args): void
    {
        if (!$args->hasChangedField('enabled')) {
            return;
        }

        $this->journaliser(
            $user,
            $args->getNewValue('enabled') ? self::ETAT_REACTIVATION : self::ETAT_DESACTIVATION
        );
    }

    /**
     * Enregistré AVANT la suppression : après, l'identifiant et le login
     * auraient disparu. La table ne porte d'ailleurs aucune clé étrangère vers
     * `user`, sans quoi cette ligne serait emportée par la suppression qu'elle
     * sert à documenter.
     */
    public function preRemove(User $user, PreRemoveEventArgs $args): void
    {
        $this->journaliser($user, self::ETAT_SUPPRESSION);
    }

    private function journaliser(User $user, string $etat): void
    {
        $userId = $user->getId();

        if (null === $userId) {
            return;
        }

        try {
            $this->connection->executeStatement(
                <<<'SQL'
                INSERT INTO user_etat_historique
                    (user_id, username, etat, date_evenement, auteur, origine)
                VALUES (:user, :username, :etat, NOW(), :auteur, :origine)
                SQL,
                [
                    'user' => $userId,
                    'username' => $user->getUsername(),
                    'etat' => $etat,
                    'auteur' => $this->auteur(),
                    'origine' => self::ORIGINE_OBSERVE,
                ]
            );
        } catch (\Throwable $e) {
            // Un historique qui échoue ne doit pas empêcher un administrateur de
            // désactiver un compte. L'incident est journalisé : c'est une perte
            // de traçabilité, pas une perte de donnée métier.
            $this->logger->error('Historique des états de compte : écriture impossible', [
                'user_id' => $userId,
                'etat' => $etat,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Qui a fait le changement. Le nom de compte est posé en session par le
     * back-office ; hors contexte HTTP (commande, import), on note « Automate »,
     * comme le fait déjà Historique_.
     */
    private function auteur(): string
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if (null !== $session && $session->isStarted()) {
            $utilisateur = $session->get('login');

            if (\is_object($utilisateur) && method_exists($utilisateur, 'getUsername')) {
                return (string) $utilisateur->getUsername();
            }
        }

        return 'Automate';
    }
}

<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Partenaire_;
use App\Entity\Partenaire_statut;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Journalise les changements d'activité des partenaires (Reco-13).
 *
 * La recommandation demande « un historique précis des comptes actifs /
 * inactifs (un compte peut être temporairement inactif) », que
 * `partenaire_statut` ne peut pas fournir : en relation un pour un avec le
 * partenaire, elle ne retient que la dernière désactivation.
 *
 * L'écoute se fait sur l'ENTITÉ, et non dans les contrôleurs : un changement
 * d'état passe aujourd'hui par un formulaire lié à l'entité, demain peut-être
 * par un import ou une commande, et un historique qui dépendrait du chemin
 * emprunté serait faux sans qu'on s'en aperçoive.
 *
 * L'écriture se fait en DBAL, à l'intérieur de la transaction de la mise à
 * jour : si celle-ci échoue, la ligne d'historique disparaît avec elle. Passer
 * par l'ORM depuis un `preUpdate` demanderait de manipuler l'unité de travail
 * en cours de vidage, ce qui est une source d'ennuis connue.
 */
#[AsEntityListener(event: Events::preUpdate, entity: Partenaire_statut::class)]
#[AsEntityListener(event: Events::postPersist, entity: Partenaire_::class)]
class PartenaireStatutHistoriqueListener
{
    /** Changement constaté par l'application, par opposition à la reconstitution initiale. */
    private const ORIGINE_OBSERVE = 'observe';

    /** Date que portent les partenaires actifs : ce n'est pas une désactivation. */
    private const SENTINELLE = '1900-01-01';

    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Un changement d'activité sur une fiche existante.
     */
    public function preUpdate(Partenaire_statut $statut, PreUpdateEventArgs $args): void
    {
        if (!$args->hasChangedField('enabled')) {
            return;
        }

        $nouvelEtat = (bool) $args->getNewValue('enabled');
        $partenaireId = $this->partenaireDuStatut($statut);

        if (null === $partenaireId) {
            // Aucun partenaire ne référence encore ce statut : la fiche est en
            // cours de création, et postPersist s'en chargera.
            return;
        }

        // Une RÉACTIVATION n'a pas de date métier : le schéma n'en porte
        // aucune. `date_rattachement` est la date de rattachement initiale et
        // ne bouge jamais — s'en servir ici daterait la réactivation de
        // plusieurs années en arrière, et produirait des périodes dont la fin
        // précède le début. On laisse donc la date d'enregistrement faire foi.
        $dateEvenement = $nouvelEtat ? null : $this->dateDeDesactivation($statut);

        $this->journaliser($partenaireId, $nouvelEtat, $dateEvenement);
    }

    /**
     * La création d'un partenaire vaut première activation.
     */
    public function postPersist(Partenaire_ $partenaire, PostPersistEventArgs $args): void
    {
        $statut = $partenaire->getPartenaireStatut();
        $partenaireId = $partenaire->getId();

        if (null === $statut || null === $partenaireId) {
            return;
        }

        $actif = (bool) $statut->getEnabled();

        // À la création, et seulement là, la date de rattachement DÉSIGNE bien
        // l'activation initiale.
        $dateEvenement = $actif
            ? $this->formater($statut->getDateRattachement())
            : $this->dateDeDesactivation($statut);

        $this->journaliser($partenaireId, $actif, $dateEvenement);
    }

    /**
     * La date de désactivation saisie sur la fiche. La sentinelle que portent
     * les partenaires actifs est écartée : elle ne désigne aucune date réelle.
     */
    private function dateDeDesactivation(Partenaire_statut $statut): ?string
    {
        return $this->formater($statut->getDateInactif());
    }

    private function formater(?\DateTime $date): ?string
    {
        if (null === $date) {
            return null;
        }

        $formatee = $date->format('Y-m-d');

        return self::SENTINELLE === $formatee ? null : $formatee;
    }

    private function partenaireDuStatut(Partenaire_statut $statut): ?int
    {
        $id = $statut->getId();

        if (null === $id) {
            return null;
        }

        $trouve = $this->connection->fetchOne(
            'SELECT id FROM partenaire_ WHERE partenaire_statut_id = :statut',
            ['statut' => $id]
        );

        return false === $trouve || null === $trouve ? null : (int) $trouve;
    }

    private function journaliser(int $partenaireId, bool $actif, ?string $dateEvenement): void
    {
        try {
            $this->connection->executeStatement(
                <<<'SQL'
                INSERT INTO partenaire_statut_historique
                    (partenaire_id, enabled, date_evenement, date_creation, auteur, origine)
                VALUES (:partenaire, :enabled, :date_evenement, NOW(), :auteur, :origine)
                SQL,
                [
                    'partenaire' => $partenaireId,
                    'enabled' => $actif ? 1 : 0,
                    'date_evenement' => $dateEvenement,
                    'auteur' => $this->auteur(),
                    'origine' => self::ORIGINE_OBSERVE,
                ]
            );
        } catch (\Throwable $e) {
            // Un historique qui échoue ne doit pas empêcher un gestionnaire de
            // désactiver un partenaire. L'incident est journalisé : c'est une
            // perte de traçabilité, pas une perte de donnée métier.
            $this->logger->error('Historique du statut partenaire : écriture impossible', [
                'partenaire_id' => $partenaireId,
                'enabled' => $actif,
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

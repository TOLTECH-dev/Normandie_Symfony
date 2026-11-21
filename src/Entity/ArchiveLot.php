<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ArchiveLotRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une campagne annuelle d'archivage (Reco-03).
 *
 * L'état est le garde-fou central de la procédure : la destruction n'est
 * autorisée qu'à partir de ETAT_RECEPTION_VALIDEE, c'est-à-dire une fois que
 * les Archives de la Région ont confirmé avoir récupéré les archives. Aucune
 * donnée ne disparaît donc avant que sa conservation soit assurée ailleurs.
 */
#[ORM\Table(name: 'archive_lot')]
#[ORM\Entity(repositoryClass: ArchiveLotRepository::class)]
#[ORM\Index(name: 'idx_archive_lot_etat', columns: ['etat'])]
class ArchiveLot
{
    /** Comptes identifiés et partenaires prévenus ; rien n'est encore produit. */
    final public const ETAT_ANNONCE = 'annonce';

    /** Les archives sont constituées et vérifiées. */
    final public const ETAT_CONSTITUE = 'constitue';

    /** Les archives sont déposées sur le serveur, à disposition des Archives. */
    final public const ETAT_VERSE = 'verse';

    /** Les Archives ont confirmé la récupération : la destruction devient possible. */
    final public const ETAT_RECEPTION_VALIDEE = 'reception_validee';

    final public const ETAT_DETRUIT = 'detruit';

    final public const ETAT_ATTESTE = 'atteste';

    /** Campagne interrompue : aucune suite, conservée pour la traçabilité. */
    final public const ETAT_ABANDONNE = 'abandonne';

    /**
     * Enchaînement autorisé. Un état absent de ce tableau est terminal.
     *
     * @var array<string, list<string>>
     */
    final public const TRANSITIONS = [
        self::ETAT_ANNONCE => [self::ETAT_CONSTITUE, self::ETAT_ABANDONNE],
        self::ETAT_CONSTITUE => [self::ETAT_VERSE, self::ETAT_ABANDONNE],
        self::ETAT_VERSE => [self::ETAT_RECEPTION_VALIDEE, self::ETAT_ABANDONNE],
        self::ETAT_RECEPTION_VALIDEE => [self::ETAT_DETRUIT],
        self::ETAT_DETRUIT => [self::ETAT_ATTESTE],
    ];

    /** Les états dans lesquels une campagne est encore en cours. */
    final public const ETATS_OUVERTS = [
        self::ETAT_ANNONCE,
        self::ETAT_CONSTITUE,
        self::ETAT_VERSE,
        self::ETAT_RECEPTION_VALIDEE,
        self::ETAT_DETRUIT,
    ];

    /** Préavis laissé aux conseillers, auditeurs et rénovateurs, en mois. */
    final public const PREAVIS_PARTENAIRES_MOIS = 1;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private string $reference;

    #[ORM\Column(type: 'string', length: 32)]
    private string $etat = self::ETAT_ANNONCE;

    /**
     * Date à laquelle l'éligibilité a été calculée. Conservée pour que la
     * sélection soit reproductible : rejouer la commande avec cette date doit
     * redonner la même liste de comptes.
     */
    #[ORM\Column(name: 'date_reference', type: 'datetime')]
    private \DateTimeInterface $dateReference;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'date_annonce', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateAnnonce = null;

    #[ORM\Column(name: 'date_fin_preavis', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateFinPreavis = null;

    #[ORM\Column(name: 'date_constitution', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateConstitution = null;

    #[ORM\Column(name: 'date_versement', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateVersement = null;

    #[ORM\Column(name: 'date_reception_validee', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateReceptionValidee = null;

    #[ORM\Column(name: 'date_destruction', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDestruction = null;

    #[ORM\Column(name: 'date_attestation', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateAttestation = null;

    /**
     * Date à laquelle les archives déposées ont été retirées du serveur.
     *
     * Le répertoire de dépôt est une zone de transit : garder les archives
     * après que les Archives de la Région les ont récupérées et que
     * l'attestation est produite reviendrait à conserver, sous une autre
     * forme, les données dont on vient d'attester la destruction.
     */
    #[ORM\Column(name: 'date_purge_depot', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $datePurgeDepot = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $auteur;

    #[ORM\Column(name: 'nb_comptes', type: 'integer')]
    private int $nbComptes = 0;

    #[ORM\Column(name: 'nb_dossiers', type: 'integer')]
    private int $nbDossiers = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    public function __construct(string $reference, \DateTimeInterface $dateReference, string $auteur)
    {
        $this->reference = $reference;
        $this->dateReference = $dateReference;
        $this->auteur = $auteur;
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getEtat(): string
    {
        return $this->etat;
    }

    /**
     * Fait passer le lot à l'état demandé, en refusant tout enchaînement qui
     * n'est pas prévu : c'est ce qui empêche, par exemple, de détruire un lot
     * dont les Archives n'ont pas confirmé la réception.
     *
     * @throws \LogicException si la transition n'est pas autorisée
     */
    public function passerA(string $etat): self
    {
        $autorises = self::TRANSITIONS[$this->etat] ?? [];

        if (!in_array($etat, $autorises, true)) {
            throw new \LogicException(sprintf(
                'Transition interdite pour le lot %s : « %s » vers « %s ». Attendu : %s.',
                $this->reference,
                $this->etat,
                $etat,
                [] === $autorises ? 'aucune transition (état terminal)' : implode(', ', $autorises)
            ));
        }

        $this->etat = $etat;

        return $this;
    }

    public function estOuvert(): bool
    {
        return in_array($this->etat, self::ETATS_OUVERTS, true);
    }

    /**
     * La destruction est-elle autorisée ? C'est le verrou de la procédure.
     */
    public function destructionAutorisee(): bool
    {
        return self::ETAT_RECEPTION_VALIDEE === $this->etat;
    }

    /**
     * Le préavis donné aux partenaires est-il échu ? Tant qu'il ne l'est pas,
     * aucune archive ne doit être constituée : les partenaires doivent avoir le
     * temps de réaliser leur propre export.
     */
    public function preavisEchu(?\DateTimeInterface $maintenant = null): bool
    {
        if (null === $this->dateFinPreavis) {
            return false;
        }

        return ($maintenant ?? new \DateTime()) >= $this->dateFinPreavis;
    }

    public function getDateReference(): \DateTimeInterface
    {
        return $this->dateReference;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function getDateAnnonce(): ?\DateTimeInterface
    {
        return $this->dateAnnonce;
    }

    /**
     * Enregistre l'envoi réel de l'annonce et en déduit le terme du préavis :
     * la Région a retenu un préavis d'un mois « décompté à partir de la date
     * d'envoi réelle du courriel ».
     */
    public function annoncerLe(\DateTimeInterface $dateEnvoi): self
    {
        $this->dateAnnonce = $dateEnvoi;

        $fin = \DateTime::createFromInterface($dateEnvoi);
        $fin->modify(sprintf('+%d month', self::PREAVIS_PARTENAIRES_MOIS));
        $this->dateFinPreavis = $fin;

        return $this;
    }

    public function getDateFinPreavis(): ?\DateTimeInterface
    {
        return $this->dateFinPreavis;
    }

    public function getDateConstitution(): ?\DateTimeInterface
    {
        return $this->dateConstitution;
    }

    public function setDateConstitution(?\DateTimeInterface $date): self
    {
        $this->dateConstitution = $date;

        return $this;
    }

    public function getDateVersement(): ?\DateTimeInterface
    {
        return $this->dateVersement;
    }

    public function setDateVersement(?\DateTimeInterface $date): self
    {
        $this->dateVersement = $date;

        return $this;
    }

    public function getDateReceptionValidee(): ?\DateTimeInterface
    {
        return $this->dateReceptionValidee;
    }

    public function setDateReceptionValidee(?\DateTimeInterface $date): self
    {
        $this->dateReceptionValidee = $date;

        return $this;
    }

    public function getDateDestruction(): ?\DateTimeInterface
    {
        return $this->dateDestruction;
    }

    public function setDateDestruction(?\DateTimeInterface $date): self
    {
        $this->dateDestruction = $date;

        return $this;
    }

    public function getDateAttestation(): ?\DateTimeInterface
    {
        return $this->dateAttestation;
    }

    public function setDateAttestation(?\DateTimeInterface $date): self
    {
        $this->dateAttestation = $date;

        return $this;
    }

    public function getDatePurgeDepot(): ?\DateTimeInterface
    {
        return $this->datePurgeDepot;
    }

    public function setDatePurgeDepot(?\DateTimeInterface $date): self
    {
        $this->datePurgeDepot = $date;

        return $this;
    }

    public function getAuteur(): string
    {
        return $this->auteur;
    }

    public function getNbComptes(): int
    {
        return $this->nbComptes;
    }

    public function setNbComptes(int $nbComptes): self
    {
        $this->nbComptes = $nbComptes;

        return $this;
    }

    public function getNbDossiers(): int
    {
        return $this->nbDossiers;
    }

    public function setNbDossiers(int $nbDossiers): self
    {
        $this->nbDossiers = $nbDossiers;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }
}

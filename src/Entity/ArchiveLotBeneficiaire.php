<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ArchiveLotBeneficiaireRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un compte bénéficiaire retenu dans une campagne d'archivage (Reco-03).
 *
 * L'unité d'archivage est le COMPTE (`user` portant ROLE_MEMBER), et non la
 * ligne de la table `beneficiaire` : 197 comptes en portent plusieurs, et une
 * archive doit rassembler les dossiers de toutes les lignes du compte.
 *
 * Volontairement sans association vers User : le registre doit rester lisible
 * et attestable même si le compte disparaissait un jour, alors qu'une relation
 * contrainte le ferait disparaître avec lui.
 */
#[ORM\Table(name: 'archive_lot_beneficiaire')]
#[ORM\Entity(repositoryClass: ArchiveLotBeneficiaireRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_archive_lot_beneficiaire', columns: ['lot_id', 'user_id'])]
#[ORM\Index(name: 'idx_archive_lot_beneficiaire_etat', columns: ['etat'])]
#[ORM\Index(name: 'idx_archive_lot_beneficiaire_user', columns: ['user_id'])]
class ArchiveLotBeneficiaire
{
    final public const ETAT_A_PRODUIRE = 'a_produire';
    final public const ETAT_PRODUITE = 'produite';
    final public const ETAT_SUPPRIMEE = 'supprimee';

    /** Compte redevenu non éligible pendant le préavis : il sort du lot. */
    final public const ETAT_RETIREE = 'retiree';

    final public const ETAT_EN_ERREUR = 'en_erreur';

    /** Les cinq déclencheurs de la politique d'archivage. */
    final public const DECLENCHEUR_TRAVAUX_PAYE = 'cheque travaux paye (RMH + 1 an)';
    final public const DECLENCHEUR_AUDIT_PAYE = 'cheque audit paye (RMH + 5 ans)';
    final public const DECLENCHEUR_DEMANDE_REFUSEE = 'demande refusee (+ 1 an)';
    final public const DECLENCHEUR_REMBOURSEMENT_REFUSE = 'remboursement refuse (+ 1 an)';
    final public const DECLENCHEUR_SANS_DEMANDE = 'compte sans demande (+ 2 ans)';
    final public const DECLENCHEUR_NON_FINALISEE = 'demande non finalisee (+ 2 ans)';

    /**
     * Libellé lisible de chaque déclencheur, pour les documents destinés à
     * être lus — l'attestation de destruction au premier chef.
     *
     * Les constantes ci-dessus restent sans accent : elles sont enregistrées
     * en base, où elles servent d'identifiant stable d'une campagne à l'autre.
     * L'accentuation est une affaire de présentation, et un document signé par
     * la Région doit être écrit en français correct.
     *
     * @var array<string, string>
     */
    final public const LIBELLES = [
        self::DECLENCHEUR_TRAVAUX_PAYE => 'chèque travaux payé depuis plus d’un an',
        self::DECLENCHEUR_AUDIT_PAYE => 'chèque audit payé depuis plus de cinq ans, sans chèque travaux',
        self::DECLENCHEUR_DEMANDE_REFUSEE => 'demande refusée depuis plus d’un an',
        self::DECLENCHEUR_REMBOURSEMENT_REFUSE => 'remboursement refusé depuis plus d’un an',
        self::DECLENCHEUR_SANS_DEMANDE => 'compte ouvert sans aucune demande depuis plus de deux ans',
        self::DECLENCHEUR_NON_FINALISEE => 'demande jamais finalisée depuis plus de deux ans',
    ];

    /**
     * Correspondance entre la situation du dossier le plus tardif, telle que la
     * calcule la requête d'éligibilité, et le déclencheur enregistré.
     *
     * @var array<string, string>
     */
    final public const DECLENCHEURS_PAR_SITUATION = [
        'travaux paye' => self::DECLENCHEUR_TRAVAUX_PAYE,
        'audit paye' => self::DECLENCHEUR_AUDIT_PAYE,
        'demande refusee' => self::DECLENCHEUR_DEMANDE_REFUSEE,
        'remboursement refuse' => self::DECLENCHEUR_REMBOURSEMENT_REFUSE,
        'demande non finalisee' => self::DECLENCHEUR_NON_FINALISEE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ArchiveLot::class)]
    #[ORM\JoinColumn(name: 'lot_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ArchiveLot $lot;

    #[ORM\Column(name: 'user_id', type: 'integer')]
    private int $userId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $declencheur;

    /**
     * Date à laquelle le compte est devenu archivable : la plus tardive de ses
     * dossiers. NULL pour un compte ouvert sans aucune demande.
     */
    #[ORM\Column(name: 'date_eligibilite', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateEligibilite = null;

    #[ORM\Column(name: 'nb_dossiers', type: 'integer')]
    private int $nbDossiers = 0;

    #[ORM\Column(type: 'string', length: 32)]
    private string $etat = self::ETAT_A_PRODUIRE;

    #[ORM\Column(name: 'zip_nom', type: 'string', length: 255, nullable: true)]
    private ?string $zipNom = null;

    #[ORM\Column(name: 'zip_taille', type: 'bigint', nullable: true)]
    private ?string $zipTaille = null;

    #[ORM\Column(name: 'zip_sha256', type: 'string', length: 64, nullable: true)]
    private ?string $zipSha256 = null;

    #[ORM\Column(name: 'nb_pieces', type: 'integer', nullable: true)]
    private ?int $nbPieces = null;

    /**
     * Pièces référencées en base mais absentes du disque. Déclarées plutôt que
     * passées sous silence : c'est ce qui rend l'attestation de destruction honnête.
     */
    #[ORM\Column(name: 'nb_pieces_manquantes', type: 'integer', nullable: true)]
    private ?int $nbPiecesManquantes = null;

    #[ORM\Column(name: 'date_production', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateProduction = null;

    #[ORM\Column(name: 'date_suppression', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateSuppression = null;

    #[ORM\Column(name: 'message_erreur', type: 'text', nullable: true)]
    private ?string $messageErreur = null;

    public function __construct(
        ArchiveLot $lot,
        int $userId,
        string $declencheur,
        ?\DateTimeInterface $dateEligibilite,
        int $nbDossiers
    ) {
        $this->lot = $lot;
        $this->userId = $userId;
        $this->declencheur = $declencheur;
        $this->dateEligibilite = $dateEligibilite;
        $this->nbDossiers = $nbDossiers;
    }

    /**
     * Déclencheur correspondant à une situation calculée par la requête
     * d'éligibilité. Une situation inconnue est une anomalie : mieux vaut
     * l'arrêter que d'enregistrer un motif faux dans une attestation.
     *
     * @throws \InvalidArgumentException
     */
    public static function declencheurPourSituation(?string $situation, int $nbDossiers): string
    {
        if (0 === $nbDossiers) {
            return self::DECLENCHEUR_SANS_DEMANDE;
        }

        if (null !== $situation && isset(self::DECLENCHEURS_PAR_SITUATION[$situation])) {
            return self::DECLENCHEURS_PAR_SITUATION[$situation];
        }

        throw new \InvalidArgumentException(sprintf(
            'Situation de dossier inconnue : « %s ». Déclencheurs connus : %s.',
            $situation ?? 'null',
            implode(', ', array_keys(self::DECLENCHEURS_PAR_SITUATION))
        ));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLot(): ArchiveLot
    {
        return $this->lot;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getDeclencheur(): string
    {
        return $this->declencheur;
    }

    public function getDateEligibilite(): ?\DateTimeInterface
    {
        return $this->dateEligibilite;
    }

    public function getNbDossiers(): int
    {
        return $this->nbDossiers;
    }

    public function getEtat(): string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): self
    {
        $this->etat = $etat;

        return $this;
    }

    public function getZipNom(): ?string
    {
        return $this->zipNom;
    }

    public function getZipSha256(): ?string
    {
        return $this->zipSha256;
    }

    public function getZipTaille(): ?int
    {
        return null === $this->zipTaille ? null : (int) $this->zipTaille;
    }

    /**
     * Enregistre l'archive produite. Les trois informations vont de pair :
     * sans empreinte, la destruction ne peut pas vérifier que l'archive est
     * intacte, et elle refusera de s'exécuter.
     */
    public function archiveProduite(string $nom, int $taille, string $sha256, int $nbPieces, int $nbPiecesManquantes): self
    {
        $this->zipNom = $nom;
        $this->zipTaille = (string) $taille;
        $this->zipSha256 = $sha256;
        $this->nbPieces = $nbPieces;
        $this->nbPiecesManquantes = $nbPiecesManquantes;
        $this->dateProduction = new \DateTime();
        $this->etat = self::ETAT_PRODUITE;
        $this->messageErreur = null;

        return $this;
    }

    public function getNbPieces(): ?int
    {
        return $this->nbPieces;
    }

    public function getNbPiecesManquantes(): ?int
    {
        return $this->nbPiecesManquantes;
    }

    public function getDateProduction(): ?\DateTimeInterface
    {
        return $this->dateProduction;
    }

    public function getDateSuppression(): ?\DateTimeInterface
    {
        return $this->dateSuppression;
    }

    public function supprimeeLe(\DateTimeInterface $date): self
    {
        $this->dateSuppression = $date;
        $this->etat = self::ETAT_SUPPRIMEE;

        return $this;
    }

    public function getMessageErreur(): ?string
    {
        return $this->messageErreur;
    }

    public function enErreur(string $message): self
    {
        $this->etat = self::ETAT_EN_ERREUR;
        $this->messageErreur = $message;

        return $this;
    }
}

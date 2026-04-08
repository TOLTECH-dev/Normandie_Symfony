<?php

namespace App\Entity;

use App\Repository\Historique_Repository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\Collection;

#[ORM\Table(name: 'historique_')]
#[ORM\Index(name: 'demande_idx', columns: ['demande_id'])]
#[ORM\Index(name: 'remboursement_idx', columns: ['remboursement_id'])]
#[ORM\Entity(repositoryClass: Historique_Repository::class)]
class Historique_
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTime $dateCreation;

    #[ORM\Column(name: 'auteur_creation', type: 'string', length: 255)]
    private string $auteurCreation;

    #[ORM\Column(name: 'auteur_role', type: 'string', length: 255)]
    private ?string $auteurRole = null;

    #[ORM\Column(name: 'action', type: 'string', length: 255)]
    private string $action;

    #[ORM\Column(name: 'demande_id', type: 'integer')]
    private int $demande_id;

    #[ORM\Column(name: 'statut_slug', type: 'string', length: 255)]
    private string $statutSlug;

    #[ORM\Column(name: 'is_email_sent', type: 'boolean', options: ['default' => false])]
    private bool $isEmailSent = false;

    #[ORM\OneToMany(targetEntity: Historique_email::class, mappedBy: 'historique', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Valid]
    private Collection $historique_email;

    #[ORM\OneToMany(targetEntity: Historique_post::class, mappedBy: 'historique', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $historique_post;

    #[ORM\Column(name: 'remboursement_id', type: 'integer', nullable: true)]
    private ?int $remboursement_id = null;

    /**
     * @var string[]
     */
    public static array $actionModificationDemande = [
        'Modification demande Audit énergétique et scénarios',
        'Modification demande Travaux',
        'Modification demande Audit énergétique et scénarios par le Bénéficiaire',
        'Modification demande Audit énergétique et scénarios par le Conseiller',
        'Modification demande Travaux par le Conseiller',
        'Modification demande Travaux par le Bénéficiaire',
        'Modification demande Audit énergétique et scénarios par la Région',
        'Modification demande Travaux par la Région',
        'Modification demande Audit Numérique par le Bénéficiaire',
        'Modification demande Audit énergétique Région Normandie par le Bénéficiaire',
        'Modification demande Audit énergétique Région Normandie par le Conseiller',
        'Modification demande Audit énergétique Région Normandie par la Région',
        'Modification demande Mise à jour Audit énergétique et scénarios par le Conseiller'
    ];

    const LABEL_HISTORIQUE_ACTION_COMMENTAIRE = 'commentaire';

    /**
     * Historique_ constructor.
     */
    public function __construct(
        string $historiqueAction,
        int $demandeId,
        ?int $remboursementId = null,
        bool $isEmailSent = false,
        ?string $roleNom = null,
        ?string $statutSlug = null
    ) {
//        if (PHP_SAPI != 'cli' && empty($_SESSION['login'])) {
//            return false;
//        }

        $this->historique_email = new ArrayCollection();
        $this->historique_post = new ArrayCollection();

        $this->dateCreation = new \DateTime();
        $this->auteurCreation = isset($_SESSION['login']) ? $_SESSION['login']->getUsername() : 'Automate';

        $this->setAction($historiqueAction);
        $this->setDemandeId($demandeId);
        $this->setRemboursementId($remboursementId);

        $this->setIsEmailSent($isEmailSent);
        $this->setAuteurRole($roleNom);
        $this->setStatutSlug($statutSlug);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDateCreation(\DateTime $dateCreation): self
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDateCreation(): \DateTime
    {
        return $this->dateCreation;
    }

    public function setAuteurCreation(string $auteurCreation): self
    {
        $this->auteurCreation = $auteurCreation;

        return $this;
    }

    public function getAuteurCreation(): string
    {
        return $this->auteurCreation;
    }

    public function setAuteurRole(?string $auteurRole): self
    {
        $this->auteurRole = $auteurRole;

        return $this;
    }

    public function getAuteurRole(): ?string
    {
        return $this->auteurRole;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setDemandeId(int $demandeId): self
    {
        $this->demande_id = $demandeId;

        return $this;
    }

    public function getDemandeId(): int
    {
        return $this->demande_id;
    }

    public function setIsEmailSent(bool $isEmailSent): self
    {
        $this->isEmailSent = $isEmailSent;

        return $this;
    }

    public function getIsEmailSent(): bool
    {
        return $this->isEmailSent;
    }

    public function addHistoriqueEmail($historiqueEmail): self
    {
        if (!$this->historique_email->contains($historiqueEmail)) {
            $this->historique_email->add($historiqueEmail);
        }

        return $this;
    }

    public function removeHistoriqueEmail($historiqueEmail): self
    {
        $this->historique_email->removeElement($historiqueEmail);

        return $this;
    }

    public function getHistoriqueEmail(): Collection
    {
        return $this->historique_email;
    }

    public function addHistoriquePost($historiquePost): self
    {
        if (!$this->historique_post->contains($historiquePost)) {
            $this->historique_post->add($historiquePost);
        }

        return $this;
    }

    public function removeHistoriquePost($historiquePost): self
    {
        $this->historique_post->removeElement($historiquePost);

        return $this;
    }

    public function getHistoriquePost(): Collection
    {
        return $this->historique_post;
    }

    public function setRemboursementId(?int $remboursementId): self
    {
        $this->remboursement_id = $remboursementId;

        return $this;
    }

    public function getRemboursementId(): ?int
    {
        return $this->remboursement_id;
    }

    public function setStatutSlug(string $statutSlug): self
    {
        $this->statutSlug = $statutSlug;

        return $this;
    }

    public function getStatutSlug(): string
    {
        return $this->statutSlug;
    }
}

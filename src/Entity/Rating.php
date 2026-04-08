<?php

namespace App\Entity;

use App\Repository\RatingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RatingRepository::class)]
#[ORM\Table(name: 'rating')]
class Rating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTime $dateCreation;

    #[ORM\Column(name: 'auteur_creation', type: 'string', length: 255)]
    private string $auteurCreation;

    #[ORM\Column(name: 'date_modif', type: 'datetime')]
    private \DateTime $dateModif;

    #[ORM\Column(name: 'auteur_modif', type: 'string', length: 255)]
    private string $auteurModif;

    #[ORM\Column(name: 'type', type: 'integer')]
    private int $type;

    #[ORM\Column(name: 'from_user_id', type: 'integer')]
    private int $fromUserId;

    #[ORM\Column(name: 'to_user_id', type: 'integer')]
    private int $toUserId;

    #[ORM\Column(name: 'score', type: 'integer')]
    private int $score;

    #[ORM\Column(name: 'commentaire', type: 'text', nullable: true)]
    #[Assert\Length(max: 245)]
    private ?string $commentaire = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModif = new \DateTime();
        $this->auteurCreation = $_SESSION['login']->getUsername();
        $this->auteurModif = $_SESSION['login']->getUsername();
    }

    public function getId(): ?int { return $this->id; }
    public function setDateCreation(\DateTime $dateCreation): self { $this->dateCreation = $dateCreation; return $this; }
    public function getDateCreation(): \DateTime { return $this->dateCreation; }
    public function setAuteurCreation(string $auteurCreation): self { $this->auteurCreation = $auteurCreation; return $this; }
    public function getAuteurCreation(): string { return $this->auteurCreation; }
    public function setDateModif(\DateTime $dateModif): self { $this->dateModif = $dateModif; return $this; }
    public function getDateModif(): \DateTime { return $this->dateModif; }
    public function setAuteurModif(string $auteurModif): self { $this->auteurModif = $auteurModif; return $this; }
    public function getAuteurModif(): string { return $this->auteurModif; }
    public function setType(int $type): self { $this->type = $type; return $this; }
    public function getType(): int { return $this->type; }
    public function setFromUserId(int $fromUserId): self { $this->fromUserId = $fromUserId; return $this; }
    public function getFromUserId(): int { return $this->fromUserId; }
    public function setToUserId(int $toUserId): self { $this->toUserId = $toUserId; return $this; }
    public function getToUserId(): int { return $this->toUserId; }
    public function setScore(int $score): self { $this->score = $score; return $this; }
    public function getScore(): int { return $this->score; }
    public function setCommentaire(?string $commentaire): self { $this->commentaire = $commentaire; return $this; }
    public function getCommentaire(): ?string { return $this->commentaire; }
}

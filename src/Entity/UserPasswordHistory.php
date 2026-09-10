<?php

namespace App\Entity;

use App\Repository\UserPasswordHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Empreinte d'un mot de passe précédemment utilisé par un compte.
 *
 * Permet d'interdire la réutilisation des derniers mots de passe (Reco-15 du plan de traitement
 * des risques, ANSSI PG-078 R27). Seule l'empreinte est conservée, jamais le mot de passe.
 * Table créée par sql/reco15_expiration_mots_de_passe.sql.
 */
#[ORM\Table(name: "user_password_history")]
#[ORM\Index(columns: ["user_id"], name: "idx_user_password_history_user")]
#[ORM\Entity(repositoryClass: UserPasswordHistoryRepository::class)]
class UserPasswordHistory
{
    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(name: "password", type: "string", length: 255)]
    private string $password;

    #[ORM\Column(name: "created_at", type: "datetime")]
    private \DateTime $createdAt;

    public function __construct(User $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}

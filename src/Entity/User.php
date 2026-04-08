<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: "user")]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ["username"], message: "Le nom d'utilisateur est déjà utilisé.")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Role constants
    final public const PARAM_ROLE_ADMIN = 'ROLE_ADMIN';
    final public const PARAM_ROLE_AUDITEUR = 'ROLE_AUDITEUR';
    final public const PARAM_ROLE_AUTOMATE = 'ROLE_AUTOMATE';
    final public const PARAM_ROLE_MEMBER = 'ROLE_MEMBER';
    final public const PARAM_ROLE_CLIENT = 'ROLE_CLIENT';
    final public const PARAM_ROLE_CONSEILLER = 'ROLE_CONSEILLER';
    final public const PARAM_ROLE_EPCI = 'ROLE_EPCI';
    final public const PARAM_ROLE_INSTRUCTEUR = 'ROLE_INSTRUCTEUR';
    final public const PARAM_ROLE_INSTRUCTEUR_UP = 'ROLE_INSTRUCTEUR_UP';
    final public const PARAM_ROLE_RENOVATEUR = 'ROLE_RENOVATEUR';
    final public const PARAM_ROLE_TECHNIQUE = 'ROLE_TECHNIQUE';
    final public const PARAM_ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    final public const ROLES_LABEL = [
        self::PARAM_ROLE_ADMIN => 'Administrateur',
        self::PARAM_ROLE_AUDITEUR => 'Auditeur',
        self::PARAM_ROLE_AUTOMATE => 'Automate',
        self::PARAM_ROLE_MEMBER => 'Bénéficiaire',
        self::PARAM_ROLE_CLIENT => 'Client',
        self::PARAM_ROLE_CONSEILLER => 'Conseiller',
        self::PARAM_ROLE_EPCI => 'EPCI',
        self::PARAM_ROLE_INSTRUCTEUR => 'Instructeur',
        self::PARAM_ROLE_INSTRUCTEUR_UP => 'Instructeur UP',
        self::PARAM_ROLE_RENOVATEUR => 'Rénovateur',
        self::PARAM_ROLE_TECHNIQUE => 'Technique',
        self::PARAM_ROLE_SUPER_ADMIN => 'Super Administrateur'
    ];

    #[ORM\Id]
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    #[ORM\Column(name: "username", type: "string", length: 180, unique: true)]
    #[Assert\NotBlank]
    private ?string $username = null;

    #[ORM\Column(name: "username_canonical", type: "string", length: 180, unique: true)]
    private ?string $usernameCanonical = null;

    #[ORM\Column(name: "email", type: "string", length: 180)]
    #[Assert\NotBlank]
    private ?string $email = null;

    #[ORM\Column(name: "email_canonical", type: "string", length: 180, unique: true)]
    private ?string $emailCanonical = null;

    #[ORM\Column(name: "password", type: "string", length: 255)]
    private ?string $password = null;

    #[ORM\Column(name: "salt", type: "string", length: 255, nullable: true)]
    private ?string $salt = null;

    #[ORM\Column(name: "roles", type: "array")]
    private array $roles = [self::PARAM_ROLE_MEMBER];

    #[ORM\Column(name: "enabled", type: "boolean")]
    private bool $enabled = true;

    #[ORM\Column(name: "firstname", type: "text", length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $firstname = null;

    #[ORM\Column(name: "lastname", type: "text", length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $lastname = null;

    #[ORM\Column(name: "date_creation", type: "datetime", nullable: true)]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column(name: "auteur_creation", type: "string", length: 255, nullable: true)]
    private ?string $auteurCreation = null;

    #[ORM\Column(name: "date_modif", type: "datetime", nullable: true)]
    private ?\DateTime $dateModif = null;

    #[ORM\Column(name: "auteur_modif", type: "string", length: 255, nullable: true)]
    private ?string $auteurModif = null;

    #[ORM\Column(name: "date_inactif", type: "date", nullable: true)]
    private ?\DateTime $dateInactif = null;

    #[ORM\Column(name: "is_france_connect", type: "boolean", options: ["default" => false])]
    private bool $isFranceConnect = false;

    #[ORM\Column(name: "count_failed_connection", type: "integer", nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $countFailedConnection = null;

    #[ORM\Column(name: "last_login", type: "datetime", nullable: true)]
    private ?\DateTime $lastLogin = null;

    #[ORM\Column(name: "confirmation_token", type: "string", length: 180, unique: true, nullable: true)]
    private ?string $confirmationToken = null;

    #[ORM\Column(name: "password_requested_at", type: "datetime", nullable: true)]
    private ?\DateTime $passwordRequestedAt = null;

    /**
     * User constructor.
     */
    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModif = new \DateTime();
        $this->dateInactif = new \DateTime();
        $this->roles = [self::PARAM_ROLE_MEMBER];

        if (isset($_SESSION['login']) && $_SESSION['login']) {
            $this->auteurCreation = $_SESSION['login']->getUsername();
            $this->auteurModif = $_SESSION['login']->getUsername();
        } else {
            $this->auteurCreation = "Bénéficiaire";
            $this->auteurModif = "Bénéficiaire";
        }
    }

    /**
     * Get id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set id
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get username (required by UserInterface)
     */
    public function getUsername(): string
    {
        return (string) $this->username;
    }

    /**
     * Get user identifier (required by UserInterface)
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * Set username
     */
    public function setUsername(?string $username): self
    {
        $this->username = $username;
        $this->usernameCanonical = $username;

        return $this;
    }

    /**
     * Get username canonical
     */
    public function getUsernameCanonical(): ?string
    {
        return $this->usernameCanonical;
    }

    /**
     * Set username canonical
     */
    public function setUsernameCanonical(?string $usernameCanonical): self
    {
        $this->usernameCanonical = $usernameCanonical;
        return $this;
    }

    /**
     * Get email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set email
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        $this->emailCanonical = $email;
        return $this;
    }

    /**
     * Get email canonical
     */
    public function getEmailCanonical(): ?string
    {
        return $this->emailCanonical;
    }

    /**
     * Set email canonical
     */
    public function setEmailCanonical(?string $emailCanonical): self
    {
        $this->emailCanonical = $emailCanonical;
        return $this;
    }

    /**
     * Get password (required by PasswordAuthenticatedUserInterface)
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Set password
     */
    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Get salt
     */
    public function getSalt(): ?string
    {
        return $this->salt;
    }

    /**
     * Set salt
     */
    public function setSalt(?string $salt): self
    {
        $this->salt = $salt;
        return $this;
    }

    /**
     * Get roles (required by UserInterface)
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Ensure ROLE_USER is always present
        if (!in_array('ROLE_USER', $roles)) {
            $roles[] = 'ROLE_USER';
        }
        return array_unique($roles);
    }

    /**
     * Set roles
     */
    public function setRoles($roles): self
    {
        if(is_string($roles)) {
            $roles = [$roles];
        }
        $this->roles = $roles;
        return $this;
    }

    /**
     * Add a role
     */
    public function addRole(string $role): self
    {
        if (!in_array($role, $this->roles)) {
            $this->roles[] = $role;
        }
        return $this;
    }

    /**
     * Remove a role
     */
    public function removeRole(string $role): self
    {
        if (in_array($role, $this->roles)) {
            $this->roles = array_filter($this->roles, fn($r) => $r !== $role);
        }
        return $this;
    }

    /**
     * Get enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set enabled
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get firstname
     */
    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    /**
     * Set firstname
     */
    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;
        return $this;
    }

    /**
     * Get lastname
     */
    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    /**
     * Set lastname
     */
    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;
        return $this;
    }

    /**
     * Get full name
     */
    public function getFullName(): string
    {
        return trim(sprintf('%s %s', $this->firstname ?? '', $this->lastname ?? ''));
    }

    /**
     * Get date creation
     */
    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    /**
     * Set date creation
     */
    public function setDateCreation(?\DateTime $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    /**
     * Get auteur creation
     */
    public function getAuteurCreation(): ?string
    {
        return $this->auteurCreation;
    }

    /**
     * Set auteur creation
     */
    public function setAuteurCreation(?string $auteurCreation): self
    {
        $this->auteurCreation = $auteurCreation;
        return $this;
    }

    /**
     * Get date modif
     */
    public function getDateModif(): ?\DateTime
    {
        return $this->dateModif;
    }

    /**
     * Set date modif
     */
    public function setDateModif(?\DateTime $dateModif): self
    {
        $this->dateModif = $dateModif;
        return $this;
    }

    /**
     * Get auteur modif
     */
    public function getAuteurModif(): ?string
    {
        return $this->auteurModif;
    }

    /**
     * Set auteur modif
     */
    public function setAuteurModif(?string $auteurModif): self
    {
        $this->auteurModif = $auteurModif;
        return $this;
    }

    /**
     * Get date inactif
     */
    public function getDateInactif(): ?\DateTime
    {
        return $this->dateInactif;
    }

    /**
     * Set date inactif
     */
    public function setDateInactif(?\DateTime $dateInactif): self
    {
        $this->dateInactif = $dateInactif;
        return $this;
    }

    /**
     * Get is france connect
     */
    public function isFranceConnect(): bool
    {
        return $this->isFranceConnect;
    }

    /**
     * Set is france connect
     */
    public function setIsFranceConnect(bool $isFranceConnect): self
    {
        $this->isFranceConnect = $isFranceConnect;
        return $this;
    }

    /**
     * Get count failed connection
     */
    public function getCountFailedConnection(): ?int
    {
        return $this->countFailedConnection;
    }

    /**
     * Set count failed connection
     */
    public function setCountFailedConnection(?int $countFailedConnection): self
    {
        $this->countFailedConnection = $countFailedConnection;
        return $this;
    }

    /**
     * Get last login
     */
    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    /**
     * Set last login
     */
    public function setLastLogin(?\DateTime $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    /**
     * Get first role label
     */
    public function getFirstRoleLabel(): ?string
    {
        if (!empty($this->roles)) {
            return self::ROLES_LABEL[$this->roles[0]] ?? null;
        }
        return null;
    }

    /**
     * Get all roles labels
     */
    public function getRolesLabels(): array
    {
        $labels = [];
        foreach ($this->getRoles() as $role) {
            $labels[$role] = self::ROLES_LABEL[$role] ?? $role;
        }
        return $labels;
    }

    /**
     * Get confirmation token
     */
    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    /**
     * Set confirmation token
     */
    public function setConfirmationToken(?string $confirmationToken): self
    {
        $this->confirmationToken = $confirmationToken;
        return $this;
    }

    /**
     * Get password requested at
     */
    public function getPasswordRequestedAt(): ?\DateTime
    {
        return $this->passwordRequestedAt;
    }

    /**
     * Set password requested at
     */
    public function setPasswordRequestedAt(?\DateTime $passwordRequestedAt): self
    {
        $this->passwordRequestedAt = $passwordRequestedAt;
        return $this;
    }

    public function isPasswordRequestNonExpired($ttl): bool
    {
        return $this->getPasswordRequestedAt() instanceof \DateTime &&
            $this->getPasswordRequestedAt()->getTimestamp() + $ttl > time();
    }

    /**
     * Erase credentials (required by UserInterface)
     */
    public function eraseCredentials(): void
    {
        // Clear any temporary, sensitive data if needed
    }
}

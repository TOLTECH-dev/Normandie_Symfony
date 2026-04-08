<?php

namespace App\Entity;

use App\Repository\Historique_emailRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Historique_emailRepository::class)]
#[ORM\Table(name: 'historique_email')]
class Historique_email
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $sender = null;

    /**
     * Stored as JSON in database, but legacy data may be string.
     * Use getter to always get array format.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private string|array|null $recipient = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    #[Assert\Length(max: 245)]
    private string $content;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $format = null;

    #[ORM\ManyToOne(targetEntity: Historique_::class, inversedBy: 'historique_email')]
    private ?Historique_ $historique = null;

    public function __construct(string $content, ?string $sender = null, ?array $recipient = null, ?string $subject = null, ?string $format = null)
    {
        $this->content = $content;
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->subject = $subject ?? '';
        $this->format = $format;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(?string $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    /**
     * Always returns array format, converting legacy string data if needed.
     */
    public function getRecipient(): ?array
    {
        if (is_string($this->recipient)) {
            return !empty($this->recipient) ? [$this->recipient] : null;
        }
        return $this->recipient;
    }

    /**
     * @param array|string|null $recipient
     * @return Historique_email
     */
    public function setRecipient(array|string|null $recipient): self
    {
        if (is_string($recipient)) {
            $this->recipient = !empty($recipient) ? [$recipient] : null;
        } else {
            $this->recipient = $recipient;
        }
        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function getHistorique(): ?Historique_
    {
        return $this->historique;
    }

    public function setHistorique(?Historique_ $historique): self
    {
        $this->historique = $historique;
        return $this;
    }
}

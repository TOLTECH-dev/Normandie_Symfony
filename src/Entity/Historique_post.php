<?php

namespace App\Entity;

use App\Repository\Historique_postRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Historique_postRepository::class)]
#[ORM\Table(name: 'historique_post')]
class Historique_post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'json')]
    private array $post = [];

    #[ORM\ManyToOne(targetEntity: Historique_::class, inversedBy: 'historique_post')]
    private ?Historique_ $historique = null;

    public function __construct(array $post = [])
    {
        $this->setPost($post);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setPost(array $post): self
    {
        $this->post = $post;

        return $this;
    }

    public function getPost(): array
    {
        return $this->post;
    }

    public function setHistorique(?Historique_ $historique): self
    {
        $this->historique = $historique;

        return $this;
    }

    public function getHistorique(): ?Historique_
    {
        return $this->historique;
    }
}

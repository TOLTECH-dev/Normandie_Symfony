<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
class Log
{
    #[ORM\Column(name: 'date_create', type: 'datetime')]
    private \DateTime $dateCreate;

    #[ORM\Column(name: 'auteur_create', type: 'string', length: 180)]
    private string $auteurCreate;

    #[ORM\Column(name: 'date_edit', type: 'datetime')]
    private \DateTime $dateEdit;

    #[ORM\Column(name: 'auteur_edit', type: 'string', length: 180)]
    private string $auteurEdit;

    public function __construct()
    {
        $this->dateCreate = new \DateTime();
        $this->dateEdit = new \DateTime();
        if (isset($_SESSION) && $_SESSION && array_key_exists('login', $_SESSION) && $_SESSION['login']) {
            $this->auteurCreate = $_SESSION['login']->getUsername();
            $this->auteurEdit = $_SESSION['login']->getUsername();
        } else {
            $this->auteurCreate = "ADMIN";
            $this->auteurEdit = "ADMIN";
        }
    }

    public function setDateCreate(\DateTime $dateCreate): self
    {
        $this->dateCreate = $dateCreate;
        return $this;
    }

    public function getDateCreate(): \DateTime
    {
        return $this->dateCreate;
    }

    public function setAuteurCreate(string $auteurCreate): self
    {
        $this->auteurCreate = $auteurCreate;
        return $this;
    }

    public function getAuteurCreate(): string
    {
        return $this->auteurCreate;
    }

    public function setDateEdit(\DateTime $dateEdit): self
    {
        $this->dateEdit = $dateEdit;
        return $this;
    }

    public function getDateEdit(): \DateTime
    {
        return $this->dateEdit;
    }

    public function setAuteurEdit(string $auteurEdit): self
    {
        $this->auteurEdit = $auteurEdit;
        return $this;
    }

    public function getAuteurEdit(): string
    {
        return $this->auteurEdit;
    }
}

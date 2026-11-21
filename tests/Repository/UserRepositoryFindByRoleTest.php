<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * UserRepository::findByRole() — la requête doit être interprétable, et viser le bon champ.
 *
 * Ce test existe parce que la méthode est restée inutilisée depuis son écriture, donc jamais
 * exécutée. Son premier appel réel, par AlerteAntivirusMailer lors de l'essai EICAR en recette du
 * 06/11/2026, a levé « Expected known function, got 'JSON_CONTAINS' ». Elle portait deux défauts :
 *
 *  - `JSON_CONTAINS` n'est pas déclarée dans `doctrine.yaml`, et n'aurait de toute façon rien
 *    donné : la colonne « roles » est de type Doctrine « array », donc sérialisée par PHP ;
 *  - la condition portait sur « u.isActive », champ inexistant — l'entité porte « enabled ».
 *
 * L'analyse du DQL suffit à prendre les deux : elle résout les fonctions contre le registre
 * configuré et les champs contre les métadonnées. Elle ne demande **aucune base de données**, ce
 * qui permet de garder la suite indépendante de tout service extérieur.
 */
class UserRepositoryFindByRoleTest extends KernelTestCase
{
    private UserRepository $depot;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->depot = static::getContainer()->get(UserRepository::class);
    }

    public function testLeDqlEstInterpretable(): void
    {
        // C'est ici que le défaut d'origine se manifestait : l'analyse échouait avant tout accès
        // aux données, sur la fonction inconnue puis sur le champ inexistant.
        $ast = $this->depot->createQueryBuilderForRole('ROLE_ADMIN')->getQuery()->getAST();

        self::assertNotNull($ast);
    }

    public function testLaRequetePorteSurLesChampsAttendus(): void
    {
        $dql = $this->depot->createQueryBuilderForRole('ROLE_ADMIN')->getQuery()->getDQL();

        self::assertStringContainsString('u.roles LIKE', $dql, 'Le filtrage se fait par LIKE sur les rôles sérialisés.');
        self::assertStringContainsString('u.enabled', $dql, "Le champ d'activité de l'entité est « enabled », pas « isActive ».");
        self::assertStringNotContainsString('JSON_CONTAINS', $dql, 'Les rôles ne sont pas stockés en JSON.');
        self::assertStringNotContainsString('isActive', $dql);
    }

    /**
     * Le piège du filtrage par LIKE : sans les guillemets encadrant le nom, ROLE_INSTRUCTEUR
     * ramènerait aussi les comptes ROLE_INSTRUCTEUR_UP.
     */
    public function testLeRoleEstEncadreDeGuillemetsPourEviterLesPrefixes(): void
    {
        $requete = $this->depot->createQueryBuilderForRole('ROLE_INSTRUCTEUR')->getQuery();

        self::assertSame('%"ROLE_INSTRUCTEUR"%', $requete->getParameter('role')->getValue());
    }

    public function testSeulsLesComptesActifsSontVises(): void
    {
        $requete = $this->depot->createQueryBuilderForRole('ROLE_ADMIN')->getQuery();

        self::assertTrue($requete->getParameter('enabled')->getValue());
    }
}

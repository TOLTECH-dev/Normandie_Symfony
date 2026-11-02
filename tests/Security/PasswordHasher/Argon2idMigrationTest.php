<?php

declare(strict_types=1);

namespace App\Tests\Security\PasswordHasher;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Reco-16 — protection des mots de passe par Argon2id.
 *
 * Ces essais portent sur le hacheur tel que le conteneur le construit, et non sur une
 * configuration reconstituée : c'est le comportement livré qui est éprouvé.
 */
final class Argon2idMigrationTest extends KernelTestCase
{
    private const MOT_DE_PASSE = 'MotDePasseUtilisateur2026!';

    public function testUnNouveauMotDePasseEstEnregistreEnArgon2id(): void
    {
        $empreinte = $this->hasher()->hash(self::MOT_DE_PASSE);

        self::assertStringStartsWith('$argon2id$', $empreinte);
        self::assertFalse($this->hasher()->needsRehash($empreinte), 'Une empreinte neuve n\'a pas à être réécrite.');
        self::assertLessThanOrEqual(255, \strlen($empreinte), 'La colonne user.password est un varchar(255).');
    }

    /**
     * Les 295 comptes déjà passés en bcrypt se connectent normalement, et leur empreinte est
     * réécrite en Argon2id à cette occasion : la bascule est invisible pour eux.
     */
    public function testUneEmpreinteBcryptResteValableEtDoitEtreReecrite(): void
    {
        $bcrypt = password_hash(self::MOT_DE_PASSE, PASSWORD_BCRYPT, ['cost' => 4]);

        self::assertTrue($this->hasher()->verify($bcrypt, self::MOT_DE_PASSE));
        self::assertTrue($this->hasher()->needsRehash($bcrypt), 'Sans cela, upgradePassword ne serait jamais appelé.');
    }

    /**
     * Les empreintes héritées de l'ancienne application (SHA-512 + sel, 88 caractères en base64)
     * ne sont pas vérifiables, et ce n'est pas une régression de la Reco-16 : PHP ne reconnaît pas
     * ce format, et nous ne rétablissons pas de vérificateur pour l'algorithme que la
     * recommandation demande précisément d'abandonner.
     *
     * Ce test fige ce choix : s'il échoue un jour, c'est qu'un vérificateur d'ancien format a été
     * réintroduit quelque part.
     */
    public function testUneEmpreinteHeriteeNeAuthentifieAucunMotDePasse(): void
    {
        $heritee = base64_encode(hash('sha512', 'peu importe', true));
        self::assertSame(88, \strlen($heritee));

        foreach ([self::MOT_DE_PASSE, 'peu importe', ''] as $essai) {
            self::assertFalse($this->hasher()->verify($heritee, $essai));
        }
    }

    /**
     * L'entité User ne porte pas de sel exploitable par Symfony : le cloisonnement entre l'ancien
     * format et le nouveau est donc total, et aucune tentative de vérification héritée ne subsiste.
     */
    public function testLUtilisateurNeDeclarePasDInterfaceDeHachageHeritee(): void
    {
        self::assertFalse(
            is_subclass_of(\App\Entity\User::class, \Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface::class),
            'Rétablir cette interface ferait resurgir le hachage hérité.'
        );
    }

    public function testLeHacheurDesUtilisateursEstBienCeluiConfigure(): void
    {
        $hasherUtilisateur = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new \App\Entity\User();
        $empreinte = $hasherUtilisateur->hashPassword($user, self::MOT_DE_PASSE);

        self::assertStringStartsWith('$argon2id$', $empreinte);
        self::assertTrue($hasherUtilisateur->isPasswordValid($this->avecEmpreinte($empreinte), self::MOT_DE_PASSE));
    }

    private function avecEmpreinte(string $empreinte): \App\Entity\User
    {
        $user = new \App\Entity\User();
        $user->setPassword($empreinte);

        return $user;
    }

    /**
     * Le noyau est démarré une fois par test, et non à chaque appel : un redémarrage par
     * assertion portait la suite à plus d'une minute pour cinq essais.
     */
    protected function setUp(): void
    {
        self::bootKernel();
    }

    private function hasher(): \Symfony\Component\PasswordHasher\PasswordHasherInterface
    {
        return self::getContainer()
            ->get(PasswordHasherFactoryInterface::class)
            ->getPasswordHasher(PasswordAuthenticatedUserInterface::class);
    }
}

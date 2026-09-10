<?php

namespace App\Security\Password;

use App\Entity\User;
use App\Entity\UserPasswordHistory;
use App\Repository\UserPasswordHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Enregistrement d'un mot de passe choisi par l'utilisateur : renouvellement, réinitialisation,
 * inscription (Reco-15 du plan de traitement des risques CEE).
 *
 * Complète les règles de constitution (contrainte PasswordStrength) par les contrôles qui
 * dépendent du compte (ANSSI PG-078 R27) :
 *  - le mot de passe ne contient ni l'identifiant, ni l'adresse e-mail, ni le nom ou le prénom ;
 *  - il diffère des "security_password_history_size" derniers mots de passe, actuel compris.
 *
 * Chaque changement date le mot de passe (point de départ de l'expiration) et alimente
 * l'historique, dont seules les empreintes sont conservées.
 */
final class PasswordChangeService
{
    /**
     * Longueur minimale d'une donnée personnelle recherchée dans le mot de passe : en deçà, le
     * contrôle refuserait des mots de passe légitimes.
     */
    private const MIN_PERSONAL_TOKEN_LENGTH = 3;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly UserPasswordHistoryRepository $historyRepository,
        private readonly int $historySize
    ) {
    }

    /**
     * @return string[] messages d'erreur ; tableau vide si le mot de passe est acceptable pour ce compte
     */
    public function validate(User $user, string $plainPassword): array
    {
        $errors = [];

        if ($this->containsPersonalInformation($user, $plainPassword)) {
            $errors[] = 'Le mot de passe ne doit contenir ni votre identifiant, ni votre adresse e-mail, ni vos nom et prénom.';
        }

        if ($this->isRecentlyUsed($user, $plainPassword)) {
            $errors[] = sprintf(
                'Ce mot de passe a déjà été utilisé : choisissez-en un différent de vos %d derniers mots de passe.',
                max(1, $this->historySize)
            );
        }

        return $errors;
    }

    /**
     * Hache et enregistre le nouveau mot de passe (le compte doit être persisté).
     */
    public function change(User $user, string $plainPassword): void
    {
        // Le sel hérité de FOSUserBundle ne sert qu'aux anciennes empreintes : les algorithmes
        // actuels intègrent le leur à l'empreinte.
        $user->setSalt(null);

        $hash = $this->passwordHasherFactory->getPasswordHasher($user)->hash($plainPassword);
        $user->setPassword($hash);
        $user->setPasswordChangedAt(new \DateTime());

        if ($this->historySize > 0) {
            $this->entityManager->persist(new UserPasswordHistory($user, $hash));
        }

        $this->entityManager->flush();

        $this->historyRepository->purgeOlderEntries($user, $this->historySize);
    }

    /**
     * Compare le mot de passe à l'empreinte actuelle et aux dernières empreintes de l'historique.
     * Chaque comparaison coûte un calcul d'empreinte complet : le contrôle ne porte que sur les
     * "security_password_history_size" dernières.
     */
    private function isRecentlyUsed(User $user, string $plainPassword): bool
    {
        $hasher = $this->passwordHasherFactory->getPasswordHasher($user);

        $hashes = $this->historyRepository->findLatestPasswords($user, $this->historySize);
        if (null !== $user->getPassword() && '' !== $user->getPassword()) {
            array_unshift($hashes, $user->getPassword());
        }

        foreach (array_unique($hashes) as $hash) {
            if ($hasher->verify($hash, $plainPassword, $user->getSalt())) {
                return true;
            }
        }

        return false;
    }

    private function containsPersonalInformation(User $user, string $plainPassword): bool
    {
        $password = mb_strtolower($plainPassword);
        $email = (string) $user->getEmail();

        $tokens = [
            $user->getUsername(),
            $email,
            (string) strstr($email, '@', true),
            (string) $user->getFirstname(),
            (string) $user->getLastname(),
        ];

        foreach ($tokens as $token) {
            $token = mb_strtolower(trim($token));

            if (mb_strlen($token) >= self::MIN_PERSONAL_TOKEN_LENGTH && str_contains($password, $token)) {
                return true;
            }
        }

        return false;
    }
}

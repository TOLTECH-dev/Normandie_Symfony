<?php

namespace App\Twig;

use App\Security\Password\PasswordExpirationPolicy;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fonction Twig « password_expires_in_days() » : nombre de jours avant l'expiration du mot de
 * passe de l'utilisateur connecté lorsqu'elle approche (Reco-15), sinon null. Alimente le bandeau
 * d'avertissement du back-office.
 */
class PasswordExpirationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly PasswordExpirationPolicy $passwordExpirationPolicy
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('password_expires_in_days', [$this, 'getDaysBeforeExpiration'])];
    }

    public function getDaysBeforeExpiration(): ?int
    {
        $user = $this->security->getUser();

        return null === $user ? null : $this->passwordExpirationPolicy->getDaysBeforeExpiration($user);
    }
}

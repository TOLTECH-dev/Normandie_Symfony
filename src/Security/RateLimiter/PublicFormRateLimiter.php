<?php

namespace App\Security\RateLimiter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Limiteur des formulaires publics autres que l'authentification (recommandation R7 du
 * rapport d'audit ALMOND NOR005-22, page 32 : « Il est recommandé de mettre en place la
 * protection anti-bruteforce sur tous les formulaires d'une application web »).
 *
 * Le formulaire de connexion, lui, est couvert par "login_throttling" et
 * App\Security\RateLimiter\LoginRateLimiter.
 *
 * Les seuils et les fenêtres sont portés par config/packages/rate_limiter.yaml.
 */
final class PublicFormRateLimiter
{
    private RateLimiterFactory $resettingRequestIpLimiter;
    private RateLimiterFactory $resettingRequestUsernameLimiter;
    private RateLimiterFactory $resettingResetIpLimiter;
    private RateLimiterFactory $registrationIpLimiter;

    public function __construct(
        RateLimiterFactory $resettingRequestIpLimiter,
        RateLimiterFactory $resettingRequestUsernameLimiter,
        RateLimiterFactory $resettingResetIpLimiter,
        RateLimiterFactory $registrationIpLimiter
    )
    {
        $this->resettingRequestIpLimiter = $resettingRequestIpLimiter;
        $this->resettingRequestUsernameLimiter = $resettingRequestUsernameLimiter;
        $this->resettingResetIpLimiter = $resettingResetIpLimiter;
        $this->registrationIpLimiter = $registrationIpLimiter;
    }

    /**
     * Demande de réinitialisation du mot de passe.
     *
     * Le compteur par identifiant est alimenté avec la valeur saisie, sans vérifier
     * qu'elle correspond à un compte : le comportement reste ainsi identique que le
     * compte existe ou non. L'absence d'énumération de comptes est une bonne pratique
     * relevée par l'audit (§3.2.2, page 31), elle ne doit pas être perdue ici.
     */
    public function consumeResettingRequest(Request $request, string $identifier): RateLimit
    {
        return $this->consume([
            $this->resettingRequestIpLimiter->create($this->clientIp($request)),
            $this->resettingRequestUsernameLimiter->create($this->hashIdentifier($identifier)),
        ]);
    }

    /**
     * Page de saisie du nouveau mot de passe, atteinte via le lien reçu par courriel.
     */
    public function consumeResettingReset(Request $request): RateLimit
    {
        return $this->consume([
            $this->resettingResetIpLimiter->create($this->clientIp($request)),
        ]);
    }

    /**
     * Formulaire d'inscription.
     */
    public function consumeRegistration(Request $request): RateLimit
    {
        return $this->consume([
            $this->registrationIpLimiter->create($this->clientIp($request)),
        ]);
    }

    /**
     * Message unique pour tous les formulaires : il ne dépend d'aucune donnée métier et
     * ne permet donc de rien déduire sur l'existence d'un compte.
     */
    public static function tooManyAttemptsMessage(RateLimit $rateLimit): string
    {
        $minutes = (int) max(1, ceil(($rateLimit->getRetryAfter()->getTimestamp() - time()) / 60));

        return sprintf(
            'Trop de tentatives ont été effectuées. Merci de réessayer dans %d minute%s.',
            $minutes,
            $minutes > 1 ? 's' : ''
        );
    }

    /**
     * Tous les compteurs sont consommés, y compris après un premier refus : sinon, saturer
     * le compteur le plus permissif suffirait à mettre les autres à l'abri.
     *
     * On renvoie ensuite le plus contraignant. Dès qu'un compteur refuse, c'est le refus
     * dont le déblocage est le plus lointain qui fait foi, sans quoi le délai annoncé à
     * l'utilisateur serait sous-estimé et il se heurterait à un second blocage.
     *
     * @param LimiterInterface[] $limiters
     */
    private function consume(array $limiters): RateLimit
    {
        $mostRestrictive = null;

        foreach ($limiters as $limiter) {
            $rateLimit = $limiter->consume();

            if (null === $mostRestrictive || $this->isMoreRestrictive($rateLimit, $mostRestrictive)) {
                $mostRestrictive = $rateLimit;
            }
        }

        return $mostRestrictive;
    }

    private function isMoreRestrictive(RateLimit $candidate, RateLimit $reference): bool
    {
        if ($candidate->isAccepted() !== $reference->isAccepted()) {
            return !$candidate->isAccepted();
        }

        if (!$candidate->isAccepted()) {
            return $candidate->getRetryAfter() > $reference->getRetryAfter();
        }

        return $candidate->getRemainingTokens() < $reference->getRemainingTokens();
    }

    private function clientIp(Request $request): string
    {
        return $request->getClientIp() ?? '';
    }

    /**
     * Même normalisation que LoginRateLimiter : la casse et les espaces de saisie ne
     * doivent pas ouvrir un nouveau quota, et l'identifiant ne transite en clair ni dans
     * les clés du pool de cache, ni dans les noms des verrous.
     */
    private function hashIdentifier(string $identifier): string
    {
        return hash('sha512', mb_strtolower(trim($identifier), 'UTF-8'));
    }
}

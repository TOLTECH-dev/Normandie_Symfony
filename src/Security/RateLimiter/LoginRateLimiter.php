<?php

namespace App\Security\RateLimiter;

use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Security;

/**
 * Limiteur de tentatives de connexion (vulnérabilité V3 / recommandation R7 du rapport
 * d'audit ALMOND NOR005-22).
 *
 * Le limiteur fourni par Symfony (DefaultLoginRateLimiter) indexe ses deux compteurs sur
 * l'adresse IP : « IP seule » et « identifiant + IP ». Une attaque distribuée qui change
 * d'IP à chaque tentative les contourne donc tous les deux. On ajoute ici un troisième
 * compteur indexé sur le seul identifiant, indépendant de l'origine de la requête.
 *
 * Ce service est branché sur les deux firewalls via l'option
 * "security.firewalls.*.login_throttling.limiter".
 */
final class LoginRateLimiter extends AbstractRequestRateLimiter
{
    private RateLimiterFactory $ipLimiter;
    private RateLimiterFactory $usernameIpLimiter;
    private RateLimiterFactory $usernameLimiter;

    public function __construct(
        RateLimiterFactory $loginIpLimiter,
        RateLimiterFactory $loginUsernameIpLimiter,
        RateLimiterFactory $loginUsernameLimiter
    )
    {
        $this->ipLimiter = $loginIpLimiter;
        $this->usernameIpLimiter = $loginUsernameIpLimiter;
        $this->usernameLimiter = $loginUsernameLimiter;
    }

    /**
     * L'identifiant saisi est posé sur la requête par LoginThrottlingListener juste avant
     * l'appel à consume(), à partir du UserBadge : il est donc disponible même lorsque le
     * compte n'existe pas, et les tentatives sur un identifiant inconnu sont comptées.
     */
    protected function getLimiters(Request $request): array
    {
        $ip = $request->getClientIp() ?? '';
        $username = $this->hashUsername((string) $request->attributes->get(Security::LAST_USERNAME, ''));

        return [
            $this->ipLimiter->create($ip),
            $this->usernameIpLimiter->create($username . '-' . $ip),
            $this->usernameLimiter->create($username),
        ];
    }

    /**
     * Les identifiants sont normalisés puis hachés : ils ne transitent en clair ni dans
     * les clés du pool de cache, ni dans les noms des verrous flock.
     */
    private function hashUsername(string $username): string
    {
        return hash('sha512', mb_strtolower($username, 'UTF-8'));
    }
}

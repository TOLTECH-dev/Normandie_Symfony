<?php

namespace App\Security\TwoFactor;

use App\Entity\User;
use App\Security\TwoFactor\Exception\TwoFactorProcessAbortedException;
use App\Service\MailerService;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Second facteur d'authentification par code à usage unique envoyé par courriel
 * (recommandation MFA du rapport d'audit ALMOND NOR005-22).
 *
 * Le processus est orchestré par scheb/2fa-bundle : une fois le mot de passe validé, le
 * firewall "backoffice" ne détient qu'un jeton intermédiaire sans rôle, et la connexion
 * ne devient effective qu'après la saisie du code.
 *
 * Le code en attente est conservé en session plutôt qu'en base :
 *  - il reste lié à la session qui a validé le mot de passe ;
 *  - seule son empreinte HMAC est stockée, jamais sa valeur ;
 *  - le schéma de la table "user" n'a pas à évoluer.
 *
 * Les essais sont bornés à deux niveaux :
 *  - par code : au-delà de "security_mfa_code_max_attempts" essais, le code est invalidé ;
 *  - par compte : le limiteur "mfa_code_username" plafonne les essais tous codes
 *    confondus. Contrairement aux compteurs de login_throttling, une nouvelle validation
 *    du mot de passe ne le remet pas à zéro : connaître le mot de passe ne permet donc pas
 *    d'enchaîner les codes pour multiplier les essais.
 *
 * Un code expiré ou invalidé met fin au processus (cf. TwoFactorFailureHandler).
 */
final class EmailCodeProvider implements TwoFactorProviderInterface
{
    private const SESSION_KEY = '_security_mfa_email_code';
    private const CODE_LENGTH = 6;

    public function __construct(
        private readonly MfaPolicy $mfaPolicy,
        private readonly MailerService $mailerService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactory $mfaCodeUsernameLimiter,
        private readonly TwoFactorFormRendererInterface $formRenderer,
        private readonly LoggerInterface $securityLogger,
        private readonly string $secret,
        private readonly int $codeLifetimeMinutes,
        private readonly int $maxAttemptsPerCode
    ) {
    }

    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        return $user instanceof User && $this->mfaPolicy->isRequiredFor($user);
    }

    /**
     * Appelée par le bundle à l'affichage du formulaire de saisie, une seule fois par
     * processus : recharger la page n'envoie pas de nouveau courriel.
     */
    public function prepareAuthentication(object $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $code = $this->generateCode();

        $this->requestStack->getSession()->set(self::SESSION_KEY, [
            'user'      => $user->getUserIdentifier(),
            'hash'      => $this->hashCode($code),
            'expiresAt' => time() + $this->codeLifetimeMinutes * 60,
            'attempts'  => 0,
        ]);

        // Aucune donnée du compte (identifiant, nom) n'est transmise au gabarit : le courriel
        // ne doit fournir aucune piste d'attaque s'il est intercepté ou transféré.
        $isSent = $this->mailerService->sendTemplateEmail(
            (string) $user->getEmail(),
            'Votre code de connexion Chèque Éco-Énergie',
            'Email/mfa_code.html.twig',
            [
                'code'                => $code,
                'codeLifetimeMinutes' => $this->codeLifetimeMinutes,
            ],
            null,
            // Voie réservée : sur un environnement où le courrier ordinaire est coupé, le code
            // de connexion doit malgré tout partir, faute de quoi plus personne ne s'authentifie.
            MailerService::TRANSPORT_MFA
        );

        if ($isSent) {
            $this->securityLogger->info('MFA : code de vérification envoyé.', ['username' => $user->getUserIdentifier()]);
        } else {
            $this->securityLogger->error('MFA : échec de l\'envoi du code de vérification.', ['username' => $user->getUserIdentifier()]);
        }
    }

    /**
     * @throws TwoFactorProcessAbortedException lorsque le code ne peut plus être validé
     */
    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $session = $this->requestStack->getSession();
        $pending = $session->get(self::SESSION_KEY);
        $context = ['username' => $user->getUserIdentifier()];

        // Aucun code en attente pour ce compte : déjà utilisé, invalidé, ou émis pour un
        // autre compte dans cette session.
        if (!\is_array($pending) || ($pending['user'] ?? null) !== $user->getUserIdentifier()) {
            throw TwoFactorProcessAbortedException::codeExpired();
        }

        if (time() > $pending['expiresAt']) {
            $session->remove(self::SESSION_KEY);
            $this->securityLogger->info('MFA : code de vérification expiré.', $context);

            throw TwoFactorProcessAbortedException::codeExpired();
        }

        // Consommé avant la comparaison : chaque essai compte, qu'il aboutisse ou non.
        $limiter = $this->mfaCodeUsernameLimiter->create($this->hashIdentifier($user->getUserIdentifier()));
        if (!$limiter->consume()->isAccepted()) {
            $session->remove(self::SESSION_KEY);
            $this->securityLogger->warning('MFA : plafond d\'essais atteint pour le compte.', $context);

            throw TwoFactorProcessAbortedException::tooManyAttempts();
        }

        if (hash_equals($pending['hash'], $this->hashCode($this->normalizeCode($authenticationCode)))) {
            // Usage unique : le code est retiré dès qu'il a servi.
            $session->remove(self::SESSION_KEY);
            $limiter->reset();

            return true;
        }

        ++$pending['attempts'];

        if ($pending['attempts'] >= $this->maxAttemptsPerCode) {
            $session->remove(self::SESSION_KEY);
            $this->securityLogger->warning('MFA : code de vérification invalidé après trop d\'essais.', $context);

            throw TwoFactorProcessAbortedException::tooManyAttempts();
        }

        $session->set(self::SESSION_KEY, $pending);

        return false;
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this->formRenderer;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', \STR_PAD_LEFT);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, $this->secret);
    }

    /**
     * Tolère les espaces saisis ou collés avec le code (ex. « 123 456 »).
     */
    private function normalizeCode(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?? '';
    }

    /**
     * Même normalisation que LoginRateLimiter : l'identifiant ne transite en clair ni dans
     * les clés du pool de cache, ni dans les noms des verrous.
     */
    private function hashIdentifier(string $identifier): string
    {
        return hash('sha512', mb_strtolower($identifier, 'UTF-8'));
    }
}

<?php

namespace App\Tests\Security\TwoFactor;

use App\Entity\User;
use App\Security\TwoFactor\EmailCodeProvider;
use App\Security\TwoFactor\Exception\TwoFactorProcessAbortedException;
use App\Security\TwoFactor\MfaPolicy;
use App\Service\MailerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * Couvre le second facteur par code envoyé par courriel (MFA préconisé par le rapport
 * d'audit ALMOND NOR005-22).
 *
 * Aucun courriel n'est envoyé : le code est intercepté dans le contexte transmis au
 * MailerService. Le test ne dépend ni de la base de données ni de Redis.
 */
class EmailCodeProviderTest extends TestCase
{
    private const MAX_ATTEMPTS_PER_CODE = 5;
    private const MAX_ATTEMPTS_PER_ACCOUNT = 12;

    private Session $session;
    private EmailCodeProvider $provider;
    private ?string $lastSentCode = null;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $mailer = $this->createMock(MailerService::class);
        $mailer->method('sendTemplateEmail')->willReturnCallback(
            function (string $to, string $subject, string $template, array $context): bool {
                $this->lastSentCode = $context['code'];

                return true;
            }
        );

        $this->provider = new EmailCodeProvider(
            new MfaPolicy(new RoleHierarchy([]), ['ROLE_ADMIN']),
            $mailer,
            $requestStack,
            new RateLimiterFactory(
                ['id' => 'mfa_code_username', 'policy' => 'sliding_window', 'limit' => self::MAX_ATTEMPTS_PER_ACCOUNT, 'interval' => '1 hour'],
                new InMemoryStorage()
            ),
            $this->createMock(TwoFactorFormRendererInterface::class),
            new NullLogger(),
            'secret-de-test',
            10,
            self::MAX_ATTEMPTS_PER_CODE
        );
    }

    public function testSeulsLesComptesSoumisAuMfaDemarrentLeProcessus(): void
    {
        $this->assertTrue($this->provider->beginAuthentication($this->contexte($this->user('admin', ['ROLE_ADMIN']))));
        $this->assertFalse($this->provider->beginAuthentication($this->contexte($this->user('beneficiaire', ['ROLE_MEMBER']))));
    }

    public function testLeCodeEnvoyeEstComposeDeSixChiffres(): void
    {
        $this->provider->prepareAuthentication($this->user('admin'));

        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->lastSentCode);
    }

    public function testLeCodeRecuParCourrielFinaliseLaConnexion(): void
    {
        $user = $this->user('admin');
        $this->provider->prepareAuthentication($user);

        $this->assertTrue($this->provider->validateAuthenticationCode($user, $this->lastSentCode));
    }

    public function testLesEspacesSaisisAvecLeCodeSontIgnores(): void
    {
        $user = $this->user('admin');
        $this->provider->prepareAuthentication($user);
        $saisie = ' ' . substr($this->lastSentCode, 0, 3) . ' ' . substr($this->lastSentCode, 3) . ' ';

        $this->assertTrue($this->provider->validateAuthenticationCode($user, $saisie));
    }

    public function testLeCodeNEstPasConserveEnClairEnSession(): void
    {
        $this->provider->prepareAuthentication($this->user('admin'));

        $this->assertStringNotContainsString($this->lastSentCode, serialize($this->session->all()));
    }

    public function testUnCodeNeSertQuUneSeuleFois(): void
    {
        $user = $this->user('admin');
        $this->provider->prepareAuthentication($user);
        $code = $this->lastSentCode;
        $this->provider->validateAuthenticationCode($user, $code);

        $this->expectException(TwoFactorProcessAbortedException::class);
        $this->provider->validateAuthenticationCode($user, $code);
    }

    public function testUnCodeErroneEstRefuse(): void
    {
        $user = $this->user('admin');
        $this->provider->prepareAuthentication($user);

        $this->assertFalse($this->provider->validateAuthenticationCode($user, $this->autreCode()));
    }

    /**
     * Au-delà du nombre d'essais, le code est invalidé : même le bon code est refusé.
     */
    public function testLeCodeEstInvalideApresTropDEssaisErrones(): void
    {
        $user = $this->user('admin');
        $this->provider->prepareAuthentication($user);

        for ($i = 1; $i < self::MAX_ATTEMPTS_PER_CODE; $i++) {
            $this->assertFalse($this->provider->validateAuthenticationCode($user, $this->autreCode()));
        }

        try {
            $this->provider->validateAuthenticationCode($user, $this->autreCode());
            $this->fail('Le dernier essai toléré doit mettre fin au processus.');
        } catch (TwoFactorProcessAbortedException) {
        }

        $this->expectException(TwoFactorProcessAbortedException::class);
        $this->provider->validateAuthenticationCode($user, $this->lastSentCode);
    }

    public function testUnCodeExpireEstRefuse(): void
    {
        $user = $this->user('admin');
        $this->provider->prepareAuthentication($user);

        $sessionKey = (new \ReflectionClassConstant(EmailCodeProvider::class, 'SESSION_KEY'))->getValue();
        $pending = $this->session->get($sessionKey);
        $pending['expiresAt'] = time() - 1;
        $this->session->set($sessionKey, $pending);

        $this->expectException(TwoFactorProcessAbortedException::class);
        $this->provider->validateAuthenticationCode($user, $this->lastSentCode);
    }

    /**
     * Un attaquant qui connaît le mot de passe peut obtenir un nouveau code à chaque
     * connexion : le plafond par compte doit survivre à ce renouvellement.
     */
    public function testLePlafondParCompteResisteAuRenouvellementDuCode(): void
    {
        $user = $this->user('admin');
        $essaisParCode = self::MAX_ATTEMPTS_PER_CODE - 1;

        for ($consommes = 0; $consommes + $essaisParCode <= self::MAX_ATTEMPTS_PER_ACCOUNT; $consommes += $essaisParCode) {
            $this->provider->prepareAuthentication($user);
            for ($i = 0; $i < $essaisParCode; $i++) {
                $this->provider->validateAuthenticationCode($user, $this->autreCode());
            }
        }

        $this->provider->prepareAuthentication($user);

        $this->expectException(TwoFactorProcessAbortedException::class);
        $this->provider->validateAuthenticationCode($user, $this->lastSentCode);
    }

    public function testUnCodeEmisPourUnAutreCompteEstRefuse(): void
    {
        $this->provider->prepareAuthentication($this->user('admin'));

        $this->expectException(TwoFactorProcessAbortedException::class);
        $this->provider->validateAuthenticationCode($this->user('instructeur'), $this->lastSentCode);
    }

    private function user(string $username, array $roles = ['ROLE_ADMIN']): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.org');
        $user->setRoles($roles);

        return $user;
    }

    private function contexte(User $user): AuthenticationContextInterface
    {
        $context = $this->createMock(AuthenticationContextInterface::class);
        $context->method('getUser')->willReturn($user);

        return $context;
    }

    private function autreCode(): string
    {
        return str_pad((string) (((int) $this->lastSentCode + 1) % 1000000), 6, '0', \STR_PAD_LEFT);
    }
}

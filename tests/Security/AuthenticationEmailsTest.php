<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\SecurityService;
use App\Security\TwoFactor\EmailCodeProvider;
use App\Security\TwoFactor\MfaPolicy;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

/**
 * Les courriels de réinitialisation du mot de passe et de code de connexion (MFA) ne doivent
 * contenir aucune donnée du compte : intercepté ou transféré, un tel courriel ne doit fournir
 * aucune piste d'attaque (identifiant à cibler par bruteforce, par exemple).
 *
 * Le contexte est capturé à la sortie des vrais services, puis les vrais gabarits sont rendus.
 * En environnement de test, Twig fonctionne en "strict_variables" : un gabarit qui
 * référencerait à nouveau "user" ferait échouer le rendu.
 */
class AuthenticationEmailsTest extends KernelTestCase
{
    private const LOGIN = 'identifiant-a-ne-pas-divulguer';
    private const FIRSTNAME = 'Prenom-a-ne-pas-divulguer';
    private const LASTNAME = 'Nom-a-ne-pas-divulguer';

    /** @var array{subject: string, template: string, context: array}|null */
    private ?array $sent = null;

    public function testLeCourrielDeReinitialisationNeDivulgueAucuneDonneeDuCompte(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('http://example.org/resetting/jeton-de-test');

        $tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('generateToken')->willReturn('jeton-de-test');

        $service = new SecurityService(
            $this->mailer(),
            $router,
            $tokenGenerator,
            $this->createMock(EntityManagerInterface::class)
        );
        $service->handleSendResettingEmail($this->user(), '3600');

        $html = $this->assertCourrielSansDonneeDuCompte();
        $this->assertStringContainsString('/resetting/jeton-de-test', $html);
    }

    public function testLeCourrielDeCodeDeConnexionNeDivulgueAucuneDonneeDuCompte(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new EmailCodeProvider(
            new MfaPolicy(new RoleHierarchy([]), ['ROLE_ADMIN']),
            $this->mailer(),
            $requestStack,
            new RateLimiterFactory(
                ['id' => 'mfa_code_username', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '1 hour'],
                new InMemoryStorage()
            ),
            $this->createMock(TwoFactorFormRendererInterface::class),
            new NullLogger(),
            'secret-de-test',
            10,
            5
        );
        $provider->prepareAuthentication($this->user());

        $html = $this->assertCourrielSansDonneeDuCompte();
        $this->assertStringContainsString($this->sent['context']['code'], $html);
    }

    /**
     * Vérifie le contexte, l'objet et le rendu du dernier courriel capturé ; renvoie le HTML.
     */
    private function assertCourrielSansDonneeDuCompte(): string
    {
        $this->assertNotNull($this->sent, 'Aucun courriel n\'a été envoyé.');

        foreach ($this->sent['context'] as $key => $value) {
            $this->assertNotInstanceOf(UserInterface::class, $value, sprintf('Le compte est transmis au gabarit (clé "%s").', $key));
        }

        self::bootKernel();
        $html = self::getContainer()->get('twig')->render($this->sent['template'], $this->sent['context']);

        foreach ([self::LOGIN, self::FIRSTNAME, self::LASTNAME] as $donnee) {
            $this->assertStringNotContainsStringIgnoringCase($donnee, $this->sent['subject']);
            $this->assertStringNotContainsStringIgnoringCase($donnee, $html);
        }

        return $html;
    }

    private function mailer(): MailerService
    {
        $mailer = $this->createMock(MailerService::class);
        $mailer->method('sendTemplateEmail')->willReturnCallback(
            function (string $to, string $subject, string $template, array $context): bool {
                $this->sent = ['subject' => $subject, 'template' => $template, 'context' => $context];

                return true;
            }
        );

        return $mailer;
    }

    private function user(): User
    {
        $user = new User();
        $user->setUsername(self::LOGIN);
        $user->setFirstname(self::FIRSTNAME);
        $user->setLastname(self::LASTNAME);
        $user->setEmail('destinataire@example.org');
        $user->setRoles(['ROLE_ADMIN']);

        return $user;
    }
}

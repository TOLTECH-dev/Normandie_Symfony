<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MailerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;

/**
 * Choix de la voie d'acheminement (cf. config/packages/mailer.yaml).
 *
 * En recette, la voie ordinaire est « null://null » : tout le courrier y est jeté, parce que la
 * base est une copie de la production. Seul le code de double authentification emprunte une voie
 * distincte, sans laquelle plus aucun compte du back-office ne pourrait se connecter.
 *
 * Ces essais figent le mécanisme qui rend cette distinction possible : l'en-tête « X-Transport ».
 * S'ils échouent, c'est soit que le courrier ordinaire s'est mis à sortir en recette, soit que le
 * code de connexion a cessé de partir.
 */
final class MailerServiceTransportTest extends TestCase
{
    public function testUnCourrielOrdinaireNeChoisitAucuneVoie(): void
    {
        $email = $this->capturerEnvoi(static function (MailerService $service): void {
            $service->sendTemplateEmail('destinataire@example.com', 'Sujet', 'Email/exemple.html.twig');
        });

        self::assertFalse(
            $email->getHeaders()->has('X-Transport'),
            'Sans en-tête, Symfony retient la première voie déclarée — la voie ordinaire.'
        );
    }

    public function testLeCodeDeConnexionEmprunteLaVoieReservee(): void
    {
        $email = $this->capturerEnvoi(static function (MailerService $service): void {
            $service->sendTemplateEmail(
                'agent@example.com',
                'Votre code de connexion Chèque Éco-Énergie',
                'Email/mfa_code.html.twig',
                ['code' => '123456'],
                null,
                MailerService::TRANSPORT_MFA
            );
        });

        self::assertSame('mfa', $email->getHeaders()->get('X-Transport')?->getBodyAsString());
    }

    /**
     * Le nom de la voie doit correspondre à une entrée de « framework.mailer.transports ». Si la
     * constante change sans que la configuration suive, le code de connexion part sur la voie
     * ordinaire — donc nulle part en recette.
     */
    public function testLeNomDeLaVoieCorrespondALaConfiguration(): void
    {
        $configuration = file_get_contents(\dirname(__DIR__, 2) . '/config/packages/mailer.yaml');

        self::assertIsString($configuration);
        self::assertMatchesRegularExpression(
            '/^\s+' . preg_quote(MailerService::TRANSPORT_MFA, '/') . ':\s/m',
            $configuration
        );
    }

    /**
     * Un envoi qui échoue ne doit pas interrompre le traitement appelant : il est journalisé et
     * signalé par la valeur de retour, ce dont dépend le message d'erreur de la double
     * authentification.
     */
    public function testUnEchecEstSignaleSansExceptionLancee(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('serveur injoignable'));

        $service = new MailerService($mailer, $this->createMock(Environment::class), new NullLogger(), 'expediteur@example.com');

        self::assertFalse($service->sendTemplateEmail('a@example.com', 'Sujet', 'Email/exemple.html.twig'));
    }

    private function capturerEnvoi(callable $envoi): Email
    {
        $capture = null;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (RawMessage $message) use (&$capture): void {
                $capture = $message;
            });

        $envoi(new MailerService($mailer, $this->createMock(Environment::class), new NullLogger(), 'expediteur@example.com'));

        self::assertInstanceOf(Email::class, $capture);

        return $capture;
    }
}

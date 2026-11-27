<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Lancement de l'export des dossiers en arrière-plan.
 *
 * L'export s'exécute dans un processus séparé, et son seul retour visible est
 * un courriel. Quand ce lancement échoue, il échoue en silence : pas d'écran
 * d'erreur, pas de journal — l'utilisateur voit « La demande d'export a été
 * enregistrée avec succès » et n'a jamais rien. D'où ces contrôles.
 *
 * Deux défauts constatés sur le poste de développement le 24/11/2026 :
 *
 *  1. `PhpExecutableFinder`, appelé depuis le SAPI web, rend le PHP trouvé dans
 *     le PATH et non celui qui exécute la requête. Mesuré sous `cgi-fcgi` :
 *     `PHP_BINARY` vaut `php84/php-cgi.exe`, `find()` rend `php82/php.EXE`. Le
 *     verrou de composer exigeant PHP >= 8.4, la commande mourait aussitôt sur
 *     `platform_check`, sans rien journaliser. Le même piège guette toute
 *     machine où plusieurs versions de PHP cohabitent — la production comprise.
 *
 *  2. Sous Windows, `Process::start()` ne détache pas davantage que sous Linux :
 *     le destructeur de l'objet tue l'enfant dès que l'action retourne. Le
 *     commentaire du code l'expliquait pour Linux sans en tirer la conséquence
 *     pour Windows.
 */
class ExportDemandeLancementTest extends TestCase
{
    private static function source(string $relatif): string
    {
        $chemin = \dirname(__DIR__, 2) . '/' . $relatif;

        self::assertFileExists($chemin);

        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu);

        return $contenu;
    }

    private static function corpsDeLAction(): string
    {
        $source = self::source('src/Controller/DemandeController.php');

        $position = strpos($source, 'public function export(Request $request)');
        self::assertIsInt($position, 'Action export introuvable.');

        return substr($source, $position);
    }

    /**
     * L'interpréteur doit pouvoir être désigné, la détection automatique n'étant
     * qu'un repli.
     */
    public function testLInterpreteurPeutEtreDesigneParConfiguration(): void
    {
        $corps = self::corpsDeLAction();

        self::assertStringContainsString("\$this->getParameter('app_php_binary')", $corps);
        self::assertStringContainsString('PhpExecutableFinder())->find()', $corps, 'La détection reste le repli.');

        $services = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/services.yaml');

        self::assertSame(
            '%env(APP_PHP_BINARY)%',
            $services['parameters']['app_php_binary'] ?? null,
            'Le paramètre doit venir de l\'environnement, pour se régler par machine.'
        );

        self::assertStringContainsString(
            'APP_PHP_BINARY=',
            self::source('.env'),
            'La variable doit être déclarée, sinon le conteneur ne se compile pas.'
        );
    }

    /**
     * Des deux côtés, le processus doit survivre à la fin de la requête.
     */
    public function testLeProcessusEstDetacheSurLesDeuxSystemes(): void
    {
        $corps = self::corpsDeLAction();

        self::assertStringContainsString('start /B', $corps, 'Windows : « start /B » détache le processus.');
        self::assertStringContainsString('nohup', $corps, 'Linux : nohup détache le processus.');

        self::assertStringNotContainsString(
            '$process->start();',
            $corps,
            'Process::start() ne détache pas : le destructeur tue l\'enfant dès le retour de l\'action.'
        );
    }

    /**
     * L'identifiant d'export part dans une ligne de commande passée au shell :
     * il doit être contraint, et l'environnement échappé.
     */
    public function testLesParametresDeLaCommandeSontContraints(): void
    {
        $corps = self::corpsDeLAction();

        self::assertSame(
            2,
            substr_count($corps, '(int) $exportId'),
            'L\'identifiant doit être converti en entier des deux côtés.'
        );

        self::assertSame(
            4,
            substr_count($corps, 'escapeshellarg('),
            'Le chemin de l\'interpréteur et l\'environnement doivent être échappés des deux côtés.'
        );
    }
}

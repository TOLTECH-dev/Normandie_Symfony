<?php

namespace App\Utils;



use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Classe utilitaire pour les opérations communes et les redirections
 * @package App\Utils
 */
class DefaultUtils
{
    final public const FILE_CODE_TXT  = 'txt';
    final public const FILE_CODE_CSV  = 'csv';
    final public const FILE_CODE_DATA = 'data';
    final public const FILE_CODE_ZIP  = 'zip';
    final public const FILE_CODE_XML  = 'xml';
    // Constantes pour les encodages de caractères couramment utilisés
    final public const CHARSET_UTF8 = 'utf-8';

    /**
     *
     * @param string $str
     * @param string $charset
     * @return string
     */
    public static function formatString(string $str, string $charset = self::CHARSET_UTF8): string
    {
        $str = htmlentities($str, ENT_QUOTES, $charset);

        // Remplacer les caractères accentuées par leurs correspondants
        $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'

        // Retire les caractères speciaux
        $str = preg_replace('#&[^;]+;#', '', $str);

        // Mettre la chaine en majuscule
        $str = mb_strtoupper($str, $charset);

        // Retirer les quotes simples et guillemets
        $str = str_replace(array('&#039;', '&quot;'), array(' ', ' '), $str);

        return $str;
    }

    /**
     * Extrait la clé d'une chaîne contenant un séparateur pipe (|)
     * Retourne la partie gauche du séparateur (ex: "number | description" -> "number")
     *
     * @param string|null $string $string
     * @return string|null
     */
    public static function getKey(?string $string): ?string
    {
        if ($string) {
            $dataArray = explode('|', $string);
            $string = trim($dataArray[0]);
        }
        return $string;
    }

    /**
     * @param string $pathname
     * @param int $mode
     * @param bool $recursive
     * @throws \Exception
     */
    public static function createDirectory(string $pathname, int $mode = 0755, bool $recursive = false): void
    {
        if (!file_exists($pathname) || !is_dir($pathname)) {
            if (true !== @mkdir($pathname, $mode, $recursive)) {
                if (!is_dir($pathname)) {
                    $error = error_get_last();
                    if ($error) {
                        throw new IOException(sprintf('Failed to create "%s": %s.', $pathname, $error['message']), 0, null, $pathname);
                    }
                    throw new IOException(sprintf('Failed to create "%s"', $pathname), 0, null, $pathname);
                }
            }
        }

        if (!is_writable($pathname)) {
            throw new \Exception(sprintf('Directory "%s" is not writable.', $pathname));
        }
    }

    /**
     * @param string $input
     * @param int $padLength
     * @param string|null $padString
     * @param int|null $padType
     * @return string
     */
    public static function strPadCustom(string $input, int $padLength, ?string $padString = null, ?int $padType = null): string
    {
        if (strlen($input) > $padLength) {
            $input = substr($input, 0, $padLength);
        } else {
            $input = str_pad($input, $padLength, $padString, $padType);
        }
        return $input;
    }

    /**
     *
     * @param string $beneficiaireId
     * @return array
     */
    public static function getDataRedirectLogementListFO(string $beneficiaireId): array
    {
        return [
            'isRedirectToRoute' => true,
            'routeName' => 'logement_list',
            'routeParams' => ['beneficiaireId' => $beneficiaireId]
        ];
    }

    /**
     *
     * @param string $beneficiaireId
     * @return array
     */
    public static function getDataRedirectDemandeListFO(string $beneficiaireId): array
    {
        return [
            'isRedirectToRoute' => true,
            'routeName' => 'demande_list_fo',
            'routeParams' => ['beneficiaireId' => $beneficiaireId]
        ];
    }

    /**
     *
     * @return array
     */
    public static function getDataRedirectConseillerBeneficaireListBO(): array
    {
        return [
            'isRedirectToRoute' => true,
            'routeName' => DefaultServiceUtils::PATHNAME_CONSEILLER_BENEFICIAIRE_LIST,
            'routeParams' => []
        ];
    }

    /**
     *
     * @param string $beneficiaireId
     * @return array
     */
    public static function getDataRedirectConseillerLogementListBO(string $beneficiaireId): array
    {
        return [
            'isRedirectToRoute' => true,
            'routeName' => 'conseiller_logement_list',
            'routeParams' => ['beneficiaireId' => $beneficiaireId]
        ];
    }

    /**
     *
     * @param string $beneficiaireId
     * @return array
     */
    public static function getDataRedirectConseillerDemandeListBO(string $beneficiaireId): array
    {
        return [
            'isRedirectToRoute' => true,
            'routeName' => 'conseiller_demande_list',
            'routeParams' => ['beneficiaireId' => $beneficiaireId]
        ];
    }

    /**
     *
     * @return array
     */
    public static function getDataRedirectClientDemandeListBO(): array
    {
        return [
            'isRedirectToRoute' => true,
            'routeName' => 'demande_list_all',
            'routeParams' => []
        ];
    }

}

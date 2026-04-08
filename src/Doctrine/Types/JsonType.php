<?php

namespace App\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Type JSON custom pour gérer à la fois les données PHP sérialisées (ancien format)
 * et JSON standard (nouveau format) sans modifier la structure de la BD.
 */
class JsonType extends Type
{
    public const NAME = 'json';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    /**
     * Convertit la valeur de la BD en PHP (désérialisation)
     *
     * Gère 3 formats:
     * 1. JSON: [1,3] → [1,3]
     * 2. PHP sérialisé: a:2:{i:0;i:1;i:1;i:3;} → [1,3]
     * 3. Null/vide → null
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Essayer JSON en premier
        if (is_string($value) && (strpos($value, '{') === 0 || strpos($value, '[') === 0)) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                // Pas du JSON, essayer PHP sérialisé
            }
        }

        // Essayer PHP sérialisé (format: a:2:{...}, O:5:{...}, etc.)
        if (is_string($value) && preg_match('/^[a-z]:\d+:/', $value)) {
            try {
                return unserialize($value, ['allowed_classes' => false]);
            } catch (\Exception $e) {
                return [];
            }
        }

        return is_string($value) ? null : $value;
    }

    /**
     * Convertit la valeur PHP en format BD (sérialisation)
     *
     * Stratégie:
     * - String: garder comme est (JSON ou PHP sérialisé)
     * - Array/Object: sérialiser en PHP (maintenir format original)
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }

        return $value;
    }
}

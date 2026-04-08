<?php

declare(strict_types=1);

namespace App\Legacy;

/**
 * Entity aliases for legacy data deserialization
 * 
 * This class provides class aliases to handle legacy serialized data
 * that still contains old namespaces from the whiteLabel bundle.
 */
class EntityAliases
{
    /**
     * Register all legacy entity aliases
     */
    public static function registerAliases(): void
    {
        // Register an autoloader which maps legacy entity namespaces
        // `whiteLabel\BackOfficeBundle\Entity\Foo` -> `App\Entity\Foo`.
        // This allows `unserialize()` to find the correct classes after migration.
        spl_autoload_register(function (string $class): void {
            $prefix = 'whiteLabel\\BackOfficeBundle\\Entity\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $short = substr($class, strlen($prefix));
            if ($short === '' ) {
                return;
            }

            $newClass = 'App\\Entity\\' . $short;

            // If the new class exists (autoloadable), create an alias from new -> legacy
            if (class_exists($newClass)) {
                if (!class_exists($class, false)) {
                    class_alias($newClass, $class);
                }
            }
        }, true, true);

        // Also create aliases for any already-declared App\Entity classes
        // to ensure immediate compatibility without relying on autoload.
        foreach (get_declared_classes() as $declared) {
            if (!str_starts_with($declared, 'App\\Entity\\')) {
                continue;
            }

            $short = substr($declared, strlen('App\\Entity\\'));
            if ($short === '') {
                continue;
            }

            $legacy = 'whiteLabel\\BackOfficeBundle\\Entity\\' . $short;
            if (!class_exists($legacy, false)) {
                class_alias($declared, $legacy);
            }
        }
    }
}
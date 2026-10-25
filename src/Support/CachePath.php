<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Support;

final class CachePath
{
    private static ?string $baseDirectoryOverride = null;

    public static function useBaseDirectory(?string $absolute): void
    {
        if ($absolute === null || $absolute === '') {
            self::$baseDirectoryOverride = null;

            return;
        }

        self::$baseDirectoryOverride = rtrim(str_replace('\\', '/', $absolute), '/');
    }

    public static function directory(): string
    {
        if (self::$baseDirectoryOverride !== null) {
            return self::$baseDirectoryOverride;
        }

        return rtrim(str_replace('\\', '/', base_path('bootstrap/cache/laravarc')), '/');
    }

    public static function abilities(): string
    {
        return self::directory().'/authorizer-abilities.php';
    }
}

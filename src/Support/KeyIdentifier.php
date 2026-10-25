<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Support;

use Illuminate\Support\Str;

/**
 * Same convention as laravarc/core Support\KeyIdentifier (package stays independent).
 *
 * @see \Laravarc\Core\Support\KeyIdentifier
 */
final class KeyIdentifier
{
    public static function fromClass(string $className): string
    {
        $segments = explode('\\', ltrim($className, '\\'));

        return implode('.', array_map(
            static fn (string $segment): string => Str::snake($segment),
            $segments,
        ));
    }

    public static function fromPath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\.[^.\/]+$/', '', $normalized) ?? $normalized;
        $segments = array_values(array_filter(
            explode('/', $normalized),
            static fn (string $segment): bool => $segment !== '',
        ));

        return implode('.', array_map(
            static fn (string $segment): string => Str::snake($segment),
            $segments,
        ));
    }
}

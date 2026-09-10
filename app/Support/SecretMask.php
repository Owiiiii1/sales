<?php

namespace App\Support;

class SecretMask
{
    public static function key(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $plain = trim((string) $value);
        $length = strlen($plain);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($plain, 0, 4).'...'.substr($plain, -4);
    }

    public static function error(?string $message, ?string $secret = null): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        $sanitized = $message;
        if (filled($secret)) {
            $sanitized = str_replace((string) $secret, '***', $sanitized);
        }

        return substr($sanitized, 0, 300);
    }
}

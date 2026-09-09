<?php

namespace App\Support;

class LanguageCode
{
    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return config('sales-analyzer.transcription.supported_languages', ['en', 'ru', 'uk']);
    }

    public static function normalize(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $code = strtolower(trim($code));

        if ($code === '') {
            return null;
        }

        return match ($code) {
            'en', 'eng', 'english' => 'en',
            'ru', 'rus', 'russian' => 'ru',
            'uk', 'ukr', 'ukrainian' => 'uk',
            default => $code,
        };
    }

    public static function isSupported(?string $code): bool
    {
        $normalized = self::normalize($code);

        return $normalized !== null && in_array($normalized, self::supported(), true);
    }
}

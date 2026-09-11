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

        $code = str_replace('_', '-', $code);
        $primary = explode('-', $code)[0];

        if ($primary === '') {
            return null;
        }

        return match ($primary) {
            'en', 'eng', 'english' => 'en',
            'ru', 'rus', 'russian' => 'ru',
            'uk', 'ukr', 'ukrainian', 'ua' => 'uk',
            default => $primary,
        };
    }

    public static function isSupported(?string $code): bool
    {
        $normalized = self::normalize($code);

        return $normalized !== null && in_array($normalized, self::supported(), true);
    }

    public static function toProviderCode(?string $code): ?string
    {
        $normalized = self::normalize($code);

        return match ($normalized) {
            'en' => 'eng',
            'ru' => 'rus',
            'uk' => 'ukr',
            default => $normalized,
        };
    }
}

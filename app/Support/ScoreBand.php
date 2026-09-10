<?php

namespace App\Support;

class ScoreBand
{
    public static function good(): int
    {
        return (int) config('sales-analyzer.analytics.score_good', 80);
    }

    public static function warning(): int
    {
        return (int) config('sales-analyzer.analytics.score_warning', 60);
    }

    public static function for(null|int|float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        $value = (float) $score;

        if ($value >= self::good()) {
            return 'good';
        }

        if ($value >= self::warning()) {
            return 'warning';
        }

        return 'poor';
    }
}

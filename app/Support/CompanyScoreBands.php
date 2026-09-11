<?php

namespace App\Support;

class CompanyScoreBands
{
    /**
     * @return array<int, array{min:int, max:int, label:string}>
     */
    public static function defaults(): array
    {
        return [
            ['min' => 90, 'max' => 100, 'label' => 'Excellent'],
            ['min' => 75, 'max' => 89, 'label' => 'Good'],
            ['min' => 60, 'max' => 74, 'label' => 'Average'],
            ['min' => 40, 'max' => 59, 'label' => 'Weak'],
            ['min' => 0, 'max' => 39, 'label' => 'Critical'],
        ];
    }

    /**
     * @param  array<int, mixed>|null  $bands
     */
    public static function label(?int $score, ?array $bands): ?string
    {
        if ($score === null) {
            return null;
        }

        $rows = is_array($bands) && $bands !== [] ? $bands : null;

        if ($rows === null) {
            return match (ScoreBand::for($score)) {
                'good' => 'Good',
                'warning' => 'Average',
                'poor' => 'Weak',
                default => null,
            };
        }

        foreach ($rows as $band) {
            if (! is_array($band)) {
                continue;
            }

            $min = (int) ($band['min'] ?? 0);
            $max = (int) ($band['max'] ?? 100);

            if ($score >= $min && $score <= $max) {
                $label = trim((string) ($band['label'] ?? ''));

                return $label !== '' ? $label : null;
            }
        }

        return null;
    }
}

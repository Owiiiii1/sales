<?php

namespace App\Services\Analysis;

use App\Models\AnalysisSetting;

class AnalysisSettingsRepository
{
    public function current(): AnalysisSetting
    {
        $row = AnalysisSetting::query()->first();

        if ($row === null) {
            $row = AnalysisSetting::query()->create([
                'report_language_mode' => AnalysisSetting::LANGUAGE_SAME_AS_CALL,
                'max_output_tokens' => (int) config('sales-analyzer.analysis.max_output_tokens.default', 16384),
            ]);
        }

        return $row;
    }

    public function maxOutputTokens(): int
    {
        $min = (int) config('sales-analyzer.analysis.max_output_tokens.min', 4096);
        $max = (int) config('sales-analyzer.analysis.max_output_tokens.max', 32768);
        $value = (int) $this->current()->max_output_tokens;

        return max($min, min($max, $value));
    }

    public function maxOutputTokensFor(string $provider): int
    {
        $configured = $this->maxOutputTokens();
        $caps = config('sales-analyzer.analysis.max_output_tokens.providers', []);
        $cap = (int) ($caps[$provider] ?? $configured);

        return max(1, min($configured, $cap > 0 ? $cap : $configured));
    }
}

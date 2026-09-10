<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AnalysisSetting;
use App\Services\Analysis\AnalysisSettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnalysisSettingsController extends Controller
{
    public function __construct(private AnalysisSettingsRepository $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $row = $this->settings->current();
        $min = (int) config('sales-analyzer.analysis.max_output_tokens.min', 4096);
        $max = (int) config('sales-analyzer.analysis.max_output_tokens.max', 32768);

        return [
            'report_language_mode' => $row->report_language_mode ?: AnalysisSetting::LANGUAGE_SAME_AS_CALL,
            'max_output_tokens' => $this->settings->maxOutputTokens(),
            'min_output_tokens' => $min,
            'max_output_tokens_limit' => $max,
            'schema_version' => (int) config('sales-analyzer.analysis.schema_version', 3),
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $min = (int) config('sales-analyzer.analysis.max_output_tokens.min', 4096);
        $max = (int) config('sales-analyzer.analysis.max_output_tokens.max', 32768);

        $validated = $request->validate([
            'max_output_tokens' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
        ]);

        $row = $this->settings->current();
        $row->fill([
            'report_language_mode' => AnalysisSetting::LANGUAGE_SAME_AS_CALL,
            'max_output_tokens' => (int) $validated['max_output_tokens'],
        ])->save();

        return back()->with('success', 'Analysis settings saved.');
    }
}

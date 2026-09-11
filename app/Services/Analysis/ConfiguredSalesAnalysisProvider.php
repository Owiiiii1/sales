<?php

namespace App\Services\Analysis;

use App\Exceptions\Analysis\PermanentAnalysisException;
use App\Models\Transcript;
use App\Services\Ai\ActiveAiProvider;
use App\Services\Ai\AiProviderManager;
use App\Services\Analysis\DTO\AnalysisContext;
use App\Services\Analysis\DTO\SalesAnalysisResult;
use Illuminate\Support\Facades\Log;

class ConfiguredSalesAnalysisProvider implements SalesAnalysisProvider
{
    public function __construct(
        private ActiveAiProvider $active,
        private AiProviderManager $manager,
        private SalesAnalysisPromptBuilder $prompts,
        private SalesAnalysisResultValidator $validator,
        private AnalysisSettingsRepository $analysisSettings,
    ) {}

    public function isConfigured(): bool
    {
        return $this->active->isConfigured();
    }

    public function analyze(Transcript $transcript, AnalysisContext $context): SalesAnalysisResult
    {
        $setting = $this->active->current();

        if ($setting === null) {
            throw new PermanentAnalysisException('AI provider is not configured.');
        }

        $messages = $this->prompts->messages($transcript, $context);

        Log::info('Sales analysis provider request started.', [
            'call_id' => $transcript->call_id,
            'provider' => $setting->provider,
            'model' => $setting->active_model,
        ]);

        $payload = $this->manager->completeJson(
            (string) $setting->provider,
            (string) $setting->api_key,
            (string) $setting->active_model,
            $messages['system'],
            $messages['user'],
            SalesAnalysisSchema::jsonSchema(),
            $this->analysisSettings->maxOutputTokensFor((string) $setting->provider),
        );

        if ((string) $setting->provider === 'gemini') {
            $payload = $this->alignGeminiCompanyCriteria($payload, $context);
        }

        return $this->validator->validate(
            $payload,
            (string) $setting->provider,
            (string) $setting->active_model,
            $context,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function alignGeminiCompanyCriteria(array $payload, AnalysisContext $context): array
    {
        $expected = [];
        foreach ($context->scorecardSnapshot['criteria'] ?? [] as $criterion) {
            $expected[] = (string) $criterion['key'];
        }

        $criteria = $payload['company_specific']['scorecard']['criteria'] ?? null;
        if ($expected === [] || ! is_array($criteria)) {
            if ($context->companyContextUsed) {
                $payload['company_context_used'] = true;
            }

            return $payload;
        }

        foreach ($criteria as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! isset($item['key']) && isset($expected[$index])) {
                $item['key'] = $expected[$index];
            }

            if (! isset($item['summary']) && isset($item['text']) && is_string($item['text'])) {
                $item['summary'] = $item['text'];
            }

            $criteria[$index] = $item;
        }

        $payload['company_specific']['scorecard']['criteria'] = $criteria;

        if ($context->companyContextUsed) {
            $payload['company_context_used'] = true;
        }

        return $payload;
    }
}

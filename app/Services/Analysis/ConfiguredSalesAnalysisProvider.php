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
        );

        return $this->validator->validate(
            $payload,
            (string) $setting->provider,
            (string) $setting->active_model,
        );
    }
}

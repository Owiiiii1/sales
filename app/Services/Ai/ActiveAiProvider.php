<?php

namespace App\Services\Ai;

use App\Models\AiProviderSetting;

class ActiveAiProvider
{
    public function current(): ?AiProviderSetting
    {
        $setting = AiProviderSetting::query()
            ->where('is_active', true)
            ->whereNotNull('active_model')
            ->first();

        if ($setting === null || ! filled($setting->api_key) || ! filled($setting->active_model)) {
            return null;
        }

        return $setting;
    }

    public function isConfigured(): bool
    {
        return $this->current() !== null;
    }
}

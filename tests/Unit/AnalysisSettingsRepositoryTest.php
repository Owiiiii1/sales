<?php

namespace Tests\Unit;

use App\Services\Analysis\AnalysisSettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisSettingsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_caps_are_applied(): void
    {
        $row = app(AnalysisSettingsRepository::class)->current();
        $row->update(['max_output_tokens' => 32768]);

        $repo = app(AnalysisSettingsRepository::class);

        $this->assertSame(32768, $repo->maxOutputTokensFor('openai'));
        $this->assertSame(16384, $repo->maxOutputTokensFor('anthropic'));
        $this->assertSame(16384, $repo->maxOutputTokensFor('gemini'));
    }
}

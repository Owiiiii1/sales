<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Transcription\ActiveTranscriptionProvider;
use App\Services\Transcription\ElevenLabsConnectionChecker;
use App\Support\SecretMask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

class TranscriptionSettingsController extends Controller
{
    public function __construct(
        private ActiveTranscriptionProvider $active,
        private ElevenLabsConnectionChecker $checker,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $row = $this->active->row();
        $current = $this->active->current();
        $models = $row->available_models ?: config('sales-analyzer.transcription.supported_models');

        return [
            'provider' => $row->provider,
            'label' => $row->label ?: 'ElevenLabs',
            'has_api_key' => $current !== null && $current->source === 'database',
            'api_key_masked' => $current?->source === 'database' ? SecretMask::key($current->apiKey) : null,
            'is_connected' => (bool) ($current?->connected ?? $row->is_connected),
            'is_active' => (bool) $row->is_active,
            'active_model' => $row->active_model,
            'available_models' => $models,
            'last_checked_at' => optional($row->last_checked_at)?->toIso8601String(),
            'last_error' => SecretMask::error($row->last_error),
            'source' => $this->active->source(),
        ];
    }

    public function save(Request $request): RedirectResponse
    {
        $models = collect(config('sales-analyzer.transcription.supported_models'))->pluck('id')->all();

        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'max:4096'],
            'model' => ['required', 'string', Rule::in($models)],
        ]);

        $row = $this->active->row();
        $key = trim((string) ($validated['api_key'] ?? ''));

        $row->active_model = $validated['model'];
        $row->is_active = true;

        if ($key !== '') {
            $row->api_key = $key;
            $row->is_connected = false;
            $row->last_error = null;
            $row->last_checked_at = null;
        }

        $row->save();

        return back()->with('success', 'Transcription settings saved.');
    }

    public function check(): RedirectResponse
    {
        $current = $this->active->current();
        $row = $this->active->row();

        if ($current === null || $current->apiKey === '') {
            return back()->withErrors(['transcription' => 'Save an API key before checking connection.']);
        }

        try {
            $this->checker->check($current->apiKey);
            $row->fill([
                'is_connected' => true,
                'is_active' => true,
                'last_checked_at' => Carbon::now(),
                'last_error' => null,
            ])->save();

            return back()->with('success', 'Transcription connection checked.');
        } catch (Throwable $e) {
            $row->fill([
                'is_connected' => false,
                'last_checked_at' => Carbon::now(),
                'last_error' => SecretMask::error($e->getMessage(), $current->apiKey),
            ])->save();

            return back()->withErrors(['transcription' => SecretMask::error($e->getMessage(), $current->apiKey)]);
        }
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\AiProviderSetting;
use App\Services\Transcription\ActiveTranscriptionProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user(),
            ],

            'locale' => app()->getLocale(),

            'owlAdmin' => fn () => [
                ...config('owl-admin.branding', [
                    'brand_name' => config('owl-admin.brand_name', config('owl-admin.name', 'Service Admin')),
                    'logo_path' => config('owl-admin.logo_path', '/images/company-logo.svg'),
                ]),
                'ai' => (function (): array {
                    $fallback = [
                        'connected' => false,
                        'provider' => null,
                        'provider_label' => null,
                        'model' => null,
                        'status_label' => 'AI: not connected',
                    ];

                    try {
                        if (! class_exists(AiProviderSetting::class)) {
                            return $fallback;
                        }

                        if (! Schema::hasTable('ai_provider_settings')) {
                            return $fallback;
                        }

                        $active = AiProviderSetting::query()
                            ->where('is_active', true)
                            ->where('is_connected', true)
                            ->first();

                        if ($active === null) {
                            return $fallback;
                        }

                        $providerLabel = $active->label ?: ucfirst((string) $active->provider);

                        return [
                            'connected' => true,
                            'provider' => $active->provider,
                            'provider_label' => $providerLabel,
                            'model' => $active->active_model,
                            'status_label' => sprintf(
                                'AI: connected — %s / %s',
                                $providerLabel,
                                $active->active_model ?? 'unknown'
                            ),
                        ];
                    } catch (\Throwable) {
                        return $fallback;
                    }
                })(),
                'transcription' => (function (): array {
                    $fallback = [
                        'connected' => false,
                        'provider' => 'elevenlabs',
                        'provider_label' => 'ElevenLabs',
                        'model' => null,
                        'status_label' => 'ElevenLabs: not connected',
                    ];

                    try {
                        if (! class_exists(ActiveTranscriptionProvider::class)) {
                            return $fallback;
                        }

                        if (! Schema::hasTable('transcription_provider_settings')) {
                            return $fallback;
                        }

                        $active = app(ActiveTranscriptionProvider::class);
                        $current = $active->current();
                        $ready = $active->isReady();
                        $model = $current?->model ?: null;
                        $modelLabel = $model === 'scribe_v2' ? 'Scribe v2' : ($model ?: 'unknown');

                        if (! $ready) {
                            return $fallback;
                        }

                        return [
                            'connected' => true,
                            'provider' => $current?->provider ?: 'elevenlabs',
                            'provider_label' => $current?->label ?: 'ElevenLabs',
                            'model' => $model,
                            'status_label' => sprintf('ElevenLabs: connected — %s', $modelLabel),
                        ];
                    } catch (\Throwable) {
                        return $fallback;
                    }
                })(),
            ],
        ];
    }
}

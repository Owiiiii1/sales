<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicAnalyzeRequest;
use App\Models\Call;
use App\Services\Calls\CallUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicAnalyzerController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Public/Home', [
            'upload' => [
                'max_audio_size_mb' => (int) config('sales-analyzer.max_audio_size_mb'),
                'allowed_extensions' => config('sales-analyzer.allowed_audio_extensions'),
                'accept' => collect(config('sales-analyzer.allowed_audio_extensions'))
                    ->map(static fn (string $extension): string => '.'.$extension)
                    ->implode(','),
                'poll_interval_ms' => 3000,
            ],
        ]);
    }

    public function store(PublicAnalyzeRequest $request, CallUploadService $uploads): JsonResponse
    {
        try {
            $call = $uploads->upload(null, [
                'company_id' => null,
                'employee_id' => null,
                'source' => 'public',
            ], $request->file('audio'));
        } catch (Throwable $e) {
            report($e);
            Log::error('Public call upload failed.', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'The audio file could not be stored. Please try again.',
                'errors' => [
                    'audio' => ['The audio file could not be stored. Please try again.'],
                ],
            ], 500);
        }

        return response()->json($this->safePayload($call), 201);
    }

    public function show(string $publicToken): JsonResponse
    {
        return response()->json($this->safePayload($this->callByToken($publicToken)));
    }

    public function status(string $publicToken): JsonResponse
    {
        $call = $this->callByToken($publicToken);

        return response()->json([
            'status' => $call->status,
            'progress' => null,
            'error' => $this->publicError($call),
            'report_available' => $this->reportAvailable($call),
        ]);
    }

    private function callByToken(string $publicToken): Call
    {
        return Call::query()
            ->where('public_token', $publicToken)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function safePayload(Call $call): array
    {
        return [
            'public_token' => $call->public_token,
            'status' => $call->status,
            'original_filename' => $call->original_filename,
            'progress' => null,
            'error' => $this->publicError($call),
            'report_available' => $this->reportAvailable($call),
            'report' => null,
            'message' => $call->status === 'uploaded'
                ? 'Call uploaded successfully. Analysis engine is not connected yet.'
                : null,
        ];
    }

    private function publicError(Call $call): ?string
    {
        if ($call->status !== 'failed') {
            return null;
        }

        return 'Analysis failed. Please try again.';
    }

    private function reportAvailable(Call $call): bool
    {
        return $call->status === 'completed';
    }
}

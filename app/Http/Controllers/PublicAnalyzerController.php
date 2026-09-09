<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicAnalyzeRequest;
use App\Models\Call;
use App\Services\Calls\CallUploadService;
use App\Support\TranscriptPresenter;
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
            ->with(['transcript.segments'])
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function safePayload(Call $call): array
    {
        $transcript = TranscriptPresenter::public($call);

        return [
            'public_token' => $call->public_token,
            'status' => $call->status,
            'original_filename' => $call->original_filename,
            'progress' => null,
            'error' => $this->publicError($call),
            'report_available' => $this->reportAvailable($call),
            'report' => null,
            'language' => $transcript['language'] ?? null,
            'duration_seconds' => $transcript['duration_seconds'] ?? $call->duration_seconds,
            'transcript' => $transcript,
            'message' => $this->publicMessage($call),
        ];
    }

    private function publicMessage(Call $call): ?string
    {
        return match ($call->status) {
            'uploaded' => 'Your call is queued for transcription.',
            'processing' => 'Transcribing your call…',
            'transcribed' => 'Transcription complete.',
            'failed' => $this->publicError($call),
            default => null,
        };
    }

    private function publicError(Call $call): ?string
    {
        if ($call->status !== 'failed') {
            return null;
        }

        if ($call->error_message === 'This language is not supported yet.') {
            return $call->error_message;
        }

        return 'Transcription failed. Please try again.';
    }

    private function reportAvailable(Call $call): bool
    {
        return $call->status === 'completed';
    }
}

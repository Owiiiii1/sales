<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicAnalyzeRequest;
use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use App\Services\Calls\CallUploadService;
use App\Services\Transcription\ActiveTranscriptionProvider;
use App\Support\CompanyKnowledgeCompleteness;
use App\Support\SalesAnalysisPresenter;
use App\Support\TranscriptPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicAnalyzerController extends Controller
{
    public function home(ActiveTranscriptionProvider $transcription): Response
    {
        $available = $transcription->isReady();

        return Inertia::render('Public/Home', [
            'upload' => [
                'max_audio_size_mb' => (int) config('sales-analyzer.max_audio_size_mb'),
                'allowed_extensions' => config('sales-analyzer.allowed_audio_extensions'),
                'accept' => collect(config('sales-analyzer.allowed_audio_extensions'))
                    ->map(static fn (string $extension): string => '.'.$extension)
                    ->implode(','),
                'poll_interval_ms' => 3000,
                'available' => $available,
                'unavailable_message' => $available ? null : __('Audio analysis is temporarily unavailable.'),
            ],
            ...$this->workspaceOptions(),
        ]);
    }

    public function store(PublicAnalyzeRequest $request, CallUploadService $uploads, ActiveTranscriptionProvider $transcription): JsonResponse
    {
        if (! $transcription->isReady()) {
            return response()->json([
                'message' => __('Audio analysis is temporarily unavailable.'),
            ], 503);
        }

        try {
            $call = $uploads->upload(null, $request->payload(), $request->file('audio'));
            $call->load(['company:id,name', 'employee:id,first_name,last_name']);
        } catch (Throwable $e) {
            report($e);
            Log::error('Public call upload failed.', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => __('The audio file could not be stored. Please try again.'),
                'errors' => [
                    'audio' => [__('The audio file could not be stored. Please try again.')],
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
            ->with(['transcript.segments', 'analysis', 'company:id,name', 'employee:id,first_name,last_name'])
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
            'report' => SalesAnalysisPresenter::public($call),
            'language' => $transcript['language'] ?? null,
            'duration_seconds' => $transcript['duration_seconds'] ?? $call->duration_seconds,
            'transcript' => $transcript,
            'message' => $this->publicMessage($call),
            'analysis_mode' => $call->company_id === null ? 'generic' : 'company',
            'company_name' => $call->company?->name,
            'employee_name' => $call->employee?->full_name,
        ];
    }

    /**
     * @return array{companies: array<int, array{id:int, name:string, knowledge_completeness:int, scorecard_name:?string}>, employees: array<int, array{id:int, company_id:int, name:string}>}
     */
    private function workspaceOptions(): array
    {
        $completeness = app(CompanyKnowledgeCompleteness::class);

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->with([
                'profile',
                'offerings',
                'salesScripts',
                'scorecards' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_default')->orderBy('id'),
                'scorecards.criteria',
            ])
            ->get();

        return [
            'companies' => $companies
                ->map(function (Company $company) use ($completeness): array {
                    $scorecard = $company->scorecards->first(fn ($row): bool => (bool) $row->is_default)
                        ?? $company->scorecards->first();

                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'knowledge_completeness' => $completeness->for($company)['percent'],
                        'scorecard_name' => $scorecard?->name,
                    ];
                })
                ->values()
                ->all(),
            'employees' => Employee::query()
                ->where('is_active', true)
                ->whereIn('company_id', $companies->pluck('id'))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'company_id' => $employee->company_id,
                    'name' => $employee->full_name,
                ])
                ->values()
                ->all(),
        ];
    }

    private function publicMessage(Call $call): ?string
    {
        return match ($call->status) {
            'uploaded' => __('Your call is queued for transcription.'),
            'processing' => __('Transcribing your call…'),
            'transcribed' => __('Transcription complete.'),
            'analysis_pending' => __('Transcription completed, but AI analysis is temporarily unavailable.'),
            'analyzing' => __('Analyzing your sales call…'),
            'completed' => __('Analysis complete.'),
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
            return __($call->error_message);
        }

        if ($call->transcript !== null) {
            return $call->error_message === 'Analysis is not available yet.'
                ? __($call->error_message)
                : __('Analysis failed. Please try again.');
        }

        return __('Transcription failed. Please try again.');
    }

    private function reportAvailable(Call $call): bool
    {
        return $call->status === 'completed';
    }
}

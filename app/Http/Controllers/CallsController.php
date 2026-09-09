<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeCall;
use App\Jobs\TranscribeCall;
use App\Http\Requests\StoreCallRequest;
use App\Http\Requests\UpdateCallRequest;
use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use App\Services\Calls\CallAudioStreamer;
use App\Services\Calls\CallAudioStorage;
use App\Services\Calls\CallUploadService;
use App\Support\SalesAnalysisPresenter;
use App\Support\TranscriptPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CallsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'company_id' => $request->query('company_id'),
            'employee_id' => $request->query('employee_id'),
            'status' => $request->query('status'),
        ];

        if ($filters['company_id'] === '') {
            $filters['company_id'] = null;
        }
        if ($filters['employee_id'] === '') {
            $filters['employee_id'] = null;
        }
        if ($filters['status'] === '' || ! in_array($filters['status'], Call::STATUSES, true)) {
            $filters['status'] = null;
        }

        $calls = Call::query()
            ->with(['company:id,name', 'employee:id,first_name,last_name,company_id'])
            ->when($filters['company_id'], fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['employee_id'], fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Call $call): array => $this->listPayload($call))
            ->all();

        return Inertia::render('Calls/Index', [
            'calls' => $calls,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'employees' => $this->employeeOptions(),
            'statuses' => Call::STATUSES,
            'filters' => [
                'company_id' => $filters['company_id'] !== null ? (int) $filters['company_id'] : null,
                'employee_id' => $filters['employee_id'] !== null ? (int) $filters['employee_id'] : null,
                'status' => $filters['status'],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Calls/Create', [
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'employees' => $this->employeeOptions(),
            'upload' => $this->uploadConfig(),
        ]);
    }

    public function store(StoreCallRequest $request, CallUploadService $uploads): RedirectResponse
    {
        try {
            $call = $uploads->upload($request->user(), $request->payload(), $request->file('audio'));
        } catch (Throwable $e) {
            report($e);
            Log::error('Call upload failed.', ['error' => $e->getMessage()]);

            return back()->withErrors([
                'audio' => 'The audio file could not be stored. Please try again.',
            ]);
        }

        return redirect()->route('calls.show', $call);
    }

    public function show(Call $call, CallAudioStorage $storage): Response
    {
        $call->load(['company:id,name', 'employee:id,first_name,last_name,company_id', 'uploadedBy:id,name,email', 'transcript.segments', 'analysis']);

        return Inertia::render('Calls/Show', [
            'call' => $this->detailPayload($call, $storage),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function update(UpdateCallRequest $request, Call $call): RedirectResponse
    {
        $call->update($request->payload());

        return redirect()->route('calls.show', $call);
    }

    public function destroy(Call $call, CallUploadService $uploads): RedirectResponse
    {
        try {
            $uploads->delete($call);
        } catch (Throwable $e) {
            report($e);
            Log::error('Call delete failed.', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'call' => 'This call could not be deleted. Please try again.',
            ]);
        }

        return redirect()->route('calls.index');
    }

    public function audio(Call $call, CallAudioStreamer $streamer): BinaryFileResponse
    {
        return $streamer->stream($call);
    }

    public function download(Call $call, CallAudioStreamer $streamer): BinaryFileResponse
    {
        return $streamer->download($call);
    }

    public function transcribe(Call $call): RedirectResponse
    {
        if ($call->status === 'processing') {
            return back()->withErrors([
                'call' => 'Transcription is already in progress.',
            ]);
        }

        TranscribeCall::dispatch($call->id);

        return back();
    }

    public function analyze(Call $call): RedirectResponse
    {
        if (in_array($call->status, ['processing', 'analyzing'], true)) {
            return back()->withErrors([
                'call' => 'This call is already being processed.',
            ]);
        }

        $call->loadMissing('transcript');

        if ($call->transcript === null) {
            return back()->withErrors([
                'call' => 'A transcript is required before analysis can run.',
            ]);
        }

        AnalyzeCall::dispatch($call->id);

        return back();
    }

    /**
     * @return array<int, array{id:int, company_id:int, full_name:string}>
     */
    private function employeeOptions(): array
    {
        return Employee::query()
            ->orderBy('first_name')
            ->get(['id', 'company_id', 'first_name', 'last_name'])
            ->map(static fn (Employee $employee): array => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'full_name' => $employee->full_name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadConfig(): array
    {
        return [
            'max_audio_size_mb' => (int) config('sales-analyzer.max_audio_size_mb'),
            'allowed_extensions' => config('sales-analyzer.allowed_audio_extensions'),
            'accept' => collect(config('sales-analyzer.allowed_audio_extensions'))
                ->map(static fn (string $extension): string => '.'.$extension)
                ->implode(','),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(Call $call): array
    {
        return [
            'id' => $call->id,
            'company_id' => $call->company_id,
            'company_name' => $call->company?->name,
            'employee_id' => $call->employee_id,
            'employee_name' => $call->employee?->full_name,
            'source' => $call->source,
            'original_filename' => $call->original_filename,
            'status' => $call->status,
            'duration_seconds' => $call->duration_seconds,
            'file_size' => $call->file_size,
            'file_size_label' => $call->fileSizeLabel(),
            'recorded_at' => optional($call->recorded_at)->toIso8601String(),
            'created_at' => optional($call->created_at)->toIso8601String(),
            'show_url' => route('calls.show', $call),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(Call $call, CallAudioStorage $storage): array
    {
        $hasAudio = $storage->exists($call->storage_path);

        return [
            'id' => $call->id,
            'company_id' => $call->company_id,
            'company_name' => $call->company?->name,
            'employee_id' => $call->employee_id,
            'employee_name' => $call->employee?->full_name,
            'status' => $call->status,
            'source' => $call->source,
            'original_filename' => $call->original_filename,
            'mime_type' => $call->mime_type,
            'file_size' => $call->file_size,
            'file_size_label' => $call->fileSizeLabel(),
            'duration_seconds' => $call->duration_seconds,
            'recorded_at' => optional($call->recorded_at)->toIso8601String(),
            'created_at' => optional($call->created_at)->toIso8601String(),
            'uploaded_by_name' => $call->uploadedBy?->name,
            'processing_started_at' => optional($call->processing_started_at)->toIso8601String(),
            'processing_completed_at' => optional($call->processing_completed_at)->toIso8601String(),
            'error_message' => $call->error_message,
            'has_audio' => $hasAudio,
            'audio_url' => $hasAudio ? route('calls.audio', $call) : null,
            'download_url' => $hasAudio ? route('calls.download', $call) : null,
            'can_retry_transcription' => in_array($call->status, ['uploaded', 'failed', 'transcribed', 'analysis_pending', 'completed'], true),
            'can_run_analysis' => $call->transcript !== null && in_array($call->status, ['transcribed', 'analysis_pending', 'failed'], true),
            'can_rerun_analysis' => $call->transcript !== null && $call->status === 'completed',
            'transcript' => TranscriptPresenter::admin($call),
            'analysis' => SalesAnalysisPresenter::admin($call),
        ];
    }
}

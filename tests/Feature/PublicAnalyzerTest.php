<?php

namespace Tests\Feature;

use App\Jobs\TranscribeCall;
use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('sales-analyzer.storage_disk'));
    }

    public function test_guest_can_upload_valid_audio_as_a_public_call(): void
    {
        $response = $this->post('/analyze', [
            'audio' => $this->fakeAudio('public-call.mp3'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path')
            ->assertJsonMissingPath('uploaded_by')
            ->assertJsonMissingPath('company_id');

        $token = $response->json('public_token');
        $this->assertTrue(Str::isUuid($token));

        $call = Call::query()->where('public_token', $token)->first();
        $this->assertNotNull($call);
        $this->assertNull($call->company_id);
        $this->assertNull($call->employee_id);
        $this->assertNull($call->uploaded_by);
        $this->assertSame('public', $call->source);
        $this->assertSame('uploaded', $call->status);
        $this->assertSame('public-call.mp3', $call->original_filename);
        $this->assertStringStartsWith('public/', $call->storage_path);
        Storage::disk(config('sales-analyzer.storage_disk'))->assertExists($call->storage_path);
        Queue::assertPushed(TranscribeCall::class, fn (TranscribeCall $job): bool => $job->callId === $call->id);
    }

    public function test_public_tokens_are_unique(): void
    {
        $this->post('/analyze', ['audio' => $this->fakeAudio('a.mp3')], ['Accept' => 'application/json'])->assertCreated();
        $this->post('/analyze', ['audio' => $this->fakeAudio('b.mp3')], ['Accept' => 'application/json'])->assertCreated();

        $tokens = Call::query()->pluck('public_token');
        $this->assertCount(2, $tokens);
        $this->assertCount(2, $tokens->unique());
    }

    public function test_invalid_audio_is_rejected(): void
    {
        $this->post('/analyze', [
            'audio' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_oversized_audio_is_rejected(): void
    {
        config([
            'sales-analyzer.max_audio_size_mb' => 1,
            'sales-analyzer.max_audio_size_kb' => 1,
        ]);

        $this->post('/analyze', [
            'audio' => UploadedFile::fake()->create('huge.mp3', 20, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);
    }

    public function test_guest_cannot_inject_company_employee_or_uploader(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->post('/analyze', [
            'audio' => $this->fakeAudio(),
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'uploaded_by' => $user->id,
            'source' => 'manual',
        ], ['Accept' => 'application/json'])->assertCreated();

        $call = Call::query()->first();
        $this->assertNull($call->company_id);
        $this->assertNull($call->employee_id);
        $this->assertNull($call->uploaded_by);
        $this->assertSame('public', $call->source);
    }

    public function test_status_endpoint_returns_safe_payload_for_a_valid_token(): void
    {
        $this->post('/analyze', [
            'audio' => $this->fakeAudio('safe.mp3'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $token = Call::query()->value('public_token');

        $this->getJson("/analysis/{$token}/status")
            ->assertOk()
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonPath('report_available', false)
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path')
            ->assertJsonMissingPath('uploaded_by');

        $this->getJson("/analysis/{$token}")
            ->assertOk()
            ->assertJsonPath('report', null)
            ->assertJsonPath('transcript', null)
            ->assertJsonPath('message', 'Your call is queued for transcription.')
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('storage_path');
    }

    public function test_invalid_public_token_returns_404(): void
    {
        $this->getJson('/analysis/'.Str::uuid().'/status')->assertNotFound();
        $this->getJson('/analysis/1/status')->assertNotFound();
    }

    public function test_admin_audio_endpoints_still_require_auth(): void
    {
        $call = Call::factory()->create([
            'storage_path' => '1/2026/09/missing.mp3',
        ]);

        $this->get("/calls/{$call->id}/audio")->assertRedirect('/login');
        $this->get("/calls/{$call->id}/download")->assertRedirect('/login');
    }

    public function test_public_upload_is_rate_limited(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for(
            'public-analyze',
            fn () => \Illuminate\Cache\RateLimiting\Limit::perMinute(2)->by('public-analyze-test'),
        );

        $headers = ['Accept' => 'application/json'];

        $this->post('/analyze', ['audio' => $this->fakeAudio('one.mp3')], $headers)->assertCreated();
        $this->post('/analyze', ['audio' => $this->fakeAudio('two.mp3')], $headers)->assertCreated();
        $this->post('/analyze', ['audio' => $this->fakeAudio('three.mp3')], $headers)->assertStatus(429);
    }

    private function fakeAudio(string $name = 'sample.mp3'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 20, 'audio/mpeg');
    }
}

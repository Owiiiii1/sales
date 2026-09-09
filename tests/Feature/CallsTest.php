<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CallsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('sales-analyzer.storage_disk'));
    }

    public function test_guest_is_redirected_from_call_routes(): void
    {
        $call = Call::factory()->create();

        $this->get('/calls')->assertRedirect('/login');
        $this->get('/calls/create')->assertRedirect('/login');
        $this->get("/calls/{$call->id}")->assertRedirect('/login');
        $this->get("/calls/{$call->id}/audio")->assertRedirect('/login');
        $this->get("/calls/{$call->id}/download")->assertRedirect('/login');
        $this->post('/calls', [])->assertRedirect('/login');
    }

    public function test_authenticated_admin_can_access_calls_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/calls')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calls/Index', false)
                ->has('calls')
                ->has('filters'));
    }

    public function test_authenticated_admin_can_open_upload_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/calls/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Calls/Create', false));
    }

    public function test_guest_cannot_upload(): void
    {
        $company = Company::factory()->create();

        $this->post('/calls', [
            'company_id' => $company->id,
            'audio' => $this->fakeAudio(),
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_admin_can_upload_valid_audio(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->post('/calls', [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'audio' => $this->fakeAudio('customer-call.mp3'),
            ])
            ->assertRedirect();

        $call = Call::query()->first();

        $this->assertNotNull($call);
        $this->assertSame('uploaded', $call->status);
        $this->assertSame('customer-call.mp3', $call->original_filename);
        $this->assertSame($user->id, $call->uploaded_by);
        $this->assertSame($company->id, $call->company_id);
        $this->assertSame($employee->id, $call->employee_id);
        $this->assertNotNull($call->storage_path);
        $this->assertStringNotContainsString('customer-call.mp3', $call->storage_path);
        $this->assertStringStartsWith($company->id.'/', $call->storage_path);
        $this->assertSame('manual', $call->source);
        Storage::disk(config('sales-analyzer.storage_disk'))->assertExists($call->storage_path);
    }

    public function test_company_is_required_to_upload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/calls/create')
            ->post('/calls', [
                'audio' => $this->fakeAudio(),
            ])
            ->assertRedirect('/calls/create')
            ->assertSessionHasErrors(['company_id']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_invalid_extension_is_rejected(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->from('/calls/create')
            ->post('/calls', [
                'company_id' => $company->id,
                'audio' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
            ])
            ->assertRedirect('/calls/create')
            ->assertSessionHasErrors(['audio']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_invalid_mime_is_rejected(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->from('/calls/create')
            ->post('/calls', [
                'company_id' => $company->id,
                'audio' => UploadedFile::fake()->create('song.mp3', 20, 'application/pdf'),
            ])
            ->assertRedirect('/calls/create')
            ->assertSessionHasErrors(['audio']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_oversized_file_is_rejected(): void
    {
        config([
            'sales-analyzer.max_audio_size_mb' => 1,
            'sales-analyzer.max_audio_size_kb' => 1,
        ]);

        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->from('/calls/create')
            ->post('/calls', [
                'company_id' => $company->id,
                'audio' => UploadedFile::fake()->create('huge.mp3', 20, 'audio/mpeg'),
            ])
            ->assertRedirect('/calls/create')
            ->assertSessionHasErrors(['audio']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_employee_from_another_company_is_rejected(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $foreignEmployee = Employee::factory()->create(['company_id' => $other->id]);

        $this->actingAs($user)
            ->from('/calls/create')
            ->post('/calls', [
                'company_id' => $company->id,
                'employee_id' => $foreignEmployee->id,
                'audio' => $this->fakeAudio(),
            ])
            ->assertRedirect('/calls/create')
            ->assertSessionHasErrors(['employee_id']);

        $this->assertDatabaseCount('calls', 0);
    }

    public function test_file_is_removed_when_call_is_deleted(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/calls', [
                'company_id' => $company->id,
                'audio' => $this->fakeAudio(),
            ])
            ->assertRedirect();

        $call = Call::query()->first();
        $path = $call->storage_path;
        $disk = config('sales-analyzer.storage_disk');

        Storage::disk($disk)->assertExists($path);

        $this->actingAs($user)
            ->delete("/calls/{$call->id}")
            ->assertRedirect('/calls');

        $this->assertDatabaseCount('calls', 0);
        Storage::disk($disk)->assertMissing($path);
    }

    public function test_authenticated_admin_sees_call_detail(): void
    {
        $user = User::factory()->create();
        $call = Call::factory()->create(['status' => 'uploaded']);

        $this->actingAs($user)
            ->get("/calls/{$call->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calls/Show', false)
                ->where('call.id', $call->id)
                ->missing('call.storage_path'));
    }

    public function test_authenticated_user_can_stream_and_download_audio(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/calls', [
                'company_id' => $company->id,
                'audio' => $this->fakeAudio('demo.mp3'),
            ]);

        $call = Call::query()->first();

        $stream = $this->actingAs($user)->get("/calls/{$call->id}/audio");
        $stream->assertOk();
        $this->assertStringContainsString('audio/mpeg', (string) $stream->headers->get('Content-Type'));
        $this->assertSame('bytes', $stream->headers->get('Accept-Ranges'));

        $download = $this->actingAs($user)->get("/calls/{$call->id}/download");
        $download->assertOk();
        $this->assertStringContainsString('demo.mp3', (string) $download->headers->get('Content-Disposition'));
    }

    public function test_missing_physical_file_returns_404(): void
    {
        $user = User::factory()->create();
        $call = Call::factory()->create([
            'storage_path' => '1/2026/09/missing-file.mp3',
            'original_filename' => 'gone.mp3',
            'mime_type' => 'audio/mpeg',
            'status' => 'uploaded',
        ]);

        $this->actingAs($user)
            ->get("/calls/{$call->id}/audio")
            ->assertNotFound();

        $this->actingAs($user)
            ->get("/calls/{$call->id}/download")
            ->assertNotFound();
    }

    public function test_changing_company_with_incompatible_employee_is_rejected(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $call = Call::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAs($user)
            ->from("/calls/{$call->id}")
            ->patch("/calls/{$call->id}", [
                'company_id' => $other->id,
                'employee_id' => $employee->id,
            ])
            ->assertRedirect("/calls/{$call->id}")
            ->assertSessionHasErrors(['employee_id']);

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_changing_company_and_clearing_employee_succeeds(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $call = Call::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAs($user)
            ->patch("/calls/{$call->id}", [
                'company_id' => $other->id,
                'employee_id' => '',
            ])
            ->assertRedirect("/calls/{$call->id}");

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'company_id' => $other->id,
            'employee_id' => null,
        ]);
    }

    public function test_call_factory_can_bind_employee_to_same_company(): void
    {
        $employee = Employee::factory()->create();
        $call = Call::factory()->forEmployee($employee)->create(['status' => 'completed']);

        $this->assertSame($employee->company_id, $call->company_id);
        $this->assertSame($employee->id, $call->employee_id);
    }

    private function fakeAudio(string $name = 'sample.mp3'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 20, 'audio/mpeg');
    }
}

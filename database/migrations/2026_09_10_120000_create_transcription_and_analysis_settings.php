<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcription_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('label')->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('active_model')->nullable();
            $table->json('available_models')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('analysis_settings', function (Blueprint $table) {
            $table->id();
            $table->string('report_language_mode')->default('same_as_call');
            $table->unsignedInteger('max_output_tokens')->default(16384);
            $table->timestamps();
        });

        DB::table('transcription_provider_settings')->insert([
            'provider' => 'elevenlabs',
            'label' => 'ElevenLabs',
            'api_key' => null,
            'is_connected' => false,
            'is_active' => true,
            'active_model' => 'scribe_v2',
            'available_models' => json_encode([
                ['id' => 'scribe_v2', 'name' => 'Scribe v2'],
            ]),
            'last_checked_at' => null,
            'last_error' => null,
            'settings' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('analysis_settings')->insert([
            'report_language_mode' => 'same_as_call',
            'max_output_tokens' => 16384,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_settings');
        Schema::dropIfExists('transcription_provider_settings');
    }
};

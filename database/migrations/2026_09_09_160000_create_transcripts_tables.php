<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('language', 16)->nullable();
            $table->longText('raw_text')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('confidence', 8, 4)->nullable();
            $table->string('provider_request_id')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transcript_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('speaker');
            $table->decimal('start_seconds', 10, 3);
            $table->decimal('end_seconds', 10, 3);
            $table->text('text');
            $table->decimal('confidence', 8, 4)->nullable();
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->index('transcript_id');
            $table->index('sequence');
            $table->index('speaker');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_segments');
        Schema::dropIfExists('transcripts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source')->default('manual');
            $table->string('external_id')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('recorded_at');
            $table->index('source');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};

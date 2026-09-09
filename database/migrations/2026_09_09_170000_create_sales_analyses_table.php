<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('schema_version');
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->longText('summary')->nullable();
            $table->json('result');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_analyses');
    }
};

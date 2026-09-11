<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('value');
            $table->string('status')->default('current');
            $table->date('valid_until')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('company_scorecard_caps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('company_scorecards')->cascadeOnDelete();
            $table->string('name');
            $table->string('criterion_key')->nullable();
            $table->string('trigger_type');
            $table->unsignedTinyInteger('max_total_score');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['scorecard_id', 'is_active']);
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('report_language')->default('same_as_call')->after('notes');
        });

        Schema::table('company_scorecards', function (Blueprint $table) {
            $table->json('score_bands')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('company_scorecards', function (Blueprint $table) {
            $table->dropColumn('score_bands');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('report_language');
        });

        Schema::dropIfExists('company_scorecard_caps');
        Schema::dropIfExists('company_facts');
    }
};

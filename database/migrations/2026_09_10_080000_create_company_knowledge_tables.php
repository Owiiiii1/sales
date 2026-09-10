<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('short_description')->nullable();
            $table->longText('sales_context')->nullable();
            $table->longText('target_audience')->nullable();
            $table->longText('ideal_customer_profile')->nullable();
            $table->longText('value_proposition')->nullable();
            $table->longText('usp')->nullable();
            $table->longText('pricing_context')->nullable();
            $table->longText('competitors')->nullable();
            $table->longText('customer_pains')->nullable();
            $table->longText('sales_goals')->nullable();
            $table->longText('desired_next_steps')->nullable();
            $table->longText('forbidden_claims')->nullable();
            $table->longText('mandatory_questions')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('company_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('target_customer')->nullable();
            $table->text('value_proposition')->nullable();
            $table->text('pricing')->nullable();
            $table->text('differentiators')->nullable();
            $table->text('common_use_cases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('company_objections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('objection');
            $table->longText('recommended_response')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('priority')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('company_sales_scripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('script_text');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('company_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'is_default']);
        });

        Schema::create('company_scorecard_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('company_scorecards')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 8, 2)->default(0);
            $table->unsignedInteger('max_score')->default(100);
            $table->boolean('is_critical')->default(false);
            $table->longText('ai_instructions')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['scorecard_id', 'key']);
            $table->index(['scorecard_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_scorecard_criteria');
        Schema::dropIfExists('company_scorecards');
        Schema::dropIfExists('company_sales_scripts');
        Schema::dropIfExists('company_objections');
        Schema::dropIfExists('company_offerings');
        Schema::dropIfExists('company_profiles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_analyses', function (Blueprint $table) {
            $table->string('company_context_hash', 64)->nullable()->after('error_message');
            $table->foreignId('scorecard_id')->nullable()->after('company_context_hash')->constrained('company_scorecards')->nullOnDelete();
            $table->unsignedTinyInteger('company_scorecard_score')->nullable()->after('overall_score');
            $table->json('scorecard_snapshot')->nullable()->after('scorecard_id');
            $table->json('context_snapshot')->nullable()->after('scorecard_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('sales_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scorecard_id');
            $table->dropColumn([
                'company_context_hash',
                'company_scorecard_score',
                'scorecard_snapshot',
                'context_snapshot',
            ]);
        });
    }
};

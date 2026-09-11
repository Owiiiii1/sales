<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('processing_completed_at');
            $table->string('cancelled_stage')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancelled_stage']);
        });
    }
};

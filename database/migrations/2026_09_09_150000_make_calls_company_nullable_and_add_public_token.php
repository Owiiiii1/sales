<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->uuid('public_token')->nullable()->after('id');
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });

        DB::table('calls')->orderBy('id')->chunkById(100, function ($calls): void {
            foreach ($calls as $call) {
                if (filled($call->public_token)) {
                    continue;
                }

                DB::table('calls')->where('id', $call->id)->update([
                    'public_token' => (string) Str::uuid(),
                ]);
            }
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->uuid('public_token')->nullable(false)->change();
            $table->unique('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
            $table->dropForeign(['company_id']);
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });
    }
};

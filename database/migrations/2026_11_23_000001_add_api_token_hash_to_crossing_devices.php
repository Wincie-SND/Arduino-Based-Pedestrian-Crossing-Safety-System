<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crossing_devices', function (Blueprint $table): void {
            $table->string('api_token_hash', 64)->nullable()->unique()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('crossing_devices', function (Blueprint $table): void {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn('api_token_hash');
        });
    }
};

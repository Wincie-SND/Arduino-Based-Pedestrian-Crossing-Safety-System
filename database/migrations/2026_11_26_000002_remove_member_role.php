<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (['team_members', 'team_invitations'] as $table) {
                DB::table($table)->where('role', 'member')->update(['role' => 'enforcer']);
            }
        });
    }

    public function down(): void
    {
        // Converted rows keep Enforcer access because their previous role is no longer stored.
    }
};

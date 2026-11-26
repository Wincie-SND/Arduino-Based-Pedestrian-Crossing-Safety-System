<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (['team_members', 'team_invitations'] as $table) {
                // Move the old Admin role before reusing its value for the former Owner role.
                DB::table($table)->where('role', 'admin')->update(['role' => 'enforcer']);
                DB::table($table)->where('role', 'owner')->update(['role' => 'admin']);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (['team_members', 'team_invitations'] as $table) {
                DB::table($table)->where('role', 'admin')->update(['role' => 'owner']);
                DB::table($table)->where('role', 'enforcer')->update(['role' => 'admin']);
            }
        });
    }
};

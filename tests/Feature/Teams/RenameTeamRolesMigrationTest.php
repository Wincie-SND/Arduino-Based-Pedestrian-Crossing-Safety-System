<?php

namespace Tests\Feature\Teams;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RenameTeamRolesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_membership_and_invitation_roles_are_renamed_and_can_be_rolled_back(): void
    {
        $team = Team::factory()->create();
        $users = User::factory()->count(3)->create();
        $legacyRoles = ['owner', 'admin', 'member'];
        $renamedRoles = ['admin', 'enforcer', 'member'];

        foreach ($legacyRoles as $index => $role) {
            DB::table('team_members')->insert([
                'team_id' => $team->id,
                'user_id' => $users[$index]->id,
                'role' => $role,
            ]);

            DB::table('team_invitations')->insert([
                'team_id' => $team->id,
                'invited_by' => $users[0]->id,
                'email' => $role.'@example.com',
                'code' => 'legacy-'.$role,
                'role' => $role,
            ]);
        }

        $memberships = DB::table('team_members')->where('team_id', $team->id)->orderBy('id');
        $invitations = DB::table('team_invitations')->where('team_id', $team->id)->orderBy('id');
        $migration = require database_path('migrations/2026_11_26_000001_rename_team_roles.php');

        $migration->up();

        $this->assertSame($renamedRoles, $memberships->pluck('role')->all());
        $this->assertSame($renamedRoles, $invitations->pluck('role')->all());

        $migration->down();

        $this->assertSame($legacyRoles, $memberships->pluck('role')->all());
        $this->assertSame($legacyRoles, $invitations->pluck('role')->all());
    }

    public function test_retiring_member_converts_legacy_memberships_and_invitations_to_enforcer(): void
    {
        $admin = User::factory()->create();
        $team = $admin->personalTeam();
        $member = User::factory()->create();
        $invitation = TeamInvitation::factory()->for($team)->create(['invited_by' => $admin->id]);

        DB::table('team_members')->insert([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
        DB::table('team_invitations')->where('id', $invitation->id)->update(['role' => 'member']);

        $migration = require database_path('migrations/2026_11_26_000002_remove_member_role.php');
        $migration->up();
        $migration->up();

        $this->assertSame('enforcer', $member->teamRole($team)->value);
        $this->assertSame('enforcer', $invitation->fresh()->role->value);
        $this->assertSame('admin', $admin->teamRole($team)->value);
    }
}

<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneExpiredTeamInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_invitations_are_deleted_by_the_scheduled_cleanup(): void
    {
        $this->travelTo(now()->startOfDay());

        $admin = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $expiredInvitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'invited_by' => $admin->id,
        ]);

        $unexpiredInvitation = TeamInvitation::factory()->expiresIn(1)->create([
            'team_id' => $team->id,
            'invited_by' => $admin->id,
        ]);

        $invitationWithoutExpiration = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'invited_by' => $admin->id,
        ]);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertDatabaseMissing('team_invitations', [
            'id' => $expiredInvitation->id,
        ]);

        $this->assertDatabaseHas('team_invitations', [
            'id' => $unexpiredInvitation->id,
        ]);

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitationWithoutExpiration->id,
        ]);
    }
}

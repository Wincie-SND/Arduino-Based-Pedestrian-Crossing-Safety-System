<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retired_invitation_endpoints_cannot_grant_workspace_access(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($admin, ['role' => TeamRole::Admin]);
        $invitation = TeamInvitation::factory()->for($team)->create([
            'email' => $user->email,
            'invited_by' => $admin->id,
        ]);

        $this->get(route('login', ['invitation' => $invitation->code]))
            ->assertOk()
            ->assertDontSee('name="invitation"', escape: false);

        $this->actingAs($admin)->post("/settings/teams/{$team->slug}/invitations", [
            'email' => $user->email,
            'role' => TeamRole::Enforcer->value,
        ])->assertNotFound();

        $this->actingAs($user)->post("/invitations/{$invitation->code}/accept")->assertNotFound();
        $this->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
            ->assertOk()
            ->assertDontSee('Pending team invitations');

        $this->assertFalse($user->belongsToTeam($team));
        $this->assertNull($invitation->fresh()->accepted_at);
    }
}

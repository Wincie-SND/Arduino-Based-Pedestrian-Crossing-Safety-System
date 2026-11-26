<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_teams_index_redirects_to_the_current_team()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('teams.index'));

        $response->assertRedirect(route('teams.edit', ['team' => $user->currentTeam->slug]));
    }

    public function test_teams_can_be_created()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('teams.store'), [
                'name' => 'Test Team',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('teams', [
            'name' => 'Test Team',
            'is_personal' => false,
        ]);
    }

    public function test_teams_cannot_be_created_when_the_policy_denies_it()
    {
        $user = User::factory()->create();

        Gate::before(fn ($user, string $ability) => $ability === 'create' ? false : null);

        $response = $this
            ->actingAs($user)
            ->post(route('teams.store'), [
                'name' => 'Test Team',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('teams', [
            'name' => 'Test Team',
        ]);
    }

    public function test_team_slug_uses_next_available_suffix()
    {
        $user = User::factory()->create();

        Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        Team::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
        Team::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

        $this
            ->actingAs($user)
            ->post(route('teams.store'), [
                'name' => 'Acme',
            ]);

        $this->assertDatabaseHas('teams', [
            'name' => 'Acme',
            'slug' => 'acme-11',
        ]);
    }

    public function test_the_team_edit_page_can_be_rendered()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($user)
            ->get(route('teams.edit', $team));

        $response
            ->assertOk()
            ->assertViewIs('teams.edit')
            ->assertViewHas('members', fn ($members) => $members->first()->pivot->role === TeamRole::Admin)
            ->assertSee('Create an Enforcer account')
            ->assertSee(route('teams.members.store', $team))
            ->assertDontSee('Pending invitations');
    }

    public function test_teams_can_be_updated_by_admins()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Original Name']);

        $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($user)
            ->patch(route('teams.update', $team), [
                'name' => 'Updated Name',
            ]);

        $response->assertRedirect(route('teams.edit', $team->fresh()));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_users_cannot_update_teams_they_dont_belong_to()
    {
        $admin = User::factory()->create();
        $outsider = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($outsider)
            ->patch(route('teams.update', $team), [
                'name' => 'Updated Name',
            ]);

        $response->assertForbidden();
    }

    public function test_teams_can_be_deleted_by_admins()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('teams', [
            'id' => $team->id,
        ]);
    }

    public function test_team_deletion_requires_name_confirmation()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $team), [
                'name' => 'Wrong Name',
            ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleting_current_team_switches_to_alphabetically_first_remaining_team()
    {
        $user = User::factory()->create(['name' => 'Mike']);

        $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
        $zuluTeam->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
        $alphaTeam->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
        $betaTeam->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $user->update(['current_team_id' => $zuluTeam->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $zuluTeam), [
                'name' => $zuluTeam->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('teams', [
            'id' => $zuluTeam->id,
        ]);

        $this->assertEquals($alphaTeam->id, $user->fresh()->current_team_id);
    }

    public function test_deleting_current_team_falls_back_to_personal_team_when_alphabetically_first()
    {
        $user = User::factory()->create();
        $personalTeam = $user->personalTeam();
        $team = Team::factory()->create(['name' => 'Zulu Team']);
        $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $user->update(['current_team_id' => $team->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('teams', [
            'id' => $team->id,
        ]);

        $this->assertEquals($personalTeam->id, $user->fresh()->current_team_id);
    }

    public function test_deleting_non_current_team_leaves_current_team_unchanged()
    {
        $user = User::factory()->create();
        $personalTeam = $user->personalTeam();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $user->update(['current_team_id' => $personalTeam->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('teams', [
            'id' => $team->id,
        ]);

        $this->assertEquals($personalTeam->id, $user->fresh()->current_team_id);
    }

    public function test_members_can_leave_non_personal_teams()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $response = $this
            ->actingAs($member)
            ->delete(route('teams.leave', $team));

        $response->assertRedirect(route('teams.index'));
        $response->assertSessionHas('toast', "You left the team \"{$team->name}\"");

        $this->assertFalse($member->fresh()->belongsToTeam($team));
    }

    public function test_leaving_current_team_switches_to_alphabetically_first_remaining_team()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create(['name' => 'Mike']);

        $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
        $zuluTeam->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $zuluTeam->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
        $alphaTeam->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
        $betaTeam->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $member->update(['current_team_id' => $zuluTeam->id]);

        $response = $this
            ->actingAs($member)
            ->delete(route('teams.leave', $zuluTeam));

        $response->assertRedirect(route('teams.index'));

        $this->assertFalse($member->fresh()->belongsToTeam($zuluTeam));
        $this->assertEquals($alphaTeam->id, $member->fresh()->current_team_id);
    }

    public function test_personal_teams_cannot_be_left()
    {
        $user = User::factory()->create();
        $personalTeam = $user->personalTeam();

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.leave', $personalTeam));

        $response->assertForbidden();

        $this->assertTrue($user->fresh()->belongsToTeam($personalTeam));
    }

    public function test_team_admins_cannot_leave_their_team()
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('teams.leave', $team));

        $response->assertForbidden();

        $this->assertTrue($admin->fresh()->belongsToTeam($team));
    }

    public function test_users_cannot_leave_teams_they_dont_belong_to()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.leave', $team));

        $response->assertForbidden();
    }

    public function test_deleting_team_switches_other_affected_users_to_their_personal_team()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create();
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $admin->update(['current_team_id' => $team->id]);
        $member->update(['current_team_id' => $team->id]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertRedirect();

        $this->assertEquals($member->personalTeam()->id, $member->fresh()->current_team_id);
    }

    public function test_personal_teams_cannot_be_deleted()
    {
        $user = User::factory()->create();

        $personalTeam = $user->personalTeam();

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.destroy', $personalTeam), [
                'name' => $personalTeam->name,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('teams', [
            'id' => $personalTeam->id,
            'deleted_at' => null,
        ]);
    }

    public function test_teams_cannot_be_deleted_by_non_admins()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $response = $this
            ->actingAs($member)
            ->delete(route('teams.destroy', $team), [
                'name' => $team->name,
            ]);

        $response->assertForbidden();
    }

    public function test_users_can_switch_teams()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Enforcer->value]);

        $response = $this
            ->actingAs($user)
            ->post(route('teams.switch', $team));

        $response->assertRedirect();

        $this->assertEquals($team->id, $user->fresh()->current_team_id);
    }

    public function test_users_cannot_switch_to_team_they_dont_belong_to()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('teams.switch', $team));

        $response->assertForbidden();
    }

    public function test_guests_cannot_access_teams()
    {
        $response = $this->get(route('teams.index'));

        $response->assertRedirect(route('login'));
    }
}

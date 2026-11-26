<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_management_opens_at_the_workspace_users_url(): void
    {
        $admin = User::factory()->create();
        $workspace = $admin->currentTeam;
        $users = route('users.index', $workspace);

        $this->actingAs($admin)
            ->get(route('dashboard', $workspace))
            ->assertOk()
            ->assertSee($users)
            ->assertDontSee('/settings/teams');

        $this->get($users)
            ->assertOk()
            ->assertViewIs('users.index')
            ->assertSee('Create an Enforcer account')
            ->assertSee(route('users.store', $workspace));

        $this->get('/settings/teams')->assertRedirect($users);
        $this->get("/settings/teams/{$workspace->slug}")->assertRedirect($users);
    }

    public function test_an_enforcer_with_a_saved_teams_url_is_sent_to_the_dashboard(): void
    {
        $admin = User::factory()->create();
        $enforcer = User::factory()->create();
        $workspace = $admin->currentTeam;
        $workspace->members()->attach($enforcer, ['role' => TeamRole::Enforcer->value]);
        $enforcer->update(['current_team_id' => $workspace->id]);

        $legacy = url("/settings/teams/{$workspace->slug}");
        $this->get($legacy)->assertRedirect(route('login'))->assertSessionMissing('url.intended');

        $this->withSession(['url.intended' => $legacy])
            ->post(route('login.store'), ['email' => $enforcer->email, 'password' => 'password'])
            ->assertRedirect($legacy);

        $this->get($legacy)->assertRedirect(route('dashboard', $workspace));
        $this->get(route('dashboard', $workspace))->assertOk()->assertDontSee('User management');
        $this->get(route('users.index', $workspace))->assertForbidden();
    }

    public function test_a_user_without_a_workspace_can_open_the_workspace_access_page(): void
    {
        $user = User::create([
            'name' => 'Unassigned Enforcer',
            'email' => 'unassigned@example.com',
            'password' => 'password',
        ]);
        $user->markEmailAsVerified();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('workspace.unassigned'));

        $this->get('/settings/teams')->assertRedirect(route('workspace.unassigned'));
        $this->get(route('workspace.unassigned'))->assertOk()->assertSee('No monitoring location assigned');
    }

    public function test_the_old_workspace_and_member_write_routes_are_retired(): void
    {
        $admin = User::factory()->create();
        $legacy = "/settings/teams/{$admin->currentTeam->slug}";

        $this->actingAs($admin)->post('/settings/teams', ['name' => 'New workspace'])->assertStatus(405);
        $this->patch($legacy, ['name' => 'Renamed workspace'])->assertStatus(405);
        $this->delete($legacy)->assertStatus(405);
        $this->post($legacy.'/switch')->assertNotFound();
        $this->delete($legacy.'/leave')->assertNotFound();
        $this->post($legacy.'/members')->assertNotFound();

        $this->assertDatabaseCount('teams', 1);
    }

    public function test_workspace_slug_uses_next_available_suffix(): void
    {
        Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        Team::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
        Team::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

        $workspace = Team::create(['name' => 'Acme', 'is_personal' => false]);

        $this->assertSame('acme-11', $workspace->slug);
    }
}

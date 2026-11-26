<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_provision_an_enforcer_who_can_sign_in_and_verify_their_email(): void
    {
        $admin = User::factory()->create();
        $team = $admin->currentTeam;
        $input = $this->accountInput(['email' => 'ENFORCER@EXAMPLE.COM', 'role' => TeamRole::Admin->value]);

        $this->actingAs($admin)->post(route('users.store', $team), $input)
            ->assertRedirect(route('users.index', $team))
            ->assertSessionHas('toast', 'Enforcer account created.');

        $enforcer = User::where('email', 'enforcer@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($admin);
        $this->assertSame($team->id, $enforcer->current_team_id);
        $this->assertSame(TeamRole::Enforcer, $enforcer->teamRole($team));
        $this->assertTrue(Hash::check($input['password'], $enforcer->password));
        $this->assertFalse($enforcer->hasVerifiedEmail());
        $this->get(route('users.index', $team))->assertSee('enforcer@example.com');

        $dashboard = route('dashboard', ['current_team' => $team->slug]);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $enforcer->email, 'password' => $input['password']])
            ->assertRedirect($dashboard);
        $this->assertAuthenticatedAs($enforcer);
        $this->get($dashboard)->assertRedirect(route('verification.notice'));

        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $enforcer->id,
            'hash' => sha1($enforcer->email),
        ]))->assertRedirect();
        $this->get($dashboard)->assertOk()->assertDontSee('User management');
    }

    public function test_only_workspace_admins_can_create_enforcer_accounts(): void
    {
        $admin = User::factory()->create();
        $team = $admin->currentTeam;
        $enforcer = User::create([
            'name' => 'Existing Enforcer',
            'email' => 'existing-enforcer@example.com',
            'password' => 'enforcer-password',
        ]);
        $enforcer->markEmailAsVerified();
        $team->members()->attach($enforcer, ['role' => TeamRole::Enforcer->value]);
        $outsider = User::factory()->create();
        $endpoint = route('users.store', $team);

        $this->post($endpoint, $this->accountInput())->assertRedirect(route('login'));
        $this->actingAs($outsider)->post($endpoint, $this->accountInput())->assertForbidden();
        $this->actingAs($enforcer)->post($endpoint, $this->accountInput())->assertForbidden();
        $this->get(route('users.index', $team))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'enforcer@example.com']);
    }

    public function test_invalid_account_details_leave_existing_accounts_untouched(): void
    {
        $admin = User::factory()->create();
        $existing = User::factory()->create(['email' => 'enforcer@example.com']);
        $team = $admin->currentTeam;

        $this->actingAs($admin)->post(route('users.store', $team), $this->accountInput([
            'email' => 'ENFORCER@EXAMPLE.COM',
            'password_confirmation' => 'different-password',
        ]))->assertSessionHasErrors(['email', 'password']);

        $this->assertFalse($existing->belongsToTeam($team));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_revoking_a_provisioned_enforcer_clears_their_only_workspace(): void
    {
        $admin = User::factory()->create();
        $team = $admin->currentTeam;

        $this->actingAs($admin)->post(route('users.store', $team), $this->accountInput())
            ->assertSessionHasNoErrors();

        $enforcer = User::where('email', 'enforcer@example.com')->firstOrFail();
        $this->delete(route('users.destroy', [$team, $enforcer]))
            ->assertRedirect(route('users.index', $team));

        $this->assertFalse($enforcer->belongsToTeam($team));
        $this->assertNull($enforcer->fresh()->current_team_id);
    }

    public function test_admins_can_save_the_enforcer_role()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('users.update', [$team, $member]), [
                'role' => TeamRole::Enforcer->value,
            ]);

        $response->assertRedirect(route('users.index', $team));

        $this->assertEquals(
            TeamRole::Enforcer->value,
            $team->members()->where('user_id', $member->id)->first()->pivot->role->value,
        );
    }

    public function test_team_member_roles_cannot_be_updated_by_enforcers()
    {
        $admin = User::factory()->create();
        $enforcer = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($enforcer, ['role' => TeamRole::Enforcer->value]);
        $team->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $response = $this
            ->actingAs($enforcer)
            ->patch(route('users.update', [$team, $member]), [
                'role' => TeamRole::Enforcer->value,
            ]);

        $response->assertForbidden();
    }

    public function test_team_members_can_be_removed_by_admins()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('users.destroy', [$team, $member]));

        $response->assertRedirect(route('users.index', $team));

        $this->assertFalse($member->fresh()->belongsToTeam($team));
    }

    public function test_team_members_cannot_be_removed_by_enforcers()
    {
        $admin = User::factory()->create();
        $enforcer = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($enforcer, ['role' => TeamRole::Enforcer->value]);
        $team->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $response = $this
            ->actingAs($enforcer)
            ->delete(route('users.destroy', [$team, $member]));

        $response->assertForbidden();
    }

    public function test_team_admin_cannot_be_removed()
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('users.destroy', [$team, $admin]));

        $response->assertForbidden();

        $this->assertTrue($admin->fresh()->belongsToTeam($team));
    }

    public function test_team_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $this->actingAs($admin)
            ->patch(route('users.update', [$team, $admin]), [
                'role' => TeamRole::Enforcer->value,
            ])
            ->assertForbidden();

        $this->assertSame(TeamRole::Admin, $admin->fresh()->teamRole($team));
    }

    public function test_enforcer_roles_cannot_be_changed_to_admin_or_member()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $this->actingAs($admin);
        foreach ([TeamRole::Admin->value, 'member'] as $role) {
            $this->patch(route('users.update', [$team, $member]), [
                'role' => $role,
            ])->assertSessionHasErrors('role');
        }

        $this->assertSame(TeamRole::Enforcer, $member->fresh()->teamRole($team));
    }

    public function test_removed_member_current_team_is_set_to_personal_team()
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $personalTeam = $member->personalTeam();
        $team = Team::factory()->create();

        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($member, ['role' => TeamRole::Enforcer->value]);

        $member->update(['current_team_id' => $team->id]);

        $this
            ->actingAs($admin)
            ->delete(route('users.destroy', [$team, $member]));

        $this->assertEquals($personalTeam->id, $member->fresh()->current_team_id);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function accountInput(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Test Enforcer',
            'email' => 'enforcer@example.com',
            'password' => 'enforcer-password',
            'password_confirmation' => 'enforcer-password',
        ];
    }
}

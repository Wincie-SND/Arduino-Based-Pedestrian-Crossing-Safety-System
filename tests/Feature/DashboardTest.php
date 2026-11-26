<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\CrossingDevice;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_the_dashboard_displays_each_team_role(): void
    {
        $team = Team::factory()->create();

        foreach ([
            [TeamRole::Admin, 'Admin'],
            [TeamRole::Enforcer, 'Enforcer'],
        ] as [$role, $label]) {
            $user = User::factory()->create();
            $team->members()->attach($user, ['role' => $role->value]);

            $this->actingAs($user)
                ->get(route('dashboard', ['current_team' => $team->slug]))
                ->assertOk()
                ->assertSee('<span class="account-role">'.$label.'</span>', escape: false);
        }
    }

    public function test_browser_camera_status_is_independent_of_registered_hardware_heartbeats(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $camera = $team->devices()->create([
            'name' => 'Camera module',
            'category' => 'camera',
            'status' => 'offline',
        ]);
        $controller = $team->devices()->create([
            'name' => 'Arduino control board',
            'category' => 'controller',
            'status' => 'online',
            'last_seen_at' => now(),
        ]);
        $parameters = ['current_team' => $team->slug];

        $this->actingAs($user)->get(route('dashboard', $parameters))
            ->assertOk()
            ->assertViewHas('systemStatus', 'online')
            ->assertSee('data-camera-device-status>Offline', escape: false)
            ->assertDontSee('data-device-id="'.$camera->id.'"', escape: false);

        $this->getJson(route('dashboard.device-status', $parameters))
            ->assertOk()
            ->assertExactJson([
                'devices' => [['id' => $controller->id, 'status' => 'online']],
                'system_status' => 'online',
            ]);
    }

    public function test_led_warning_lights_follow_the_arduino_heartbeat(): void
    {
        $this->freezeSecond();

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $token = 'test-arduino-token';
        $team->devices()->forceCreate([
            'name' => 'Arduino control board',
            'category' => 'controller',
            'status' => 'offline',
            'api_token_hash' => hash('sha256', $token),
        ]);

        $leds = $team->devices()->create([
            'name' => 'LED warning lights',
            'category' => 'warning',
            'status' => 'offline',
        ]);

        $heartbeat = route('api.device.heartbeat');
        $status = route('dashboard.device-status', ['current_team' => $team->slug]);
        $headers = ['Authorization' => 'Bearer '.$token];
        $components = ['ultrasonic' => 'online', 'infrared' => 'online', 'buzzer' => 'online'];

        $this->postJson($heartbeat, ['components' => $components], $headers)->assertOk();
        $this->actingAs($user)->getJson($status)
            ->assertOk()
            ->assertJsonPath('devices.1', ['id' => $leds->id, 'status' => 'offline']);

        $this->postJson($heartbeat, ['components' => $components + ['leds' => 'online']], $headers)
            ->assertOk();
        $this->getJson($status)
            ->assertOk()
            ->assertJsonPath('devices.1', ['id' => $leds->id, 'status' => 'online']);

        $this->travel(CrossingDevice::HEARTBEAT_TIMEOUT_SECONDS + 1)->seconds();
        $this->getJson($status)
            ->assertOk()
            ->assertJsonPath('devices.1', ['id' => $leds->id, 'status' => 'offline']);
    }
}

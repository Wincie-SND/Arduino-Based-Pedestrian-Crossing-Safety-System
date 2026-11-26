<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ViolationReportTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_and_exports_use_the_manila_calendar_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25T18:00:00Z'));

        $user = User::factory()->create();
        $team = $user->currentTeam;

        foreach ([
            'CG-PREVIOUS-DAY' => '2026-11-25 15:59:59',
            'CG-START-OF-DAY' => '2026-11-25 16:00:00',
            'CG-END-OF-DAY' => '2026-11-26 15:59:59',
            'CG-NEXT-DAY' => '2026-11-26 16:00:00',
        ] as $code => $occurredAt) {
            $team->incidents()->create([
                'incident_code' => $code,
                'incident_type' => 'Potential crossing conflict',
                'status' => 'needs_review',
                'occurred_at' => $occurredAt,
            ]);
        }

        $parameters = ['current_team' => $team->slug, 'date' => 'today'];
        $this->actingAs($user)->get(route('violations.index', $parameters))
            ->assertOk()
            ->assertSee('CG-START-OF-DAY')
            ->assertSee('CG-END-OF-DAY')
            ->assertDontSee('CG-PREVIOUS-DAY')
            ->assertDontSee('CG-NEXT-DAY')
            ->assertSee('datetime="2026-11-26T00:00:00+08:00"', escape: false)
            ->assertSee('12:00 AM')
            ->assertSee('11:59 PM');

        $export = $this->get(route('violations.export', $parameters))
            ->assertOk()
            ->assertDownload('violation-reports-20261126.csv');

        $rows = array_map(
            fn (string $row): array => str_getcsv($row, escape: ''),
            explode("\n", trim($export->streamedContent())),
        );

        $this->assertSame([
            ['Incident ID', 'Date and time', 'Incident type', 'Status', 'Assigned enforcer', 'Evidence'],
            ['CG-END-OF-DAY', '2026-11-26 23:59:59', 'Potential crossing conflict', 'Pending verification', 'Unassigned', 'Not attached'],
            ['CG-START-OF-DAY', '2026-11-26 00:00:00', 'Potential crossing conflict', 'Pending verification', 'Unassigned', 'Not attached'],
        ], $rows);
    }

    public function test_device_incident_timestamps_with_explicit_offsets_are_stored_in_utc(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $token = Str::random(64);

        $team->devices()->forceCreate([
            'name' => 'Arduino control board',
            'category' => 'controller',
            'api_token_hash' => hash('sha256', $token),
        ]);

        $report = $this->postJson(route('api.device.incidents.store'), [
            'event_id' => 'crossing-midnight',
            'occurred_at' => '2026-11-26T00:00:00+08:00',
        ], ['Authorization' => 'Bearer '.$token])->assertCreated();

        $this->assertDatabaseHas('crossing_incidents', [
            'team_id' => $team->id,
            'incident_code' => $report->json('incident_code'),
            'occurred_at' => '2026-11-25 16:00:00',
        ]);
    }
}

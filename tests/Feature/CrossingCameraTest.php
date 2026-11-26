<?php

namespace Tests\Feature;

use App\Models\CrossingIncident;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrossingCameraTest extends TestCase
{
    use RefreshDatabase;

    public function test_camera_lists_only_recent_pending_arduino_incidents_for_the_team(): void
    {
        $this->freezeTime();

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $otherTeam = Team::factory()->create();
        $priorIncident = $this->incident($team);
        $this->incident($otherTeam);
        $pollUrl = route('dashboard.camera.incidents', ['current_team' => $team->slug]);

        $this->actingAs($user)->getJson($pollUrl)
            ->assertOk()
            ->assertJsonPath('latest_incident_id', $priorIncident->id);

        $this->incident($team, ['created_at' => now()->subSeconds(11)]);
        $this->incident($team, ['occurred_at' => now()->subSeconds(11)]);
        $this->incident($team, ['status' => 'verified']);
        $this->incident($team, ['evidence_path' => 'existing-evidence.jpg']);
        $this->incident($team, ['evidence_source' => 'Manual report']);

        $token = Str::random(64);
        $team->devices()->forceCreate([
            'name' => 'Arduino control board',
            'category' => 'controller',
            'api_token_hash' => hash('sha256', $token),
        ]);
        $report = $this->postJson(route('api.device.incidents.store'), [
            'event_id' => 'crossing-1',
        ], ['Authorization' => 'Bearer '.$token])->assertCreated();
        $first = $team->incidents()->where('incident_code', $report->json('incident_code'))->firstOrFail();

        $this->incident($otherTeam);
        $second = $this->incident($team);

        $this->getJson($pollUrl.'?after='.$priorIncident->id)
            ->assertOk()
            ->assertJsonCount(2, 'incidents')
            ->assertJsonPath('incidents.0.id', $first->id)
            ->assertJsonPath('incidents.0.evidence_url', route('dashboard.camera.evidence.store', [
                'current_team' => $team->slug,
                'incident' => $first->id,
            ], absolute: false))
            ->assertJsonPath('incidents.1.id', $second->id);
    }

    public function test_camera_saves_a_private_snapshot_and_subsequent_uploads_keep_the_original(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $incident = $this->incident($team);
        $parameters = ['current_team' => $team->slug, 'incident' => $incident->id];
        $uploadUrl = route('dashboard.camera.evidence.store', $parameters);
        $evidenceUrl = route('violations.evidence', $parameters);
        $image = $this->jpeg();

        $this->actingAs($user)->post($uploadUrl, ['snapshot' => $image], ['Accept' => 'application/json'])
            ->assertNoContent();

        $incident->refresh();
        $path = $incident->evidence_path;
        $this->assertStringStartsWith('camera-evidence/', $path);
        $this->assertSame('Browser webcam', $incident->evidence_source);
        $this->assertSame('needs_review', $incident->status);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        $this->post($uploadUrl, ['snapshot' => $this->jpeg('different frame')], ['Accept' => 'application/json'])
            ->assertNoContent();

        $this->assertSame([$path], Storage::disk('local')->allFiles());
        $this->assertSame($path, $incident->fresh()->evidence_path);
        $this->get($evidenceUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertStreamedContent($image->getContent());

        $this->get(route('violations.show', $parameters))
            ->assertOk()
            ->assertSee(route('violations.evidence', $parameters, absolute: false));

        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get($evidenceUrl)->assertForbidden();
        $this->get(route('violations.evidence', array_replace($parameters, ['current_team' => $outsider->currentTeam->slug])))
            ->assertNotFound();
    }

    public function test_violation_evidence_can_still_open_a_legacy_public_snapshot(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $path = 'incidents/previous-snapshot.jpg';
        $image = $this->jpeg()->getContent();
        Storage::disk('public')->put($path, $image);

        $incident = $this->incident($team, ['evidence_path' => $path, 'evidence_source' => 'Camera module']);

        $this->actingAs($user)->get(route('violations.evidence', ['current_team' => $team->slug, 'incident' => $incident->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertStreamedContent($image);
    }

    public function test_camera_rejects_invalid_snapshots_and_ineligible_incidents(): void
    {
        $this->freezeTime();
        Storage::fake('local');

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $incident = $this->incident($team);
        $uploadUrl = fn (CrossingIncident $incident) => route('dashboard.camera.evidence.store', [
            'current_team' => $team->slug,
            'incident' => $incident->id,
        ]);

        $this->post($uploadUrl($incident), ['snapshot' => $this->jpeg()])->assertRedirect(route('login'));
        $this->actingAs($user);

        foreach ([UploadedFile::fake()->createWithContent('frame.jpg', 'invalid image'), $this->jpeg()->size(2049)] as $snapshot) {
            $this->post($uploadUrl($incident), ['snapshot' => $snapshot], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('snapshot');
        }

        $staleReceipt = $this->incident($team, ['created_at' => now()->subSeconds(11)]);
        $staleOccurrence = $this->incident($team, ['occurred_at' => now()->subSeconds(11)]);
        $reviewed = $this->incident($team, ['status' => 'verified']);
        $otherTeam = $this->incident(Team::factory()->create());

        foreach ([$staleReceipt, $staleOccurrence, $reviewed, $otherTeam] as $ineligible) {
            $this->post($uploadUrl($ineligible), ['snapshot' => $this->jpeg()], ['Accept' => 'application/json'])
                ->assertNotFound();
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function incident(Team $team, array $attributes = []): CrossingIncident
    {
        return $team->incidents()->forceCreate(array_replace([
            'incident_code' => 'CG-'.Str::random(20),
            'incident_type' => 'Potential crossing conflict',
            'status' => 'needs_review',
            'occurred_at' => now(),
            'evidence_source' => 'Arduino sensors',
        ], $attributes));
    }

    private function jpeg(string $comment = ''): UploadedFile
    {
        $content = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFs'.
            'aXR5ID0gNTAK/9sAQwAQCwwODAoQDg0OEhEQExgoGhgWFhgxIyUdKDozPTw5Mzg3QEhcTkBEV0U3OFBtUVdfYmdoZz5NcXlw'.
            'ZHhcZWdj/9sAQwEREhIYFRgvGhovY0I4QmNjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2Nj'.
            'Y2Nj/8AAEQgAAQACAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAAB'.
            'fQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldY'.
            'WVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh'.
            '4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAEC'.
            'dwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZX'.
            'WFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ'.
            '2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8Au0UUV4h5p//Z',
        );

        // A JPEG comment makes separate uploads distinguishable without requiring GD.
        $content = substr_replace($content, "\xFF\xFE".pack('n', strlen($comment) + 2).$comment, 2, 0);

        return UploadedFile::fake()->createWithContent('snapshot.jpg', $content);
    }
}

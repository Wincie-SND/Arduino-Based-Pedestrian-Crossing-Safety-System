<?php

namespace App\Http\Controllers;

use App\Models\CrossingIncident;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CrossingCameraController extends Controller
{
    private const CAPTURE_WINDOW_SECONDS = 10;

    private const CAMERA_EVIDENCE_SOURCE = 'Browser webcam';

    public function index(Request $request, Team $current_team): JsonResponse
    {
        $validated = $request->validate(['after' => ['sometimes', 'required', 'integer', 'min:0']]);

        if (! isset($validated['after'])) {
            return response()->json([
                'latest_incident_id' => (int) $current_team->incidents()->max('id'),
            ]);
        }

        $incidents = $this->eligibleIncidents($current_team)
            ->where('id', '>', $validated['after'])
            ->orderBy('id')
            ->limit(10)
            ->pluck('id')
            ->map(fn (int $id) => [
                'id' => $id,
                'evidence_url' => route('dashboard.camera.evidence.store', [
                    'current_team' => $current_team->slug,
                    'incident' => $id,
                ], absolute: false),
            ]);

        return response()->json(['incidents' => $incidents]);
    }

    public function store(Request $request, Team $current_team, string $incident): Response
    {
        $request->validate([
            'snapshot' => ['required', 'image', 'mimes:jpg,jpeg', 'max:2048', 'dimensions:max_width=1280,max_height=1280'],
        ]);

        $snapshot = $request->file('snapshot');
        $storage = Storage::disk('local');
        $directory = CrossingIncident::CAMERA_EVIDENCE_DIRECTORY.'/'.$current_team->id.'/'.$incident;
        $filename = Str::uuid().'.jpg';
        $storedPath = null;

        try {
            DB::transaction(function () use ($current_team, $incident, $snapshot, $storage, $directory, $filename, &$storedPath): void {
                $record = $current_team->incidents()->lockForUpdate()->findOrFail($incident);

                if ($record->hasCameraEvidence()) {
                    return;
                }

                abort_unless($this->eligibleIncidents($current_team)->whereKey($record->id)->exists(), 404);

                $storedPath = $storage->putFileAs($directory, $snapshot, $filename);
                abort_if($storedPath === false, 503, 'The camera snapshot could not be saved.');

                $record->evidence_path = $storedPath;
                $record->evidence_source = self::CAMERA_EVIDENCE_SOURCE;
                $record->save();
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                $storage->delete($storedPath);
            }

            throw $exception;
        }

        return response()->noContent();
    }

    /** @return Builder<CrossingIncident> */
    private function eligibleIncidents(Team $team): Builder
    {
        return $team->incidents()->getQuery()
            ->where('status', 'needs_review')
            ->where('evidence_source', CrossingIncident::ARDUINO_EVIDENCE_SOURCE)
            ->whereNull('evidence_path')
            ->where('created_at', '>=', now()->subSeconds(self::CAPTURE_WINDOW_SECONDS))
            ->where('occurred_at', '>=', now()->subSeconds(self::CAPTURE_WINDOW_SECONDS));
    }
}

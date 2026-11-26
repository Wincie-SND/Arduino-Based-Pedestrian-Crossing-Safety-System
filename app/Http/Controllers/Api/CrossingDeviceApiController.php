<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrossingDevice;
use App\Models\CrossingIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class CrossingDeviceApiController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:online,warning'],
            'components' => ['sometimes', 'array:ultrasonic,infrared,buzzer,leds'],
            'components.ultrasonic' => ['sometimes', 'in:online,warning'],
            'components.infrared' => ['sometimes', 'in:online,warning'],
            'components.buzzer' => ['sometimes', 'in:online,warning'],
            'components.leds' => ['sometimes', 'in:online,warning'],
        ]);

        $device = $this->deviceFrom($request);
        $device->status = $validated['status'] ?? 'online';
        $device->last_seen_at = now();
        $device->save();

        $networkDevice = $device->team->devices()
            ->where('category', 'network')
            ->where('name', 'Network connection')
            ->first();

        if ($networkDevice) {
            $networkDevice->status = 'online';
            $networkDevice->last_seen_at = $device->last_seen_at;
            $networkDevice->save();
        }

        $componentNames = [
            'ultrasonic' => 'Ultrasonic sensor',
            'infrared' => 'Infrared sensor',
            'buzzer' => 'Buzzer',
            'leds' => 'LED warning lights',
        ];

        foreach ($validated['components'] ?? [] as $component => $status) {
            $componentDevice = $device->team->devices()
                ->where('name', $componentNames[$component])
                ->first();

            if (! $componentDevice) {
                continue;
            }

            $componentDevice->status = $status;
            $componentDevice->last_seen_at = $device->last_seen_at;
            $componentDevice->save();
        }

        return response()->json([
            'ok' => true,
            'device_id' => $device->getKey(),
            'last_seen_at' => $device->last_seen_at?->toISOString(),
        ]);
    }

    public function incident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:128'],
            'incident_type' => ['nullable', 'string', 'max:255'],
            'detection_method' => ['nullable', 'string', 'max:255'],
            'pedestrian_status' => ['nullable', 'string', 'max:255'],
            'vehicle_status' => ['nullable', 'string', 'max:255'],
            'event_description' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $device = $this->deviceFrom($request);
        $device->status = 'online';
        $device->last_seen_at = now();
        $device->save();

        $deviceCode = strtoupper(base_convert((string) $device->getKey(), 10, 36));
        $eventCode = strtoupper(substr(hash('sha256', $validated['event_id']), 0, 20));
        $incidentCode = "CG-{$deviceCode}-{$eventCode}";
        $occurredAt = isset($validated['occurred_at'])
            ? Carbon::parse($validated['occurred_at'], 'UTC')->utc()
            : now();

        $incident = CrossingIncident::query()->firstOrCreate(
            [
                'team_id' => $device->team_id,
                'incident_code' => $incidentCode,
            ],
            [
                'incident_type' => $validated['incident_type'] ?? 'Potential crossing conflict',
                'status' => 'needs_review',
                'occurred_at' => $occurredAt,
                'detection_method' => $validated['detection_method'] ?? 'Arduino sensors',
                'pedestrian_status' => $validated['pedestrian_status'] ?? 'Not recorded',
                'vehicle_status' => $validated['vehicle_status'] ?? 'Not recorded',
                'device_status' => 'online',
                'alert_transmission_status' => 'Received by CrossGuard',
                'evidence_source' => CrossingIncident::ARDUINO_EVIDENCE_SOURCE,
                'event_description' => $validated['event_description'] ?? 'A potential crossing conflict was detected by the Arduino controller.',
            ],
        );

        return response()->json([
            'ok' => true,
            'incident_code' => $incident->incident_code,
            'status' => $incident->status,
            'created' => $incident->wasRecentlyCreated,
        ], $incident->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    private function deviceFrom(Request $request): CrossingDevice
    {
        $device = $request->attributes->get('crossing_device');

        if (! $device instanceof CrossingDevice) {
            throw new \LogicException('The device authentication middleware did not attach a device.');
        }

        return $device;
    }
}

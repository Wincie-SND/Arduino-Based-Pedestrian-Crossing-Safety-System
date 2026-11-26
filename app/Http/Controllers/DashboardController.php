<?php

namespace App\Http\Controllers;

use App\Models\CrossingDevice;
use App\Models\CrossingNotification;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $currentTeam = $request->user()->currentTeam;
        $devices = $this->registeredHardware($currentTeam);
        $systemStatus = $this->systemStatus($devices->map(fn ($device) => $device->connection_status));

        return view('dashboard', [
            'currentTeam' => $currentTeam,
            'totalViolations' => $currentTeam->incidents()->count(),
            'activeAlerts' => CrossingNotification::query()
                ->where('team_id', $currentTeam->id)
                ->where('level', 'alert')
                ->whereNull('read_at')
                ->count(),
            'issuesReported' => $currentTeam->issues()->count(),
            'systemStatus' => $systemStatus,
            'devices' => $devices,
            'recentIncidents' => $currentTeam->incidents()->latest('occurred_at')->take(8)->get(),
            'headerNotifications' => $currentTeam->notifications()->latest('occurred_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }

    public function deviceStatuses(Request $request): JsonResponse
    {
        $currentTeam = $request->user()->currentTeam;

        abort_if($currentTeam === null, 404);

        $statuses = $this->registeredHardware($currentTeam)->map(fn (CrossingDevice $device) => [
            'id' => $device->id,
            'status' => $device->connection_status,
        ]);

        return response()->json([
            'devices' => $statuses,
            'system_status' => $this->systemStatus($statuses->pluck('status')),
        ]);
    }

    /** @return Collection<int, CrossingDevice> */
    private function registeredHardware(Team $team): Collection
    {
        return $team->devices()->where('category', '!=', 'camera')->orderBy('name')->get();
    }

    /** @param Collection<int, string> $statuses */
    private function systemStatus(Collection $statuses): string
    {
        if ($statuses->isEmpty()) {
            return 'not_connected';
        }

        return $statuses->contains(fn (string $status) => in_array($status, ['warning', 'offline', 'unknown'], true))
            ? 'attention'
            : 'online';
    }
}

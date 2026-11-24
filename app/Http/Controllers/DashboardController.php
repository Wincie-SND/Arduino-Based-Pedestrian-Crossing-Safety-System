<?php

namespace App\Http\Controllers;

use App\Models\CrossingNotification;
use App\Models\TeamInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $currentTeam = $request->user()->currentTeam;
        $devices = $currentTeam->devices()->orderBy('name')->get();
        $systemStatus = $this->systemStatus($devices->map(fn ($device) => $device->connection_status));

        return view('dashboard', [
            'pendingInvitations' => $pendingInvitations,
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
            'recentNotifications' => $currentTeam->notifications()->latest('occurred_at')->take(4)->get(),
        ]);
    }

    public function deviceStatuses(Request $request): JsonResponse
    {
        $currentTeam = $request->user()->currentTeam;

        abort_if($currentTeam === null, 404);

        $devices = $currentTeam->devices()->orderBy('name')->get();
        $statuses = $devices->map(fn ($device) => [
            'id' => $device->id,
            'status' => $device->connection_status,
        ]);

        return response()->json([
            'devices' => $statuses,
            'system_status' => $this->systemStatus($statuses->pluck('status')),
        ]);
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

<?php

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use App\Models\CrossingNotification;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

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
        $systemStatus = $devices->isEmpty()
            ? 'not_connected'
            : ($devices->contains(fn ($device) => in_array($device->status, ['warning', 'offline', 'unknown'], true))
                ? 'attention'
                : 'online');

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
}

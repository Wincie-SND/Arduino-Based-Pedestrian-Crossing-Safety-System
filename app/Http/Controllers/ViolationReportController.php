<?php

namespace App\Http\Controllers;

use App\Models\CrossingIncident;
use App\Models\CrossingIncidentEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViolationReportController extends Controller
{
    public function index(Request $request): View
    {
        $team = $this->teamForRequest($request);
        $filters = $this->validatedFilters($request);
        $query = $this->applyFilters(
            CrossingIncident::query()->where('team_id', $team->id)->with('assignedEnforcer'),
            $filters,
        );
        $statusCounts = $team->incidents()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('violations.index', [
            'currentTeam' => $team,
            'incidents' => $query->latest('occurred_at')->paginate(10)->withQueryString(),
            'types' => $team->incidents()->select('incident_type')->distinct()->orderBy('incident_type')->pluck('incident_type'),
            'filters' => $filters,
            'totalIncidents' => $statusCounts->sum(),
            'pendingCount' => $statusCounts->get('needs_review', 0),
            'verifiedCount' => $statusCounts->get('verified', 0),
            'resolvedCount' => $statusCounts->get('resolved', 0),
        ]);
    }

    public function show(Request $request): View
    {
        $incident = (string) $request->route('incident');
        $team = $this->teamForRequest($request);
        $record = $team->incidents()
            ->with(['assignedEnforcer', 'reviewer', 'events.user'])
            ->findOrFail($incident);

        return view('violations.show', [
            'currentTeam' => $team,
            'incident' => $record,
        ]);
    }

    public function review(Request $request): RedirectResponse
    {
        $incident = (string) $request->route('incident');
        $validated = $request->validate([
            'decision' => ['required', 'in:verify,reject,resolve,save_remarks'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $team = $this->teamForRequest($request);
        $decision = $validated['decision'];
        $remarks = trim($validated['remarks'] ?? '');
        $eventTitles = [
            'verify' => 'Incident verified',
            'reject' => 'Incident rejected',
            'resolve' => 'Incident marked resolved',
            'save_remarks' => 'Review remarks saved',
        ];

        DB::transaction(function () use ($request, $team, $incident, $decision, $remarks, $eventTitles): void {
            $record = $team->incidents()->lockForUpdate()->findOrFail($incident);
            $record->review_remarks = $remarks !== '' ? $remarks : null;

            if ($decision !== 'save_remarks') {
                $record->status = match ($decision) {
                    'verify' => 'verified',
                    'reject' => 'rejected',
                    'resolve' => 'resolved',
                    default => throw new \LogicException('Unexpected incident review decision.'),
                };
                $record->reviewed_by = $request->user()->id;
                $record->reviewed_at = now();
            }

            $record->save();

            CrossingIncidentEvent::create([
                'crossing_incident_id' => $record->id,
                'user_id' => $request->user()->id,
                'event_code' => 'review-'.Str::random(32),
                'title' => $eventTitles[$decision],
                'description' => $remarks !== '' ? $remarks : null,
                'occurred_at' => now(),
            ]);
        });

        return to_route('violations.show', [
            'current_team' => $team->slug,
            'incident' => $incident,
        ])->with('toast', $eventTitles[$decision].'.');
    }

    public function export(Request $request): StreamedResponse
    {
        $team = $this->teamForRequest($request);
        $records = $this->applyFilters(
            CrossingIncident::query()->where('team_id', $team->id)->with('assignedEnforcer'),
            $this->validatedFilters($request),
        )->latest('occurred_at')->get();

        return response()->streamDownload(function () use ($records): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            try {
                fputcsv($stream, ['Incident ID', 'Date and time', 'Incident type', 'Status', 'Assigned enforcer', 'Evidence']);

                foreach ($records as $record) {
                    fputcsv($stream, [
                        $record->incident_code,
                        $record->occurred_at->format('Y-m-d H:i:s'),
                        $record->incident_type,
                        $this->statusLabel($record->status),
                        $record->assignedEnforcer->name ?? 'Unassigned',
                        $record->evidence_path ? 'Attached' : 'Not attached',
                    ]);
                }
            } finally {
                fclose($stream);
            }
        }, 'violation-reports-'.now()->format('Ymd').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{search: string, date: string, type: string, status: string} */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'in:all,today,7_days,30_days'],
            'type' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:all,needs_review,verified,resolved,rejected'],
        ]);

        return [
            'search' => $validated['search'] ?? '',
            'date' => $validated['date'] ?? 'all',
            'type' => $validated['type'] ?? 'all',
            'status' => $validated['status'] ?? 'all',
        ];
    }

    /** @param Builder<CrossingIncident> $query
     * @param  array{search: string, date: string, type: string, status: string}  $filters
     * @return Builder<CrossingIncident>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(fn (Builder $builder) => $builder
                ->where('incident_code', 'like', '%'.$search.'%')
                ->orWhere('incident_type', 'like', '%'.$search.'%'));
        }

        if ($filters['date'] === 'today') {
            $query->whereDate('occurred_at', today());
        } elseif ($filters['date'] === '7_days') {
            $query->where('occurred_at', '>=', now()->subDays(7));
        } elseif ($filters['date'] === '30_days') {
            $query->where('occurred_at', '>=', now()->subDays(30));
        }

        if ($filters['type'] !== 'all') {
            $query->where('incident_type', $filters['type']);
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'needs_review' => 'Pending verification',
            'verified' => 'Verified',
            'resolved' => 'Resolved',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function teamForRequest(Request $request): Team
    {
        $routeTeam = $request->route('current_team');
        $team = $routeTeam instanceof Team
            ? $routeTeam
            : Team::query()->where('slug', $routeTeam)->firstOrFail();
        $user = $request->user();

        abort_unless($user instanceof User && $user->belongsToTeam($team), 404);

        return $team;
    }
}

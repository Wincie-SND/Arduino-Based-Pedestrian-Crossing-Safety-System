@extends('layouts.dashboard')
@section('title', 'Violation Reports')
@section('content')
@php
    $statusLabels = [
        'needs_review' => 'Pending verification',
        'verified' => 'Verified',
        'resolved' => 'Resolved',
        'rejected' => 'Rejected',
    ];
@endphp

<div class="reports-page">
    <section class="reports-heading">
        <div>
            <p class="eyebrow">Incident management</p>
            <h1>Violation Reports</h1>
            <p>Review and manage potential crosswalk violations recorded by CrossGuard.</p>
        </div>
        <a class="export-button" style="color: #fff; background-color: #007bff;"  href="{{ route('violations.export', array_merge(['current_team' => $currentTeam->slug], request()->query())) }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5v11M8 10.5l4 4 4-4M4 16v3.5h16V16"/></svg>
            Export CSV
        </a>
    </section>

    <section class="report-summary" aria-label="Violation report totals">
        <a class="summary-segment" href="{{ route('violations.index', ['current_team' => $currentTeam->slug]) }}">
            <span class="summary-dot summary-all"></span><span>All incidents</span><strong>{{ $totalIncidents }}</strong>
        </a>
        <a class="summary-segment" href="{{ route('violations.index', ['current_team' => $currentTeam->slug, 'status' => 'needs_review']) }}">
            <span class="summary-dot summary-pending"></span><span>Pending verification</span><strong>{{ $pendingCount }}</strong>
        </a>
        <a class="summary-segment" href="{{ route('violations.index', ['current_team' => $currentTeam->slug, 'status' => 'verified']) }}">
            <span class="summary-dot summary-verified"></span><span>Verified</span><strong>{{ $verifiedCount }}</strong>
        </a>
        <a class="summary-segment" href="{{ route('violations.index', ['current_team' => $currentTeam->slug, 'status' => 'resolved']) }}">
            <span class="summary-dot summary-resolved"></span><span>Resolved</span><strong>{{ $resolvedCount }}</strong>
        </a>
    </section>

    <section class="reports-table-panel" aria-label="Violation reports">
        <form class="report-filters" method="get" action="{{ route('violations.index', ['current_team' => $currentTeam->slug]) }}">
            <label class="report-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.3"/><path d="m15.5 15.5 4 4"/></svg>
                <span class="sr-only">Search incident ID or type</span>
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search incident ID or type…">
            </label>
            <label class="filter-select">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17"/></svg>
                <span class="sr-only">Date range</span>
                <select name="date">
                    <option value="all" @selected($filters['date'] === 'all')>All dates</option>
                    <option value="today" @selected($filters['date'] === 'today')>Today</option>
                    <option value="7_days" @selected($filters['date'] === '7_days')>Last 7 days</option>
                    <option value="30_days" @selected($filters['date'] === '30_days')>Last 30 days</option>
                </select>
            </label>
            <label class="filter-select">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6.2 7.2v5.1l-3.6 1.8v-6.9L4 5Z"/></svg>
                <span class="sr-only">Incident type</span>
                <select name="type">
                    <option value="all" @selected($filters['type'] === 'all')>All incident types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </label>
            <label class="filter-select">
                <span class="sr-only">Verification status</span>
                <select name="status">
                    <option value="all" @selected($filters['status'] === 'all')>All statuses</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="filter-actions"><button class="filter-apply" type="submit">Apply filters</button>@if (request()->query())<a href="{{ route('violations.index', ['current_team' => $currentTeam->slug]) }}">Clear</a>@endif</div>
        </form>

        <div class="table-wrap report-table-wrap">
            <table class="report-table">
                <thead><tr><th scope="col">Incident ID</th><th scope="col">Date &amp; time</th><th scope="col">Incident type</th><th scope="col">Evidence</th><th scope="col">Verification status</th><th scope="col">Assigned enforcer</th><th scope="col">Details</th></tr></thead>
                <tbody>
                    @forelse ($incidents as $incident)
                        <tr class="clickable-incident-row" data-detail-url="{{ route('violations.show', ['current_team' => $currentTeam->slug, 'incident' => $incident->id]) }}" tabindex="0" aria-label="Open incident {{ $incident->incident_code }}">
                            <td><a class="incident-id-link" href="{{ route('violations.show', ['current_team' => $currentTeam->slug, 'incident' => $incident->id]) }}">{{ $incident->incident_code }}</a></td>
                            <td><time datetime="{{ $incident->occurred_at->toIso8601String() }}">{{ $incident->occurred_at->format('M j, Y') }}<small>{{ $incident->occurred_at->format('g:i A') }}</small></time></td>
                            <td>{{ $incident->incident_type }}</td>
                            <td>
                                @if ($incident->evidence_path)
                                    <a class="evidence-link" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($incident->evidence_path) }}" target="_blank" rel="noopener">View evidence</a>
                                @else
                                    <span class="table-muted">Not attached</span>
                                @endif
                            </td>
                            <td><span @class(['report-status', 'status-pending' => $incident->status === 'needs_review', 'status-verified' => $incident->status === 'verified', 'status-resolved' => $incident->status === 'resolved', 'status-rejected' => $incident->status === 'rejected'])>{{ $statusLabels[$incident->status] ?? ucfirst(str_replace('_', ' ', $incident->status)) }}</span></td>
                            <td>{{ $incident->assignedEnforcer?->name ?? 'Unassigned' }}</td>
                            <td><a class="details-action" href="{{ route('violations.show', ['current_team' => $currentTeam->slug, 'incident' => $incident->id]) }}" aria-label="Open {{ $incident->incident_code }} details"><span aria-hidden="true">•••</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="report-empty"><span class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4.5h14v15H5zM8 8h8M8 11.5h8M8 15h5"/></svg></span><strong>No violation reports found</strong><span>Try changing your filters, or wait for a crossing incident to be recorded.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="report-table-footer">
            <span>Showing {{ $incidents->firstItem() ?? 0 }}–{{ $incidents->lastItem() ?? 0 }} of {{ $incidents->total() }} results</span>
            @if ($incidents->hasPages())
                <nav class="report-pagination" aria-label="Violation report pages">
                    @if ($incidents->onFirstPage())<span class="page-control disabled">Previous</span>@else<a class="page-control" href="{{ $incidents->previousPageUrl() }}">Previous</a>@endif
                    @foreach ($incidents->getUrlRange(max(1, $incidents->currentPage() - 2), min($incidents->lastPage(), $incidents->currentPage() + 2)) as $page => $url)
                        <a @class(['page-number', 'current' => $incidents->currentPage() === $page]) href="{{ $url }}" @if ($incidents->currentPage() === $page) aria-current="page" @endif>{{ $page }}</a>
                    @endforeach
                    @if ($incidents->hasMorePages())<a class="page-control" href="{{ $incidents->nextPageUrl() }}">Next</a>@else<span class="page-control disabled">Next</span>@endif
                </nav>
            @endif
        </footer>
    </section>
</div>
@endsection
@push('scripts')
<script>
    document.querySelectorAll('.clickable-incident-row').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('a, button')) return;
            window.location.assign(row.dataset.detailUrl);
        });
        row.addEventListener('keydown', (event) => {
            if (event.target !== row || !['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            window.location.assign(row.dataset.detailUrl);
        });
    });
</script>
@endpush

@extends('layouts.dashboard')
@section('title', 'Dashboard')
@section('content')
@php
    $firstName = explode(' ', trim(auth()->user()->name))[0] ?? auth()->user()->name;
    $systemStatusLabel = match ($systemStatus) {
        'online' => 'Online',
        'attention' => 'Needs attention',
        default => 'Not connected',
    };
@endphp

<section class="dashboard-intro" aria-labelledby="dashboard-title">
    <div>
        <div class="dashboard-eyebrow-row">
            <p class="eyebrow">Operations overview</p>
            @if (app()->environment(['local', 'testing']))<span class="demo-badge">Demo data</span>@endif
        </div>
        <h1 id="dashboard-title">Good day, {{ $firstName }}</h1>
        <p class="dashboard-summary">Here’s what’s happening at the monitored crossing today.</p>
    </div>
    <div class="date-chip" aria-label="Current date">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17"/></svg>
        <span><small>Today</small><strong>{{ now()->format('M j, Y') }}</strong></span>
    </div>
</section>

<section class="metric-grid" aria-label="Crossing summary">
    <article class="metric-card">
        <div class="metric-card-top"><span>Total violations</span><span class="metric-icon metric-icon-amber" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4.5M12 16.5h.01"/></svg></span></div>
        <strong class="metric-value">{{ $totalViolations }}</strong>
        <span class="metric-note">Recorded incident reports</span>
    </article>
    <article class="metric-card">
        <div class="metric-card-top"><span>Active alerts</span><span class="metric-icon metric-icon-red" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Z"/><path d="M12 7.5v5M12 16h.01"/></svg></span></div>
        <strong class="metric-value">{{ $activeAlerts }}</strong>
        <span class="metric-note">Unread high priority alerts</span>
    </article>
    <article class="metric-card">
        <div class="metric-card-top"><span>Issues reported</span><span class="metric-icon metric-icon-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12.5 4.2 4.2L19 7"/></svg></span></div>
        <strong class="metric-value">{{ $issuesReported }}</strong>
        <span class="metric-note">Reports submitted by personnel</span>
    </article>
    <article class="metric-card metric-card-status">
        <div class="metric-card-top"><span>System status</span><span @class(['status-dot', 'status-online-dot' => $systemStatus === 'online', 'status-attention-dot' => $systemStatus === 'attention', 'status-offline-dot' => $systemStatus === 'not_connected']) aria-hidden="true"></span></div>
        <strong @class(['status-awaiting', 'status-online' => $systemStatus === 'online', 'status-attention' => $systemStatus === 'attention', 'status-offline' => $systemStatus === 'not_connected'])>{{ $systemStatusLabel }}</strong>
        <span class="metric-note">Based on registered device status</span>
    </article>
</section>

<section class="dashboard-grid">
    <article class="dashboard-panel violations-panel" id="recent-violations">
        <header class="panel-heading">
            <div><h2>Recent violations</h2><p>Latest incidents recorded at this crossing.</p></div>
            <span class="panel-link" aria-disabled="true">View all</span>
        </header>
        <div class="table-wrap dashboard-table-wrap">
            <table class="dashboard-table">
                <thead><tr><th scope="col">Incident ID</th><th scope="col">Date &amp; time</th><th scope="col">Incident type</th><th scope="col">Status</th><th scope="col">Evidence</th></tr></thead>
                <tbody>
                    @forelse ($recentIncidents as $incident)
                        <tr class="incident-row">
                            <td><strong>{{ $incident->incident_code }}</strong></td>
                            <td><time datetime="{{ $incident->occurred_at->toIso8601String() }}">{{ $incident->occurred_at->format('M j, Y') }}<small>{{ $incident->occurred_at->format('g:i A') }}</small></time></td>
                            <td>{{ $incident->incident_type }}</td>
                            <td><span @class(['incident-status', 'incident-review' => $incident->status === 'needs_review', 'incident-confirmed' => $incident->status === 'confirmed', 'incident-resolved' => $incident->status === 'resolved'])>{{ ucfirst(str_replace('_', ' ', $incident->status)) }}</span></td>
                            <td>{{ $incident->evidence_path ? 'Attached' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="dashboard-empty"><span class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4.5h14v15H5zM8 8h8M8 11.5h8M8 15h5"/></svg></span><strong>No incident records yet</strong><span>Incident reports for this crossing will appear here.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    <div class="dashboard-side-stack">
        <article class="dashboard-panel device-panel">
            <header class="panel-heading"><div><h2>Device &amp; sensor status</h2><p>Crossing hardware connection</p></div></header>
            <ul class="device-list">
                @forelse ($devices as $device)
                    <li>
                        <span>{{ $device->name }}</span>
                        <span @class(['device-status', 'device-online' => $device->status === 'online', 'device-warning' => $device->status === 'warning', 'device-offline' => $device->status === 'offline', 'device-unknown' => ! in_array($device->status, ['online', 'warning', 'offline'], true)])>{{ match ($device->status) { 'online' => 'Online', 'warning' => 'Check', 'offline' => 'Offline', default => 'Unknown' } }}</span>
                    </li>
                @empty
                    <li class="device-empty">No devices registered for this crossing.</li>
                @endforelse
            </ul>
            <p class="panel-footnote">Device states are based on the latest saved status.</p>
        </article>

        <article class="dashboard-panel notifications-panel">
            <header class="panel-heading"><div><h2>Recent notifications</h2><p>System and crossing updates</p></div><span class="panel-link" aria-disabled="true">View all</span></header>
            <div class="recent-notification-list">
                @forelse ($recentNotifications as $notification)
                    <div class="recent-notification">
                        <span @class(['notification-indicator', 'notification-alert' => $notification->level === 'alert', 'notification-warning' => $notification->level === 'warning', 'notification-info' => $notification->level === 'info']) aria-hidden="true"></span>
                        <div><strong>{{ $notification->title }}</strong><span>{{ $notification->message }}</span><small>{{ $notification->occurred_at->diffForHumans() }}</small></div>
                    </div>
                @empty
                    <div class="notification-empty">
                        <span class="notification-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-2.5 7-2.5 8.5h17C20.5 16 18 16 18 9ZM10 20h4"/></svg></span>
                        <span>Notifications will appear here when an alert is recorded.</span>
                    </div>
                @endforelse
            </div>
        </article>
    </div>
</section>

@if ($pendingInvitations->isNotEmpty())
    <section class="dashboard-panel invitation-panel">
        <header class="panel-heading"><div><h2>Pending team invitations</h2><p>Accept an invitation to join an authorized monitoring team.</p></div></header>
        @foreach ($pendingInvitations as $invitation)
            <div class="invitation-row">
                <div><strong>{{ $invitation['team']['name'] }}</strong><span>Invited by {{ $invitation['inviterName'] }}</span></div>
                <div class="invitation-actions">
                    <form method="post" action="{{ route('invitations.accept', ['invitation' => $invitation['code']]) }}">@csrf<button class="button small" type="submit">Accept</button></form>
                    <form method="post" action="{{ route('invitations.decline', ['invitation' => $invitation['code']]) }}">@csrf @method('delete')<button class="button secondary small" type="submit">Decline</button></form>
                </div>
            </div>
        @endforeach
    </section>
@endif
@endsection

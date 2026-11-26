@extends('layouts.dashboard')
@section('title', 'Dashboard')
@section('content')
@php
    $firstName = explode(' ', trim(auth()->user()->name))[0] ??          auth()->user()->name;
    $systemStatusLabel = match ($systemStatus) {
        'online' => 'Online',
        'attention' => 'Needs attention',
        default => 'Not connected',
    };
@endphp

<section class="dashboard-intro" aria-labelledby="dashboard-title">
    <div>
        <p class="eyebrow">Operations overview</p>
        <h1 id="dashboard-title">Good day, {{ $firstName }}</h1>
        <p class="dashboard-summary">Here’s what’s happening at the monitored crossing today.</p>
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
        <div class="metric-card-top"><span>System status</span><span id="system-status-dot" @class(['status-dot', 'status-online-dot' => $systemStatus === 'online', 'status-attention-dot' => $systemStatus === 'attention', 'status-offline-dot' => $systemStatus === 'not_connected']) aria-hidden="true"></span></div>
        <strong id="system-status-label" @class(['status-awaiting', 'status-online' => $systemStatus === 'online', 'status-attention' => $systemStatus === 'attention', 'status-offline' => $systemStatus === 'not_connected'])>{{ $systemStatusLabel }}</strong>
        <span class="metric-note">Based on registered hardware status</span>
    </article>
</section>

<section class="dashboard-grid">
    <article class="dashboard-panel violations-panel" id="recent-violations">
        <header class="panel-heading">
            <div><h2>Recent violations</h2><p>Latest incidents recorded at this crossing.</p></div>
            <a class="panel-link" href="{{ route('violations.index', ['current_team' => $currentTeam->slug]) }}">View all</a>
        </header>
        <div class="table-wrap dashboard-table-wrap">
            <table class="dashboard-table">
                <thead><tr><th scope="col">Incident ID</th><th scope="col">Date &amp; time</th><th scope="col">Incident type</th><th scope="col">Status</th><th scope="col">Evidence</th></tr></thead>
                <tbody>
                    @forelse ($recentIncidents as $incident)
                        @php($occurredAt = $incident->occurred_at->copy()->setTimezone(config()->string('app.display_timezone')))
                        <tr class="incident-row">
                            <td data-label="Incident ID"><strong>{{ $incident->incident_code }}</strong></td>
                            <td data-label="Date &amp; time"><time datetime="{{ $occurredAt->toIso8601String() }}">{{ $occurredAt->format('M j, Y') }}<small>{{ $occurredAt->format('g:i A') }}</small></time></td>
                            <td data-label="Incident type">{{ $incident->incident_type }}</td>
                            <td data-label="Status"><span @class(['incident-status', 'incident-review' => $incident->status === 'needs_review', 'incident-confirmed' => $incident->status === 'confirmed', 'incident-resolved' => $incident->status === 'resolved'])>{{ ucfirst(str_replace('_', ' ', $incident->status)) }}</span></td>
                            <td data-label="Evidence">{{ $incident->evidence_path ? 'Attached' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="dashboard-empty"><span class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4.5h14v15H5zM8 8h8M8 11.5h8M8 15h5"/></svg></span><strong>No incident records yet</strong><span>Incident reports for this crossing will appear here.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    <div class="dashboard-side-stack">
        <article class="dashboard-panel camera-panel" aria-labelledby="crossing-camera-title" data-crossing-camera data-incidents-url="{{ route('dashboard.camera.incidents', ['current_team' => $currentTeam->slug], false) }}">
            <header class="panel-heading camera-heading">
                <h2 id="crossing-camera-title">Camera module</h2>
                <button class="button small camera-toggle" type="button" data-camera-toggle disabled>Start camera</button>
            </header>
            <div class="camera-preview">
                <div class="camera-placeholder" data-camera-placeholder aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 7.5h3l1.5-2h7l1.5 2h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                </div>
                <video aria-label="Live crossing camera" data-camera-video autoplay muted playsinline hidden></video>
            </div>
            <p class="camera-status" data-camera-status role="status">Camera is off.</p>
            <p class="panel-footnote camera-footnote">Point this device’s camera at the crossing and keep this tab visible to save incident snapshots.</p>
        </article>

        <article class="dashboard-panel device-panel">
            <header class="panel-heading"><div><h2>Device &amp; sensor status</h2><p>Crossing hardware connection</p></div></header>
            <ul class="device-list">
                @forelse ($devices as $device)
                    <li data-device-id="{{ $device->id }}">
                        <span>{{ $device->name }}</span>
                        <span data-device-status @class(['device-status', 'device-online' => $device->connection_status === 'online', 'device-warning' => $device->connection_status === 'warning', 'device-offline' => $device->connection_status === 'offline', 'device-unknown' => ! in_array($device->connection_status, ['online', 'warning', 'offline'], true)])>{{ match ($device->connection_status) { 'online' => 'Online', 'warning' => 'Check', 'offline' => 'Offline', default => 'Unknown' } }}</span>
                    </li>
                @empty
                    <li class="device-empty">No crossing hardware registered.</li>
                @endforelse
                <li>
                    <span>Camera module</span>
                    <span class="device-status device-offline" data-camera-device-status>Offline</span>
                </li>
            </ul>
            <p class="panel-footnote">Camera status reflects the live preview in this browser.</p>
        </article>

        <article class="dashboard-panel notifications-panel">
            <header class="panel-heading"><div><h2>Recent notifications</h2><p>System and crossing updates</p></div><a class="panel-link" href="{{ route('notifications.index', ['current_team' => $currentTeam->slug]) }}">View all</a></header>
            <div class="recent-notification-list">
                @forelse ($headerNotifications->take(4) as $notification)
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

@push('scripts')
<script src="{{ asset('js/crossing-camera.js') }}?v={{ filemtime(public_path('js/crossing-camera.js')) }}" defer></script>
<script>
    (() => {
        const endpoint = @json(route('dashboard.device-status', ['current_team' => $currentTeam->slug]));
        const labels = { online: 'Online', warning: 'Check', offline: 'Offline', unknown: 'Unknown' };
        const deviceClasses = ['device-online', 'device-warning', 'device-offline', 'device-unknown'];
        const systemClasses = ['status-online', 'status-attention', 'status-offline'];
        const dotClasses = ['status-online-dot', 'status-attention-dot', 'status-offline-dot'];

        async function refreshDeviceStatuses() {
            if (document.visibilityState !== 'visible') return;

            try {
                const response = await fetch(endpoint, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    credentials: 'same-origin',
                });

                if (!response.ok) return;

                const snapshot = await response.json();

                for (const device of snapshot.devices ?? []) {
                    const row = document.querySelector(`[data-device-id="${device.id}"]`);
                    const badge = row?.querySelector('[data-device-status]');
                    if (!badge) continue;

                    const status = Object.hasOwn(labels, device.status) ? device.status : 'unknown';
                    badge.classList.remove(...deviceClasses);
                    badge.classList.add(`device-${status}`);
                    badge.textContent = labels[status];
                }

                const systemStatus = ['online', 'attention', 'not_connected'].includes(snapshot.system_status)
                    ? snapshot.system_status
                    : 'not_connected';
                const statusLabel = document.getElementById('system-status-label');
                const statusDot = document.getElementById('system-status-dot');

                if (statusLabel) {
                    statusLabel.classList.remove(...systemClasses);
                    statusLabel.classList.add(systemStatus === 'not_connected' ? 'status-offline' : `status-${systemStatus}`);
                    statusLabel.textContent = systemStatus === 'not_connected'
                        ? 'Not connected'
                        : systemStatus === 'attention' ? 'Needs attention' : 'Online';
                }

                if (statusDot) {
                    statusDot.classList.remove(...dotClasses);
                    statusDot.classList.add(systemStatus === 'not_connected' ? 'status-offline-dot' : `status-${systemStatus}-dot`);
                }
            } catch {
                // Keep the last rendered status if a temporary request fails.
            }
        }

        window.setInterval(refreshDeviceStatuses, 500);
    })();
</script>
@endpush

@endsection

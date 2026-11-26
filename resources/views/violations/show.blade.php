@extends('layouts.dashboard')
@section('title', 'Incident '.$incident->incident_code)
@section('content')
@php
    $displayTimezone = config()->string('app.display_timezone');
    $occurredAt = $incident->occurred_at->copy()->setTimezone($displayTimezone);
    $statusLabel = match ($incident->status) {
        'needs_review' => 'Pending verification',
        'verified' => 'Verified',
        'resolved' => 'Resolved',
        'rejected' => 'Rejected',
        default => ucfirst(str_replace('_', ' ', $incident->status)),
    };
@endphp

<div class="incident-detail-page">
    <section class="incident-detail-heading">
        <div>
            <a class="return-link" href="{{ route('violations.index', ['current_team' => $currentTeam->slug]) }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
                Return to reports
            </a>
            <p class="eyebrow">Incident record</p>
            <h1>{{ $incident->incident_code }}</h1>
            <p class="incident-subtitle">Potential violation captured on {{ $occurredAt->format('F j, Y') }} at {{ $occurredAt->format('g:i:s A') }}.</p>
        </div>
        <span @class(['report-status incident-heading-status', 'status-pending' => $incident->status === 'needs_review', 'status-verified' => $incident->status === 'verified', 'status-resolved' => $incident->status === 'resolved', 'status-rejected' => $incident->status === 'rejected'])>{{ $statusLabel }}</span>
    </section>

    <section class="incident-detail-grid">
        <div class="incident-left-stack">
            <article class="dashboard-panel evidence-panel">
                <header class="panel-heading evidence-heading">
                    <div><h2>Incident evidence</h2><p>{{ $incident->evidence_source ? 'Evidence source: '.$incident->evidence_source : 'Evidence provided with this incident' }}</p></div>
                </header>
                @if ($incident->evidence_path)
                    @php($evidenceUrl = route('violations.evidence', ['current_team' => $currentTeam->slug, 'incident' => $incident->id]))
                    <a class="evidence-preview" href="{{ $evidenceUrl }}" data-evidence-open="{{ $incident->incident_code }}" aria-haspopup="dialog" aria-controls="evidence-viewer" aria-label="Enlarge evidence for incident {{ $incident->incident_code }}">
                        <img src="{{ $evidenceUrl }}" alt="Evidence for incident {{ $incident->incident_code }}">
                    </a>
                @else
                    <div class="evidence-preview evidence-unavailable">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5h3l1.5-2h7l1.5 2h3v11H4z"/><circle cx="12" cy="13" r="3.5"/><path d="m5 19 14-14"/></svg>
                        <strong>No evidence snapshot attached</strong>
                        <span>This record has incident details, but no image file was saved.</span>
                    </div>
                @endif
                <div class="evidence-metadata">
                    <div><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17"/></svg><span><small>Detection time</small><strong>{{ $occurredAt->format('M j, Y | g:i:s A') }}</strong></span></div>
                    <div><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.2"/></svg><span><small>Monitoring location</small><strong>{{ $currentTeam->name }}</strong></span></div>
                    <div><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5h3l1.5-2h7l1.5 2h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg><span><small>Evidence source</small><strong>{{ $incident->evidence_source ?: 'Not recorded' }}</strong></span></div>
                </div>
            </article>

            <article class="dashboard-panel review-panel">
                <header class="panel-heading review-panel-heading">
                    <div><h2>Review &amp; verification</h2><p>Only authorized team members can confirm this potential violation.</p></div>
                    <div class="human-review-note"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4.5 6v5.5c0 4.2 3.1 7.4 7.5 9.5 4.4-2.1 7.5-5.3 7.5-9.5V6L12 3Z"/><path d="m9 12 2 2 4-4"/></svg><span><strong>Human verification required</strong><small>Review the record and available evidence before taking action.</small></span></div>
                </header>
                <form method="post" action="{{ route('violations.review', ['current_team' => $currentTeam->slug, 'incident' => $incident->id]) }}" class="incident-review-form">
                    @csrf
                    @method('patch')
                    <label for="review-remarks">Enforcer remarks <span>Optional</span></label>
                    <textarea id="review-remarks" name="remarks" rows="4" placeholder="Add observations, evidence notes, or a reason for your decision…">{{ old('remarks', $incident->review_remarks) }}</textarea>
                    @error('remarks')<p class="field-error">{{ $message }}</p>@enderror
                    <div class="review-actions">
                        <div>
                            <button class="review-action verify-action" type="submit" name="decision" value="verify" @disabled($incident->status !== 'needs_review')><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.2 4.2L19 7"/></svg>{{ in_array($incident->status, ['verified', 'resolved'], true) ? 'Verified' : 'Verify incident' }}</button>
                            <button class="review-action reject-action" type="submit" name="decision" value="reject"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>Reject</button>
                        </div>
                        <div>
                            <button class="review-action resolve-action" type="submit" name="decision" value="resolve" @disabled($incident->status !== 'verified')><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.2 4.2L19 7"/></svg>{{ $incident->status === 'resolved' ? 'Resolved' : 'Mark as resolved' }}</button>
                            <button class="review-action neutral-action" type="submit" name="decision" value="save_remarks"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4zM8 4v5h8V4M8 20v-7h8v7"/></svg>Save remarks</button>
                        </div>
                    </div>
                </form>
            </article>
        </div>

        <div class="incident-right-stack">
            <article class="dashboard-panel incident-info-panel">
                <header class="panel-heading"><div><h2>Incident information</h2></div></header>
                <dl class="incident-info-list">
                    <div><dt>Classification</dt><dd>{{ $incident->incident_type }}</dd></div>
                    <div><dt>Detection method</dt><dd>{{ $incident->detection_method ?: 'Not recorded' }}</dd></div>
                    <div><dt>Pedestrian status</dt><dd>{{ $incident->pedestrian_status ?: 'Not recorded' }}</dd></div>
                    <div><dt>Vehicle status</dt><dd>{{ $incident->vehicle_status ?: 'Not recorded' }}</dd></div>
                    <div><dt>Device status</dt><dd>{{ $incident->device_status ?: 'Not recorded' }}</dd></div>
                    <div><dt>Alert transmission</dt><dd>{{ $incident->alert_transmission_status ?: 'Not recorded' }}</dd></div>
                    <div><dt>Verification status</dt><dd>{{ $statusLabel }}</dd></div>
                    <div><dt>Assigned enforcer</dt><dd>{{ $incident->assignedEnforcer?->name ?? 'Unassigned' }}</dd></div>
                </dl>
                <div class="event-description"><h3>Event description</h3><p>{{ $incident->event_description ?: 'No additional event description was recorded.' }}</p></div>
            </article>

            <article class="dashboard-panel review-history-panel">
                <header class="panel-heading"><div><h2>Review history</h2><p>Incident activity and personnel actions</p></div></header>
                @if ($incident->events->isNotEmpty())
                    <ol class="incident-timeline">
                        @foreach ($incident->events as $event)
                            <li>
                                <span class="timeline-dot" aria-hidden="true"></span>
                                <div><strong>{{ $event->title }}</strong>@if ($event->description)<p>{{ $event->description }}</p>@endif<small>{{ $event->user?->name ?? 'System' }} · {{ $event->occurred_at->copy()->setTimezone($displayTimezone)->format('F j, Y | g:i A') }}</small></div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="history-empty">No review activity has been recorded yet.</p>
                @endif
                @if ($incident->reviewed_at)
                    <p class="reviewed-by">Last reviewed by {{ $incident->reviewer?->name ?? 'an authorized user' }} on {{ $incident->reviewed_at->copy()->setTimezone($displayTimezone)->format('M j, Y | g:i A') }}.</p>
                @endif
            </article>
        </div>
    </section>
</div>
@if ($incident->evidence_path)
    @include('violations.partials.evidence-viewer')
@endif
@endsection

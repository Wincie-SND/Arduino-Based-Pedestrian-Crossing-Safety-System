@extends('layouts.dashboard')
@section('title', 'Notifications')
@section('content')
    <header class="dashboard-intro">
        <h1>Notifications</h1>
    </header>

    <section class="dashboard-panel notifications-panel" aria-label="Crossing notifications">
        <div class="recent-notification-list">
            @forelse ($notifications as $notification)
                @php($occurredAt = $notification->occurred_at->copy()->setTimezone(config()->string('app.display_timezone')))
                <article class="recent-notification">
                    <span @class(['notification-indicator', 'notification-alert' => $notification->level === 'alert', 'notification-warning' => $notification->level === 'warning', 'notification-info' => $notification->level === 'info']) aria-hidden="true"></span>
                    <div>
                        <strong>{{ $notification->title }}</strong>
                        @if ($notification->message)
                            <span>{{ $notification->message }}</span>
                        @endif
                        <small><time datetime="{{ $occurredAt->toIso8601String() }}">{{ $occurredAt->format('M j, Y, g:i A') }}</time></small>
                    </div>
                </article>
            @empty
                <p class="notification-empty">No notifications to display.</p>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <footer class="report-table-footer">
                @if ($notifications->firstItem())
                    <span>Showing {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} of {{ $notifications->total() }} notifications</span>
                @endif
                <nav class="report-pagination" aria-label="Notification pages">
                    @if ($notifications->onFirstPage())
                        <span class="page-control disabled">Previous</span>
                    @else
                        <a class="page-control" href="{{ $notifications->previousPageUrl() }}" rel="prev">Previous</a>
                    @endif
                    @if ($notifications->hasMorePages())
                        <a class="page-control" href="{{ $notifications->nextPageUrl() }}" rel="next">Next</a>
                    @else
                        <span class="page-control disabled">Next</span>
                    @endif
                </nav>
            </footer>
        @endif
    </section>
@endsection

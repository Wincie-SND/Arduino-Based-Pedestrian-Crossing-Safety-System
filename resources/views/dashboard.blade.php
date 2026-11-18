@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<header class="app-heading"><h1>Operations dashboard</h1><p>Welcome back, {{ auth()->user()->name }}.</p></header>
<div class="content-column">
    @if ($currentTeam)
        <section class="card"><h2 class="section-title">Current team</h2><p class="muted">{{ $currentTeam->name }}</p><a class="button small" href="{{ route('teams.edit', ['team' => $currentTeam->slug]) }}">View team</a></section>
    @endif
    <section class="card">
        <h2 class="section-title">Pending team invitations</h2>
        @forelse ($pendingInvitations as $invitation)
            <div class="row-actions" style="justify-content:space-between;padding:.8rem 0;border-bottom:1px solid #e5e9ec">
                <div><strong>{{ $invitation['team']['name'] }}</strong><div class="muted">Invited by {{ $invitation['inviterName'] }}</div></div>
                <div class="row-actions">
                    <form method="post" action="{{ route('invitations.accept', ['invitation' => $invitation['code']]) }}">@csrf<button class="button small" type="submit">Accept</button></form>
                    <form method="post" action="{{ route('invitations.decline', ['invitation' => $invitation['code']]) }}">@csrf @method('delete')<button class="button secondary small" type="submit">Decline</button></form>
                </div>
            </div>
        @empty
            <p class="muted">There are no pending invitations.</p>
        @endforelse
    </section>
</div>
@endsection

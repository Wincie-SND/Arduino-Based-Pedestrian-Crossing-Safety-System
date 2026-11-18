@extends('layouts.app')
@section('title', 'Teams')
@section('content')
<header class="app-heading"><h1>Teams</h1><p>Manage your teams and memberships.</p></header>
<div class="settings-grid">
    @include('settings.partials.navigation', ['active' => 'teams'])
    <div class="content-column">
        @can('create', App\Models\Team::class)
            <section class="card"><h2 class="section-title">Create a team</h2><form method="post" action="{{ route('teams.store') }}" class="row-actions">@csrf<div class="field" style="flex:1;margin:0"><label for="team-name">Team name</label><input id="team-name" name="name" value="{{ old('name') }}" required>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div><button class="button" type="submit">Create team</button></form></section>
        @endcan
        <section class="card">
            <h2 class="section-title">Your teams</h2>
            @forelse ($teams as $team)
                <div class="row-actions" style="justify-content:space-between;padding:.8rem 0;border-bottom:1px solid #e5e9ec">
                    <div><strong>{{ $team->name }}</strong> @if ($team->isPersonal)<span class="badge">Personal</span>@endif @if ($team->isCurrent)<span class="badge">Current</span>@endif<div class="muted">{{ $team->roleLabel }}</div></div>
                    <div class="row-actions">
                        @if (! $team->isPersonal && $team->role !== 'owner')
                            <form method="post" action="{{ route('teams.leave', ['team' => $team->slug]) }}" onsubmit="return confirm('Leave {{ addslashes($team->name) }}?')">@csrf @method('delete')<button class="button secondary small" type="submit">Leave</button></form>
                        @endif
                        <a class="button secondary small" href="{{ route('teams.edit', ['team' => $team->slug]) }}">{{ $team->role === 'member' ? 'View' : 'Manage' }}</a>
                        @if (! $team->isCurrent)<form method="post" action="{{ route('teams.switch', ['team' => $team->slug]) }}">@csrf<button class="button small" type="submit">Switch to team</button></form>@endif
                    </div>
                </div>
            @empty
                <p class="muted">You don’t belong to any teams yet.</p>
            @endforelse
        </section>
    </div>
</div>
@endsection

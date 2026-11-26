@extends('layouts.app')
@section('title', 'Welcome')
@section('content')
<section class="welcome">
    <div class="welcome-content">
        <div class="crossguard-brand" style="color:#07152a">TOMECO<strong><em>Cross</em> Guard</strong></div>
        <h1>CrossGuard</h1>
        <p>Safer pedestrian crossings through real-time monitoring and evidence-based incident management.</p>
        <div class="welcome-actions">
            @auth
                @if (auth()->user()->currentTeam)<a class="button" href="{{ route('dashboard', ['current_team' => auth()->user()->currentTeam->slug]) }}">Open dashboard</a>@endif
                <a class="button secondary" href="{{ route('teams.index') }}">Manage teams</a>
            @else
                <a class="button" href="{{ route('login') }}">Log in</a>
            @endauth
        </div>
    </div>
</section>
@endsection

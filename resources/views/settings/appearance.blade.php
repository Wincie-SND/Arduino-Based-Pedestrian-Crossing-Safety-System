@extends('layouts.dashboard')
@section('title', 'Appearance settings')
@section('content')
<header class="dashboard-intro settings-intro"><div><p class="eyebrow">Account settings</p><h1>Appearance</h1><p class="dashboard-summary">Choose how CrossGuard looks on this device.</p></div></header>
<div class="settings-grid settings-dashboard-grid">
    @include('settings.partials.navigation', ['active' => 'appearance'])
    <section class="dashboard-panel settings-panel">
        <h2 class="section-title">Theme</h2><p class="muted">Your choice is saved in a browser cookie.</p>
        <form method="post" action="{{ route('appearance.update') }}" class="stack">
            @csrf
            @foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'Use system setting'] as $value => $label)
                <label class="checkbox-line"><input type="radio" name="appearance" value="{{ $value }}" @checked(($appearance ?? 'system') === $value)> {{ $label }}</label>
            @endforeach
            <div><button class="button" type="submit">Save appearance</button></div>
        </form>
    </section>
</div>
@endsection

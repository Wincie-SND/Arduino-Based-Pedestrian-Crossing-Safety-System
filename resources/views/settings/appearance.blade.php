@extends('layouts.app')
@section('title', 'Appearance settings')
@section('content')
<header class="app-heading"><h1>Appearance settings</h1><p>Choose how CrossGuard looks on this device.</p></header>
<div class="settings-grid">
    @include('settings.partials.navigation', ['active' => 'appearance'])
    <section class="card">
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

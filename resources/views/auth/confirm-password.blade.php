@extends('layouts.dashboard')
@section('title', 'Confirm password')
@section('content')
<header class="dashboard-intro settings-intro">
    <div><p class="eyebrow">Account settings</p><h1>Confirm password</h1><p class="dashboard-summary">Verify your identity before opening security settings.</p></div>
</header>
<div class="settings-grid settings-dashboard-grid">
    @include('settings.partials.navigation', ['active' => 'security'])
    <section class="dashboard-panel settings-panel">
        <h2 class="section-title">Security verification</h2>
        <p class="muted">Enter your current password to continue to this secure area.</p>
        @if ($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('password.confirm.store') }}" class="password-confirm-form">
            @csrf
            <div class="field"><label for="password">Current password</label><input id="password" name="password" type="password" required autofocus autocomplete="current-password">@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
            <button class="button" type="submit">Continue</button>
        </form>
    </section>
</div>
@endsection

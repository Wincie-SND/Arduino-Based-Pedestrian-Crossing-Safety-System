@extends('layouts.dashboard')
@section('title', 'Security settings')
@section('content')
<header class="dashboard-intro settings-intro"><div><p class="eyebrow">Account settings</p><h1>Security</h1><p class="dashboard-summary">Change your account password.</p></div></header>
<div class="settings-grid settings-dashboard-grid">
    @include('settings.partials.navigation', ['active' => 'security'])
    <section class="dashboard-panel settings-panel">
        <h2 class="section-title">Update password</h2><p class="muted">Use a long, unique password to protect your account.</p>
        @if ($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('user-password.update') }}">
            @csrf @method('put')
            <div class="field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>@error('current_password')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required>@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
            <button class="button" type="submit">Save password</button>
        </form>
    </section>
</div>
@endsection

@extends('layouts.dashboard')
@section('title', 'Profile settings')
@section('content')
<header class="dashboard-intro settings-intro"><div><p class="eyebrow">Account settings</p><h1>Profile</h1><p class="dashboard-summary">Update your name and email address.</p></div></header>
<div class="settings-grid settings-dashboard-grid">
    @include('settings.partials.navigation', ['active' => 'profile'])
    <div class="content-column">
        <section class="dashboard-panel settings-panel">
            <h2 class="section-title">Profile information</h2><p class="muted">Manage the name and email address associated with your account.</p>
            @if ($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
            <form method="post" action="{{ route('profile.update') }}">
                @csrf @method('patch')
                <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required autocomplete="name">@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required autocomplete="email">@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
                @if ($mustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                    <p class="muted">Your email address is unverified. <a href="{{ route('verification.notice') }}">Resend the verification email</a>.</p>
                    @if ($status === 'verification-link-sent')<div class="notice">A new verification link has been sent to your email address.</div>@endif
                @endif
                <button class="button" type="submit">Save profile</button>
            </form>
        </section>
        <section class="dashboard-panel settings-panel">
            <h2 class="section-title">Delete account</h2><p class="muted">Permanently remove your account and its associated information.</p>
            <form method="post" action="{{ route('profile.destroy') }}" onsubmit="return confirm('Delete your account permanently?')">
                @csrf @method('delete')
                <div class="field"><label for="delete-password">Confirm with your password</label><input id="delete-password" name="password" type="password" required autocomplete="current-password">@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
                <button class="button danger" type="submit">Delete account</button>
            </form>
        </section>
    </div>
</div>
@endsection

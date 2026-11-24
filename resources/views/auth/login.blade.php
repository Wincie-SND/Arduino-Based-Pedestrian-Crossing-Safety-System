@extends('layouts.auth')

@section('title', 'Log in')

@section('content')
<main class="login-page">
    <div class="login-shell">
        <section class="login-brand-panel">
            <a class="crossguard-brand" href="{{ route('home') }}" aria-label="TOMECO CrossGuard home">
                TOMECO<strong><em>Cross</em> Guard</strong>
            </a>
            <div class="login-message">
                <h1>Safer crossings.<br>Smarter enforcement.</h1>
                <p>Real-time pedestrian crossing monitoring and evidence-based incident management for authorized TOMECO personnel.</p>
            </div>
            <div class="monitor-site">
                <span class="pin" aria-hidden="true">⌖</span>
                <div><small>Designated monitoring site</small><strong>LNU Youngfield Pedestrian Crossing</strong></div>
            </div>
        </section>
        <section class="login-form-panel">
            <div class="login-form">
                @if ($teamInvitation)
                    <div class="notice">Sign in to join the {{ $teamInvitation['teamName'] }} team.</div>
                @endif
                <header>
                    <h2>Welcome back</h2>
                    <p>Sign in to access CrossGuard operations dashboard.</p>
                </header>
                @if (session('status'))<div class="notice">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif

                <form method="post" action="{{ route('login.store') }}">
                    @csrf
                    @if ($teamInvitation)<input type="hidden" name="invitation" value="{{ $teamInvitation['code'] }}">@endif
                    <div class="field">
                        <label for="email">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="email@example.com">
                        @error('email')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <div class="field-top"><label for="password">Password</label>@if ($canResetPassword)<a href="{{ route('password.request') }}">Forgot password?</a>@endif</div>
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter password">
                        @error('password')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <label class="checkbox-line"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Remember me</label>
                    <div class="login-actions">
                        <button class="button" type="submit">Sign In</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>
@endsection

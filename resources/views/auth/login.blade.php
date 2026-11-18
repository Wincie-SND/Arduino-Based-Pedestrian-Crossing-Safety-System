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

                @if ($canUsePasskey)
                    <button class="button secondary" id="passkey-login" type="button" style="width:100%;margin-bottom:1.25rem">Sign in with a passkey</button>
                    <p id="passkey-login-error" class="field-error" hidden></p>
                @endif

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
@if ($canUsePasskey)
    <script type="module">
        import { Passkeys } from '/js/passkeys/index.js';
        const button = document.getElementById('passkey-login');
        const error = document.getElementById('passkey-login-error');
        button.addEventListener('click', async () => {
            button.disabled = true;
            error.hidden = true;
            try {
                const result = await Passkeys.verify({ routes: { options: @json(route('passkey.login-options')), submit: @json(route('passkey.login')) } });
                if (result?.redirect) window.location.assign(result.redirect);
                else window.location.reload();
            } catch (exception) {
                error.textContent = exception instanceof Error ? exception.message : 'Passkey sign-in failed.';
                error.hidden = false;
            } finally { button.disabled = false; }
        });
    </script>
@endif
@endsection

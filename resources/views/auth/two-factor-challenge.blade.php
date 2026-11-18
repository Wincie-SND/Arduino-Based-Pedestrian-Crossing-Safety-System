@extends('layouts.auth')
@section('title', 'Two-factor authentication')
@section('content')
<main class="auth-page"><section class="auth-card">
    <h1 id="challenge-title">Authentication code</h1><p id="challenge-description">Enter the authentication code provided by your authenticator application.</p>
    @if ($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
    <form id="two-factor-form" method="post" action="{{ route('two-factor.login.store') }}">
        @csrf
        <div class="field" id="totp-field"><label for="code">Authentication code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus></div>
        <div class="field" id="recovery-field" hidden><label for="recovery_code">Recovery code</label><input id="recovery_code" name="recovery_code" autocomplete="one-time-code" disabled></div>
        <button class="button" type="submit" style="width:100%">Continue</button>
    </form>
    <p class="auth-links"><button class="button secondary" id="toggle-challenge" type="button">Login using a recovery code</button></p>
</section></main>
<script>
    const toggle = document.getElementById('toggle-challenge');
    const totp = document.getElementById('totp-field');
    const recovery = document.getElementById('recovery-field');
    const code = document.getElementById('code');
    const recoveryCode = document.getElementById('recovery_code');
    toggle.addEventListener('click', () => {
        const recoveryMode = recovery.hidden;
        recovery.hidden = !recoveryMode; recoveryCode.disabled = !recoveryMode;
        totp.hidden = recoveryMode; code.disabled = recoveryMode;
        document.getElementById('challenge-title').textContent = recoveryMode ? 'Recovery code' : 'Authentication code';
        document.getElementById('challenge-description').textContent = recoveryMode ? 'Enter one of your emergency recovery codes.' : 'Enter the authentication code provided by your authenticator application.';
        toggle.textContent = recoveryMode ? 'Login using an authentication code' : 'Login using a recovery code';
    });
</script>
@endsection

@extends('layouts.dashboard')
@section('title', 'Security settings')
@section('content')
<header class="dashboard-intro settings-intro"><div><p class="eyebrow">Account settings</p><h1>Security</h1><p class="dashboard-summary">Manage your password and sign-in protection.</p></div></header>
<div class="settings-grid settings-dashboard-grid">
    @include('settings.partials.navigation', ['active' => 'security'])
    <div class="content-column">
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

        @if ($canManageTwoFactor)
            <section class="dashboard-panel settings-panel">
                <h2 class="section-title">Two-factor authentication</h2>
                <p class="muted">Add an extra sign-in check with an authenticator app. Save your recovery codes somewhere safe.</p>
                @if ($twoFactorEnabled)
                    <p class="notice">Two-factor authentication is enabled.</p>
                    <form method="post" action="{{ route('two-factor.disable') }}" onsubmit="return confirm('Disable two-factor authentication?')">@csrf @method('delete')<button class="button danger" type="submit">Disable 2FA</button></form>
                    <details style="margin-top:1.2rem" open>
                        <summary>Recovery codes</summary>
                        @if ($recoveryCodes)
                            <ul class="stack" style="font-family:monospace">@foreach ($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach</ul>
                        @endif
                        <form method="post" action="{{ route('two-factor.regenerate-recovery-codes') }}" onsubmit="return confirm('Generate new recovery codes? The old codes will stop working.')" style="margin-top:1rem">@csrf<button class="button secondary" type="submit">Regenerate recovery codes</button></form>
                    </details>
                @elseif ($qrCodeSvg)
                    <h3 class="section-title">Finish setting up your authenticator</h3>
                    <p>Scan this QR code with your authenticator app, then enter its six-digit code below.</p>
                    <div class="qr-code">{!! $qrCodeSvg !!}</div>
                    <p class="muted">You can also enter this key manually: <code>{{ \Laravel\Fortify\Fortify::currentEncrypter()->decrypt(auth()->user()->two_factor_secret) }}</code></p>
                    @if ($requiresConfirmation)
                        <form method="post" action="{{ route('two-factor.confirm') }}" style="margin-top:1rem">@csrf
                            <div class="field"><label for="code">Authenticator code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required>@error('code')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <button class="button" type="submit">Confirm and enable</button>
                        </form>
                    @endif
                @else
                    <form method="post" action="{{ route('two-factor.enable') }}">@csrf<button class="button" type="submit">Enable 2FA</button></form>
                @endif
            </section>
        @endif

        @if ($canManagePasskeys)
            <section class="dashboard-panel settings-panel">
                <h2 class="section-title">Passkeys</h2><p class="muted">Use a device passkey for passwordless sign-in.</p>
                @forelse ($passkeys as $passkey)
                    <div class="row-actions" style="justify-content:space-between;padding:.75rem 0;border-bottom:1px solid #e5e9ec">
                        <div><strong>{{ $passkey['name'] }}</strong><div class="muted">Added {{ $passkey['created_at_diff'] }}@if ($passkey['last_used_at_diff']) · Last used {{ $passkey['last_used_at_diff'] }}@endif</div></div>
                        <form method="post" action="{{ route('passkey.destroy', ['passkey' => $passkey['id']]) }}" onsubmit="return confirm('Remove this passkey?')">@csrf @method('delete')<button class="button secondary small" type="submit">Remove</button></form>
                    </div>
                @empty
                    <p class="muted">No passkeys are registered yet.</p>
                @endforelse
                <form id="passkey-register-form" class="stack" style="margin-top:1rem">
                    <div class="field"><label for="passkey-name">Passkey name</label><input id="passkey-name" required placeholder="e.g. Work laptop"></div>
                    <div class="row-actions"><button class="button" id="passkey-register" type="submit">Add passkey</button><span class="muted" id="passkey-register-status" aria-live="polite"></span></div>
                </form>
                <p class="field-error" id="passkey-register-error" hidden></p>
            </section>
            @push('scripts')
                <script type="module">
                    import { Passkeys } from '/js/passkeys/index.js';
                    const form = document.getElementById('passkey-register-form');
                    const button = document.getElementById('passkey-register');
                    const status = document.getElementById('passkey-register-status');
                    const error = document.getElementById('passkey-register-error');
                    form.addEventListener('submit', async (event) => {
                        event.preventDefault(); button.disabled = true; error.hidden = true; status.textContent = 'Waiting for your device…';
                        try {
                            await Passkeys.register({ name: document.getElementById('passkey-name').value.trim(), routes: { options: @json(route('passkey.registration-options')), submit: @json(route('passkey.store')) } });
                            window.location.reload();
                        } catch (exception) {
                            error.textContent = exception instanceof Error ? exception.message : 'Passkey registration failed.';
                            error.hidden = false; status.textContent = '';
                        } finally { button.disabled = false; }
                    });
                </script>
            @endpush
        @endif
    </div>
</div>
@endsection

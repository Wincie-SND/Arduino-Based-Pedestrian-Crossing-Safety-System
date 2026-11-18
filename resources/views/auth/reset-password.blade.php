@extends('layouts.auth')
@section('title', 'Reset password')
@section('content')
<main class="auth-page"><section class="auth-card">
    <h1>Reset password</h1><p>Please enter your new password below.</p>
    @if ($errors->any())<ul class="errors">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <form method="post" action="{{ route('password.update') }}">
        @csrf<input type="hidden" name="token" value="{{ $token }}">
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" required readonly>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password">@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
        <button class="button" type="submit" style="width:100%">Reset password</button>
    </form>
</section></main>
@endsection

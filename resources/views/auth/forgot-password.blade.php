@extends('layouts.auth')
@section('title', 'Forgot password')
@section('content')
<main class="auth-page"><section class="auth-card">
    <h1>Forgot password</h1><p>Enter your email to receive a password reset link.</p>
    @if (session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="email@example.com">@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
        <button class="button" type="submit" style="width:100%">Email password reset link</button>
    </form>
    <p class="auth-links">Or, return to <a href="{{ route('login') }}">log in</a></p>
</section></main>
@endsection

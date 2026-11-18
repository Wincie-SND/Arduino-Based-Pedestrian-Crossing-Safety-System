@extends('layouts.auth')
@section('title', 'Email verification')
@section('content')
<main class="auth-page"><section class="auth-card">
    <h1>Verify your email</h1><p>Please verify your email address by clicking the link we just emailed to you.</p>
    @if (session('status') === 'verification-link-sent')<div class="notice">A new verification link has been sent to your email address.</div>@endif
    <form method="post" action="{{ route('verification.send') }}">@csrf<button class="button" type="submit" style="width:100%">Resend verification email</button></form>
    <form method="post" action="{{ route('logout') }}" class="auth-links">@csrf<button class="button secondary" type="submit">Log out</button></form>
</section></main>
@endsection

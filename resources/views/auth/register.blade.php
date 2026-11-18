@extends('layouts.auth')
@section('title', 'Create an account')
@section('content')
<main class="auth-page"><section class="auth-card">
    <h1>Create an account</h1><p>Enter your details below to create your account.</p>
    @if ($teamInvitation)<div class="notice">You’re registering to join {{ $teamInvitation['teamName'] }}.</div>@endif
    @if ($errors->any())<ul class="errors">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <form method="post" action="{{ route('register.store') }}">
        @csrf
        <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password">@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
        <button class="button" type="submit" style="width:100%">Create account</button>
    </form>
    <p class="auth-links">Already have an account? <a href="{{ route('login', $teamInvitation ? ['invitation' => $teamInvitation['code']] : []) }}">Log in</a></p>
</section></main>
@endsection

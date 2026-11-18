@extends('layouts.auth')
@section('title', 'Create an account')
@section('content')
<main class="auth-page"><section class="auth-card">
    <h1>Create an account</h1>
    @if ($teamInvitation)
        @if ($teamInvitation['canRegister'])
            <p>Accept your invitation to join {{ $teamInvitation['teamName'] }}. Create an account with your invited email address, then verify it to access CrossGuard.</p>
            @if ($errors->any())<ul class="errors">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            <form method="post" action="{{ route('register.store') }}">
                @csrf
                <input type="hidden" name="invitation" value="{{ $teamInvitation['code'] }}">
                <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="email">Invited email address</label><input id="email" name="email" type="email" value="{{ old('email', $teamInvitation['email']) }}" required autocomplete="email">@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password">@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
                <button class="button" type="submit" style="width:100%">Create account</button>
            </form>
        @else
            <div class="notice">An account already exists for {{ $teamInvitation['email'] }}. Sign in to review the invitation.</div>
        @endif
    @else
        <p>You need an active team invitation to create an account. Ask a team administrator to invite your email address.</p>
    @endif
    <p class="auth-links">Already have an account? <a href="{{ route('login', $teamInvitation ? ['invitation' => $teamInvitation['code']] : []) }}">Log in</a></p>
</section></main>
@endsection

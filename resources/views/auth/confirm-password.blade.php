@extends('layouts.auth')
@section('title', 'Confirm password')
@section('content')
<main class="auth-page"><section class="auth-card">
    <h1>Confirm password</h1><p>This is a secure area. Please confirm your password before continuing.</p>
    @if ($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('password.confirm.store') }}">
        @csrf
        <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autofocus autocomplete="current-password">@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
        <button class="button" type="submit" style="width:100%">Confirm password</button>
    </form>
</section></main>
@endsection

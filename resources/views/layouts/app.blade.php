<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'CrossGuard')) · TOMECO CrossGuard</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" type="image/x-icon" sizes="16x16 32x32 48x48 64x64">
    <link rel="stylesheet" href="/css/app.css">
    <script>
        (() => {
            const appearance = @json($appearance ?? 'system');
            if (appearance === 'system' && matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    <header class="app-header">
        <a class="app-brand" href="{{ route('home') }}">TOMECO <span>Cross</span>Guard</a>
        @auth
            @php($currentTeam = auth()->user()->currentTeam)
            <nav class="app-nav" aria-label="Main navigation">
                @if ($currentTeam)
                    <a href="{{ route('dashboard', ['current_team' => $currentTeam->slug]) }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
                @endif
                <a href="{{ route('profile.edit') }}" @class(['active' => request()->routeIs('profile.*')])>Profile</a>
                <a href="{{ route('security.edit') }}" @class(['active' => request()->routeIs('security.edit')])>Security</a>
            </nav>
            <div class="app-user">
                <span>{{ auth()->user()->name }}</span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="button secondary small" type="submit">Log out</button></form>
            </div>
        @else
            <nav class="app-nav" aria-label="Account navigation">
                <a href="{{ route('login') }}">Log in</a>
            </nav>
        @endauth
    </header>
    <main class="app-main">
        @if (session('toast'))<div class="notice">{{ is_array(session('toast')) ? (session('toast')['message'] ?? '') : session('toast') }}</div>@endif
        @if (session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="notice error" role="alert">Please check the highlighted fields and try again.</div>
        @endif
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>

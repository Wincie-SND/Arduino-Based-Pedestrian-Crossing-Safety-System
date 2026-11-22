<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · TOMECO CrossGuard</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script>
        (() => {
            const appearance = @json($appearance ?? 'system');
            if (appearance === 'system' && matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <aside class="dashboard-sidebar">
            <a class="dashboard-brand" href="{{ route('home') }}" aria-label="TOMECO CrossGuard home">
                <span>TOMECO</span>
                <span><em>Cross</em>Guard</span>
            </a>

            <div class="sidebar-divider"></div>
            <p class="sidebar-label">Workspace</p>
            <nav class="dashboard-nav" aria-label="Dashboard navigation">
                @if (auth()->user()->currentTeam)
                    <a @class(['dashboard-nav-link', 'active' => request()->routeIs('dashboard')]) href="{{ route('dashboard', ['current_team' => request()->route('current_team')]) }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                        <span>Dashboard</span>
                    </a>
                    <a @class(['dashboard-nav-link', 'active' => request()->routeIs('violations.*')]) href="{{ route('violations.index', ['current_team' => request()->route('current_team')]) }}" @if (request()->routeIs('violations.*')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3.75h10a2 2 0 0 1 2 2v14.5l-2.3-1.5-2.2 1.5-2.5-1.5-2.5 1.5-2.2-1.5L5 20.25V5.75a2 2 0 0 1 2-2Z"/><path d="M8.5 9h7M8.5 13h7M8.5 16.5h3"/></svg>
                        <span>Violation reports</span>
                    </a>
                @endif
                @if (auth()->user()->currentTeam)
                    @can('inviteMember', auth()->user()->currentTeam)
                        <a @class(['dashboard-nav-link', 'active' => request()->routeIs('teams.*')]) href="{{ route('teams.index') }}" @if (request()->routeIs('teams.*')) aria-current="page" @endif>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7" r="3.5"/><path d="M17 11a3.5 3.5 0 1 0-1.5-6.66M17 14.5h.5a4 4 0 0 1 4 4V20"/></svg>
                            <span>User management</span>
                        </a>
                    @endcan
                @endif
                <a @class(['dashboard-nav-link', 'active' => request()->routeIs('profile.*', 'security.*', 'appearance.*', 'password.confirm*')]) href="{{ route('appearance.edit') }}" @if (request()->routeIs('profile.*', 'security.*', 'appearance.*', 'password.confirm*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 14 5l2.5-.2.8 2.4 2.1 1.4-.8 2.4.8 2.4-2.1 1.4-.8 2.4L14 17l-2 1.5L10 17l-2.5.2-.8-2.4-2.1-1.4.8-2.4-.8-2.4 2.1-1.4.8-2.4L10 5l2-1.5Z"/><circle cx="12" cy="11.9" r="3"/></svg>
                    <span>Settings</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-site-mark" aria-hidden="true"><span class="site-pin">⌖</span></div>
                <div class="sidebar-site-copy"><small>Designated monitoring site</small><strong>{{ auth()->user()->currentTeam?->name ?? 'Crossing location' }}</strong></div>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <div class="monitoring-location">
                    <a class="mobile-topbar-brand" href="{{ route('home') }}" aria-label="TOMECO CrossGuard home">
                        <span>TOMECO</span><strong><em>Cross</em>Guard</strong>
                    </a>
                    <span>Monitoring location</span>
                    <strong>{{ auth()->user()->currentTeam?->name ?? 'Location not set' }}</strong>
                </div>
                <div class="dashboard-account">
                    <span class="notification-mark" aria-label="Notifications" title="Notifications">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-2.5 7-2.5 8.5h17C20.5 16 18 16 18 9ZM10 20h4"/></svg>
                    </span>
                    <span class="account-divider" aria-hidden="true"></span>
                    <span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="account-name">{{ auth()->user()->name }}</span>
                    <form method="post" action="{{ route('logout') }}" class="dashboard-logout">@csrf<button type="submit">Log out</button></form>
                </div>
            </header>

            <div class="dashboard-content">
                @if (session('toast'))<div class="notice">{{ is_array(session('toast')) ? (session('toast')['message'] ?? '') : session('toast') }}</div>@endif
                @if (session('status'))<div class="notice">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="notice error" role="alert">Please check the highlighted fields and try again.</div>@endif
                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>

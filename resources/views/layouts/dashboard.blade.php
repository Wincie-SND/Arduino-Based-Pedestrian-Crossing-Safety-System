<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · TOMECO CrossGuard</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" type="image/x-icon" sizes="16x16 32x32 48x48 64x64">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script>
        (() => {
            const appearance = @json($appearance ?? 'system');
            if (appearance === 'system' && matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body class="dashboard-body">
    @php
        $account = auth()->user();
        $accountTeam = $account->currentTeam;
        $displayTimezone = config()->string('app.display_timezone');
        $workspaceTime = now($displayTimezone);
        $accountRoleLabel = $accountRole?->label();
    @endphp
    <div class="dashboard-shell">
        <aside class="dashboard-sidebar">
            <a class="dashboard-brand" href="{{ route('home') }}" aria-label="TOMECO CrossGuard home">
                <img class="dashboard-brand-logo" src="{{ asset('pictures/tomecologo.png') }}" alt="" width="36" height="36">
                <span>TOMECO<br><em>Cross</em>Guard</span>
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
                    @can('addMember', auth()->user()->currentTeam)
                        <a @class(['dashboard-nav-link', 'active' => request()->routeIs('users.*')]) href="{{ route('users.index', ['current_team' => $accountTeam->slug]) }}" @if (request()->routeIs('users.*')) aria-current="page" @endif>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7" r="3.5"/><path d="M17 11a3.5 3.5 0 1 0-1.5-6.66M17 14.5h.5a4 4 0 0 1 4 4V20"/></svg>
                            <span>User management</span>
                        </a>
                    @endcan
                @endif
                <a @class(['dashboard-nav-link', 'active' => request()->routeIs('profile.*', 'security.*', 'password.confirm*')]) href="{{ route('profile.edit') }}" @if (request()->routeIs('profile.*', 'security.*', 'password.confirm*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 14 5l2.5-.2.8 2.4 2.1 1.4-.8 2.4.8 2.4-2.1 1.4-.8 2.4L14 17l-2 1.5L10 17l-2.5.2-.8-2.4-2.1-1.4.8-2.4-.8-2.4 2.1-1.4.8-2.4L10 5l2-1.5Z"/><circle cx="12" cy="11.9" r="3"/></svg>
                    <span>Settings</span>
                </a>
            </nav>

            <x-monitoring-site class="sidebar-footer" :site="$accountTeam?->name" />
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <div class="dashboard-topbar-leading">
                    <a class="mobile-topbar-brand" href="{{ route('home') }}" aria-label="TOMECO CrossGuard home">
                        <img class="dashboard-brand-logo" src="{{ asset('pictures/tomecologo.png') }}" alt="" width="24" height="24">
                        <span>TOMECO <em>Cross</em>Guard</span>
                    </a>
                    <div class="date-chip">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17"/></svg>
                        <div>
                            <small>UTC{{ $workspaceTime->format('P') }}</small>
                            <time datetime="{{ $workspaceTime->format('Y-m-d\TH:i:s.vP') }}" data-dashboard-clock data-time-zone="{{ $displayTimezone }}">
                                <span data-clock-date>{{ $workspaceTime->format('M j, Y') }}</span>
                                <span data-clock-time>{{ $workspaceTime->format('g:i:s A') }}</span>
                            </time>
                        </div>
                    </div>
                </div>
                <div class="dashboard-account">
                    @if ($accountTeam)
                        <details class="header-notifications" id="header-notifications">
                            <summary class="notification-mark" aria-label="Notifications">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-2.5 7-2.5 8.5h17C20.5 16 18 16 18 9ZM10 20h4"/></svg>
                            </summary>
                            <div class="header-notifications-panel">
                                <h2>Recent notifications</h2>
                                <ul class="recent-notification-list header-notifications-list">
                                    @forelse ($headerNotifications as $notification)
                                        <li class="recent-notification">
                                            <span @class(['notification-indicator', 'notification-alert' => $notification->level === 'alert', 'notification-warning' => $notification->level === 'warning', 'notification-info' => $notification->level === 'info']) aria-hidden="true"></span>
                                            <div>
                                                <strong>{{ $notification->title }}</strong>
                                                @if (filled($notification->message))<span>{{ $notification->message }}</span>@endif
                                                <time datetime="{{ $notification->occurred_at->toIso8601String() }}">{{ $notification->occurred_at->diffForHumans() }}</time>
                                            </div>
                                        </li>
                                    @empty
                                        <li class="header-notifications-empty">No notifications yet.</li>
                                    @endforelse
                                </ul>
                                <a class="panel-link header-notifications-more" href="{{ route('notifications.index', ['current_team' => $accountTeam->slug]) }}">See more</a>
                            </div>
                        </details>
                        <span class="account-divider" aria-hidden="true"></span>
                    @endif
                    <div @class(['account-identity', 'account-with-role' => $accountRole !== null])>
                        <span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}</span>
                        <div class="account-details">
                            <span class="account-name" title="{{ $account->name }}">{{ $account->name }}</span>
                            @if ($accountRoleLabel)<span class="account-role">{{ $accountRoleLabel }}</span>@endif
                        </div>
                    </div>
                    <form method="post" action="{{ route('logout') }}" class="dashboard-logout">@csrf<button type="submit">Log out</button></form>
                </div>
            </header>

            <div class="dashboard-content">
                <x-monitoring-site class="mobile-monitoring-site" :site="$accountTeam?->name" />
                @if (session('toast'))<div class="notice">{{ is_array(session('toast')) ? (session('toast')['message'] ?? '') : session('toast') }}</div>@endif
                @if (session('status'))<div class="notice">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="notice error" role="alert">Please check the highlighted fields and try again.</div>@endif
                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
    <script src="{{ asset('js/dashboard-clock.js') }}?v={{ filemtime(public_path('js/dashboard-clock.js')) }}" defer></script>
    <script>
        const notificationsMenu = document.getElementById('header-notifications');
        if (notificationsMenu) {
            notificationsMenu.addEventListener('pointerenter', (event) => {
                if (event.pointerType === 'mouse') notificationsMenu.open = true;
            });
            notificationsMenu.addEventListener('pointerleave', (event) => {
                if (event.pointerType === 'mouse' && !notificationsMenu.contains(document.activeElement)) {
                    notificationsMenu.open = false;
                }
            });
            notificationsMenu.addEventListener('focusout', (event) => {
                if (!notificationsMenu.contains(event.relatedTarget) && !notificationsMenu.matches(':hover')) {
                    notificationsMenu.open = false;
                }
            });
            document.addEventListener('pointerdown', (event) => {
                if (!notificationsMenu.contains(event.target)) notificationsMenu.open = false;
            });
            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape' || !notificationsMenu.open) return;
                if (notificationsMenu.contains(document.activeElement)) notificationsMenu.querySelector('summary').focus();
                notificationsMenu.open = false;
            });
        }

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) window.location.reload();
        });
    </script>
</body>
</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Account') · TOMECO CrossGuard</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/css/app.css">
    <script>
        (() => {
            const appearance = @json($appearance ?? 'system');
            if (appearance === 'system' && matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    @if ($errors->any())
        <div class="sr-only" aria-live="polite">Please correct the errors shown on the form.</div>
    @endif
    @yield('content')
    @stack('scripts')
</body>
</html>

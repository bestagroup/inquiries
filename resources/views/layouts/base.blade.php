<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
<div class="app-shell">
    @include('partials.sidebar')
    <button type="button" class="sidebar-backdrop" data-sidebar-close aria-label="بستن منو"></button>
    <main class="app-main">
        @include('partials.header')
        <div class="app-content">
            @include('partials.alerts')
            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>

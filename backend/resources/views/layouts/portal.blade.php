<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PharmaCare')</title>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..24,400,0,0">
    <link rel="stylesheet" href="{{ asset('css/pharmacare-portal.css') }}">
    @stack('styles')
</head>

<body class="portal-body">
    <div class="portal-shell">
        @include('components.navigation.sidebar')
        <div class="portal-workspace">
            <main class="portal-content">
                @include('components.navigation.breadcrumb')
                @yield('content')
            </main>
        </div>
    </div>
    <script src="{{ asset('js/pharmacare-portal.js') }}"></script>
    @stack('scripts')
</body>

</html>

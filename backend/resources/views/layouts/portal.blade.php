<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PharmaCare')</title>

    <script>
        (() => {
            try {
                window.PC_DARK_MODE = @json((bool) config('pharmacare_v1.features.dark_mode'));
                // Niveau 4 : choix du profil appliqué avant l'affichage (pas d'éclair blanc en mode sombre).
                window.PC_THEME = @json(auth()->user()?->theme_preference);
                const preference = window.PC_DARK_MODE ? (window.PC_THEME || localStorage.getItem('pc-theme') || 'light') : 'light';
                const dark = preference === 'dark' || (preference === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            } catch (_) {}
        })();
        window.PC_I18N = {
            locale: @json(app()->getLocale()),
            texts: @json(app()->getLocale() === 'en' ? __('ui.texts') : [])
        };
    </script>

    @stack('styles')
@include('components.assets')
</head>

<body class="pc-app portal-body">
    <div class="portal-shell">
        @include('components.navigation.sidebar')
        <div class="portal-workspace">
            <main class="portal-content">
                <x-app-page-layout>
                    @unless(trim($__env->yieldContent('hide-breadcrumb')) === '1')
                        @include('components.navigation.breadcrumb')
                    @endunless
                    @yield('content')
                </x-app-page-layout>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>

</html>

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
                if (localStorage.getItem('pc-theme') === 'dark') document.documentElement.dataset.theme = 'dark';
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

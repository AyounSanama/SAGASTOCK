@php($actor = $actor ?? auth()->user())
<header class="app-shell-topbar">
    <button class="sidebar-toggle material-symbols-outlined secondary" type="button" aria-label="Ouvrir ou fermer le menu" aria-expanded="true" aria-controls="pharmacare-sidebar">menu</button>
    <strong class="topbar-context">@yield('page-title', 'PharmaCare')</strong>
    <span class="topbar-spacer"></span>
    <div class="topbar-tools" aria-label="Préférences d’affichage">
        <form class="topbar-locale-switch" method="post" action="{{ route('profile.locale') }}" aria-label="{{ __('ui.language') }}">
            @csrf
            @foreach(['fr' => 'FR', 'en' => 'EN'] as $locale => $label)
                <button class="topbar-locale-option {{ app()->getLocale() === $locale ? 'is-active' : '' }}" type="submit" name="locale" value="{{ $locale }}" aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </form>
        @if(config('pharmacare_v1.features.dark_mode'))
        <button class="topbar-theme-toggle" type="button" data-theme-toggle aria-label="{{ __('ui.enable_dark_mode') }}" title="{{ __('ui.enable_dark_mode') }}">
            <span class="material-symbols-outlined" data-theme-icon aria-hidden="true">dark_mode</span>
        </button>
        @endif
    </div>
    @include('components.notification-center')
    <details class="profile-menu">
        <summary class="topbar-profile" aria-label="Menu utilisateur">
            <span class="profile-avatar">{{ mb_strtoupper(mb_substr($actor?->name ?? 'U', 0, 1)) }}</span>
            <span class="topbar-profile-copy"><strong>{{ $actor?->name }}</strong><small>{{ $actor?->email }}</small></span>
        </summary>
        <div class="profile-menu-panel">
            <a href="{{ route('profile.show') }}">{{ __('Mon profil') }}</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">{{ __('Déconnexion') }}</button></form>
        </div>
    </details>
</header>

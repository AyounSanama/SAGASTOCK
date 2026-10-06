@php($actor = $actor ?? auth()->user())
@php($context = $actor ? app(\App\Services\ApplicationNavigationService::class)->context($actor) : [])
@php($trail = array_values(array_filter([$context['organization'] ?? null, $context['coordination'] ?? null, $context['project'] ?? null, $context['facility'] ?? null])))
@php($nameParts = preg_split('/\s+/', trim((string) $actor?->name)) ?: [])
@php($initials = mb_strtoupper(mb_substr($nameParts[0] ?? 'U', 0, 1).(count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : '')))
<header class="app-shell-topbar">
    <button class="sidebar-toggle material-symbols-outlined secondary" type="button" aria-label="Ouvrir ou fermer le menu" aria-expanded="true" aria-controls="pharmacare-sidebar">menu</button>
    <div class="topbar-heading">
        <strong class="topbar-context">@yield('page-title', 'PharmaCare')</strong>
        @if($trail)<nav class="topbar-breadcrumb" aria-label="{{ __('Périmètre') }}">@foreach($trail as $crumb)<span>{{ $crumb }}</span>@endforeach</nav>@endif
    </div>
    <span class="topbar-spacer"></span>
    {{-- Niveau 3 (maquettes Coordination) : dernière synchronisation des FOSA, fournie par la page. --}}
    @php($syncLabel = isset($syncedAt) ? ($syncedAt instanceof \Carbon\CarbonInterface ? __('Synchronisé il y a :delay', ['delay' => $syncedAt->locale(app()->getLocale())->diffForHumans(['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'short' => true])]) : __('Aucune synchronisation')) : null)
    <span class="topbar-connection" data-connection-status @if($syncLabel) data-online-label="{{ $syncLabel }}" title="{{ __('Dernière synchronisation d’une FOSA de votre coordination') }}" @endif role="status" aria-live="polite"><span class="topbar-connection-dot" aria-hidden="true"></span><span data-connection-label>{{ $syncLabel ?? __('En ligne') }}</span></span>
    @if($actor?->read_only)<span class="app-badge app-badge--info" title="Vous consultez les informations sans pouvoir les modifier."><span class="material-symbols-outlined" aria-hidden="true">visibility</span>Lecture seule</span>@endif
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
            <span class="profile-avatar">{{ $initials }}</span>
            <span class="topbar-profile-copy"><strong>{{ $actor?->name }}</strong><small>{{ $actor?->email }}</small></span>
        </summary>
        <div class="profile-menu-panel">
            <a href="{{ route('profile.show') }}">{{ __('Mon profil') }}</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">{{ __('Déconnexion') }}</button></form>
        </div>
    </details>
</header>

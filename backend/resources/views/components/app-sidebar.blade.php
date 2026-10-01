@php
    $actor = auth()->user();
    $navigationItems = $actor ? app(\App\Services\ApplicationNavigationService::class)->items($actor) : [];
    $materialIcons = config('pharmacare_ui.module_icons', []);
    $activeNavigationItem = collect($navigationItems)->first(fn ($item) => $item['route'] && (request()->routeIs($item['route']) || request()->routeIs($item['route'].'*')));
    $isSagoAdmin = $actor && app(\App\Services\GovernanceService::class)->roleCode($actor) === \App\Services\GovernanceService::SAGO_ADMIN;
    $sagoConfigurationOpen = request()->routeIs('configuration.*') || request()->routeIs('organizations.*');
    $sagoStandardsOpen = request()->routeIs('configuration.platform-standards.*');
    $standardsTab = request()->routeIs('configuration.platform-standards.history.*') ? 'history' : request('tab', 'assistance');
@endphp
@include('components.assets')




<aside id="pharmacare-sidebar" class="app-sidebar" aria-label="Navigation principale">
    <a class="app-brand" href="{{ $isSagoAdmin ? route('sago.dashboard') : route('dashboard') }}">
        <img src="/images/pharmacare-logo.png"
             alt="Logo PharmaCare" width="48" height="48">
        <span class="app-brand-copy">
            <strong><span class="brand-pharma">Pharma</span><span class="brand-care">Care</span></strong>
            <small>{{ __('ui.tagline') }}</small>
        </span>
    </a>

    <div class="app-sidebar-scroll">
        <nav class="permission-navigation">
            @if($isSagoAdmin)
                <a class="{{ request()->routeIs('sago.dashboard') ? 'active' : '' }}" href="{{ route('sago.dashboard') }}">
                    <span class="nav-icon material-symbols-outlined">dashboard</span><span class="nav-text">{{ __('Tableau de bord') }}</span>
                </a>
                <div class="nav-group {{ $sagoConfigurationOpen ? 'open' : '' }}" data-nav-group>
                    <button class="nav-group-toggle {{ $sagoConfigurationOpen ? 'active' : '' }}" type="button" aria-expanded="{{ $sagoConfigurationOpen ? 'true' : 'false' }}" aria-controls="sago-configuration-menu">
                        <span class="nav-icon material-symbols-outlined">settings</span><span class="nav-text">{{ __('Configuration') }}</span><span class="nav-group-chevron material-symbols-outlined">expand_more</span>
                    </button>
                    <div id="sago-configuration-menu" class="nav-group-items">
                        @if($actor->hasPermission('organizations.view'))
                            <a class="{{ request()->routeIs('configuration.organization') ? 'active' : '' }}" href="{{ route('configuration.organization') }}"><span class="nav-icon material-symbols-outlined">domain</span><span class="nav-text">{{ __('Organisations') }}</span></a>
                        @endif
                        @if($actor->hasPermission('platform_standards.view'))
                        <div class="nav-group {{ $sagoStandardsOpen ? 'open' : '' }}" data-nav-group>
                            <button class="nav-group-toggle {{ $sagoStandardsOpen ? 'active' : '' }}" type="button" aria-expanded="{{ $sagoStandardsOpen ? 'true' : 'false' }}" aria-controls="sago-standards-menu">
                                <span class="nav-icon material-symbols-outlined">workspace_premium</span><span class="nav-text">{{ __('Standards & Référentiels') }}</span><span class="nav-group-chevron material-symbols-outlined">expand_more</span>
                            </button>
                            <div id="sago-standards-menu" class="nav-group-items">
                                <a class="{{ $sagoStandardsOpen && $standardsTab === 'assistance' ? 'active' : '' }}" href="{{ route('configuration.platform-standards.index', ['tab' => 'assistance']) }}"><span class="nav-icon material-symbols-outlined">support_agent</span><span class="nav-text">{{ __('Assistance aux organisations') }}</span></a>
                                <a class="{{ $sagoStandardsOpen && $standardsTab === 'history' ? 'active' : '' }}" href="{{ route('configuration.platform-standards.index', ['tab' => 'history']) }}"><span class="nav-icon material-symbols-outlined">history</span><span class="nav-text">{{ __('Historique') }}</span></a>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            @else
                @foreach ($navigationItems as $item)
                    @continue(!$item['url'] || $item['key'] === 'profile' || ! ($item['menu'] ?? true))
                    @php($active = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '*') || ($item['key'] === 'projects' && request()->routeIs('modules.funding')) || (! empty($item['active_routes']) && request()->routeIs(...$item['active_routes'])))
                    <a class="{{ $active ? 'active' : '' }}" href="{{ $item['url'] }}">
                        <span class="nav-icon material-symbols-outlined">{{ $materialIcons[$item['icon']] ?? 'circle' }}</span>
                        <span class="nav-text">{{ __($item['label']) }}</span>
                    </a>
                @endforeach
            @endif
        </nav>
    </div>

    @php($sidebarContext = $actor ? app(\App\Services\ApplicationNavigationService::class)->context($actor) : [])
    @if(! $isSagoAdmin && ! empty($sidebarContext['coordination']))
    <div class="app-sidebar-context">
        <small>{{ __('Coordination') }}</small>
        <strong>{{ $sidebarContext['coordination'] }}</strong>
        @if(! empty($sidebarContext['country']))<span>{{ $sidebarContext['country'] }}</span>@endif
    </div>
    @endif
    <div class="app-account">
        <a class="app-account-profile {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show') }}"><span class="material-symbols-outlined">person</span><span class="nav-text">{{ __('Mon profil') }}</span></a>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="logout-button" aria-label="{{ __('Déconnexion') }}"><span class="material-symbols-outlined">logout</span><span class="nav-text">{{ __('Déconnexion') }}</span></button>
        </form>
    </div>
</aside>

@include('components.navigation.topbar', ['actor' => $actor])

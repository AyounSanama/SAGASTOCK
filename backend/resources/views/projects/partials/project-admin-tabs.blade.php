{{-- AM-161 — « Projet & FOSA » de l'Admin Projet : FOSA et Comptes utilisateurs
     sont des onglets (routes conservées, entrées retirées du menu latéral). --}}
@php($tab = $activeTab ?? 'project')
<nav class="project-config-tabs project-admin-tabs" aria-label="Projet et formations sanitaires">
    <a class="{{ $tab === 'project' ? 'active' : '' }}" href="{{ route('modules.projects') }}" @if($tab === 'project') aria-current="page" @endif>
        <span class="material-symbols-outlined" aria-hidden="true">work</span>{{ __('Projet') }}
    </a>
    <a class="{{ $tab === 'facilities' ? 'active' : '' }}" href="{{ route('modules.health-facilities') }}" @if($tab === 'facilities') aria-current="page" @endif>
        <span class="material-symbols-outlined" aria-hidden="true">local_hospital</span>{{ __('FOSA') }}
    </a>
    <a class="{{ $tab === 'users' ? 'active' : '' }}" href="{{ route('users.index') }}" @if($tab === 'users') aria-current="page" @endif>
        <span class="material-symbols-outlined" aria-hidden="true">group</span>{{ __('Comptes utilisateurs') }}
    </a>
</nav>

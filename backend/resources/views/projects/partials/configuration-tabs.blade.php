@php
    $projectTab = $activeTab ?? 'projects';
    // Niveau 2 : la Coordination voit « Référentiels » (Bailleurs, Référentiel
    // médical) ; Projets et Programmes passent par « Ma Coordination » et
    // l'assistant « Créer un projet / programme » (onglets masqués, routes conservées).
    $referentialsOnly = auth()->user()
        && app(\App\Services\GovernanceService::class)->roleCode(auth()->user()) === \App\Services\GovernanceService::COORDINATION_ADMIN;
    $tabs = array_filter([
        'projects' => $referentialsOnly ? null : ['route' => route('modules.projects'), 'icon' => 'work', 'label' => 'Projets'],
        'donors' => ['route' => route('modules.funding', ['section' => 'donors']), 'icon' => 'account_balance', 'label' => 'Bailleurs'],
        'programs' => $referentialsOnly ? null : ['route' => route('modules.funding', ['section' => 'programs']), 'icon' => 'folder_open', 'label' => 'Programmes'],
        'medical' => ['route' => route('projects.medical-references'), 'icon' => 'account_tree', 'label' => 'Référentiel médical'],
    ]);
@endphp
<nav class="project-config-tabs" aria-label="{{ $referentialsOnly ? 'Référentiels' : 'Configuration des projets' }}" style="--tab-count:{{ count($tabs) }}">
    @foreach($tabs as $key => $tab)
    <a class="{{ $projectTab === $key ? 'active' : '' }}" href="{{ $tab['route'] }}" @if($projectTab === $key) aria-current="page" @endif>
        <span class="material-symbols-outlined">{{ $tab['icon'] }}</span>
        {{ $tab['label'] }}
    </a>
    @endforeach
</nav>

@once
    @push('styles')
        <style>
            .project-config-tabs{display:grid;grid-template-columns:repeat(var(--tab-count,4),minmax(0,1fr));gap:6px;margin:0 0 20px;padding:5px;border:1px solid var(--pc-color-border);border-radius:14px;background:#fff;box-shadow:var(--pc-shadow-card)}
            .project-config-tabs a{min-height:44px;display:flex;align-items:center;justify-content:center;gap:8px;border:1px solid transparent;border-radius:10px;color:var(--pc-color-text-muted);font-size:13px;font-weight:700;transition:background .15s,border-color .15s,color .15s}
            .project-config-tabs a:hover{background:var(--pc-color-background);color:var(--pc-color-text)}
            .project-config-tabs a.active{border-color:var(--pc-color-primary);background:var(--pc-color-primary-soft);color:var(--pc-color-primary-strong)}
            .project-config-tabs .material-symbols-outlined{font-size:19px}
            @media(max-width:600px){.project-config-tabs{overflow-x:auto;grid-template-columns:repeat(var(--tab-count,4),minmax(132px,1fr));scrollbar-width:none}.project-config-tabs::-webkit-scrollbar{display:none}.project-config-tabs a{min-height:42px;font-size:12px}}
        </style>
    @endpush
@endonce

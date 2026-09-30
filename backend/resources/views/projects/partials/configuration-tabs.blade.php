@php
    $projectTab = $activeTab ?? 'projects';
@endphp
<nav class="project-config-tabs" aria-label="Configuration des projets">
    <a class="{{ $projectTab === 'projects' ? 'active' : '' }}" href="{{ route('modules.projects') }}">
        <span class="material-symbols-outlined">work</span>
        Projets
    </a>
    <a class="{{ $projectTab === 'donors' ? 'active' : '' }}" href="{{ route('modules.funding', ['section' => 'donors']) }}">
        <span class="material-symbols-outlined">account_balance</span>
        Bailleurs
    </a>
    <a class="{{ $projectTab === 'programs' ? 'active' : '' }}" href="{{ route('modules.funding', ['section' => 'programs']) }}">
        <span class="material-symbols-outlined">folder_open</span>
        Programmes
    </a>
    <a class="{{ $projectTab === 'medical' ? 'active' : '' }}" href="{{ route('projects.medical-references') }}">
        <span class="material-symbols-outlined">account_tree</span>
        Référentiel médical
    </a>
</nav>

@once
    @push('styles')
        <style>
            .project-config-tabs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;margin:0 0 20px;padding:5px;border:1px solid var(--pc-color-border);border-radius:14px;background:#fff;box-shadow:var(--pc-shadow-card)}
            .project-config-tabs a{min-height:44px;display:flex;align-items:center;justify-content:center;gap:8px;border:1px solid transparent;border-radius:10px;color:var(--pc-color-text-muted);font-size:13px;font-weight:700;transition:background .15s,border-color .15s,color .15s}
            .project-config-tabs a:hover{background:var(--pc-color-background);color:var(--pc-color-text)}
            .project-config-tabs a.active{border-color:var(--pc-color-primary);background:var(--pc-color-primary-soft);color:var(--pc-color-primary)}
            .project-config-tabs .material-symbols-outlined{font-size:19px}
            @media(max-width:600px){.project-config-tabs{overflow-x:auto;grid-template-columns:repeat(4,minmax(132px,1fr));scrollbar-width:none}.project-config-tabs::-webkit-scrollbar{display:none}.project-config-tabs a{min-height:42px;font-size:12px}}
        </style>
    @endpush
@endonce

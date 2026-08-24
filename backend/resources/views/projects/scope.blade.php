@extends('layouts.portal')

@section('title', 'Projets · PharmaCare')
@section('page-title', 'Projets')

@section('content')
<x-app-page-header title="Projets" description="Consultez les projets autorisés dans votre périmètre.">
    <x-slot:actions>
        @if(auth()->user()->hasPermission('projects.manage') && auth()->user()->hasPermission('missions.view'))
            <a class="app-button app-button--primary" href="{{ route('modules.missions') }}">
                <span class="material-symbols-outlined">add</span> Nouveau projet
            </a>
        @endif
    </x-slot:actions>
</x-app-page-header>

<section class="app-kpi-grid" aria-label="Indicateurs des projets">
    <x-app-kpi-card label="Projets accessibles" :value="$projects->count()" icon="account_tree" caption="Dans votre périmètre" tone="green" />
    <x-app-kpi-card label="Projets actifs" :value="$projects->where('is_active', true)->count()" icon="verified" caption="Actuellement actifs" tone="green" />
    <x-app-kpi-card label="Missions concernées" :value="$projects->pluck('mission_id')->unique()->count()" icon="flag" caption="Missions rattachées" tone="blue" />
    <x-app-kpi-card label="Formations sanitaires" :value="$projects->flatMap->healthFacilities->unique('id')->count()" icon="local_hospital" caption="Structures rattachées" tone="orange" />
</section>

<x-app-card class="projects-workspace">
    <x-app-filter-bar :action="route('modules.projects')">
        <x-app-search-input name="search" :value="request('search')" placeholder="Rechercher un projet…" />
        <x-slot:actions>
            <x-app-button type="submit" variant="secondary" icon="filter_alt">Filtrer</x-app-button>
            @if(request('search'))<a class="app-button app-button--ghost" href="{{ route('modules.projects') }}">Réinitialiser</a>@endif
        </x-slot:actions>
    </x-app-filter-bar>

    @if($projects->isEmpty())
        <x-app-empty-state icon="account_tree" title="Aucun projet accessible" description="Aucun projet ne correspond à votre périmètre ou à votre recherche." />
    @else
        <div class="projects-grid">
            @foreach($projects as $project)
                <article class="project-card">
                    <header>
                        <span class="project-card__icon material-symbols-outlined">account_tree</span>
                        <div><h2>{{ $project->name }}</h2><p>{{ $project->code }}</p></div>
                        <x-app-badge :variant="$project->is_active ? 'success' : 'warning'">{{ $project->is_active ? 'Actif' : 'Inactif' }}</x-app-badge>
                    </header>
                    <dl>
                        <div><dt>Mission</dt><dd>{{ $project->mission->name }}</dd></div>
                        <div><dt>Pays</dt><dd>{{ $project->mission->country->name }}</dd></div>
                        <div><dt>Organisation</dt><dd>{{ $project->organization->name }}</dd></div>
                        <div><dt>Formations sanitaires</dt><dd>{{ $project->healthFacilities->count() }}</dd></div>
                    </dl>
                    @if($project->description)<p class="project-card__description">{{ $project->description }}</p>@endif
                </article>
            @endforeach
        </div>
    @endif
</x-app-card>
@endsection

@push('styles')
<style>
.projects-workspace{margin-top:var(--pc-space-5)}
.projects-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--pc-space-4);margin-top:var(--pc-space-4)}
.project-card{padding:var(--pc-space-5);border:1px solid var(--pc-color-border);border-radius:var(--pc-radius-lg);background:var(--pc-color-surface);box-shadow:var(--pc-shadow-card)}
.project-card header{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:12px}
.project-card__icon{display:grid;place-items:center;width:44px;height:44px;border-radius:12px;background:#eaf8ef;color:var(--pc-color-success)}
.project-card h2{margin:0;font-size:16px}.project-card header p,.project-card__description{margin:4px 0 0;color:var(--pc-color-text-muted);font-size:12px}
.project-card dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:18px 0 0}.project-card dl div{padding:11px;border-radius:11px;background:var(--pc-color-background)}
.project-card dt{color:var(--pc-color-text-muted);font-size:11px}.project-card dd{margin:4px 0 0;font-weight:750;font-size:13px}
@media(max-width:760px){.projects-grid,.project-card dl{grid-template-columns:1fr}.project-card header{grid-template-columns:auto minmax(0,1fr)}.project-card header .app-badge{grid-column:2}}
</style>
@endpush

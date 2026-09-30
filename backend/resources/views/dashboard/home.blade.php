@extends('layouts.portal')
@section('title', 'Tableau de bord · PharmaCare')
@section('page-title', 'Tableau de bord')
@section('hide-breadcrumb', '1')

@section('content')
@php
    $dashboardLabel = 'Tableau de bord';
    $dashboardRole = app(\App\Services\GovernanceService::class)->roleCode(auth()->user());
    $isV1CoordinationOrProject = in_array($dashboardRole, [\App\Services\GovernanceService::COORDINATION_ADMIN, \App\Services\GovernanceService::PROJECT_ADMIN], true);
@endphp
<main class="dashboard-main">
    <section class="dashboard-hero">
        <div>
            <h1>{{ $dashboardLabel }}</h1>
            <p>Bonjour, {{ auth()->user()->first_name ?: auth()->user()->name }}. Voici la situation actuelle de votre espace PharmaCare.</p>
        </div>
        <div class="dashboard-date">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
            {{ now()->locale(app()->getLocale())->translatedFormat('l d F Y') }}
        </div>
    </section>

    <h2>Vue d’ensemble opérationnelle</h2>
    <section class="app-kpi-grid dashboard-metrics-dynamic" aria-label="Indicateurs adaptés à votre périmètre">
        @foreach($widgets as $widget)
            <x-app-kpi-card
                :label="$widget['label']"
                :value="number_format((float) $widget['value'], $widget['key']==='stock_quantity' ? 2 : 0, ',', ' ')"
                :icon="config('pharmacare_ui.module_icons.'.$widget['icon'], 'dashboard')"
                :caption="$widget['caption']"
                :href="$widget['route']"
            />
        @endforeach
    </section>

    <section class="pc-dashboard-grid">
        <div class="dashboard-column pc-dashboard-main">
            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></span>
                        <div><h2>Accès rapides</h2><p>Accédez à vos opérations les plus fréquentes</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body">
                    <div class="dashboard-shortcuts">
                        @if($dashboardRole === \App\Services\GovernanceService::COORDINATION_ADMIN && auth()->user()->hasPermission('funding.view'))
                        <a class="dashboard-shortcut" href="{{ route('modules.funding') }}"><span class="dashboard-shortcut-icon orange material-symbols-outlined" aria-hidden="true">volunteer_activism</span><span><b>Bailleurs et programmes</b><small>Financements de votre coordination</small></span></a>
                        @endif
                        @if(auth()->user()->hasPermission('standard_lists.view'))
                        <a class="dashboard-shortcut" href="{{ route('modules.standard-lists') }}"><span class="dashboard-shortcut-icon orange material-symbols-outlined" aria-hidden="true">format_list_bulleted</span><span><b>Liste standard</b><small>Référentiel autorisé de votre projet</small></span></a>
                        @endif
                        @if($dashboardRole === \App\Services\GovernanceService::PROJECT_ADMIN && auth()->user()->hasPermission('health_facilities.view'))
                        <a class="dashboard-shortcut" href="{{ route('modules.health-facilities') }}"><span class="dashboard-shortcut-icon orange material-symbols-outlined" aria-hidden="true">local_hospital</span><span><b>Formations sanitaires</b><small>Établissements de votre projet</small></span></a>
                        @endif
                        @if($dashboardRole === \App\Services\GovernanceService::PROJECT_ADMIN && auth()->user()->hasPermission('users.view'))
                        <a class="dashboard-shortcut" href="{{ route('users.index') }}"><span class="dashboard-shortcut-icon orange material-symbols-outlined" aria-hidden="true">group</span><span><b>Équipe FOSA</b><small>Utilisateurs de votre périmètre</small></span></a>
                        @endif
                        @if($dashboardRole === \App\Services\GovernanceService::COORDINATION_ADMIN && auth()->user()->hasPermission('projects.manage'))
                        <a class="dashboard-shortcut" href="{{ route('modules.projects',['create'=>1]) }}"><span class="dashboard-shortcut-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14M4 4h16v16H4z"/></svg></span><span><b>Créer un projet</b><small>Projet, bailleurs et premier Admin Projet</small></span></a>
                        @elseif(auth()->user()->hasPermission('users.manage') && !$isV1CoordinationOrProject)
                        <a class="dashboard-shortcut" href="{{ route('users.index',['create'=>1]) }}"><span class="dashboard-shortcut-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 19a6 6 0 0 0-12 0M9 11a4 4 0 1 0 0-8M19 8v6M16 11h6"/></svg></span><span><b>Créer un utilisateur</b><small>Nouveau compte, rôle et périmètre</small></span></a>
                        @endif
                        @if(auth()->user()->hasPermission('users.view') && !$isV1CoordinationOrProject)
                        <a class="dashboard-shortcut" href="{{ route('users.index') }}"><span class="dashboard-shortcut-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8M20 8v6M17 11h6"/></svg></span><span><b>Utilisateurs et sécurité</b><small>Consulter, modifier et archiver</small></span></a>
                        @endif
                        @if(auth()->user()->hasPermission('organizations.view') && !$isV1CoordinationOrProject)
                        <a class="dashboard-shortcut" href="{{ route('organizations.index') }}"><span class="dashboard-shortcut-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h2M13 10h2M9 14h2M13 14h2"/></svg></span><span><b>Organisations</b><small>ONG, missions et projets</small></span></a>
                        @endif
                        @if($organizations->first() && auth()->user()->hasPermission('stocks.view') && !$isV1CoordinationOrProject)
                        <a class="dashboard-shortcut" href="{{ route('organizations.stocks.index',$organizations->first()) }}"><span class="dashboard-shortcut-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg></span><span><b>Gérer le stock de médicaments</b><small>Soldes par lot et mouvements</small></span></a>
                        @endif
                        <a class="dashboard-shortcut" href="{{ route('profile.show') }}"><span class="dashboard-shortcut-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span><b>Mon profil</b><small>Informations personnelles et mot de passe</small></span></a>
                    </div>
                </div>
            </article>

            @if(!$isV1CoordinationOrProject && auth()->user()->hasPermission('stocks.view'))
            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M12 3l4 4-4 4M12 21l-4-4 4-4"/></svg></span>
                        <div><h2>Mouvements de stock récents</h2><p>Dernières entrées, sorties et corrections validées</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body">
                    @if($recentMovements->isEmpty())
                        <div class="dashboard-empty"><span class="dashboard-empty-icon">✓</span><span>Aucun mouvement visible récemment.</span></div>
                    @else
                        <div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>Date</th><th>Produit</th><th>Site</th><th>Type</th><th>Quantité</th></tr></thead><tbody>
                        @foreach($recentMovements as $movement)
                            <tr><td>{{ $movement->validated_at?->format('d/m H:i') }}</td><td>{{ $movement->product?->name }}</td><td>{{ $movement->site?->name }}</td><td>{{ $movement->movement_type }}</td><td class="{{ (float)$movement->quantity>=0?'positive':'negative' }}">{{ (float)$movement->quantity>=0?'+':'' }}{{ $movement->quantity }}</td></tr>
                        @endforeach
                        </tbody></table></div>
                    @endif
                </div>
            </article>
            @endif
        </div>

        <aside class="dashboard-column pc-dashboard-aside">
            @if(!$isV1CoordinationOrProject && auth()->user()->hasPermission('users.view'))
            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg></span>
                        <div><h2>Vue d’ensemble</h2><p>Comptes et accès</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body dashboard-summary">
                    <div class="summary-item"><strong>{{ number_format($stats['users_total']) }}</strong><span>Utilisateurs visibles</span></div>
                    <div class="summary-item"><strong>{{ number_format($stats['users_active']) }}</strong><span>Comptes actifs</span></div>
                    <div class="summary-item"><strong>{{ number_format($stats['users_archived']) }}</strong><span>Comptes archivés</span></div>
                </div>
            </article>
            @endif

            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-9-9"/></svg></span>
                        <div><h2>Activités récentes</h2><p>Dernières actions enregistrées</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body activity-list">
                    @forelse($activities as $activity)
                        @php($activityLabel = match($activity->event) {
                            'facility.created' => 'Formation sanitaire créée',
                            'facility.updated' => 'Formation sanitaire modifiée',
                            'site.created' => 'Point de dispensation créé',
                            'site.updated' => 'Point de dispensation modifié',
                            'user.created' => 'Utilisateur créé',
                            'user.updated' => 'Utilisateur modifié',
                            default => str_replace(['.','_'],' ',ucfirst($activity->event)),
                        })
                        <div class="dashboard-activity"><span class="activity-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-9-9"/></svg></span><div><p><strong>{{ $activity->user?->name ?? 'Système' }}</strong><br>{{ $activityLabel }}</p><small>{{ $activity->created_at->locale('fr')->diffForHumans() }}</small></div></div>
                    @empty
                        <div class="dashboard-empty"><span class="dashboard-empty-icon">✓</span><span>Aucune activité récente.</span></div>
                    @endforelse
                </div>
            </article>

            @if($dashboardRole !== \App\Services\GovernanceService::PROJECT_ADMIN && auth()->user()->hasPermission('organizations.view'))
            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg></span>
                        <div><h2>Organisations accessibles</h2><p>Votre périmètre actuel</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body organization-list">
                    @forelse($organizations as $organization)
                        <div class="organization-row"><span class="organization-avatar">{{ mb_strtoupper(mb_substr($organization->name,0,1)) }}</span><span><strong>{{ $organization->name }}</strong><small>{{ $organization->code }}</small></span></div>
                    @empty
                        <div class="dashboard-empty"><span>Aucune organisation accessible.</span></div>
                    @endforelse
                </div>
            </article>
            @endif
        </aside>
    </section>
</main>
@endsection

{{-- Niveau 5 — Tableau de bord de l'Admin Projet (maquette AdminProjet 01). --}}
@extends('layouts.portal')
@section('title', 'Tableau de bord · PharmaCare')
@section('page-title', 'Tableau de bord')
@include('project-admin.partials.styles')
@php
    $stats = $board['stats'];
    $tone = ['ok' => 'success', 'late' => 'info', 'never' => 'neutral', 'failed' => 'danger', 'suspended' => 'danger'];
    $months = fn ($value) => $value === null ? '—' : str_replace('.', ',', (string) $value).' mois';
    $donor = $project->donors->first();
@endphp
@section('content')
<div class="pa">
    <header class="pa-head">
        <div><h1>Tableau de bord</h1><p>Vue d’ensemble du projet {{ $project->code }} et de ses formations sanitaires</p></div>
        @if(auth()->user()->hasPermission('health_facilities.manage'))<a class="pa-btn primary" href="{{ route('project-admin.facilities.create') }}"><span class="material-symbols-outlined" aria-hidden="true">add</span>Ajouter une FOSA</a>@endif
    </header>

    <section class="pa-kpis" aria-label="Indicateurs du projet">
        <article class="pa-kpi"><small>FOSA actives</small><strong>{{ $stats['active_facilities'] }} / {{ $stats['facilities'] }}</strong><span>{{ $stats['inactive_facilities'] }} {{ $stats['inactive_facilities'] > 1 ? 'non actives' : 'non active' }} (en attente, inactive ou suspendue)</span></article>
        <article class="pa-kpi"><small>Comptes utilisateurs FOSA</small><strong>{{ $stats['accounts'] }}</strong><span>{{ $stats['accounts_to_activate'] }} à activer (1re connexion)</span></article>
        <article class="pa-kpi"><small>FOSA en échec de synchro</small><strong @class(['danger' => $stats['sync_failed'] > 0])>{{ $stats['sync_failed'] }}</strong><span>{{ $stats['sync_failed'] > 0 ? 'À traiter' : 'Aucun échec' }}</span></article>
        <article class="pa-kpi"><small>Produits en Liste Standard</small><strong>{{ $stats['standard_list_products'] }}</strong><span>Gérée par la Coordination</span></article>
    </section>

    <div class="pa-grid">
        <section class="pa-card">
            <header><h2>État de synchronisation des FOSA</h2><a class="pa-link" href="{{ route('modules.health-facilities') }}">Voir toutes les FOSA</a></header>
            @if($board['sync']->isEmpty())
                <p class="pa-empty">Aucune FOSA validée dans ce projet. Les FOSA déclarées apparaissent ici après leur validation par la Coordination.</p>
            @else
                <div class="pa-scroll"><table class="pa-table" style="min-width:560px">
                    <thead><tr><th>Formation sanitaire</th><th>Catégorie</th><th>Dernière synchro</th><th>Statut</th></tr></thead>
                    <tbody>
                    @foreach($board['sync']->take(6) as $row)
                        <tr>
                            <td><strong>{{ $row['name'] }}</strong><small>{{ $row['code'] }}</small></td>
                            <td>{{ $row['category'] ?? '—' }}</td>
                            <td class="pa-muted">{{ $row['last_contact_at'] ? \Illuminate\Support\Carbon::parse($row['last_contact_at'])->locale('fr')->diffForHumans() : 'Jamais' }}</td>
                            <td><span class="pa-badge {{ $tone[$row['sync_status']] ?? 'neutral' }}">{{ $row['sync_label'] }}@if($row['failed_operations'] > 0) · {{ $row['failed_operations'] }} op.@endif</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>

        <aside class="pa-side">
            <section class="pa-card pa-pad">
                <h2 style="margin:0 0 6px;font-size:16px">Informations du projet</h2>
                <dl class="pa-dl">
                    <dt>Pays</dt><dd>{{ $project->mission?->country?->name ?? '—' }}</dd>
                    <dt>ONG</dt><dd>{{ $project->implementing_partner ?: $project->organization?->name }}</dd>
                    <dt>Bailleur / Code projet</dt><dd>{{ $donor?->name ?? ($project->type === 'national_program' ? \App\Models\Project::NATIONAL_PROGRAM_DEFAULT_DONOR : '—') }} · {{ $project->code }}</dd>
                    <dt>Titre du projet</dt><dd>{{ $project->name }}</dd>
                    <dt>Durée</dt><dd>{{ $project->starts_on?->format('d/m/Y') ?? '—' }} – {{ $project->ends_on?->format('d/m/Y') ?? '—' }}</dd>
                </dl>
                <p class="pa-note">Définies par la Coordination — lecture seule</p>
            </section>
            <section class="pa-card pa-pad">
                <h2 style="margin:0;font-size:16px">Paramètres d’approvisionnement</h2>
                <p style="margin:2px 0 6px;color:var(--pc-color-text-muted);font-size:13px">Entrepôt central → Pharmacie du projet</p>
                <dl class="pa-dl">
                    <dt>Périodicité de commande</dt><dd>{{ $months($project->order_period_months) }}</dd>
                    <dt>Délai de livraison (DL)</dt><dd>{{ $months($project->delivery_lead_time_months) }}</dd>
                    <dt>Stock de sécurité</dt><dd>{{ $months($project->safety_stock_months) }}</dd>
                    <dt>Prochain inventaire</dt><dd>{{ $project->inventory_date?->format('d/m/Y') ?? '—' }}</dd>
                    <dt>Prochaine commande</dt><dd>{{ $project->order_submission_date?->format('d/m/Y') ?? '—' }}</dd>
                </dl>
            </section>
            @if($board['watch'] > 0)
                <section class="pa-alert danger" role="status">
                    <strong>{{ $board['watch'] }} FOSA à surveiller</strong>
                    Pas de synchronisation complète depuis plus de {{ \App\Services\CoordinationService::SYNC_LATE_DAYS }} jours, ou opérations refusées. Vérifier la connexion ou les opérations en échec.
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection

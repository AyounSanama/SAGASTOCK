{{-- Niveau 5 — « Projet & FOSA », onglet « Paramètres d'approvisionnement » (maquette AdminProjet 02). --}}
@extends('layouts.portal')
@section('title', 'Paramètres d’approvisionnement · PharmaCare')
@section('page-title', 'Projet & FOSA')
@include('project-admin.partials.styles')
@php
    $months = fn ($value) => $value === null ? '—' : str_replace('.', ',', (string) $value).' mois';
    $date = fn ($value) => $value?->format('d/m/Y') ?? '—';
@endphp
@section('content')
<div class="pa">
    <header class="pa-head"><div><h1>Projet {{ $project->code }} — Paramètres d’approvisionnement</h1><p>Utilisés pour les risques de péremption, de rupture et les commandes.</p></div></header>
    @include('projects.partials.project-admin-tabs', ['activeTab' => 'supply', 'counts' => $counts])

    <section class="pa-card pa-pad" style="margin-bottom:18px">
        <h2 style="margin:0;font-size:16px">Projet : Entrepôt central → Pharmacie du projet</h2>
        <dl class="pa-dl" style="max-width:560px">
            <dt>Périodicité de commande</dt><dd>{{ $months($project->order_period_months) }}</dd>
            <dt>Délai de livraison (DL)</dt><dd>{{ $months($project->delivery_lead_time_months) }}</dd>
            <dt>Stock de sécurité</dt><dd>{{ $months($project->safety_stock_months) }}</dd>
            <dt>Date d’inventaire</dt><dd>{{ $date($project->inventory_date) }}</dd>
            <dt>Date de soumission de commande</dt><dd>{{ $date($project->order_submission_date) }}</dd>
            <dt>Date de réception de commande</dt><dd>{{ $date($project->order_receipt_date) }}</dd>
        </dl>
        <p class="pa-note">Définis par la Coordination — lecture seule. Ils préremplissent chaque nouvelle FOSA ; une modification du projet n’écrase jamais les valeurs des FOSA.</p>
    </section>

    <section class="pa-card">
        <header><h2>FOSA : Pharmacie du projet → FOSA</h2></header>
        @if($facilities->isEmpty())
            <p class="pa-empty">Aucune formation sanitaire dans ce projet.</p>
        @else
            <div class="pa-scroll"><table class="pa-table" style="min-width:900px">
                <thead><tr><th>Formation sanitaire</th><th>Périodicité</th><th>DL</th><th>Stock de sécurité</th><th>Inventaire</th><th>Soumission</th><th>Réception</th><th></th></tr></thead>
                <tbody>
                @foreach($facilities as $facility)
                    <tr>
                        <td><strong>{{ $facility->name }}</strong><small>{{ $facility->code }}</small></td>
                        <td>{{ $months($facility->order_period_months) }}</td>
                        <td>{{ $months($facility->delivery_lead_time_months) }}</td>
                        <td>{{ $months($facility->safety_stock_months) }}</td>
                        <td>{{ $date($facility->inventory_date) }}</td>
                        <td>{{ $date($facility->order_submission_date) }}</td>
                        <td>{{ $date($facility->order_receipt_date) }}</td>
                        <td style="text-align:right">@if(auth()->user()->hasPermission('health_facilities.manage'))<a class="pa-btn sm" href="{{ route('project-admin.facilities.edit', $facility) }}#approvisionnement">Modifier</a>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
</div>
@endsection

{{-- Niveau 5 — « Projet & FOSA », onglet FOSA (maquette AdminProjet 02). --}}
@extends('layouts.portal')
@section('title', 'Projet '.$project->code.' — Formations sanitaires · PharmaCare')
@section('page-title', 'Projet & FOSA')
@include('project-admin.partials.styles')
@php
    $donor = $project->donors->first()?->name ?? ($project->type === 'national_program' ? \App\Models\Project::NATIONAL_PROGRAM_DEFAULT_DONOR : null);
    $statuses = ['active' => 'Active', 'inactive' => 'Inactive', 'pending' => 'En attente', 'refused' => 'Refusée', 'suspended' => 'Suspendue'];
    $canManage = auth()->user()->hasPermission('health_facilities.manage');
@endphp
@section('content')
<div class="pa">
    <header class="pa-head">
        <div><h1>Projet {{ $project->code }} — Formations sanitaires</h1><p>{{ collect([$project->name, $donor, $project->mission?->country?->name])->filter()->join(' · ') }}</p></div>
        @if($canManage)<a class="pa-btn primary" href="{{ route('project-admin.facilities.create') }}"><span class="material-symbols-outlined" aria-hidden="true">add</span>Ajouter une FOSA</a>@endif
    </header>
    @if(session('status'))<div class="pa-notice" role="status">{{ session('status') }}</div>@endif
    @include('projects.partials.project-admin-tabs', ['activeTab' => 'facilities', 'counts' => $counts])

    <form class="pa-filters" method="get" action="{{ route('modules.health-facilities') }}">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Rechercher une FOSA ou un code" aria-label="Rechercher une FOSA">
        <select name="category" aria-label="Catégorie" onchange="this.form.submit()">
            <option value="">Toutes catégories</option>
            @foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category') === $category->id)>{{ $category->name }}</option>@endforeach
        </select>
        <select name="status" aria-label="Statut" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
        </select>
    </form>

    <section class="pa-card">
        @if($facilities->isEmpty())
            <p class="pa-empty">{{ request()->hasAny(['search', 'category', 'status']) ? 'Aucune FOSA ne correspond à ces filtres.' : 'Aucune formation sanitaire dans ce projet. Déclarez la première avec « Ajouter une FOSA » : elle sera validée par la Coordination.' }}</p>
        @else
            <div class="pa-scroll"><table class="pa-table" style="min-width:960px">
                <thead><tr><th>Formation sanitaire</th><th>Catégorie</th><th>Niveau de soins</th><th>Population cible</th><th>Comptes</th><th>Statut</th><th style="text-align:right">Actions</th></tr></thead>
                <tbody>
                @foreach($facilities as $facility)
                    @php($status = \App\Services\ProjectAdminWorkspaceService::status($facility))
                    <tr>
                        <td><strong>{{ $facility->name }}</strong><small>{{ $facility->code }}</small></td>
                        <td>{{ $facility->facilityCategory?->name ?? '—' }}</td>
                        <td>{{ $facility->careLevel?->name ?? '—' }}</td>
                        <td>{{ \App\Services\ProjectAdminWorkspaceService::populationsShort($facility) }}</td>
                        <td>{{ ($accounts[$facility->id] ?? collect())->count() }}</td>
                        <td><span class="pa-badge {{ $status['tone'] }}" @if($facility->validation_status === 'refused' && $facility->refusal_reason) title="Motif : {{ $facility->refusal_reason }}" @endif>{{ $status['label'] }}</span></td>
                        <td style="text-align:right;white-space:nowrap">
                            @if($canManage)<a class="pa-btn sm" href="{{ route('project-admin.facilities.edit', $facility) }}">Modifier</a>@endif
                            <a class="pa-btn sm" href="{{ route('users.index', ['facility' => $facility->id]) }}">Comptes</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <footer class="pa-foot">
                <span>{{ $facilities->count() }} sur {{ $facilities->total() }} FOSA</span>
                <nav aria-label="Pagination">
                    @if($facilities->onFirstPage())<span class="pa-btn sm" aria-disabled="true" style="opacity:.5">Précédent</span>@else<a class="pa-btn sm" href="{{ $facilities->previousPageUrl() }}">Précédent</a>@endif
                    @if($facilities->hasMorePages())<a class="pa-btn sm" href="{{ $facilities->nextPageUrl() }}">Suivant</a>@else<span class="pa-btn sm" aria-disabled="true" style="opacity:.5">Suivant</span>@endif
                </nav>
            </footer>
        @endif
    </section>
</div>
@endsection

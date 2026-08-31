@extends('layouts.portal')

@section('title', ($isCoordination ? 'Ma Coordination' : 'Missions').' · PharmaCare')
@section('page-title', $isCoordination ? 'Ma Coordination' : 'Missions')

@section('content')
<x-app-page-header :title="$isCoordination ? 'Ma Coordination' : 'Missions'" :description="$isCoordination ? 'Consultez la coordination pays à laquelle votre compte est affecté.' : 'Gérez les missions de votre organisation dans les pays autorisés.'">
    @if($canManage)
        <x-slot:actions><x-app-button icon="add" data-sheet-open="mission-create-sheet">Nouvelle mission</x-app-button></x-slot:actions>
    @endif
</x-app-page-header>

@if(session('status'))<div class="app-alert app-alert--success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="app-alert app-alert--danger"><strong>Le formulaire contient des erreurs.</strong> {{ $errors->first() }}</div>@endif

<section class="app-kpi-grid">
    <x-app-kpi-card label="Missions totales" :value="$missionStats['total']" icon="flag" caption="Toutes les missions" tone="blue" />
    <x-app-kpi-card label="Missions actives" :value="$missionStats['active']" icon="verified" caption="En cours d’exécution" tone="green" />
    <x-app-kpi-card label="Pays couverts" :value="$missionStats['countries']" icon="language" :caption="$countries->count().' pays autorisé(s)'" tone="orange" />
    <x-app-kpi-card label="Projets rattachés" :value="$missionStats['projects']" icon="account_tree" caption="Dans ces missions" tone="green" />
</section>

<x-app-card class="mission-workspace">
    @unless($isCoordination)<x-app-filter-bar :action="route('modules.missions')">
        <x-app-search-input name="search" :value="request('search')" placeholder="Rechercher une mission…" />
        @if($organizations->count() > 1)
            <label class="app-filter-field"><span>Organisation</span><select name="organization_id"><option value="">Sélectionner</option>@foreach($organizations as $item)<option value="{{ $item->id }}" @selected($organization->id===$item->id)>{{ $item->name }}</option>@endforeach</select></label>
        @endif
        <label class="app-filter-field"><span>Pays</span><select name="country_id"><option value="">Tous</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected(request('country_id')===$country->id)>{{ $country->name }}</option>@endforeach</select></label>
        <label class="app-filter-field"><span>Statut</span><select name="status"><option value="">Tous</option><option value="active" @selected(request('status')==='active')>Actives</option><option value="inactive" @selected(request('status')==='inactive')>Inactives</option></select></label>
        <x-slot:actions>
            <x-app-button type="submit" variant="secondary" icon="filter_alt">Filtrer</x-app-button>
            <x-app-button href="{{ route('modules.missions') }}" variant="ghost">Réinitialiser</x-app-button>
        </x-slot:actions>
    </x-app-filter-bar>@endunless

    <div class="missions-desktop">
        <x-app-data-table>
            <thead><tr><th>Mission</th><th>Pays</th><th>Période</th><th>Responsable</th><th>Projets</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
            @forelse($missions as $mission)
                <tr>
                    <td><strong>{{ $mission->name }}</strong><small>{{ $mission->code }}</small></td>
                    <td>{{ $mission->country->name }}<small>{{ $mission->country->iso2 }}</small></td>
                    <td>{{ $mission->starts_on?->format('d/m/Y') ?? '—' }}<small>au {{ $mission->ends_on?->format('d/m/Y') ?? '—' }}</small></td>
                    <td>{{ $mission->manager_name ?: 'Non renseigné' }}<small>{{ $mission->phone }}</small></td>
                    <td>{{ $mission->projects_count }}</td>
                    <td><x-app-badge :variant="$mission->is_active ? 'success' : 'warning'">{{ $mission->is_active ? 'Active' : 'Inactive' }}</x-app-badge></td>
                    <td><div class="mission-actions">
                        <x-app-icon-button href="{{ route('organizations.missions.show',[$organization,$mission]) }}" icon="visibility" label="Consulter" />
                        @if($canManage)
                            <x-app-icon-button icon="edit" label="Modifier" data-sheet-open="mission-edit-{{ $mission->id }}" />
                            <form method="post" action="{{ route('organizations.missions.destroy',[$organization,$mission]) }}" onsubmit="return confirm('Archiver cette mission ?')">@csrf @method('DELETE')<x-app-icon-button type="submit" icon="archive" label="Archiver" variant="danger" /></form>
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-app-empty-state icon="flag" :title="$isCoordination ? 'Aucune coordination affectée' : 'Aucune mission'" :description="$isCoordination ? 'Contactez un Admin Sago pour rattacher ce compte à une coordination pays.' : 'Aucune mission ne correspond aux critères.'" /></td></tr>
            @endforelse
            </tbody>
        </x-app-data-table>
    </div>

    <div class="missions-mobile">
        @forelse($missions as $mission)
            <article class="mission-mobile-card">
                <header><div><strong>{{ $mission->name }}</strong><small>{{ $mission->code }} · {{ $mission->country->name }}</small></div><x-app-badge :variant="$mission->is_active ? 'success' : 'warning'">{{ $mission->is_active ? 'Active' : 'Inactive' }}</x-app-badge></header>
                <div class="mission-mobile-card__facts"><span><b>{{ $mission->projects_count }}</b> projets</span><span>{{ $mission->starts_on?->format('d/m/Y') ?? 'Date non définie' }}</span></div>
                <x-app-button href="{{ route('organizations.missions.show',[$organization,$mission]) }}" variant="secondary" icon="visibility" expanded>Consulter</x-app-button>
            </article>
        @empty
            <x-app-empty-state icon="flag" :title="$isCoordination ? 'Aucune coordination affectée' : 'Aucune mission'" :description="$isCoordination ? 'Contactez un Admin Sago pour rattacher ce compte à une coordination pays.' : 'Créez la première mission de votre organisation.'" />
        @endforelse
    </div>
    <x-app-pagination :paginator="$missions" />
</x-app-card>

@if($archivedMissions->isNotEmpty())
<x-app-card title="Missions archivées" class="mission-archives">
    @foreach($archivedMissions as $mission)<div class="archive-row"><div><strong>{{ $mission->name }}</strong><small>{{ $mission->code }} · {{ $mission->country->name }}</small></div>@if($canManage)<form method="post" action="{{ route('organizations.missions.restore',[$organization,$mission->id]) }}" onsubmit="return confirm('Restaurer cette mission ?')">@csrf <x-app-button type="submit" variant="secondary" icon="restore">Restaurer</x-app-button></form>@endif</div>@endforeach
</x-app-card>
@endif

@if($canManage)
<x-form-sheet id="mission-create-sheet" title="Créer une mission" description="Le pays doit appartenir au périmètre autorisé de l’organisation.">
    <form method="post" action="{{ route('organizations.missions.store',$organization) }}">@csrf <input type="hidden" name="form_context" value="mission-create">@include('missions.partials.form-fields',['mission'=>null])<div class="sheet-actions"><x-app-button type="button" variant="secondary" data-sheet-close="mission-create-sheet">Annuler</x-app-button><x-app-button type="submit" icon="add">Créer la mission</x-app-button></div></form>
</x-form-sheet>
@foreach($missions as $mission)
<x-form-sheet id="mission-edit-{{ $mission->id }}" title="Modifier la mission">
    <form method="post" action="{{ route('organizations.missions.update',[$organization,$mission]) }}">@csrf @method('PUT') @include('missions.partials.form-fields',['mission'=>$mission])<div class="sheet-actions"><x-app-button type="button" variant="secondary" data-sheet-close="mission-edit-{{ $mission->id }}">Annuler</x-app-button><x-app-button type="submit" icon="save">Enregistrer</x-app-button></div></form>
</x-form-sheet>
@endforeach
@endif
@endsection

@push('styles')
<style>
.mission-workspace,.mission-archives{margin-top:var(--pc-space-5)}.app-filter-field{display:grid;gap:5px;min-width:150px;color:var(--pc-color-text-muted);font-size:11px}.app-filter-field select{min-height:44px;border:1px solid var(--pc-color-border);border-radius:var(--pc-radius-md);padding:0 12px;background:#fff}.mission-actions{display:flex;gap:6px}.mission-actions form{margin:0}.app-data-table td small,.archive-row small,.mission-mobile-card small{display:block;margin-top:4px;color:var(--pc-color-text-muted)}.missions-mobile{display:none}.archive-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 0;border-bottom:1px solid var(--pc-color-border)}.sheet-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}
@media(max-width:760px){.missions-desktop{display:none}.missions-mobile{display:grid;gap:12px;margin-top:16px}.mission-mobile-card{padding:16px;border:1px solid var(--pc-color-border);border-radius:var(--pc-radius-lg);background:#fff}.mission-mobile-card header,.mission-mobile-card__facts{display:flex;align-items:center;justify-content:space-between;gap:10px}.mission-mobile-card__facts{margin:16px 0;color:var(--pc-color-text-muted);font-size:12px}.archive-row{align-items:flex-start;flex-direction:column}}
</style>
@endpush

@if($errors->any() && old('form_context')==='mission-create')
@push('scripts')<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('mission-create-sheet')?.showModal())</script>@endpush
@endif

@extends('layouts.portal')
@section('title', 'Assistance aux organisations · PharmaCare')
@section('page-title', 'Assistance aux organisations')
@section('content')
<main class="sa-page">
    <x-app-page-header title="Assistance aux organisations" description="Accompagnez les organisations dans la configuration technique autorisée de leur environnement PharmaCare." />

    <section class="sa-kpis" aria-label="Résumé">
        <article><span class="material-symbols-outlined">domain</span><div><small>Organisations actives</small><strong>{{ $assistanceStats['active'] }}</strong></div></article>
        <article><span class="material-symbols-outlined">support_agent</span><div><small>Organisations accompagnées</small><strong>{{ $assistanceStats['accompanied'] }}</strong></div></article>
        <article><span class="material-symbols-outlined">tune</span><div><small>Configurations actives</small><strong>{{ $assistanceStats['configurations'] }}</strong></div></article>
    </section>

    <section class="sa-card">
        <form method="get" class="sa-filters">
            <input type="hidden" name="tab" value="assistance">
            <label class="sa-search"><span class="material-symbols-outlined">search</span><input name="organization_q" value="{{ request('organization_q') }}" placeholder="Rechercher par nom ou code"></label>
            <select name="country_id"><option value="">Tous les pays</option>@foreach($assistanceCountries as $country)<option value="{{ $country->id }}" @selected((string)request('country_id') === (string)$country->id)>{{ $country->name }}</option>@endforeach</select>
            <select name="access_type"><option value="">Tous les accès</option><option value="single_country" @selected(request('access_type')==='single_country')>Unipays</option><option value="multi_country" @selected(request('access_type')==='multi_country')>Multipays</option></select>
            <select name="organization_status"><option value="">Tous les statuts</option><option value="active" @selected(request('organization_status')==='active')>Active</option><option value="inactive" @selected(request('organization_status')==='inactive')>Inactive</option></select>
            <select name="configuration_state"><option value="">Toute configuration</option><option value="configured" @selected(request('configuration_state')==='configured')>Configurée</option><option value="not_configured" @selected(request('configuration_state')==='not_configured')>À configurer</option></select>
            <button class="sa-button primary" type="submit"><span class="material-symbols-outlined">search</span>Rechercher</button>
            <a class="sa-button outline" href="{{ route('configuration.platform-standards.index', ['tab'=>'assistance']) }}"><span class="material-symbols-outlined">restart_alt</span>Réinitialiser</a>
        </form>

        <div class="sa-table-wrap">
            <table>
                <thead><tr><th>Organisation</th><th>Type d’accès</th><th>Pays</th><th>Admin Coordination</th><th>Configuration</th><th>Statut</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($assistanceOrganizations as $organization)
                    @php($latest = $organization->effectiveConfigurations->sortByDesc('configuration_version')->first())
                    <tr>
                        <td data-label="Organisation"><strong>{{ $organization->name }}</strong><small>{{ $organization->code }}</small></td>
                        <td data-label="Type d’accès">{{ $organization->geographic_access_type === 'multi_country' ? 'Multipays' : 'Unipays' }}</td>
                        <td data-label="Pays">{{ $organization->countries->pluck('name')->join(', ') ?: 'Non défini' }}</td>
                        <td data-label="Admin Coordination">{{ optional($organization->users->first())->name ?: 'Non affecté' }}</td>
                        <td data-label="Configuration">{{ $latest ? 'v1.'.($latest->configuration_version - 1) : 'Aucune' }}</td>
                        <td data-label="Statut"><span class="sa-status {{ $organization->is_active ? 'active' : 'inactive' }}">{{ $organization->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td data-label="Action"><a class="sa-action" href="{{ route('configuration.platform-standards.organizations.assist', $organization) }}">Accompagner <span class="material-symbols-outlined">arrow_forward</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="sa-empty"><span class="material-symbols-outlined">domain_disabled</span><strong>Aucune organisation ne correspond aux critères sélectionnés.</strong><p>Modifiez ou réinitialisez les filtres pour afficher les organisations accessibles.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
@push('styles')
<style>
.sa-page{display:grid;gap:20px;color:var(--pc-color-text)}.sa-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.sa-kpis article,.sa-card{background:#fff;border:1px solid var(--pc-color-border);border-radius:16px;box-shadow:0 8px 24px rgba(36,50,74,.04)}.sa-kpis article{display:flex;align-items:center;gap:14px;padding:18px}.sa-kpis .material-symbols-outlined{display:grid;place-items:center;width:46px;height:46px;border-radius:14px;background:var(--pc-color-primary-soft);color:var(--pc-color-primary-soft-text);font-size:25px}.sa-kpis small,.sa-kpis strong{display:block}.sa-kpis small{color:var(--pc-color-text-muted)}.sa-kpis strong{font-size:24px;margin-top:4px}.sa-card{padding:16px;overflow:hidden}.sa-filters{display:grid;grid-template-columns:minmax(230px,1.5fr) repeat(4,minmax(130px,1fr)) auto auto;gap:10px;margin-bottom:16px}.sa-filters input,.sa-filters select{height:46px;border:1px solid var(--pc-color-border);border-radius:10px;background:#fff;padding:0 12px;color:var(--pc-color-text);min-width:0}.sa-search{display:flex;align-items:center;border:1px solid var(--pc-color-border);border-radius:10px;padding-left:12px}.sa-search input{border:0;width:100%;outline:0}.sa-button{height:46px;padding:0 16px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;font-weight:700;white-space:nowrap}.sa-button.primary{border:1px solid var(--pc-color-primary-strong);background:var(--pc-color-primary-strong);color:#fff}.sa-button.outline{border:1px solid var(--pc-color-border);color:var(--pc-color-text);background:#fff}.sa-table-wrap{overflow:hidden;border:1px solid var(--pc-color-border);border-radius:12px}.sa-table-wrap table{width:100%;border-collapse:collapse}.sa-table-wrap th{background:var(--pc-color-background);color:var(--pc-color-text-muted);font-size:12px;text-align:left;padding:13px}.sa-table-wrap td{padding:15px 13px;border-top:1px solid var(--pc-color-border);vertical-align:middle}.sa-table-wrap td strong,.sa-table-wrap td small{display:block}.sa-table-wrap td small{color:var(--pc-color-text-muted);margin-top:4px}.sa-status{padding:6px 10px;border-radius:8px;font-size:12px;font-weight:700}.sa-status.active{background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.sa-status.inactive{background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.sa-action{display:inline-flex;align-items:center;gap:5px;color:var(--pc-color-primary-strong);font-weight:700;text-decoration:none;white-space:nowrap}.sa-action .material-symbols-outlined{font-size:18px}.sa-empty{padding:45px;text-align:center;color:var(--pc-color-text-muted)}.sa-empty .material-symbols-outlined{font-size:42px;color:var(--pc-color-text-muted)}.sa-empty strong{display:block;color:var(--pc-color-text);margin:8px}.sa-empty p{margin:0}
@media(max-width:1180px){.sa-filters{grid-template-columns:repeat(3,1fr)}.sa-search{grid-column:span 2}}
@media(max-width:760px){.sa-kpis{grid-template-columns:1fr}.sa-filters{grid-template-columns:1fr}.sa-search{grid-column:auto}.sa-table-wrap{border:0;overflow:visible}.sa-table-wrap table,.sa-table-wrap tbody{display:block}.sa-table-wrap thead{display:none}.sa-table-wrap tr{display:block;border:1px solid var(--pc-color-border);border-radius:12px;margin-bottom:12px;padding:10px}.sa-table-wrap td{display:flex;justify-content:space-between;gap:16px;padding:9px;border:0;text-align:right}.sa-table-wrap td:before{content:attr(data-label);font-weight:700;color:var(--pc-color-text-muted);text-align:left}.sa-table-wrap td[colspan]{display:block}.sa-card{padding:12px}}
</style>
@endpush

@extends('layouts.portal')
@section('title', 'Historique des interventions · PharmaCare')
@section('page-title', 'Historique des interventions')
@section('content')
@php
    $statusLabels=['pending'=>'En attente de synchronisation','synced'=>'Synchronisée','error'=>'Échec'];
    $categoryColors=['general'=>'#22a447','access'=>'#7c3aed','security'=>'#9333ea','synchronization'=>'#2563eb','platform'=>'#f57c00'];
    $total=max(1,(int)$summary['total']);
@endphp
<main class="hi-page">
    <x-app-page-header title="Historique des interventions" description="Consultez toutes les modifications et actions effectuées sur les configurations des organisations." />
    @if(session('success'))<div class="hi-success"><span class="material-symbols-outlined">check_circle</span>{{ session('success') }}</div>@endif
    <div class="hi-layout">
        <section class="hi-main">
            <form method="get" class="hi-filters">
                <input type="hidden" name="tab" value="history">
                <label><span>Période — début</span><input type="date" name="date_from" value="{{ request('date_from') }}"></label>
                <label><span>Période — fin</span><input type="date" name="date_to" value="{{ request('date_to') }}"></label>
                <label><span>Organisation</span><select name="organization_id"><option value="">Toutes les organisations</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)request('organization_id')===(string)$organization->id)>{{ $organization->name }}</option>@endforeach</select></label>
                <label><span>Catégorie</span><select name="category"><option value="">Toutes les catégories</option>@foreach($categoryLabels as $key=>$label)<option value="{{ $key }}" @selected(request('category')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label><span>Type d’intervention</span><select name="intervention"><option value="">Tous</option><option value="creation" @selected(request('intervention')==='creation')>Création</option><option value="modification" @selected(request('intervention')==='modification')>Modification</option><option value="restoration" @selected(request('intervention')==='restoration')>Restauration</option></select></label>
                <label><span>Statut</span><select name="history_status"><option value="">Tous les statuts</option>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}" @selected(request('history_status')===$key)>{{ $label }}</option>@endforeach</select></label>
                <div class="hi-filter-actions"><button type="submit" class="hi-btn primary">Rechercher</button><a class="hi-btn outline" href="{{ route('configuration.platform-standards.index',['tab'=>'history']) }}"><span class="material-symbols-outlined">restart_alt</span>Réinitialiser</a></div>
            </form>

            <section class="hi-table-card">
                <div class="hi-table-wrap"><table><thead><tr><th>Date et heure</th><th>Organisation</th><th>Catégorie</th><th>Intervention</th><th>Version</th><th>Auteur</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
                @forelse($interventions as $item)
                    @php
                      $isRestore=$item->intervention_action==='restored';
                      $intervention=$isRestore?'Restauration de configuration':($item->previous_configuration_version?'Modification de configuration':'Création de configuration');
                    @endphp
                    <tr>
                        <td data-label="Date et heure"><strong>{{ optional($item->effective_at)->format('d/m/Y') }}</strong><small>{{ optional($item->effective_at)->format('H:i') }}</small></td>
                        <td data-label="Organisation"><strong>{{ optional($item->organization)->name ?: 'Organisation indisponible' }}</strong><small>{{ optional($item->organization)->geographic_access_type==='multi_country'?'Multipays':'Unipays' }}</small></td>
                        <td data-label="Catégorie"><span class="hi-category" style="--tag:{{ $categoryColors[$item->configuration_category] ?? '#64748b' }}">{{ $categoryLabels[$item->configuration_category] ?? 'Configuration' }}</span></td>
                        <td data-label="Intervention"><strong>{{ $intervention }}</strong><small>{{ collect($item->changes)->pluck('label')->take(2)->join(', ') ?: 'Version restaurée sans suppression' }}</small></td>
                        <td data-label="Version">v1.{{ max(0,$item->configuration_version-1) }}</td>
                        <td data-label="Auteur"><strong>{{ optional($item->appliedBy)->name ?: 'Système' }}</strong><small>Admin Sago</small></td>
                        <td data-label="Statut"><span class="hi-status {{ $item->synchronization_status }}">{{ $isRestore?'Restaurée':($statusLabels[$item->synchronization_status] ?? 'Appliquée') }}</span></td>
                        <td data-label="Actions"><a class="hi-view" title="Voir l’intervention" href="{{ route('configuration.platform-standards.history.show',$item) }}"><span class="material-symbols-outlined">visibility</span><span class="sr-only">Voir</span></a></td>
                    </tr>
                @empty<tr><td colspan="8"><div class="hi-empty"><span class="material-symbols-outlined">history_toggle_off</span><strong>Aucune intervention enregistrée pour cette période.</strong><p>Les configurations appliquées aux organisations apparaîtront automatiquement ici.</p></div></td></tr>@endforelse
                </tbody></table></div>
                @if($interventions->hasPages() || $interventions->total())<footer class="hi-pagination"><span>Affichage de {{ $interventions->firstItem() ?? 0 }} à {{ $interventions->lastItem() ?? 0 }} sur {{ $interventions->total() }} résultat(s)</span><div>{{ $interventions->onEachSide(1)->links() }}</div><form method="get">@foreach(request()->except(['per_page','page']) as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach<select name="per_page" onchange="this.form.submit()">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected($interventions->perPage()===$size)>{{ $size }} / page</option>@endforeach</select></form></footer>@endif
            </section>
        </section>

        <aside class="hi-aside">
            <section><h2>Résumé</h2><dl><div><dt>Total interventions</dt><dd>{{ $summary['total'] }}</dd></div><div><dt>Synchronisées</dt><dd class="green">{{ $summary['applied'] }}</dd></div><div><dt>En attente de synchronisation</dt><dd class="orange">{{ $summary['pending'] }}</dd></div><div><dt>Échecs</dt><dd class="red">{{ $summary['errors'] }}</dd></div><div><dt>Période sélectionnée</dt><dd>{{ request('date_from')||request('date_to')?'Personnalisée':'Toutes' }}</dd></div></dl></section>
            <section><h2>Répartition par catégorie</h2><div class="hi-donut" style="--security:{{ (($categoryDistribution['security']??0)/$total)*100 }}%;--sync:{{ (($categoryDistribution['synchronization']??0)/$total)*100 }}%;--general:{{ (($categoryDistribution['general']??0)/$total)*100 }}%;--access:{{ (($categoryDistribution['access']??0)/$total)*100 }}%"><strong>{{ $summary['total'] }}</strong><small>Total</small></div><ul>@foreach($categoryLabels as $key=>$label)<li><i style="background:{{ $categoryColors[$key] }}"></i><span>{{ $label }}</span><strong>{{ $categoryDistribution[$key]??0 }}</strong></li>@endforeach</ul></section>
        </aside>
    </div>
</main>
@endsection
@push('styles')
<style>
.hi-page{color:#24324a}.hi-layout{display:grid;grid-template-columns:minmax(0,1fr) 250px;gap:16px}.hi-main{min-width:0}.hi-filters,.hi-table-card,.hi-aside section{background:#fff;border:1px solid #e8edf3;border-radius:15px;box-shadow:0 8px 24px rgba(36,50,74,.035)}.hi-filters{padding:16px;display:grid;grid-template-columns:repeat(3,minmax(150px,1fr));gap:12px;margin-bottom:14px}.hi-filters label span{display:block;font-size:12px;margin:0 0 6px;color:#526078}.hi-filters input,.hi-filters select,.hi-pagination select{width:100%;height:42px;border:1px solid #dce3ec;border-radius:9px;background:#fff;padding:0 11px;color:#24324a}.hi-filter-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:9px}.hi-btn{height:42px;padding:0 16px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-weight:700;text-decoration:none}.hi-btn.primary{background:#f57c00;color:#fff;border:1px solid #f57c00}.hi-btn.outline{color:#24324a;border:1px solid #dce3ec;background:#fff}.hi-success{display:flex;gap:8px;align-items:center;background:#e9f8ed;color:#16843a;border:1px solid #bde9c8;padding:12px;border-radius:10px;margin-bottom:14px}.hi-table-card{overflow:hidden}.hi-table-wrap{overflow-x:auto}.hi-table-wrap table{width:100%;border-collapse:collapse;min-width:960px}.hi-table-wrap th{padding:12px;background:#f7f9fc;text-align:left;font-size:11px}.hi-table-wrap td{padding:13px 10px;border-top:1px solid #e8edf3;font-size:12px}.hi-table-wrap td strong,.hi-table-wrap td small{display:block}.hi-table-wrap td small{color:#778299;margin-top:4px}.hi-category,.hi-status{display:inline-block;padding:5px 8px;border-radius:7px;font-size:10px;font-weight:700}.hi-category{color:var(--tag);background:color-mix(in srgb,var(--tag) 12%,white)}.hi-status.pending{color:#b55b00;background:#fff3e3}.hi-status.synced{color:#16843a;background:#e9f8ed}.hi-status.error{color:#c52d29;background:#fff0ef}.hi-view{display:grid;place-items:center;width:34px;height:34px;color:#24324a;border:1px solid #e1e6ee;border-radius:9px;text-decoration:none}.hi-view .material-symbols-outlined{font-size:19px}.hi-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;color:#68758c;font-size:12px}.hi-pagination nav svg{width:18px}.hi-pagination nav>div:first-child{display:none}.hi-pagination nav>div:last-child{display:flex;align-items:center;gap:8px}.hi-pagination select{width:auto}.hi-aside{display:grid;align-content:start;gap:14px}.hi-aside section{padding:16px}.hi-aside h2{font-size:15px;margin:0 0 15px}.hi-aside dl{margin:0}.hi-aside dl div,.hi-aside li{display:flex;justify-content:space-between;gap:8px;padding:7px 0;font-size:12px}.hi-aside dt{color:#526078}.hi-aside dd{margin:0;font-weight:700}.green{color:#16843a}.orange{color:#f57c00}.red{color:#e53935}.hi-donut{width:120px;height:120px;margin:8px auto 16px;border-radius:50%;display:grid;place-content:center;text-align:center;background:conic-gradient(#9333ea 0 var(--security),#2563eb var(--security) calc(var(--security) + var(--sync)),#22a447 calc(var(--security) + var(--sync)) calc(var(--security) + var(--sync) + var(--general)),#7c3aed calc(var(--security) + var(--sync) + var(--general)) calc(var(--security) + var(--sync) + var(--general) + var(--access)),#f57c00 0);position:relative}.hi-donut:before{content:"";position:absolute;inset:22px;background:#fff;border-radius:50%}.hi-donut strong,.hi-donut small{position:relative;z-index:1}.hi-aside ul{list-style:none;margin:0;padding:0}.hi-aside li{align-items:center}.hi-aside li i{width:8px;height:8px;border-radius:50%}.hi-aside li span{flex:1}.hi-empty{text-align:center;padding:55px 20px;color:#6b778d}.hi-empty .material-symbols-outlined{font-size:42px}.hi-empty strong{display:block;color:#24324a;margin:8px}.hi-empty p{margin:0}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}
@media(max-width:1100px){.hi-layout{grid-template-columns:1fr}.hi-aside{grid-template-columns:1fr 1fr}.hi-filters{grid-template-columns:repeat(2,1fr)}}
@media(max-width:720px){.hi-filters{grid-template-columns:1fr}.hi-aside{grid-template-columns:1fr}.hi-table-wrap{overflow:visible;padding:10px}.hi-table-wrap table,.hi-table-wrap tbody{display:block;min-width:0}.hi-table-wrap thead{display:none}.hi-table-wrap tr{display:block;border:1px solid #e8edf3;border-radius:12px;margin-bottom:10px;padding:8px}.hi-table-wrap td{display:flex;justify-content:space-between;gap:14px;border:0;padding:8px;text-align:right}.hi-table-wrap td:before{content:attr(data-label);font-weight:700;color:#526078;text-align:left}.hi-table-wrap td[colspan]{display:block}.hi-pagination{flex-direction:column;align-items:stretch}.hi-pagination form{width:100%}}
</style>
@endpush

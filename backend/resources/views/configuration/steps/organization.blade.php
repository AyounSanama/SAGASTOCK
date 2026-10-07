@php
    $typeLabels = $organizationTypes;
    $countryNames = $countries->pluck('name', 'iso2');
@endphp
<section class="wizard-step-content organization-management">
    <header class="organization-page-head">
        <div>
            <span class="eyebrow">Étape 1</span>
            <h2>Organisations</h2>
            <p>Gérez les organisations autorisées à utiliser la plateforme.</p>
        </div>
        @if($canManageOrganizations)
            <a class="button primary always-visible-add"
               href="{{ route('configuration.workflow.start', ['flowType' => \App\Enums\ConfigurationFlowType::NewOrganization->value]) }}">
                ＋ Ajouter une organisation
            </a>
        @endif
    </header>

    @if(session('success'))
        <div class="alert success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error validation-summary" role="alert">
            <strong>Le formulaire contient {{ $errors->count() }} erreur(s).</strong>
            <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="organization-table-wrap">
        <table class="organization-table">
            <thead><tr>
                <th>Nom de l’organisation</th>
                <th>Type</th>
                <th>Périmètre / Pays</th>
                <th>Code</th>
                <th>Missions</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            @forelse($organizations as $item)
                <tr @class(['workflow-scope-row' => $workflow->scope_id === $item->id])>
                    <td><strong>{{ $item->name }}</strong><small>{{ $item->email ?: 'Aucun e-mail' }}</small></td>
                    <td>{{ $typeLabels[$item->organization_type] ?? 'Autre' }}</td>
                    <td><span class="scope-badge">{{ $item->geographic_access_type === 'multi_country' ? 'MULTIPAYS' : 'UNIPAYS' }}</span><small>{{ $item->countries->pluck('name')->join(', ') ?: ($countryNames[$item->country_code] ?? '—') }}</small></td>
                    <td><code>{{ $item->code }}</code></td>
                    <td><span class="count-badge">{{ $item->missions_count }}</span></td>
                    <td><span class="status-badge {{ $item->is_active ? 'success' : 'muted' }}">{{ $item->is_active ? 'Actif' : 'Inactif' }}</span></td>
                    <td>
                        <div class="row-actions">
                            <button class="action-link view" type="button" data-sheet-open="view-organization-{{ $item->id }}">Voir</button>
                            @if($canManageOrganizations)
                                <a class="action-link edit" href="{{ route('configuration.organization', ['_flow'=>$workflow->workflow_id, 'edit'=>$item->id]) }}">Modifier</a>
                            @endif
                            <a class="action-link missions" href="{{ route('organizations.missions.index', $item) }}">Voir les missions</a>
                            @if($canManageOrganizations)
                                <form method="post" action="{{ route('configuration.organization.archive', ['organization'=>$item, '_flow'=>$workflow->workflow_id]) }}" onsubmit="return confirm('Archiver cette organisation ? Toutes ses données seront conservées.')">
                                    @csrf @method('DELETE')
                                    <button class="action-link archive" type="submit">Archiver</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-organizations"><strong>Aucune organisation</strong><p>Ajoutez la première organisation autorisée à utiliser PharmaCare.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @foreach($organizations as $item)
        <x-form-sheet id="view-organization-{{ $item->id }}" title="{{ $item->name }}" description="Informations de l’organisation" width="680px">
            <dl class="organization-details">
                <div><dt>Type</dt><dd>{{ $typeLabels[$item->organization_type] ?? 'Autre' }}</dd></div>
                <div><dt>Type d’accès</dt><dd>{{ $item->geographic_access_type === 'multi_country' ? 'Multipays' : 'Unipays' }}</dd></div>
                <div class="full"><dt>Pays autorisés</dt><dd>{{ $item->countries->pluck('name')->join(', ') ?: ($countryNames[$item->country_code] ?? '—') }}</dd></div>
                <div><dt>Admin Coordination</dt><dd>{{ $item->users->first()?->name ?? '—' }}</dd></div>
                <div><dt>Projets actifs</dt><dd>{{ $item->projects_count }}</dd></div>
                <div><dt>Code</dt><dd>{{ $item->code }}</dd></div>
                <div><dt>Langue</dt><dd>{{ config('pharmacare_languages.catalog.'.($item->default_language ?: 'fr'), $item->default_language ?: 'Français') }}</dd></div>
                <div><dt>Téléphone</dt><dd>{{ $item->phone ?: '—' }}</dd></div>
                <div><dt>E-mail</dt><dd>{{ $item->email ?: '—' }}</dd></div>
                <div class="full"><dt>Adresse</dt><dd>{{ $item->address ?: '—' }}</dd></div>
                <div><dt>Responsable</dt><dd>{{ $item->manager_name ?: '—' }}</dd></div>
                <div><dt>Fonction</dt><dd>{{ $item->manager_title ?: '—' }}</dd></div>
                <div class="full"><dt>Description</dt><dd>{{ $item->description ?: '—' }}</dd></div>
            </dl>
            <div class="form-sheet-actions">
                <button class="button secondary" type="button" data-sheet-close="view-organization-{{ $item->id }}">Fermer</button>
            </div>
        </x-form-sheet>
    @endforeach

    @if($workflow->scope_id && $completedSteps->contains(1))
        <div class="organization-next-step">
            <div><strong>{{ $organization?->name }}</strong><p>L’organisation est enregistrée. Vous pouvez maintenant créer sa première mission.</p></div>
            <a class="button primary" href="{{ route('configuration.workflow.start', ['flowType'=>\App\Enums\ConfigurationFlowType::NewMission->value, 'organization'=>$workflow->scope_id]) }}">Créer une mission →</a>
        </div>
    @endif
</section>

@if($canManageOrganizations)
<x-form-sheet id="create-organization-sheet" title="Ajouter une organisation" description="Créez une nouvelle organisation indépendante. Le formulaire ne reprend aucune ancienne donnée." width="860px">
    <form method="post" action="{{ route('configuration.organization.save', ['_flow'=>$workflow->workflow_id]) }}" enctype="multipart/form-data" data-organization-mode="createOrganization">
        @csrf
        <input type="hidden" name="_form_mode" value="createOrganization">
        @include('configuration.components.organization-form-fields', ['item'=>null, 'mode'=>'createOrganization'])
        <div class="form-sheet-actions">
            <button class="button secondary" type="button" data-sheet-close="create-organization-sheet">Annuler</button>
            <button class="button primary" type="submit">Créer l’organisation</button>
        </div>
    </form>
</x-form-sheet>

@if($editingOrganization)
<x-form-sheet id="edit-organization-sheet" title="Modifier l’organisation" description="Les changements s’appliqueront uniquement à {{ $editingOrganization->name }}." width="860px">
    <form method="post" action="{{ route('configuration.organization.update', ['organization'=>$editingOrganization, '_flow'=>$workflow->workflow_id]) }}" enctype="multipart/form-data" data-organization-mode="editOrganization">
        @csrf @method('PUT')
        <input type="hidden" name="_form_mode" value="editOrganization">
        @include('configuration.components.organization-form-fields', ['item'=>$editingOrganization, 'mode'=>'editOrganization'])
        <div class="form-sheet-actions">
            <button class="button secondary" type="button" data-sheet-close="edit-organization-sheet">Annuler</button>
            <button class="button primary" type="submit">Enregistrer les modifications</button>
        </div>
    </form>
</x-form-sheet>
@endif
@endif

@push('styles')
<style>
.organization-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding:26px 28px;border-bottom:1px solid var(--pc-color-border)}.organization-page-head h2{margin:4px 0 6px}.organization-page-head p{margin:0;color:var(--pc-color-text-muted)}.always-visible-add{white-space:nowrap}.organization-table-wrap{margin:24px 28px;overflow:auto;border:1px solid var(--pc-color-border);border-radius:16px}.organization-table{width:100%;min-width:1100px;border-collapse:collapse}.organization-table th{padding:13px 15px;background:var(--pc-color-background);color:var(--pc-color-text-muted);text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.03em}.organization-table td{padding:15px;border-top:1px solid var(--pc-color-border);vertical-align:middle}.organization-table td>strong,.organization-table td>small{display:block}.organization-table td>small{margin-top:4px;color:var(--pc-color-text-muted)}.organization-table code{padding:4px 8px;border-radius:7px;background:var(--pc-color-background);color:var(--pc-color-text)}.workflow-scope-row{background:var(--pc-color-background)}.count-badge{display:inline-grid;place-items:center;min-width:30px;height:30px;border-radius:10px;background:var(--pc-status-info-bg);color:var(--pc-status-info-text);font-weight:800}.row-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.row-actions form{margin:0}.action-link{border:1px solid transparent!important;background:transparent!important;box-shadow:none!important;padding:7px 9px!important;border-radius:9px!important;font-size:12px!important;font-weight:750!important;text-decoration:none;white-space:nowrap}.action-link.view{color:var(--pc-status-success-text)!important;border-color:var(--pc-status-success-bg)!important}.action-link.edit{color:var(--pc-color-primary-strong)!important;border-color:var(--pc-color-border)!important}.action-link.missions{color:var(--pc-status-info-text)!important;border-color:var(--pc-status-info-bg)!important}.action-link.archive{color:var(--pc-status-danger-text)!important;border-color:var(--pc-status-danger-bg)!important}.organization-next-step{margin:0 28px 28px;padding:18px 20px;border:1px solid var(--pc-color-border);border-radius:15px;background:var(--pc-color-primary-soft);display:flex;align-items:center;justify-content:space-between;gap:20px}.organization-next-step p{margin:4px 0 0;color:var(--pc-color-text-muted)}.empty-organizations{text-align:center;padding:35px;color:var(--pc-color-text-muted)}.organization-details{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:0}.organization-details div{padding:13px;border-radius:12px;background:var(--pc-color-background)}.organization-details .full{grid-column:1/-1}.organization-details dt{font-size:12px;color:var(--pc-color-text-muted)}.organization-details dd{margin:5px 0 0;font-weight:700}.organization-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.organization-form-grid .full{grid-column:1/-1}.organization-form-grid label{display:block;margin-bottom:6px;font-weight:700;color:var(--pc-color-text)}.organization-form-grid input,.organization-form-grid select,.organization-form-grid textarea{width:100%;min-height:46px;padding:10px 12px;border:1px solid var(--pc-color-border);border-radius:11px;background:#fff;font:inherit}.organization-form-grid textarea{min-height:90px;resize:vertical}.form-sheet{border:0;padding:0;background:transparent;max-width:none;width:100%;height:100%;margin:0}.form-sheet::backdrop{background:rgba(8,21,43,.62)}.form-sheet-panel{position:absolute;right:0;top:0;height:100%;width:min(var(--sheet-width,760px),calc(100% - 20px));background:#fff;display:flex;flex-direction:column;box-shadow:-18px 0 48px rgba(9,29,58,.2);border-radius:22px 0 0 22px}.form-sheet-header{display:flex;justify-content:space-between;padding:22px 26px;border-bottom:1px solid var(--pc-color-border)}.form-sheet-header h2{margin:0}.form-sheet-header p{margin:5px 0 0;color:var(--pc-color-text-muted)}.form-sheet-close{width:42px;height:42px;padding:0!important;border:1px solid var(--pc-color-border)!important;background:var(--pc-color-background)!important;color:var(--pc-status-info-text)!important;box-shadow:none!important}.form-sheet-body{padding:24px 26px 30px;overflow:auto}.form-sheet-actions{position:sticky;bottom:-30px;margin:24px -26px -30px;padding:17px 26px;background:#fff;border-top:1px solid var(--pc-color-border);display:flex;justify-content:flex-end;gap:10px}.field-error{display:block;color:var(--pc-status-danger-text);margin-top:5px}@media(max-width:760px){.organization-page-head,.organization-next-step{flex-direction:column}.organization-form-grid,.organization-details{grid-template-columns:1fr}.organization-form-grid .full,.organization-details .full{grid-column:auto}.form-sheet-panel{top:auto;bottom:0;width:100%;height:94%;border-radius:22px 22px 0 0}.form-sheet-actions{flex-direction:column-reverse}}
</style>
<style>
.organization-form-sections{display:grid;gap:18px}.organization-form-section{padding:18px;border:1px solid var(--pc-color-border);border-radius:15px;background:#fff}.organization-form-section>header{display:flex;align-items:center;gap:11px;margin-bottom:17px}.organization-form-section>header>span{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:var(--pc-color-primary-soft);color:var(--pc-color-primary-soft-text);font-weight:900}.organization-form-section h3,.organization-form-section p{margin:0}.organization-form-section p{margin-top:3px;color:var(--pc-color-text-muted);font-size:12px}.access-type-options{display:grid;grid-template-columns:1fr 1fr;gap:12px}.access-type-options label{display:flex;gap:9px;padding:14px;border:1px solid var(--pc-color-border);border-radius:12px}.access-type-options input{width:auto!important}.access-type-options strong,.access-type-options small{display:block}.access-type-options small{margin-top:3px;color:var(--pc-color-text-muted)}.country-selector{margin-top:15px}.country-selector>label{display:block;margin-bottom:6px}.country-selector select{margin-top:8px}.field-hint{display:block;margin-top:6px;color:var(--pc-color-text-muted)}.locked-role{display:flex;align-items:center;gap:10px;margin-bottom:15px;padding:12px;border-radius:12px;background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.locked-role small,.locked-role strong{display:block}.scope-badge{display:inline-flex;padding:4px 7px;border-radius:999px;background:var(--pc-status-neutral-bg);color:var(--pc-status-neutral-text);font-size:10px;font-weight:900}.organization-table td>.scope-badge+small{display:block;margin-top:5px}.temporary-password{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:18px 28px 0;padding:13px 15px;border:1px solid var(--pc-status-success-bg);border-radius:12px;background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.temporary-password code{padding:5px 8px;border-radius:7px;background:#fff}.temporary-password small{width:100%}.section-errors{padding:14px;border-radius:12px;background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.section-errors ul{margin:8px 0 0;padding-left:20px}@media(max-width:760px){.access-type-options{grid-template-columns:1fr}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const openSheet=id=>{const sheet=document.getElementById(id);if(sheet&&!sheet.open)sheet.showModal()};
    document.querySelectorAll('[data-sheet-open]').forEach(button=>button.addEventListener('click',()=>openSheet(button.dataset.sheetOpen)));
    @if(request()->boolean('create') || old('_form_mode') === 'createOrganization') openSheet('create-organization-sheet'); @endif
    @if($editingOrganization || old('_form_mode') === 'editOrganization') openSheet('edit-organization-sheet'); @endif
    document.querySelectorAll('[data-geographic-form]').forEach(form=>{
        const select=form.querySelector('[data-country-select]'), label=form.querySelector('[data-country-label]'), search=form.querySelector('[data-country-search]');
        const refresh=()=>{const multi=form.querySelector('[name="geographic_access_type"]:checked')?.value==='multi_country';select.multiple=multi;select.size=multi?8:1;label.textContent=multi?'Pays autorisés *':'Pays principal *';if(!multi&&select.selectedOptions.length>1)[...select.options].forEach((option,index)=>option.selected=index===0)};
        form.querySelectorAll('[name="geographic_access_type"]').forEach(input=>input.addEventListener('change',refresh));refresh();
        search?.addEventListener('input',()=>{const query=search.value.toLocaleLowerCase('fr');[...select.options].forEach(option=>option.hidden=!option.text.toLocaleLowerCase('fr').includes(query))});
        const activation=form.querySelector('[data-activation-mode]');const refreshPassword=()=>form.querySelectorAll('[data-password-field]').forEach(field=>{const visible=activation?.value!=='invitation';field.hidden=!visible;field.querySelector('input').required=visible});activation?.addEventListener('change',refreshPassword);refreshPassword();
    });
});
</script>
@endpush

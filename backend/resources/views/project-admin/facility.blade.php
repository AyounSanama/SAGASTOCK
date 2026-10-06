{{-- Niveau 5 — Configuration de la FOSA (maquette AdminProjet 03) : création et modification. --}}
@extends('layouts.portal')
@section('title', ($facility ? $facility->name : 'Nouvelle FOSA').' · PharmaCare')
@section('page-title', 'Projet & FOSA')
@include('project-admin.partials.styles')
@php
    $current = $facility;
    $donor = $project->donors->first();
    $selectedPopulations = collect(old('target_population_ids', $current?->targetPopulations->pluck('id')->all() ?? []));
    $selectedPathologies = collect(old('pathology_ids', $current?->pathologies->pluck('id')->all() ?? []));
    $defaults = $facilityOptions['supply_defaults'];
    $value = fn (string $field) => old($field, $current ? $current->{$field} : ($defaults[$field] ?? null));
    $date = fn (string $field) => old($field, $current ? $current->{$field}?->format('Y-m-d') : ($defaults[$field] ?? null));
    $error = fn (string $field) => $errors->has($field) ? 'invalid' : '';
    $status = $current ? \App\Services\ProjectAdminWorkspaceService::status($current) : null;
@endphp
@push('styles')<style>
.fc-form{display:grid;gap:18px}.fc-card{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;padding:20px 20px 22px}.fc-card h2{margin:0 0 14px;font-size:16px}
.fc-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px}.fc-fields.three{grid-template-columns:repeat(3,minmax(0,1fr))}.fc-fields .wide{grid-column:1/-1}
.fc-field{display:block;font-weight:600;font-size:14px}.fc-field :is(input,select){display:block;box-sizing:border-box;width:100%;margin-top:6px;min-height:42px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 12px;font:inherit;font-weight:400;background:var(--pc-color-surface,#fff);color:var(--pc-color-text)}
.fc-field input[readonly]{background:var(--pc-color-surface-subtle,#f3f3f1);color:var(--pc-color-text-muted)}.fc-field.invalid :is(input,select){border-color:var(--pc-status-danger-text)}.fc-error{display:block;margin-top:5px;color:var(--pc-status-danger-text);font-size:13px;font-weight:600}
.fc-legend{font-weight:600;font-size:14px;margin:4px 0 8px}.fc-chips{display:flex;flex-wrap:wrap;gap:8px}
.fc-chip{position:relative;display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:999px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);font-size:14px;cursor:pointer}.fc-chip input{width:16px;height:16px;margin:0;accent-color:var(--pc-color-primary-strong)}
.fc-chip:has(input:checked){border-color:var(--pc-color-primary);background:var(--pc-color-primary-soft);color:var(--pc-color-primary-soft-text)}.fc-help{margin:8px 0 0;color:var(--pc-color-text-muted);font-size:13px}
.fc-count{font-size:30px;font-weight:800;color:var(--pc-color-primary-strong)}.fc-count small{font-size:15px;font-weight:600;color:var(--pc-color-text-muted);margin-left:6px}
.fc-switch{display:flex;justify-content:space-between;align-items:center;gap:12px;font-weight:600}.fc-switch input{width:22px;height:22px;accent-color:var(--pc-color-primary-strong)}
.fc-actions{display:flex;gap:10px}
@media(max-width:650px){.fc-fields,.fc-fields.three{grid-template-columns:1fr}.fc-actions{width:100%}.fc-actions .pa-btn{flex:1}}
</style>@endpush
@section('content')
<div class="pa">
    <form id="facility-form" method="post" action="{{ $current ? route('project-admin.facilities.update', $current) : route('project-admin.facilities.store') }}" data-facility-form data-preview-url="{{ route('project-admin.facilities.preview') }}">
        @csrf @if($current) @method('PUT') @endif
        <header class="pa-head">
            <div>
                <p class="pa-eyebrow" style="text-transform:none;letter-spacing:0;font-weight:500"><a href="{{ route('modules.health-facilities') }}" style="color:inherit">Projet {{ $project->code }}</a> / <a href="{{ route('modules.health-facilities') }}" style="color:inherit">FOSA</a> / <strong>{{ $current?->name ?? 'Nouvelle FOSA' }}</strong></p>
                <h1>Configuration de la FOSA</h1>
                <p>Les champs grisés viennent de la configuration de la Coordination et ne sont pas modifiables ici.</p>
            </div>
            <div class="fc-actions"><a class="pa-btn" href="{{ route('modules.health-facilities') }}">Annuler</a><button class="pa-btn primary" type="submit">Enregistrer</button></div>
        </header>
        @if(session('status'))<div class="pa-notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="pa-notice error" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif

        <div class="pa-grid" style="margin-top:20px">
            <div class="fc-form">
                <section class="fc-card">
                    <h2>1. Informations générales</h2>
                    <div class="fc-fields">
                        <label class="fc-field">Pays<input value="{{ $project->mission?->country?->name }}" readonly tabindex="-1"></label>
                        <label class="fc-field">ONG<input value="{{ $project->implementing_partner ?: $project->organization?->name }}" readonly tabindex="-1"></label>
                        <label class="fc-field">Code bailleur / projet<input value="{{ collect([$donor?->name, $project->code])->filter()->join(' · ') }}" readonly tabindex="-1"></label>
                        <label class="fc-field">Titre du projet<input value="{{ $project->name }}" readonly tabindex="-1"></label>
                    </div>
                </section>

                <section class="fc-card">
                    <h2>2. Détails de la formation sanitaire</h2>
                    <div class="fc-fields">
                        <label class="fc-field {{ $error('name') }}">Nom de la FOSA *<input name="name" value="{{ old('name', $current?->name) }}" required maxlength="180">@error('name')<span class="fc-error">{{ $message }}</span>@enderror</label>
                        <label class="fc-field {{ $error('code') }}">Code FOSA *<input name="code" value="{{ old('code', $current?->code) }}" required maxlength="50" placeholder="ex. FOSA-001">@error('code')<span class="fc-error">{{ $message }}</span>@enderror</label>
                        <label class="fc-field {{ $error('care_level_id') }}">Niveau de soins *
                            <select name="care_level_id" required data-preview>
                                <option value="">Sélectionner</option>
                                @foreach($facilityOptions['care_levels'] as $level)<option value="{{ $level->id }}" @selected(old('care_level_id', $current?->care_level_id) === $level->id)>{{ str_repeat('— ', max(0, $level->depth - 1)) }}{{ $level->name }}</option>@endforeach
                            </select>@error('care_level_id')<span class="fc-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="fc-field {{ $error('facility_category_id') }}">Catégorie de FOSA *
                            <select name="facility_category_id" required data-preview>
                                <option value="">Sélectionner</option>
                                @foreach($facilityOptions['facility_categories'] as $category)<option value="{{ $category->id }}" @selected(old('facility_category_id', $current?->facility_category_id) === $category->id)>{{ $category->name }}</option>@endforeach
                            </select>@error('facility_category_id')<span class="fc-error">{{ $message }}</span>@enderror
                        </label>
                        <div class="wide">
                            <p class="fc-legend">Population cible *</p>
                            <div class="fc-chips" data-preview>
                                @forelse($facilityOptions['target_populations'] as $population)
                                    <label class="fc-chip"><input type="checkbox" name="target_population_ids[]" value="{{ $population['id'] }}" @checked($selectedPopulations->contains($population['id']))>{{ $population['name'] }}</label>
                                @empty<p class="fc-help">Aucune population configurée pour ce projet : la Coordination doit d’abord compléter la configuration de la Liste Standard.</p>@endforelse
                            </div>
                            @error('target_population_ids')<span class="fc-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="wide">
                            <p class="fc-legend">Pathologies / activités prises en charge *</p>
                            <div class="fc-chips" data-preview>
                                @forelse($facilityOptions['pathologies'] as $pathology)
                                    <label class="fc-chip"><input type="checkbox" name="pathology_ids[]" value="{{ $pathology['id'] }}" @checked($selectedPathologies->contains($pathology['id']))>{{ $pathology['name'] }}</label>
                                @empty<p class="fc-help">Aucune pathologie configurée pour ce projet.</p>@endforelse
                            </div>
                            @error('pathology_ids')<span class="fc-error">{{ $message }}</span>@enderror
                            <p class="fc-help">Listes proposées selon la configuration validée par la Coordination.</p>
                        </div>
                    </div>
                </section>

                <section class="fc-card" id="approvisionnement">
                    <h2 style="margin-bottom:2px">3. Paramètres d’approvisionnement</h2>
                    <p class="fc-help" style="margin:0 0 14px">Pharmacie du projet → FOSA. Préremplis avec les valeurs du projet, modifiables pour cette FOSA (historisés). Utilisés pour les risques de péremption, de rupture et les commandes.</p>
                    <div class="fc-fields three">
                        <label class="fc-field {{ $error('order_period_months') }}">Périodicité de commande (mois) *
                            <select name="order_period_months" required><option value="">Sélectionner</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected((int) $value('order_period_months') === $month)>{{ $month }}</option>@endforeach</select>
                            @error('order_period_months')<span class="fc-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="fc-field {{ $error('delivery_lead_time_months') }}">Délai de livraison DL (mois) *
                            <select name="delivery_lead_time_months" required><option value="">Sélectionner</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected((int) $value('delivery_lead_time_months') === $month)>{{ $month }}</option>@endforeach</select>
                            @error('delivery_lead_time_months')<span class="fc-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="fc-field {{ $error('safety_stock_months') }}">Stock de sécurité (mois) *
                            <select name="safety_stock_months" required><option value="">Sélectionner</option>@foreach(\App\Models\HealthFacility::SAFETY_STOCK_OPTIONS as $months)<option value="{{ $months }}" @selected($value('safety_stock_months') !== null && (float) $value('safety_stock_months') === (float) $months)>{{ str_replace('.', ',', (string) $months) }}</option>@endforeach</select>
                            @error('safety_stock_months')<span class="fc-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="fc-field {{ $error('inventory_date') }}">Date d’inventaire<input type="date" name="inventory_date" value="{{ $date('inventory_date') }}">@error('inventory_date')<span class="fc-error">{{ $message }}</span>@enderror</label>
                        <label class="fc-field {{ $error('order_submission_date') }}">Date de soumission de commande<input type="date" name="order_submission_date" value="{{ $date('order_submission_date') }}">@error('order_submission_date')<span class="fc-error">{{ $message }}</span>@enderror</label>
                        <label class="fc-field {{ $error('order_receipt_date') }}">Date de réception de commande<input type="date" name="order_receipt_date" value="{{ $date('order_receipt_date') }}">@error('order_receipt_date')<span class="fc-error">{{ $message }}</span>@enderror</label>
                    </div>
                </section>
            </div>

            <aside class="pa-side">
                <section class="pa-card pa-pad">
                    <h2 style="margin:0 0 6px;font-size:16px">Liste Standard générée</h2>
                    <p class="fc-count" data-preview-count>{{ $standardListCount ?? '—' }}<small>produits</small></p>
                    <p class="fc-help" style="margin-top:0">Calculée à partir du niveau de soins, de la population, des pathologies et de la catégorie de la FOSA.</p>
                    @if($current)<p style="margin:10px 0 0"><a class="pa-link" href="{{ route('project-admin.standard-list', ['facility' => $current->id]) }}">Aperçu de la liste</a></p>@endif
                </section>
                <section class="pa-alert pa-lock"><span class="material-symbols-outlined" aria-hidden="true">lock</span><span>Seule la Coordination peut ajouter ou retirer des produits de la Liste Standard.</span></section>
                <section class="pa-card pa-pad">
                    <h2 style="margin:0 0 12px;font-size:16px">Statut</h2>
                    @if($current && $current->validation_status !== 'validated')
                        <p style="margin:0 0 10px"><span class="pa-badge {{ $status['tone'] }}">{{ $current->validation_status_label }}</span></p>
                        @if($current->validation_status === 'refused' && $current->refusal_reason)<p class="fc-help">Motif du refus : {{ $current->refusal_reason }}. Corrigez la fiche : elle repassera « en attente ».</p>@endif
                        @if($current->validation_status === 'pending')<p class="fc-help">La Coordination doit valider la FOSA avant la création de ses comptes.</p>@endif
                    @endif
                    @if(! $current)
                        <p class="fc-help" style="margin:0">À l’enregistrement, la FOSA est « en attente » jusqu’à sa validation par la Coordination.</p>
                    @else
                        <input type="hidden" name="is_active" value="0">
                        <label class="fc-switch">FOSA active<input type="checkbox" name="is_active" value="1" @checked(old('is_active', $current->is_active))></label>
                        <p class="fc-help">Une FOSA inactive ne peut plus se connecter ; ses données restent conservées.</p>
                    @endif
                </section>
            </aside>
        </div>
    </form>
</div>
@endsection

@push('scripts')<script>
(() => {
    const form = document.querySelector('[data-facility-form]');
    if (!form) return;
    const output = form.querySelector('[data-preview-count]');
    let timer;
    // « Liste Standard générée » : recalculée à chaque changement des critères.
    const refresh = async () => {
        const params = new URLSearchParams();
        ['care_level_id', 'facility_category_id'].forEach((name) => { const value = form.elements[name]?.value; if (value) params.append(name, value); });
        form.querySelectorAll('input[name="target_population_ids[]"]:checked, input[name="pathology_ids[]"]:checked').forEach((input) => params.append(input.name, input.value));
        const response = await fetch(`${form.dataset.previewUrl}?${params}`, {headers: {'Accept': 'application/json'}});
        if (!response.ok) return;
        const {count} = await response.json();
        output.firstChild.textContent = count === null ? '—' : count;
    };
    form.querySelectorAll('[data-preview]').forEach((element) => element.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(refresh, 250); }));
    if (output.firstChild.textContent.trim() === '—') refresh();
})();
</script>@endpush

{{-- Niveau 2 — Étape 3 : niveaux de soins, populations, pathologies, puis produits retenus. --}}
@php
    $stepNumber = 3;
    $stepSubtitle = 'la Liste Standard est générée à partir de ces choix.';
    $selected = fn (string $key, string $id) => in_array($id, old($key, $state[$key]), true);
    $depthLabels = \App\Services\CareLevelHierarchyService::DEPTH_LABELS;
@endphp
@extends('projects.wizard.layout')
@section('wizard')
<form id="wizard-form" method="post" action="{{ route('projects.wizard.standard-list.update', $project) }}" data-wizard-list data-refresh-url="{{ route('projects.wizard.show', [$project, 'standard-list']) }}">
    @csrf @method('PUT')
    <section class="wz-card">
        <h3>Niveaux de soins *</h3>
        <div class="wz-chips" data-choice-group>
            @foreach($careLevels as $level)
                <label class="wz-chip"><input type="checkbox" name="care_level_ids[]" value="{{ $level['id'] }}" @checked($selected('care_level_ids', $level['id']))>{{ $level['name'] }}@if(($level['depth'] ?? 1) > 1)<small>{{ $depthLabels[$level['depth']] ?? '' }}</small>@endif</label>
            @endforeach
            @if($canAddService)<a class="wz-link" style="align-self:center;margin-left:4px" href="{{ route('projects.medical-references') }}#ajouter-un-service">+ Ajouter un service</a>@endif
        </div>
        @error('care_level_ids')<span class="wz-error">{{ $message }}</span>@enderror

        <h3>Population cible *</h3>
        <div class="wz-chips" data-choice-group>
            @forelse($populations as $population)
                <label class="wz-chip"><input type="checkbox" name="target_population_ids[]" value="{{ $population['id'] }}" @checked($selected('target_population_ids', $population['id']))>{{ $population['name'] }}</label>
            @empty<p class="wz-sub">Aucune population cible dans le référentiel médical.</p>@endforelse
        </div>
        @error('target_population_ids')<span class="wz-error">{{ $message }}</span>@enderror

        <div id="wizard-activities">
            @php($others = $pathologies->where('suggested', false))
            <h3>Pathologies et activités *</h3>
            <p class="wz-sub" style="margin-top:-4px">{{ $suggestions ? 'Proposées selon le niveau de soins et la population choisis.' : 'Aucune correspondance enregistrée pour ces choix : toutes les pathologies et activités sont proposées.' }}</p>
            <div class="wz-chips" data-choice-group>
                @forelse($pathologies as $pathology)
                    <label class="wz-chip" @unless($pathology['suggested']) hidden data-other-activity @endunless><input type="checkbox" name="pathology_ids[]" value="{{ $pathology['id'] }}" @checked($selected('pathology_ids', $pathology['id']))>{{ $pathology['name'] }}@if($pathology['type'] === 'laboratory_exam')<small>Examen</small>@endif</label>
                @empty<p class="wz-sub">Aucune pathologie dans le référentiel médical.</p>@endforelse
                @if($others->isNotEmpty())<button class="wz-link" type="button" style="align-self:center;border:0;background:none;cursor:pointer;font:inherit" data-show-activities>+ {{ $others->count() }} autres</button>@endif
            </div>
        </div>
    </section>

    <section class="wz-card" id="wizard-list" style="padding:0" aria-live="polite">
        <header style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:18px 20px 14px;flex-wrap:wrap">
            <div>
                <h2>Liste Standard générée : <span data-total>{{ $state['total'] }}</span> produits, <span data-retained>{{ $state['retained_count'] }}</span> retenus</h2>
                <p class="wz-sub" style="margin:4px 0 0">Cochez ou décochez selon les protocoles de votre organisation. Seule la Coordination peut modifier cette liste.</p>
            </div>
            @if($canAddService)<div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="wz-btn sm" type="button" data-open-dialog="import-dialog">Importer depuis Excel</button>
                <button class="wz-btn sm" type="button" data-open-dialog="product-dialog">+ Ajouter un produit</button>
            </div>@endif
        </header>
        @error('retained')<p class="wz-error" style="padding:0 20px">{{ $message }}</p>@enderror
        @if(session('import_errors'))<ul class="wz-error" style="margin:0 20px 12px;padding-left:18px">@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        @if($errors->hasAny(['import_file', 'new_product.code', 'new_product.name', 'new_product.barcode', 'code', 'barcode', 'file']))<ul class="wz-error" style="margin:0 20px 12px;padding-left:18px">@foreach(collect(['import_file', 'new_product.code', 'new_product.name', 'new_product.barcode', 'code', 'barcode', 'file'])->map(fn ($key) => $errors->first($key))->filter() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        @if($state['rows']->isEmpty())
            <p class="wz-sub" style="padding:0 20px 20px;margin:0">
                @if(empty($state['care_level_ids']) || empty($state['target_population_ids']))
                    Sélectionnez au moins un niveau de soins et une population cible pour générer la liste.
                @else
                    Aucun produit du catalogue ne correspond à ces choix. Ajoutez un produit ou complétez les correspondances du catalogue.
                @endif
            </p>
        @else
            <div style="padding:0 20px 12px"><input type="search" class="wz-search" placeholder="Rechercher un produit (code ou désignation)" aria-label="Rechercher un produit" data-product-search style="width:min(360px,100%);min-height:38px;border:1px solid var(--pc-color-border);border-radius:10px;padding:6px 12px;font:inherit"></div>
            <div style="overflow:auto;max-height:520px;border-top:1px solid var(--pc-color-border)">
                <table class="wz-table">
                    <thead><tr><th>Retenu</th><th>Code</th><th>Désignation</th><th>Conditionnement</th><th>Pathologie / activité</th></tr></thead>
                    <tbody>
                    @foreach($state['rows'] as $row)
                        @php($product = $row['product'])
                        <tr data-product-row data-search="{{ mb_strtolower($product->code.' '.$product->name.' '.$product->strength) }}">
                            <td>
                                <input type="hidden" name="listed[]" value="{{ $product->id }}">
                                @if($row['added'])<input type="hidden" name="added[]" value="{{ $product->id }}">@endif
                                <input type="checkbox" name="retained[]" value="{{ $product->id }}" @checked($row['retained']) aria-label="Retenir {{ $product->name }}">
                            </td>
                            <td class="wz-code">{{ $product->code }}</td>
                            <td><strong>{{ trim($product->name.' '.$product->strength) }}</strong>@if($row['added']) <span class="wz-tag">Ajouté</span>@endif</td>
                            <td class="wz-muted">{{ $product->packaging ?: collect([$product->dosageForm?->name, $product->baseUnit?->name])->filter()->join(', ') ?: '—' }}</td>
                            <td class="wz-muted">{{ $row['pathology'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="wz-sub" style="padding:12px 20px;margin:0;border-top:1px solid var(--pc-color-border)"><span data-visible>{{ $state['total'] }}</span> sur {{ $state['total'] }} produits affichés</p>
        @endif
    </section>

    <div class="wz-foot">
        <a class="wz-btn" href="{{ route('projects.wizard.show', [$project, 'identity', 'step' => 'donor']) }}">Précédent</a>
        <div class="wz-end"><span>Les champs marqués * sont obligatoires</span><button class="wz-btn primary" type="submit" name="intent" value="next">Suivant : paramètres d’approvisionnement</button></div>
    </div>
</form>

@if($canAddService)
{{-- Niveau 6 : les champs « Nouveau produit » et le fichier sont envoyés avec le formulaire de l'étape (choix en cours conservés). --}}
<dialog class="wz-dialog" id="product-dialog" aria-labelledby="product-dialog-title" style="width:min(560px,calc(100% - 32px))">
    <h2 id="product-dialog-title">Ajouter un produit</h2>
    @if($addable->isNotEmpty())
        <form method="dialog" data-product-form>
            <p>Produit déjà au catalogue de l’organisation, ajouté en plus des produits générés.</p>
            <div style="display:flex;gap:10px;align-items:flex-end">
                <label class="wz-field" style="flex:1;margin-bottom:0">Produit du catalogue
                    <select name="product">
                        <option value="">Sélectionner un produit</option>
                        @foreach($addable as $product)<option value="{{ $product->id }}">{{ trim($product->name.' '.$product->strength) }}{{ $product->code ? ' · '.$product->code : '' }}</option>@endforeach
                    </select>
                </label>
                <button class="wz-btn" type="submit">Ajouter</button>
            </div>
        </form>
        <hr style="border:0;border-top:1px solid var(--pc-color-border);margin:18px 0">
    @endif
    <p>Nouveau produit, avec la codification de votre organisation.</p>
    <div class="wz-fields">
        <label class="wz-field">Code *<input form="wizard-form" name="new_product[code]" maxlength="60" disabled data-dialog-input></label>
        <label class="wz-field">Code-barres<input form="wizard-form" name="new_product[barcode]" maxlength="190" inputmode="numeric" disabled data-dialog-input></label>
        <label class="wz-field wide">Désignation *<input form="wizard-form" name="new_product[name]" maxlength="190" placeholder="Ex. Amoxicilline 500 mg" disabled data-dialog-input></label>
        <label class="wz-field wide">Conditionnement<input form="wizard-form" name="new_product[packaging]" maxlength="190" placeholder="Ex. Gélule, boîte de 1000" disabled data-dialog-input></label>
        <label class="wz-field">Niveau de soins
            <select form="wizard-form" name="new_product[care_level_id]" disabled data-dialog-input>
                <option value="">Ajout manuel (toutes les FOSA)</option>
                @foreach($selectedCareLevels as $level)<option value="{{ $level['id'] }}">{{ $level['name'] }}</option>@endforeach
            </select>
        </label>
        <label class="wz-field">Pathologie / activité
            <select form="wizard-form" name="new_product[activity_id]" disabled data-dialog-input>
                <option value="">Aucune</option>
                @foreach($selectedActivities as $activity)<option value="{{ $activity['id'] }}">{{ $activity['name'] }}</option>@endforeach
            </select>
        </label>
    </div>
    <footer><button class="wz-btn" type="button" data-close-dialog>Annuler</button><button class="wz-btn primary" type="submit" form="wizard-form" formaction="{{ route('projects.wizard.standard-list.products.store', $project) }}" formnovalidate>Créer et retenir</button></footer>
</dialog>

<dialog class="wz-dialog" id="import-dialog" aria-labelledby="import-dialog-title" style="width:min(560px,calc(100% - 32px))">
    <h2 id="import-dialog-title">Importer depuis Excel</h2>
    <p>Liste standard totale de votre organisation. Colonnes : Code, Désignation, Conditionnement, Niveau de soins, Population, Pathologie / activité, Catégorie FOSA, Code-barres. Un produit déjà connu (même code) est mis à jour ; les produits importés sont retenus dans la liste.</p>
    <label class="wz-field">Fichier Excel (.xlsx) *<input form="wizard-form" type="file" name="import_file" accept=".xlsx,.xls" disabled data-dialog-input></label>
    <p><a class="wz-link" href="{{ route('coordination.standard-list.template') }}">Télécharger le modèle Excel</a></p>
    <footer><button class="wz-btn" type="button" data-close-dialog>Annuler</button><button class="wz-btn primary" type="submit" form="wizard-form" formaction="{{ route('projects.wizard.standard-list.import', $project) }}" formenctype="multipart/form-data" formnovalidate>Importer</button></footer>
</dialog>
@endif
@endsection

@push('styles')<style>
.wz-table{width:100%;border-collapse:collapse;min-width:720px}.wz-table th{position:sticky;top:0;z-index:1;background:var(--pc-color-surface-subtle,#f6f6f4);font-size:13px;text-align:left;color:var(--pc-color-text-muted);padding:11px 16px;border-bottom:1px solid var(--pc-color-border)}
.wz-table td{padding:11px 16px;border-bottom:1px solid var(--pc-color-border);vertical-align:middle;font-size:14px}.wz-table tr:last-child td{border-bottom:0}.wz-table input[type=checkbox]{width:18px;height:18px;accent-color:var(--pc-color-primary-strong)}
.wz-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;color:var(--pc-color-text-muted)}.wz-muted{color:var(--pc-color-text-muted)}
.wz-tag{display:inline-flex;margin-left:6px;padding:1px 8px;border-radius:999px;background:var(--pc-status-info-bg);color:var(--pc-status-info-text);font-size:12px;font-weight:600}
</style>@endpush

@push('scripts')<script>
(() => {
    const form = document.querySelector('[data-wizard-list]');
    if (!form) return;
    let added = [];

    // Les choix régénèrent la liste sans perdre les produits décochés ni les ajouts.
    const refresh = async () => {
        const params = new URLSearchParams({refresh: '1'});
        new FormData(form).forEach((value, key) => { if (!['_token', '_method', 'intent'].includes(key)) params.append(key, value); });
        added.forEach((id) => params.append('added[]', id));
        added = [];
        const url = `${form.dataset.refreshUrl}?${params}`;
        const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        if (!response.ok) return;
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const list = page.getElementById('wizard-list');
        if (list) document.getElementById('wizard-list').replaceWith(list);
        // Niveau 6 : pathologies et activités proposées selon le niveau de soins et la population.
        const activities = page.getElementById('wizard-activities');
        const expanded = !document.querySelector('[data-show-activities]');
        if (activities) {
            document.getElementById('wizard-activities').replaceWith(activities);
            if (expanded) showActivities();
        }
        const dialog = page.getElementById('product-dialog');
        const current = document.getElementById('product-dialog');
        if (current && dialog) current.replaceWith(dialog); else if (current) current.remove(); else if (dialog) document.body.appendChild(dialog);
        bindList();
    };
    let timer;
    form.addEventListener('change', (event) => {
        if (!event.target.closest('[data-choice-group]')) return;
        clearTimeout(timer);
        timer = setTimeout(refresh, 250);
    });
    const showActivities = () => {
        document.querySelectorAll('[data-other-activity]').forEach((chip) => { chip.hidden = false; });
        document.querySelector('[data-show-activities]')?.remove();
    };

    // Boîtes de dialogue : leurs champs ne partent avec le formulaire que lorsqu'elles sont ouvertes.
    const toggleInputs = (dialog, enabled) => dialog.querySelectorAll('[data-dialog-input]').forEach((input) => { input.disabled = !enabled; });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-show-activities]')) { showActivities(); return; }
        const opener = event.target.closest('[data-open-dialog]');
        if (opener) {
            const dialog = document.getElementById(opener.dataset.openDialog);
            if (!dialog) return;
            toggleInputs(dialog, true);
            dialog.showModal();
            return;
        }
        const closer = event.target.closest('[data-close-dialog]');
        if (closer) closer.closest('dialog')?.close();
    });
    document.addEventListener('close', (event) => { if (event.target.matches?.('dialog')) toggleInputs(event.target, false); }, true);

    const bindList = () => {
        const list = document.getElementById('wizard-list');
        const count = () => { const node = list.querySelector('[data-retained]'); if (node) node.textContent = list.querySelectorAll('input[name="retained[]"]:checked').length; };
        list.addEventListener('change', (event) => { if (event.target.name === 'retained[]') count(); });
        const search = list.querySelector('[data-product-search]');
        search?.addEventListener('input', () => {
            const term = search.value.trim().toLowerCase();
            let visible = 0;
            list.querySelectorAll('[data-product-row]').forEach((row) => { const match = !term || row.dataset.search.includes(term); row.hidden = !match; visible += match ? 1 : 0; });
            list.querySelector('[data-visible]').textContent = visible;
        });
        const dialog = document.getElementById('product-dialog');
        if (dialog && !dialog.dataset.bound) {
            dialog.dataset.bound = '1';
            dialog.querySelector('[data-product-form]')?.addEventListener('submit', (event) => {
                event.preventDefault();
                const id = event.target.product.value;
                if (!id) return;
                added.push(id);
                dialog.close();
                refresh();
            });
        }
    };
    bindList();
})();
</script>@endpush

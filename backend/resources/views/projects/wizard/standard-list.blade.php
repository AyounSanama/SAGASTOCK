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

        <h3>Pathologies et activités *</h3>
        <p class="wz-sub" style="margin-top:-4px">Proposées selon le niveau de soins et la population choisis.</p>
        <div class="wz-chips" data-choice-group>
            @forelse($pathologies as $pathology)
                <label class="wz-chip"><input type="checkbox" name="pathology_ids[]" value="{{ $pathology['id'] }}" @checked($selected('pathology_ids', $pathology['id']))>{{ $pathology['name'] }}</label>
            @empty<p class="wz-sub">Aucune pathologie dans le référentiel médical.</p>@endforelse
        </div>
    </section>

    <section class="wz-card" id="wizard-list" style="padding:0" aria-live="polite">
        <header style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:18px 20px 14px;flex-wrap:wrap">
            <div>
                <h2>Liste Standard générée : <span data-total>{{ $state['total'] }}</span> produits, <span data-retained>{{ $state['retained_count'] }}</span> retenus</h2>
                <p class="wz-sub" style="margin:4px 0 0">Cochez ou décochez selon les protocoles de votre organisation. Seule la Coordination peut modifier cette liste.</p>
            </div>
            @if($addable->isNotEmpty())<button class="wz-btn sm" type="button" data-open-dialog="product-dialog">+ Ajouter un produit</button>@endif
        </header>
        @error('retained')<p class="wz-error" style="padding:0 20px">{{ $message }}</p>@enderror
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

@if($addable->isNotEmpty())
<dialog class="wz-dialog" id="product-dialog" aria-labelledby="product-dialog-title">
    <form method="dialog" data-product-form>
        <h2 id="product-dialog-title">Ajouter un produit</h2>
        <p>Produit du catalogue de l’organisation, ajouté à la Liste Standard du projet en plus des produits générés.</p>
        <label class="wz-field">Produit *
            <select name="product" required>
                <option value="">Sélectionner un produit</option>
                @foreach($addable as $product)<option value="{{ $product->id }}">{{ trim($product->name.' '.$product->strength) }}{{ $product->code ? ' · '.$product->code : '' }}</option>@endforeach
            </select>
        </label>
        <footer><button class="wz-btn" type="button" data-close-dialog>Annuler</button><button class="wz-btn primary" type="submit">Ajouter</button></footer>
    </form>
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
        const dialog = page.getElementById('product-dialog');
        const current = document.getElementById('product-dialog');
        if (current && dialog) current.replaceWith(dialog); else if (current) current.remove(); else if (dialog) document.body.appendChild(dialog);
        bindList();
    };
    let timer;
    form.querySelectorAll('[data-choice-group]').forEach((group) => group.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(refresh, 250); }));

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
        list.querySelector('[data-open-dialog="product-dialog"]')?.addEventListener('click', () => dialog?.showModal());
        if (dialog && !dialog.dataset.bound) {
            dialog.dataset.bound = '1';
            dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());
            dialog.querySelector('[data-product-form]').addEventListener('submit', (event) => {
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

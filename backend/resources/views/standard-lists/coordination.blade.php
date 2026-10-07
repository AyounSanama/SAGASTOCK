{{-- Niveau 6 — Liste Standard de la Coordination : par projet, décochage par FOSA, code-barres, ajout et import Excel. --}}
@extends('layouts.portal')
@section('title', 'Liste Standard · PharmaCare')
@section('page-title', 'Liste Standard')
@include('project-admin.partials.styles')
@php
    $rows = $list['rows'] ?? collect();
    $editFacility = $facility && $canManage;
    $packaging = fn ($product) => $product->packaging ?: collect([$product->dosageForm?->name, $product->baseUnit?->name])->filter()->join(', ') ?: '—';
@endphp
@push('styles')<style>
.sl-chips{display:flex;gap:8px;flex-wrap:wrap}.sl-chip{padding:7px 14px;border-radius:999px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);font:inherit;font-size:14px;cursor:pointer;color:inherit}
.sl-chip[aria-pressed="true"]{background:var(--pc-color-primary-strong);border-color:var(--pc-color-primary-strong);color:#fff}
.sl-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;color:var(--pc-color-text-muted)}
.sl-actions{display:flex;gap:8px;flex-wrap:wrap}.sl-table input[type=checkbox]{width:18px;height:18px;accent-color:var(--pc-color-primary-strong)}
.sl-barcode{display:flex;align-items:center;gap:8px;justify-content:space-between}.sl-save{position:sticky;bottom:0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 18px;border-top:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);border-radius:0 0 14px 14px}
.sl-dialog{width:min(520px,calc(100% - 32px));border:0;border-radius:16px;padding:22px;box-shadow:0 24px 70px rgba(15,30,55,.24);color:var(--pc-color-text);background:var(--pc-color-surface,#fff)}.sl-dialog::backdrop{background:rgba(15,30,55,.45)}
.sl-dialog h2{margin:0 0 4px;font-size:18px}.sl-dialog p{margin:0 0 16px;color:var(--pc-color-text-muted);font-size:14px}.sl-dialog footer{display:flex;justify-content:flex-end;gap:10px;margin-top:6px}
.sl-field{display:block;font-weight:600;font-size:14px;margin-bottom:14px}.sl-field :is(input,select){display:block;box-sizing:border-box;width:100%;margin-top:6px;min-height:42px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 12px;font:inherit;font-weight:400;background:var(--pc-color-surface,#fff);color:var(--pc-color-text)}
.sl-field small{display:block;margin-top:5px;color:var(--pc-color-text-muted);font-weight:400;font-size:13px}.sl-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.sl-errors{margin:0 0 14px;padding:12px 16px 12px 34px;border-radius:12px;background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text);font-size:14px}
@media(max-width:650px){.sl-grid{grid-template-columns:1fr}}
</style>@endpush
@section('content')
<div class="pa">
    <header class="pa-head">
        <div>
            <p class="pa-eyebrow">Liste Standard</p>
            <h1>{{ $list['title'] ?? 'Aucun projet' }}</h1>
            @if($project)<p>Projet {{ $project->code }} · {{ $project->status_label ?? '' }}</p>@endif
        </div>
        @if($project && $canManage)
            <div class="sl-actions">
                <a class="pa-btn" href="{{ route('coordination.standard-list.template') }}"><span class="material-symbols-outlined" aria-hidden="true">download</span>Modèle Excel</a>
                <button class="pa-btn" type="button" data-open-dialog="import-dialog"><span class="material-symbols-outlined" aria-hidden="true">upload_file</span>Importer depuis Excel</button>
                <button class="pa-btn primary" type="button" data-open-dialog="product-dialog"><span class="material-symbols-outlined" aria-hidden="true">add</span>Ajouter un produit</button>
            </div>
        @endif
    </header>

    @if(session('status'))<div class="pa-notice" role="status" style="margin-top:16px">{{ session('status') }}</div>@endif
    @if(session('import_errors'))<ul class="sl-errors" style="margin-top:12px">@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    @if($errors->any())<ul class="sl-errors" style="margin-top:12px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <p class="pa-alert pa-lock" style="margin:18px 0"><span class="material-symbols-outlined" aria-hidden="true">lock</span><span>Seule la Coordination modifie la Liste Standard. Choisissez une FOSA pour décocher les articles qu’elle ne doit pas recevoir (ex. un produit que votre organisation n’envoie pas dans les centres de santé).</span></p>

    <form class="pa-filters" method="get" action="{{ route('coordination.standard-list.show') }}" style="align-items:center">
        <label style="display:flex;gap:10px;align-items:center;font-weight:600">Projet
            <select name="project" onchange="this.form.facility.value='';this.form.submit()">
                @forelse($projects as $item)<option value="{{ $item->id }}" @selected($project?->id === $item->id)>{{ $item->code }} · {{ $item->name }}</option>@empty<option value="">Aucun projet</option>@endforelse
            </select>
        </label>
        <label style="display:flex;gap:10px;align-items:center;font-weight:600">FOSA
            <select name="facility" onchange="this.form.submit()">
                <option value="">Toutes les FOSA du projet</option>
                @foreach($facilities as $item)<option value="{{ $item->id }}" @selected($facility?->id === $item->id)>{{ $item->name }}</option>@endforeach
            </select>
        </label>
        <input type="search" placeholder="Code, désignation ou code-barres" aria-label="Rechercher un produit" data-list-search>
    </form>
    @if($list && $list['pathologies']->isNotEmpty())
        <div class="sl-chips" role="group" aria-label="Pathologie / activité" style="margin-bottom:14px">
            <button class="sl-chip" type="button" aria-pressed="true" data-pathology="">Toutes</button>
            @foreach($list['pathologies'] as $pathology)<button class="sl-chip" type="button" aria-pressed="false" data-pathology="{{ $pathology }}">{{ $pathology }}</button>@endforeach
        </div>
    @endif

    <section class="pa-card">
        @if(! $project)
            <p class="pa-empty">Aucun projet dans votre coordination. Créez un projet depuis « Ma Coordination ».</p>
        @elseif($rows->isEmpty())
            <p class="pa-empty">Aucune Liste Standard validée pour ce projet. Configurez-la dans l’assistant du projet (étape 3) ou ajoutez des produits.
                @if($canManage)<a class="pa-link" href="{{ route('projects.wizard.show', [$project, 'standard-list']) }}">Configurer la Liste Standard</a>@endif</p>
        @else
            @if($editFacility)<form method="post" action="{{ route('coordination.standard-list.facility.update', [$project, $facility]) }}" id="facility-form">@csrf @method('PUT')@endif
            <div class="pa-scroll"><table class="pa-table sl-table" style="min-width:920px">
                <thead><tr><th>{{ $facility ? 'Retenu pour la FOSA' : 'Retenu' }}</th><th>Code</th><th>Désignation</th><th>Conditionnement</th><th>Pathologie / activité</th><th>Code-barres</th></tr></thead>
                <tbody>
                @foreach($rows as $row)
                    @php($product = $row['product'])
                    @php($barcode = $barcodes->get($product->id))
                    <tr data-row data-search="{{ mb_strtolower($product->code.' '.$product->name.' '.$product->strength.' '.$barcode) }}" data-pathology="{{ $row['pathology'] }}">
                        <td>
                            @if($editFacility)
                                <input type="checkbox" name="retained[]" value="{{ $product->id }}" @checked($row['retained']) aria-label="Retenir {{ $product->name }} pour {{ $facility->name }}">
                            @else
                                <span class="pa-badge {{ $row['retained'] ? 'success' : 'neutral' }}">{{ $row['retained'] ? 'Oui' : 'Non' }}</span>
                            @endif
                        </td>
                        <td class="sl-code">{{ $product->code }}</td>
                        <td><strong>{{ trim($product->name.' '.$product->strength) }}</strong></td>
                        <td class="pa-muted">{{ $packaging($product) }}</td>
                        <td class="pa-muted">{{ $row['pathology'] ?? '—' }}</td>
                        <td>
                            <div class="sl-barcode">
                                <span class="{{ $barcode ? 'sl-code' : 'pa-muted' }}">{{ $barcode ?? '—' }}</span>
                                @if($canManage)<button class="pa-btn sm" type="button" data-barcode-edit data-action="{{ route('coordination.standard-list.barcode.update', [$project, $product]) }}" data-name="{{ $product->name }}" data-value="{{ $barcode }}">{{ $barcode ? 'Modifier' : 'Associer' }}</button>@endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @if($editFacility)
                <div class="sl-save">
                    <span><span data-retained>{{ $rows->where('retained', true)->count() }}</span> sur {{ $rows->count() }} articles retenus pour {{ $facility->name }}</span>
                    <button class="pa-btn primary" type="submit">Enregistrer pour cette FOSA</button>
                </div>
                </form>
            @else
                <footer class="pa-foot"><span><span data-visible>{{ $rows->count() }}</span> sur {{ $rows->count() }} produits · {{ $rows->where('retained', true)->count() }} retenus</span></footer>
            @endif
        @endif
    </section>
</div>

@if($project && $canManage)
<dialog class="sl-dialog" id="product-dialog" aria-labelledby="product-dialog-title">
    <form method="post" action="{{ route('coordination.standard-list.products.store', $project) }}">
        @csrf
        <h2 id="product-dialog-title">Ajouter un produit</h2>
        <p>Nouveau produit avec la codification de votre organisation, ajouté à la Liste Standard du projet {{ $project->code }}.</p>
        <div class="sl-grid">
            <label class="sl-field">Code *<input name="code" required maxlength="60" value="{{ old('code') }}"></label>
            <label class="sl-field">Code-barres<input name="barcode" maxlength="190" value="{{ old('barcode') }}" inputmode="numeric"></label>
        </div>
        <label class="sl-field">Désignation *<input name="name" required maxlength="190" value="{{ old('name') }}" placeholder="Ex. Amoxicilline 500 mg"></label>
        <label class="sl-field">Conditionnement<input name="packaging" maxlength="190" value="{{ old('packaging') }}" placeholder="Ex. Gélule, boîte de 1000"></label>
        <label class="sl-field">Niveau de soins
            <select name="care_level_id"><option value="">Tous (ajout manuel)</option>@foreach($careLevels as $level)<option value="{{ $level['id'] }}">{{ str_repeat('— ', max(0, ($level['depth'] ?? 1) - 1)) }}{{ $level['name'] }}</option>@endforeach</select>
            <small>Avec un niveau de soins, le produit est aussi proposé aux FOSA selon leurs critères.</small>
        </label>
        <div class="sl-grid">
            <label class="sl-field">Population<select name="target_population_id"><option value="">Toutes</option>@foreach($populations as $population)<option value="{{ $population['id'] }}">{{ $population['name'] }}</option>@endforeach</select></label>
            <label class="sl-field">Pathologie / activité<select name="activity_id"><option value="">Aucune</option>@foreach($activities as $activity)<option value="{{ $activity['id'] }}">{{ $activity['name'] }}</option>@endforeach</select></label>
        </div>
        <footer><button class="pa-btn" type="button" data-close-dialog>Annuler</button><button class="pa-btn primary" type="submit">Ajouter</button></footer>
    </form>
</dialog>

<dialog class="sl-dialog" id="import-dialog" aria-labelledby="import-dialog-title">
    <form method="post" enctype="multipart/form-data" action="{{ route('coordination.standard-list.import', $project) }}">
        @csrf
        <h2 id="import-dialog-title">Importer depuis Excel</h2>
        <p>Colonnes : Code, Désignation, Conditionnement, Niveau de soins, Population, Pathologie / activité, Catégorie FOSA, Code-barres. Les produits existants (même code) sont mis à jour ; les produits importés sont ajoutés à la Liste Standard du projet {{ $project->code }}.</p>
        <label class="sl-field">Fichier Excel (.xlsx) *<input type="file" name="file" accept=".xlsx,.xls" required></label>
        <p><a class="pa-link" href="{{ route('coordination.standard-list.template') }}">Télécharger le modèle Excel</a></p>
        <footer><button class="pa-btn" type="button" data-close-dialog>Annuler</button><button class="pa-btn primary" type="submit">Importer</button></footer>
    </form>
</dialog>

<dialog class="sl-dialog" id="barcode-dialog" aria-labelledby="barcode-dialog-title">
    <form method="post" data-barcode-form>
        @csrf @method('PUT')
        <h2 id="barcode-dialog-title">Code-barres</h2>
        <p data-barcode-product></p>
        <label class="sl-field">Code-barres<input name="barcode" maxlength="190" inputmode="numeric" autocomplete="off"><small>Laissez vide pour retirer le lien. Un code-barres n’appartient qu’à un seul produit.</small></label>
        <footer><button class="pa-btn" type="button" data-close-dialog>Annuler</button><button class="pa-btn primary" type="submit">Enregistrer</button></footer>
    </form>
</dialog>
@endif
@endsection

@push('scripts')<script>
(() => {
    const search = document.querySelector('[data-list-search]');
    const chips = [...document.querySelectorAll('button[data-pathology]')];
    const rows = [...document.querySelectorAll('[data-row]')];
    const visible = document.querySelector('[data-visible]');
    let pathology = '';
    const apply = () => {
        const term = (search?.value || '').trim().toLowerCase();
        let count = 0;
        rows.forEach((row) => {
            const show = (!term || row.dataset.search.includes(term)) && (!pathology || row.dataset.pathology === pathology);
            row.hidden = !show;
            count += show ? 1 : 0;
        });
        if (visible) visible.textContent = count;
    };
    search?.addEventListener('input', apply);
    chips.forEach((chip) => chip.addEventListener('click', () => {
        pathology = chip.dataset.pathology;
        chips.forEach((other) => other.setAttribute('aria-pressed', other === chip ? 'true' : 'false'));
        apply();
    }));

    const retained = document.querySelector('[data-retained]');
    document.getElementById('facility-form')?.addEventListener('change', (event) => {
        if (event.target.name === 'retained[]' && retained) retained.textContent = document.querySelectorAll('#facility-form input[name="retained[]"]:checked').length;
    });

    document.querySelectorAll('[data-open-dialog]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.openDialog)?.showModal()));
    document.querySelectorAll('.sl-dialog [data-close-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog').close()));
    const barcodeDialog = document.getElementById('barcode-dialog');
    document.querySelectorAll('[data-barcode-edit]').forEach((button) => button.addEventListener('click', () => {
        const form = barcodeDialog.querySelector('[data-barcode-form]');
        form.action = button.dataset.action;
        form.barcode.value = button.dataset.value || '';
        barcodeDialog.querySelector('[data-barcode-product]').textContent = button.dataset.name;
        barcodeDialog.showModal();
        form.barcode.focus();
    }));
    @if($errors->hasAny(['code', 'name', 'packaging', 'care_level_id', 'activity_id', 'target_population_id']))document.getElementById('product-dialog')?.showModal();@endif
})();
</script>@endpush

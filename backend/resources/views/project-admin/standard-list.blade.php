{{-- Niveau 5 — Liste Standard en consultation (maquette AdminProjet 04). --}}
@extends('layouts.portal')
@section('title', 'Liste Standard · PharmaCare')
@section('page-title', 'Liste Standard')
@include('project-admin.partials.styles')
@php
    $facility = $list['facility'];
    $rows = $list['rows'];
@endphp
@push('styles')<style>
.sl-chips{display:flex;gap:8px;flex-wrap:wrap}.sl-chip{padding:7px 14px;border-radius:999px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);font:inherit;font-size:14px;cursor:pointer;color:inherit}
.sl-chip[aria-pressed="true"]{background:var(--pc-color-primary-strong);border-color:var(--pc-color-primary-strong);color:#fff}
.sl-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;color:var(--pc-color-text-muted)}
</style>@endpush
@section('content')
<div class="pa">
    <header class="pa-head">
        <div><p class="pa-eyebrow">Liste Standard</p><h1>{{ $list['title'] }}</h1></div>
        @if($rows->isNotEmpty())<a class="pa-btn" href="{{ route('project-admin.standard-list.export', array_filter(['facility' => $facility?->id])) }}"><span class="material-symbols-outlined" aria-hidden="true">download</span>Exporter (Excel)</a>@endif
    </header>
    <p class="pa-alert pa-lock" style="margin:18px 0"><span class="material-symbols-outlined" aria-hidden="true">lock</span><span>Consultation uniquement. L’ajout ou le retrait de produits est réservé à la Coordination.</span></p>

    <form class="pa-filters" method="get" action="{{ route('project-admin.standard-list') }}" style="align-items:center" data-list-filters>
        <label style="display:flex;gap:10px;align-items:center;font-weight:600">FOSA
            <select name="facility" onchange="this.form.submit()">
                <option value="">Toutes les FOSA du projet</option>
                @foreach($facilities as $item)<option value="{{ $item->id }}" @selected($facility?->id === $item->id)>{{ $item->name }}</option>@endforeach
            </select>
        </label>
        <input type="search" placeholder="Code ou désignation" aria-label="Rechercher un produit" data-list-search>
        @if($list['pathologies']->isNotEmpty())
            <div class="sl-chips" role="group" aria-label="Pathologie / activité">
                <button class="sl-chip" type="button" aria-pressed="true" data-pathology="">Toutes</button>
                @foreach($list['pathologies'] as $pathology)<button class="sl-chip" type="button" aria-pressed="false" data-pathology="{{ $pathology }}">{{ $pathology }}</button>@endforeach
            </div>
        @endif
    </form>

    <section class="pa-card">
        @if($rows->isEmpty())
            <p class="pa-empty">La Coordination n’a pas encore validé de Liste Standard pour ce projet.</p>
        @else
            <div class="pa-scroll"><table class="pa-table" style="min-width:820px">
                <thead><tr><th>Code</th><th>Désignation</th><th>Conditionnement</th><th>Pathologie / activité</th><th>Retenu</th></tr></thead>
                <tbody>
                @foreach($rows as $row)
                    @php($product = $row['product'])
                    <tr data-row data-search="{{ mb_strtolower($product->code.' '.$product->name.' '.$product->strength) }}" data-pathology="{{ $row['pathology'] }}">
                        <td class="sl-code">{{ $product->code }}</td>
                        <td><strong>{{ trim($product->name.' '.$product->strength) }}</strong></td>
                        <td class="pa-muted">{{ $product->packaging ?: collect([$product->dosageForm?->name, $product->baseUnit?->name])->filter()->join(', ') ?: '—' }}</td>
                        <td class="pa-muted">{{ $row['pathology'] ?? '—' }}</td>
                        <td><span class="pa-badge {{ $row['retained'] ? 'success' : 'neutral' }}">{{ $row['retained'] ? 'Oui' : 'Non' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <footer class="pa-foot"><span><span data-visible>{{ $rows->count() }}</span> sur {{ $rows->count() }} produits{{ $facility ? ' · '.$rows->where('retained', true)->count().' retenus pour '.$facility->name : '' }}</span></footer>
        @endif
    </section>
</div>
@endsection

@push('scripts')<script>
(() => {
    const search = document.querySelector('[data-list-search]');
    const chips = [...document.querySelectorAll('[data-pathology]')].filter((element) => element.tagName === 'BUTTON');
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
})();
</script>@endpush

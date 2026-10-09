@extends('layouts.portal')
@section('title', 'Rapports · PharmaCare')
@section('page-title', 'Rapports')
@php
    $generated = \Illuminate\Support\Carbon::parse($report['generated_at'])->setTimezone(config('app.timezone'))->locale('fr');
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ',');
    // Mêmes blocs, libellés et ordre que l'écran mobile « Rapports opérationnels ».
    $cards = [
        ['Stock', 'inventory_2', 'info', [
            'Quantité disponible' => $qty($report['stock']['available_quantity']),
            'Quantité réservée' => $qty($report['stock']['reserved_quantity']),
            'Lots disponibles' => $report['stock']['lots'],
        ]],
        ['Réceptions', 'move_to_inbox', 'primary', [
            'Total' => $report['receipts']['total'],
            'Validées' => $report['receipts']['validated'],
        ]],
        ['Inventaires', 'fact_check', 'expiry', [
            'Total' => $report['inventories']['total'],
            'Clôturés' => $report['inventories']['validated'],
            'Ouverts' => $report['inventories']['open'],
        ]],
        ['Propositions de commande', 'shopping_cart', 'success', [
            'Total' => $report['orders']['total'],
            'En cours' => $report['orders']['pending'],
        ]],
        ['Dispensations', 'medication', 'primary', [
            'Total' => $report['dispensations']['total'],
            'Ce mois' => $report['dispensations']['this_month'],
        ]],
    ];
@endphp
@push('styles')<style>
.rp{width:100%;box-sizing:border-box;max-width:1440px;margin:auto;padding:28px 32px 60px;color:var(--pc-color-text)}
.rp-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap}.rp-head h1{margin:0;font-size:24px}.rp-head p{margin:4px 0 0;color:var(--pc-color-text-muted)}
.rp-head select{min-height:40px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 12px;font:inherit;background:var(--pc-color-surface,#fff);color:var(--pc-color-text)}
.rp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:20px}
.rp-card{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;padding:18px}
.rp-card header{display:flex;align-items:center;gap:12px;margin-bottom:14px}.rp-card h2{margin:0;font-size:17px}
.rp-icon{display:inline-flex;align-items:center;justify-content:center;flex:0 0 40px;width:40px;height:40px;border-radius:50%}.rp-icon .material-symbols-outlined{font-size:22px;line-height:1;width:auto;height:auto}
.rp-icon.rp-info{background:var(--pc-status-info-bg);color:var(--pc-status-info-text)}.rp-icon.rp-primary{background:var(--pc-color-primary-soft);color:var(--pc-color-primary-strong)}.rp-icon.rp-success{background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.rp-icon.rp-expiry{background:#f1eaf7;color:#5b2c83}
.rp-values{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:12px}.rp-values small{display:block;color:var(--pc-color-text-muted);font-size:13px}.rp-values strong{font-size:24px}
@media(max-width:650px){.rp{padding:18px 16px 40px}.rp-head form,.rp-head select{width:100%}}
</style>@endpush
@section('content')
<div class="rp">
    <header class="rp-head">
        <div><h1>Rapports opérationnels</h1><p>{{ $organization->name }}, situation au {{ $generated->translatedFormat('j F Y') }} à {{ $generated->format('H:i') }}</p></div>
        @if($organizations->count() > 1)
            <form method="get" action="{{ route('modules.reports') }}">
                <select name="organization_id" aria-label="Organisation" onchange="this.form.submit()">
                    @foreach($organizations as $item)<option value="{{ $item->id }}" @selected($item->id === $organization->id)>{{ $item->name }}</option>@endforeach
                </select>
            </form>
        @endif
    </header>
    <section class="rp-grid">
        @foreach($cards as [$title, $icon, $tone, $values])
            <article class="rp-card">
                <header><span class="rp-icon rp-{{ $tone }}" aria-hidden="true"><span class="material-symbols-outlined">{{ $icon }}</span></span><h2>{{ $title }}</h2></header>
                <div class="rp-values">
                    @foreach($values as $label => $value)<div><small>{{ $label }}</small><strong>{{ $value }}</strong></div>@endforeach
                </div>
            </article>
        @endforeach
    </section>
</div>
@endsection

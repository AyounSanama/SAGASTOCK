<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Réceptions pharmaceutiques · PharmaCare</title>
<style>
.page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:22px}.page-head h1{margin:0}.page-head p{margin:7px 0 0;color:var(--pc-muted)}.crumbs{display:flex;gap:8px;align-items:center;color:var(--pc-muted);font-size:12px;margin-bottom:14px}.crumbs a{color:var(--pc-blue)}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}.stat{background:#fff;border:1px solid var(--pc-border);border-radius:16px;padding:18px}.stat span{display:block;color:var(--pc-muted);font-size:12px}.stat strong{display:block;margin-top:8px;font-size:27px;color:var(--pc-ink)}.stat:nth-child(2) strong{color:var(--pc-orange)}.stat:nth-child(3) strong{color:var(--pc-green)}.filters{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end;margin-bottom:18px}.receipt-list{display:grid;gap:12px}.receipt{display:grid;grid-template-columns:minmax(180px,1.2fr) 1fr .8fr .8fr auto;gap:15px;align-items:center;background:#fff;border:1px solid var(--pc-border);border-radius:16px;padding:17px}.receipt h3{margin:0 0 4px;font-size:15px}.receipt p,.receipt small{margin:0;color:var(--pc-muted);font-size:11px}.label{display:block;color:var(--pc-muted);font-size:10px;margin-bottom:4px}.status{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:11px;font-weight:800}.status.draft{color:var(--pc-orange);background:rgba(255,122,0,.1)}.status.validated{color:var(--pc-green);background:rgba(22,163,74,.1)}.empty-state{text-align:center;padding:38px}.empty-state strong{display:block;margin-bottom:6px}.receipt-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.receipt-form-grid .wide{grid-column:1/-1}.items-head{display:flex;justify-content:space-between;align-items:center;margin:24px 0 12px}.items-head h3{margin:0}.receipt-item{position:relative;border:1px solid var(--pc-border);border-radius:15px;padding:16px;margin-bottom:12px;background:#FAFBFD}.item-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:13px}.item-title strong{font-size:13px}.remove-item{min-height:36px!important;padding:7px 10px!important;background:#fff!important;color:var(--pc-red)!important;border:1px solid rgba(239,68,68,.35)!important;box-shadow:none!important}.item-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:11px}.item-grid .wide{grid-column:1/-1}.form-help{margin:7px 0 0;color:var(--pc-muted);font-size:10px}.sheet-actions .secondary{background:#fff!important}.notice{margin-bottom:16px}.pagination{margin-top:18px}@media(max-width:1050px){.stats{grid-template-columns:repeat(2,1fr)}.receipt{grid-template-columns:1fr 1fr}.receipt .actions{grid-column:1/-1}.filters{grid-template-columns:1fr 1fr}.filters .search{grid-column:1/-1}}@media(max-width:650px){.page-head{display:block}.page-head button{margin-top:14px;width:100%}.stats{grid-template-columns:1fr 1fr}.receipt{grid-template-columns:1fr}.receipt .actions{grid-column:auto}.receipt-form-grid,.item-grid{grid-template-columns:1fr}.receipt-form-grid .wide,.item-grid .wide{grid-column:auto}}
</style>
</head>
<body>
@include('components.app-sidebar')
<main>
<nav class="crumbs"><a href="{{ route('dashboard') }}">Tableau de bord</a><span>›</span><a href="{{ route('organizations.index') }}">Organisations</a><span>›</span><span>Réceptions pharmaceutiques</span></nav>
<header class="page-head"><div><h1>Réceptions pharmaceutiques</h1><p>Contrôlez les livraisons, les lots, les écarts et leur intégration au stock.</p></div>@if($canManage)<button type="button" data-sheet-open="receipt-create-sheet">＋ Nouvelle réception</button>@endif</header>
@if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error"><strong>La réception n’a pas été enregistrée.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="stats">
<article class="stat"><span>Réceptions visibles</span><strong>{{ number_format($stats['total']) }}</strong></article>
<article class="stat"><span>Brouillons à valider</span><strong>{{ number_format($stats['draft']) }}</strong></article>
<article class="stat"><span>Réceptions validées</span><strong>{{ number_format($stats['validated']) }}</strong></article>
<article class="stat"><span>Quantité totale reçue</span><strong>{{ number_format($stats['received'],2,',',' ') }}</strong></article>
</section>
<section class="card">
<form class="filters" method="get">
<label class="search">Recherche<input name="search" value="{{ request('search') }}" placeholder="Référence, commande ou fournisseur"></label>
<label>Site<select name="site_id"><option value="">Tous les sites</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected(request('site_id')===$site->id)>{{ $site->name }}</option>@endforeach</select></label>
<label>Statut<select name="status"><option value="">Tous</option><option value="draft" @selected(request('status')==='draft')>Brouillon</option><option value="validated" @selected(request('status')==='validated')>Validée</option></select></label>
<button class="secondary" type="submit">Filtrer</button>
</form>
<div class="receipt-list">
@forelse($receipts as $receipt)
<article class="receipt">
<div><h3>{{ $receipt->reference }}</h3><p>Commande : {{ $receipt->order_reference ?: 'Non renseignée' }}</p></div>
<div><span class="label">Destination</span><strong>{{ $receipt->site?->name }}</strong><small>{{ $receipt->site?->healthFacility?->name }}</small></div>
<div><span class="label">Fournisseur / origine</span><strong>{{ $receipt->supplier?->name ?? 'Non renseigné' }}</strong></div>
<div><span class="label">Réception</span><strong>{{ $receipt->received_on?->format('d/m/Y') }}</strong><small>{{ $receipt->items->count() }} ligne(s)</small></div>
<div class="actions"><span class="status {{ $receipt->status }}">{{ $receipt->status === 'validated' ? 'Validée' : 'Brouillon' }}</span><a class="button secondary btn-sm" href="{{ route('organizations.receipts.show',[$organization,$receipt]) }}">Voir le rapport</a>@if($canManage && $receipt->status==='draft')<form method="post" action="{{ route('organizations.receipts.validate',[$organization,$receipt]) }}" onsubmit="return confirm('Valider cette réception et créditer définitivement le stock ?')">@csrf<button class="success btn-sm">Valider</button></form>@endif</div>
</article>
@empty
<div class="empty-state"><strong>Aucune réception enregistrée</strong><span>Créez la première réception pharmaceutique de cette organisation.</span></div>
@endforelse
</div>
<div class="pagination">{{ $receipts->links() }}</div>
</section>
</main>
@if($canManage)
<x-form-sheet id="receipt-create-sheet" title="Nouvelle réception pharmaceutique" description="Enregistrez la livraison comme brouillon. Le stock sera crédité uniquement après validation." width="980px">
<form method="post" action="{{ route('organizations.receipts.store',$organization) }}" id="receipt-form">@csrf
<div class="receipt-form-grid">
<label>Référence de réception<input name="reference" value="{{ old('reference','REC-'.now()->format('Ymd-His')) }}" required></label>
<label>Référence du bon de commande<input name="order_reference" value="{{ old('order_reference') }}"></label>
<label>Site destinataire<select name="site_id" required><option value="">Sélectionner</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected(old('site_id')===$site->id)>{{ $site->name }} · {{ $site->healthFacility?->name }}</option>@endforeach</select></label>
<label>Fournisseur / origine<select name="supplier_id"><option value="">Non renseigné</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id')===$supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>
<label>Date de réception<input type="date" name="received_on" value="{{ old('received_on',now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
<label class="wide">Observations<textarea name="notes" placeholder="État de la livraison, document associé, remarques…">{{ old('notes') }}</textarea></label>
</div>
<div class="items-head"><div><h3>Produits et lots reçus</h3><p class="form-help">Ajoutez une ligne par produit et par lot. Un même produit peut apparaître plusieurs fois.</p></div><button type="button" class="secondary btn-sm" id="add-receipt-item">＋ Ajouter une ligne</button></div>
<div id="receipt-items"></div>
<div class="sheet-actions"><button type="button" class="secondary" data-sheet-close="receipt-create-sheet">Annuler</button><button type="submit">Enregistrer le brouillon</button></div>
</form>
<template id="receipt-item-template">
<article class="receipt-item">
<div class="item-title"><strong>Ligne <span data-line-number></span></strong><button type="button" class="remove-item">Retirer</button></div>
<div class="item-grid">
<label class="wide">Médicament / produit<select data-field="product_id" required><option value="">Sélectionner un produit</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach</select></label>
<label>Numéro de lot<input data-field="batch_number" required></label>
<label>Date de péremption<input type="date" data-field="expires_on" min="{{ now()->addDay()->toDateString() }}" required></label>
<label>Quantité commandée<input type="number" step="0.0001" min="0" data-field="quantity_ordered" value="0"></label>
<label>Quantité reçue<input type="number" step="0.0001" min="0.0001" data-field="quantity_received" required></label>
<label>Quantité acceptée<input type="number" step="0.0001" min="0" data-field="quantity_accepted" required></label>
<label>Quantité rejetée<input type="number" step="0.0001" min="0" data-field="quantity_rejected" value="0"></label>
<label>Coût unitaire<input type="number" step="0.0001" min="0" data-field="unit_cost"></label>
<label class="wide">Justification de l’écart ou du rejet<textarea data-field="discrepancy_reason" placeholder="Obligatoire si quantité reçue différente de la commande ou si une quantité est rejetée"></textarea></label>
</div>
</article>
</template>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const list=document.getElementById('receipt-items'),template=document.getElementById('receipt-item-template'),add=document.getElementById('add-receipt-item');
 const renumber=()=>[...list.children].forEach((item,index)=>{item.querySelector('[data-line-number]').textContent=index+1;item.querySelectorAll('[data-field]').forEach(field=>field.name=`items[${index}][${field.dataset.field}]`)});
 const append=()=>{const item=template.content.firstElementChild.cloneNode(true);item.querySelector('.remove-item').addEventListener('click',()=>{if(list.children.length>1){item.remove();renumber()}});const received=item.querySelector('[data-field="quantity_received"]'),accepted=item.querySelector('[data-field="quantity_accepted"]');received.addEventListener('input',()=>{if(!accepted.dataset.edited)accepted.value=received.value});accepted.addEventListener('input',()=>accepted.dataset.edited='true');list.appendChild(item);renumber()};
 add.addEventListener('click',append);append();
 @if($errors->any()) document.getElementById('receipt-create-sheet')?.showModal(); @endif
});
</script>
</x-form-sheet>
@endif
</body></html>

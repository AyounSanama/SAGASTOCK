<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nouveau mouvement de stock · PharmaCare</title>
<style>
:root{--o:#f47a20;--od:#bd4e08;--ink:#30251f;--muted:#78675d;--line:#eaded7;--soft:#f8f6f4;--red:#b42318;--green:#18733d}*{box-sizing:border-box}body{margin:0;background:var(--soft);color:var(--ink);font-family:Inter,system-ui,sans-serif}main{max-width:920px;margin:auto;padding:34px 24px}.crumbs{display:flex;gap:8px;color:var(--muted);font-size:14px;margin-bottom:24px}.crumbs a{color:var(--od);text-decoration:none}.page-head h1{margin:0 0 7px}.page-head p{margin:0 0 24px;color:var(--muted)}.notice{display:flex;gap:12px;background:#fff8f2;border:1px solid #f4c9aa;padding:15px;border-radius:12px;margin-bottom:18px}.notice strong{display:block;margin-bottom:3px}.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:26px;box-shadow:0 12px 34px #3923150b}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.wide{grid-column:1/-1}label{display:block;font-size:14px;font-weight:750;margin-bottom:6px}input,select,textarea{width:100%;padding:12px;border:1px solid #d5c6be;border-radius:10px;background:#fff;font:inherit}input:focus,select:focus,textarea:focus{outline:3px solid #f47a2022;border-color:var(--o)}textarea{min-height:100px;resize:vertical}.hint{display:block;color:var(--muted);font-size:12px;margin-top:5px}.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:26px;padding-top:20px;border-top:1px solid var(--line)}button,.button{border:0;border-radius:10px;padding:11px 17px;font-weight:800;text-decoration:none;cursor:pointer}.primary{background:var(--o);color:#fff}.secondary{background:#fff;border:1px solid var(--line);color:var(--ink)}.errors{background:#fff0ee;color:var(--red);padding:14px 18px;border-radius:11px;margin-bottom:18px}@media(max-width:650px){main{padding:24px 16px}.grid{grid-template-columns:1fr}.actions{flex-direction:column-reverse}.actions>*{width:100%;text-align:center}}
</style>
</head>
<body class="pc-app ">
@include('components.app-sidebar')
<main>
<nav class="crumbs" aria-label="Fil d’Ariane"><a href="{{ route('dashboard') }}">Tableau de bord</a><span>›</span><a href="{{ route('organizations.stocks.index',$organization) }}">Stock de médicaments</a><span>›</span><span>Nouveau mouvement</span></nav>
<header class="page-head"><h1>Enregistrer un mouvement de stock</h1><p>{{ $organization->name }} · sélectionnez précisément le site, le médicament et le lot concernés.</p></header>
<div class="notice"><span>ⓘ</span><div><strong>Registre pharmaceutique immuable</strong>Un mouvement validé ne peut être ni modifié ni supprimé. Toute erreur est corrigée par un mouvement compensatoire traçable.</div></div>
@if($errors->any())<div class="errors" role="alert"><strong>Le mouvement n’a pas été enregistré :</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="card" method="post" action="{{ route('organizations.stocks.movements.store',$organization) }}">@csrf
<div class="grid">
<div><label for="site_id">Site de stockage / dispensation *</label><select id="site_id" name="site_id" required><option value="">Sélectionner un site</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected(old('site_id')===$site->id)>{{ $site->name }} · {{ $site->healthFacility->name }}</option>@endforeach</select></div>
<div><label for="batch_id">Médicament / produit médical et lot *</label><select id="batch_id" name="batch_id" required><option value="">Sélectionner un lot</option>@foreach($products as $product)<optgroup label="{{ $product->name }} · {{ $product->code }}">@foreach($product->batches as $batch)<option value="{{ $batch->id }}" @selected(old('batch_id')===$batch->id)>{{ $batch->batch_number }} · expiration {{ $batch->expires_on->format('d/m/Y') }} · {{ $batch->status }}</option>@endforeach</optgroup>@endforeach</select><small class="hint">Les lots sont présentés par date d’expiration pour faciliter le FEFO.</small></div>
<div><label for="movement_type">Nature du mouvement *</label><select id="movement_type" name="movement_type" required onchange="updateReason(this.value)"><option value="">Sélectionner une opération</option>@foreach($movementLabels as $value=>$label)<option value="{{ $value }}" @selected(old('movement_type')===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="quantity">Quantité *</label><input id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.0001" step="0.0001" inputmode="decimal" required></div>
<div class="wide"><label for="reason">Justification <span id="required-mark"></span></label><textarea id="reason" name="reason" placeholder="Décrivez le motif, la pièce justificative ou les circonstances du mouvement">{{ old('reason') }}</textarea><small class="hint">Obligatoire pour les ajustements, pertes, détériorations, péremptions, quarantaines et destructions.</small></div>
</div>
<div class="actions"><a class="button secondary" href="{{ route('organizations.stocks.index',$organization) }}">Annuler</a><button class="primary" type="submit">Valider le mouvement</button></div>
</form>
</main>
<script>
function updateReason(type){const required=['adjustment_in','adjustment_out','loss','damage','expiry','quarantine','destruction'].includes(type);document.getElementById('reason').required=required;document.getElementById('required-mark').textContent=required?'*':''}updateReason(document.getElementById('movement_type').value);
</script>
</body>
</html>

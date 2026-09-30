<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ajouter un médicament · PharmaCare</title>
<style>
:root{--o:#f47a20;--od:#bd4e08;--ink:#30251f;--muted:#78675d;--line:#eaded7;--soft:#f8f6f4;--red:#b42318}
*{box-sizing:border-box}body{margin:0;background:var(--soft);color:var(--ink);font-family:Inter,system-ui,sans-serif}main{max-width:1040px;margin:auto;padding:34px 24px}.crumbs{display:flex;gap:8px;color:var(--muted);font-size:14px;margin-bottom:24px}.crumbs a{color:var(--od);text-decoration:none}.page-head h1{margin:0 0 7px}.page-head p{margin:0 0 24px;color:var(--muted)}.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:26px;box-shadow:0 12px 34px #3923150b}.section-title{font-size:17px;margin:0 0 18px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.wide{grid-column:1/-1}label{display:block;font-size:14px;font-weight:750;margin-bottom:6px}input,select,textarea{width:100%;padding:12px;border:1px solid #d5c6be;border-radius:10px;background:#fff;font:inherit}input:focus,select:focus,textarea:focus{outline:3px solid #f47a2022;border-color:var(--o)}textarea{min-height:90px;resize:vertical}.check{display:flex;align-items:center;gap:9px}.check input{width:auto}.hint{display:block;color:var(--muted);font-size:12px;margin-top:5px}.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:26px;padding-top:20px;border-top:1px solid var(--line)}button,.button{border:0;border-radius:10px;padding:11px 17px;font-weight:800;text-decoration:none;cursor:pointer}.primary{background:var(--o);color:#fff}.secondary{background:#fff;border:1px solid var(--line);color:var(--ink)}.errors{background:#fff0ee;color:var(--red);padding:14px 18px;border-radius:11px;margin-bottom:18px}@media(max-width:800px){.grid{grid-template-columns:1fr 1fr}}@media(max-width:600px){main{padding:24px 16px}.grid{grid-template-columns:1fr}.actions{flex-direction:column-reverse}.actions>*{width:100%;text-align:center}}
</style>
</head>
<body class="pc-app ">
@include('components.app-sidebar')
<main>
<nav class="crumbs" aria-label="Fil d’Ariane"><a href="{{ route('dashboard') }}">Tableau de bord</a><span>›</span><a href="{{ route('organizations.catalog.index',$organization) }}">Gestion des médicaments</a><span>›</span><span>Ajouter</span></nav>
<header class="page-head"><h1>Ajouter un médicament</h1><p>Enregistrez un médicament, un consommable ou un autre produit médical dans le référentiel de {{ $organization->name }}.</p></header>
@if($errors->any())<div class="errors" role="alert"><strong>Veuillez corriger les informations suivantes :</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button type="button" class="secondary" data-sheet-open="administrative-form">Ouvrir le formulaire</button>
<x-form-sheet id="administrative-form" title="Ajouter un médicament" :auto-open="true"><form class="card" method="post" action="{{ route('organizations.catalog.products.store',$organization) }}">@csrf
<h2 class="section-title">Identification</h2>
<div class="grid">
<div><label for="code">Code interne *</label><input id="code" name="code" value="{{ old('code') }}" required></div>
<div><label for="name">Nom commercial / désignation *</label><input id="name" name="name" value="{{ old('name') }}" required></div>
<div><label for="generic_name">DCI / nom générique</label><input id="generic_name" name="generic_name" value="{{ old('generic_name') }}"></div>
<div><label for="product_type">Type de produit médical *</label><select id="product_type" name="product_type" required>@foreach(['medicine'=>'Médicament','consumable'=>'Consommable médical','device'=>'Dispositif médical','reagent'=>'Réactif de laboratoire','program_input'=>'Intrant de programme','other'=>'Autre produit médical'] as $value=>$label)<option value="{{ $value }}" @selected(old('product_type','medicine')===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="barcode">Code-barres</label><input id="barcode" name="barcode" value="{{ old('barcode') }}" inputmode="numeric"></div>
<div><label for="strength">Dosage / concentration</label><input id="strength" name="strength" value="{{ old('strength') }}" placeholder="Ex. 500 mg"></div>
</div>
<h2 class="section-title" style="margin-top:28px">Classification pharmaceutique</h2>
<div class="grid">
@foreach(['category'=>'Catégorie','therapeutic_family'=>'Famille thérapeutique','unit'=>'Unité de base','dosage_form'=>'Forme pharmaceutique','administration_route'=>'Voie d’administration'] as $type=>$label)@php($field=match($type){'therapeutic_family'=>'therapeutic_family_id','unit'=>'base_unit_id','dosage_form'=>'dosage_form_id','administration_route'=>'administration_route_id',default=>'category_id'})<div><label for="{{ $field }}">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}"><option value="">Non renseigné</option>@foreach($references->get($type,collect()) as $reference)<option value="{{ $reference->id }}" @selected(old($field)===$reference->id)>{{ $reference->name }}</option>@endforeach</select></div>@endforeach
<div class="wide"><label for="description">Description et informations complémentaires</label><textarea id="description" name="description">{{ old('description') }}</textarea></div>
<label class="check"><input type="checkbox" name="is_controlled" value="1" @checked(old('is_controlled'))> Médicament ou produit sous contrôle particulier</label>
<label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',true))> Actif et disponible dans les opérations</label>
</div>
<div class="sheet-actions"><a class="button secondary" href="{{ route('organizations.catalog.index',$organization) }}">Annuler</a><button class="primary" type="submit">Ajouter le médicament</button></div>
</form></x-form-sheet>
</main>
</body>
</html>

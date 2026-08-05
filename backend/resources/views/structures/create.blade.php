<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nouvelle formation sanitaire · PharmaCare</title>
<style>
:root{--o:#f47a20;--od:#bd4e08;--ink:#30251f;--muted:#78675d;--line:#eaded7;--soft:#f8f6f4;--red:#b42318}
*{box-sizing:border-box}body{margin:0;background:var(--soft);color:var(--ink);font-family:Inter,system-ui,sans-serif}main{max-width:980px;margin:0 auto;padding:34px 24px}.crumbs{display:flex;gap:8px;align-items:center;color:var(--muted);font-size:14px;margin-bottom:24px}.crumbs a{color:var(--od);text-decoration:none}.page-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.page-head h1{margin:0 0 7px}.page-head p{margin:0;color:var(--muted)}.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:26px;box-shadow:0 12px 34px #3923150b}.section-title{font-size:17px;margin:0 0 18px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.wide{grid-column:1/-1}label{display:block;font-size:14px;font-weight:750;margin-bottom:6px}input,select,textarea{width:100%;padding:12px;border:1px solid #d5c6be;border-radius:10px;background:#fff;font:inherit}input:focus,select:focus,textarea:focus{outline:3px solid #f47a2022;border-color:var(--o)}textarea{min-height:90px;resize:vertical}.hint{display:block;color:var(--muted);font-size:12px;margin-top:5px}.check{display:flex;align-items:center;gap:9px}.check input{width:auto}.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:26px;padding-top:20px;border-top:1px solid var(--line)}button,.button{border:0;border-radius:10px;padding:11px 17px;font-weight:800;cursor:pointer;text-decoration:none}.primary{background:var(--o);color:#fff}.secondary{background:#fff;border:1px solid var(--line);color:var(--ink)}.errors{background:#fff0ee;color:var(--red);padding:14px 18px;border-radius:11px;margin-bottom:18px}.errors ul{margin:7px 0 0}@media(max-width:700px){main{padding:24px 16px}.grid{grid-template-columns:1fr}.page-head{display:block}.actions{flex-direction:column-reverse}.button,button{text-align:center;width:100%}}
</style>
</head>
<body>
@include('components.app-sidebar')
<main>
<nav class="crumbs" aria-label="Fil d’Ariane"><a href="{{ route('dashboard') }}">Tableau de bord</a><span>›</span><a href="{{ route('organizations.structures.index',$organization) }}">Formations sanitaires</a><span>›</span><span>Nouvelle</span></nav>
<header class="page-head"><div><h1>Nouvelle formation sanitaire</h1><p>Enregistrez l’établissement de santé et ses informations opérationnelles.</p></div></header>
@if($errors->any())<div class="errors" role="alert"><strong>Veuillez corriger les informations suivantes :</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="card" method="post" action="{{ route('organizations.facilities.store',$organization) }}">@csrf
<h2 class="section-title">Informations générales</h2>
<div class="grid">
<div><label for="name">Nom de la formation sanitaire *</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="organization" required></div>
<div><label for="code">Code unique *</label><input id="code" name="code" value="{{ old('code') }}" placeholder="Ex. HOPITAL_CENTRAL" required><small class="hint">Lettres, chiffres, tirets et traits de soulignement.</small></div>
<div><label for="facility_type">Type d’établissement *</label><select id="facility_type" name="facility_type" required><option value="">Sélectionner un type</option>@foreach(['hospital'=>'Hôpital','health_center'=>'Centre de santé','clinic'=>'Clinique','warehouse'=>'Dépôt pharmaceutique central','community'=>'Formation sanitaire communautaire','other'=>'Autre'] as $value=>$label)<option value="{{ $value }}" @selected(old('facility_type')===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="care_level">Niveau de soins</label><input id="care_level" name="care_level" value="{{ old('care_level') }}" placeholder="Primaire, secondaire, tertiaire…"></div>
<div><label for="mission_id">Mission / pays</label><select id="mission_id" name="mission_id"><option value="">Aucune mission associée</option>@foreach($missions as $mission)<option value="{{ $mission->id }}" @selected(old('mission_id')===$mission->id)>{{ $mission->name }} · {{ $mission->country->name }}</option>@endforeach</select></div>
<div><label for="project_ids">Projets associés</label><select id="project_ids" name="project_ids[]" multiple size="4">@foreach($projects as $project)<option value="{{ $project->id }}" @selected(in_array($project->id,old('project_ids',[])))>{{ $project->name }}</option>@endforeach</select><small class="hint">Maintenez Ctrl pour sélectionner plusieurs projets.</small></div>
</div>
<h2 class="section-title" style="margin-top:28px">Coordonnées</h2>
<div class="grid">
<div><label for="phone">Téléphone</label><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel"></div>
<div><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email"></div>
<div class="wide"><label for="address">Adresse complète</label><textarea id="address" name="address" autocomplete="street-address">{{ old('address') }}</textarea></div>
<label class="check wide"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',true))> Formation sanitaire active dès sa création</label>
</div>
<div class="actions"><a class="button secondary" href="{{ route('organizations.structures.index',$organization) }}">Annuler</a><button class="primary" type="submit">Enregistrer la formation sanitaire</button></div>
</form>
</main>
</body>
</html>

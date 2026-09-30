<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Créer un utilisateur · PharmaCare</title>
    <style>
        :root{--o:var(--pc-color-primary-strong);--s:var(--pc-color-primary-soft);--d:var(--pc-color-text);--danger:var(--pc-status-danger-text)}*{box-sizing:border-box}body{margin:0;font-family:system-ui;background:var(--s);color:var(--d)}
        header{background:var(--o);color:white;padding:13px 6%;display:flex;align-items:center;gap:18px}header a{color:white;text-decoration:none}.brand-logo{width:42px;height:42px;object-fit:cover;border-radius:50%;background:white}
        main{max-width:1000px;margin:30px auto;padding:0 20px}.card{background:white;padding:28px;border-radius:18px;box-shadow:0 12px 36px #7a350018}
        .intro{display:flex;justify-content:space-between;align-items:start;gap:20px;margin-bottom:24px}.intro h1{margin:0 0 7px}.muted{color:var(--pc-color-text-muted)}
        .section{padding:22px 0;border-top:1px solid var(--pc-color-border)}.section h2{font-size:18px;margin-top:0}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
        label{font-weight:700;font-size:14px}input,select{display:block;width:100%;margin-top:7px;padding:12px;border:1px solid var(--pc-color-border);border-radius:9px;font:inherit;background:white}
        input:focus,select:focus{outline:3px solid #f47a2030;border-color:var(--o)}.error{display:block;color:var(--danger);font-size:13px;margin-top:5px}.invalid{border-color:var(--danger)}
        .password-field{position:relative}.password-field input{padding-right:50px}.password-field button{position:absolute;right:4px;top:11px;width:42px;padding:8px;background:transparent;color:var(--pc-color-text-muted)}
        .notice{background:var(--pc-color-primary-soft);padding:14px;border-radius:10px;color:var(--pc-color-text-muted);font-size:14px}.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px}
        button,.button{border:0;border-radius:9px;padding:12px 17px;background:var(--o);color:white;font-weight:800;text-decoration:none;cursor:pointer}.secondary{background:var(--pc-color-primary-soft);color:var(--pc-color-primary-strong)}
        @media(max-width:700px){.grid{grid-template-columns:1fr}.intro,.actions{flex-direction:column}.actions .button,.actions button{width:100%;text-align:center}}
    </style>
</head>
<body class="pc-app ">
@include('components.app-sidebar')
<header><img class="brand-logo" src="{{ asset('images/pharmacare-logo.png') }}" alt="Logo PharmaCare"><a href="{{ route('users.index') }}">← Retour</a><strong>PharmaCare · Administration</strong></header>
<main><section class="card">
<div class="intro"><div><h1>Création d’un utilisateur</h1><div class="muted">Créez un compte personnel et attribuez-lui le niveau d’accès approprié.</div></div></div>
<button type="button" class="secondary" data-sheet-open="administrative-form">Ouvrir le formulaire</button>
<x-form-sheet id="administrative-form" title="Créer un utilisateur" :auto-open="true"><form method="post" action="{{ route('users.store') }}" novalidate>@csrf
<div class="section"><h2>Informations personnelles</h2><div class="grid">
<label>Prénom *<input class="@error('first_name') invalid @enderror" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required>@error('first_name')<span class="error">{{ $message }}</span>@enderror</label>
<label>Nom *<input class="@error('last_name') invalid @enderror" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required>@error('last_name')<span class="error">{{ $message }}</span>@enderror</label>
<label>Adresse e-mail *<input class="@error('email') invalid @enderror" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<span class="error">{{ $message }}</span>@enderror</label>
<label>Téléphone<input class="@error('phone') invalid @enderror" name="phone" value="{{ old('phone') }}" autocomplete="tel">@error('phone')<span class="error">{{ $message }}</span>@enderror</label>
</div></div>
<div class="section"><h2>Identifiant et sécurité</h2><div class="grid">
<label>Identifiant *<input class="@error('username') invalid @enderror" name="username" value="{{ old('username') }}" autocomplete="username" placeholder="ex. jdupont" required>@error('username')<span class="error">{{ $message }}</span>@enderror</label><div class="notice">L’identifiant doit être unique. Utilisez uniquement des lettres, chiffres, tirets ou underscores.</div>
<label>Mot de passe<span class="password-field"><input id="password" class="@error('password') invalid @enderror" name="password" type="password" autocomplete="new-password"><button type="button" data-toggle="password" title="Afficher le mot de passe">👁</button></span>@error('password')<span class="error">{{ $message }}</span>@enderror</label>
<label>Confirmer le mot de passe<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></label>
</div><p class="notice">Laissez les deux champs vides pour générer automatiquement un mot de passe temporaire. Un mot de passe saisi doit contenir au moins 6 caractères, majuscule, minuscule, chiffre et symbole.</p>
<label><input style="display:inline;width:auto;margin-right:7px" type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password'))> Obliger l’utilisateur à changer son mot de passe à la première connexion</label></div>
<div class="section"><h2>Rôle et niveau d’accès</h2><div class="grid">
<label>Rôle *<select class="@error('role_id') invalid @enderror" name="role_id" required><option value="">Sélectionner un rôle</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id')==$role->id)>{{ $role->name }}</option>@endforeach</select>@error('role_id')<span class="error">{{ $message }}</span>@enderror</label>
<label>Périmètre d’accès *<select class="@error('scope') invalid @enderror" name="scope" required><option value="">Sélectionner le périmètre</option>@if($expectedScope==='mission')<optgroup label="Coordinations pays">@foreach($missions as $mission)<option value="mission:{{ $mission->id }}" @selected(old('scope')==="mission:$mission->id")>{{ $mission->name }}</option>@endforeach</optgroup>@elseif($expectedScope==='organization')<optgroup label="Organisations">@foreach($organizations as $organization)<option value="organization:{{ $organization->id }}" @selected(old('scope')==="organization:$organization->id")>{{ $organization->name }}</option>@endforeach</optgroup>@elseif($expectedScope==='project')<optgroup label="Projets">@foreach($projects as $project)<option value="project:{{ $project->id }}" @selected(old('scope')==="project:$project->id")>{{ $project->name }} ({{ $project->organization->name }})</option>@endforeach</optgroup>@elseif($expectedScope==='site')<optgroup label="Sites">@foreach($sites as $site)<option value="site:{{ $site->id }}" @selected(old('scope')==="site:$site->id")>{{ $site->name }} ({{ $site->healthFacility->name }})</option>@endforeach</optgroup>@endif</select>@error('scope')<span class="error">{{ $message }}</span>@enderror</label>
</div></div>
@if($delegablePermissions->isNotEmpty())<div class="section"><h2>Autorisations opérationnelles du site</h2><p class="notice">Ces droits restent limités au site sélectionné. La gestion des utilisateurs et des rôles ne peut pas être déléguée.</p><div class="grid">@foreach($delegablePermissions as $permission)<label><input style="display:inline;width:auto;margin-right:7px" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id,old('permission_ids',[])))> {{ $permission->name }}</label>@endforeach</div></div>@endif
<div class="sheet-actions"><a class="button secondary" href="{{ route('users.index') }}">Retour à la liste</a><button>Créer l’utilisateur</button></div>
</form></x-form-sheet></section></main>
<script>document.querySelectorAll('[data-toggle]').forEach(button=>button.addEventListener('click',()=>{const field=document.getElementById(button.dataset.toggle);const visible=field.type==='text';field.type=visible?'password':'text';button.textContent=visible?'👁':'🙈';button.title=visible?'Afficher le mot de passe':'Masquer le mot de passe';field.focus()}));</script>
</body></html>

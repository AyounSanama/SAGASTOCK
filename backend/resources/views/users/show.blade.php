<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $managedUser->name }} · PharmaCare</title>
<style>:root{--o:#f47a20;--s:#fff7f0;--d:#382319}*{box-sizing:border-box}body{margin:0;font-family:system-ui;background:var(--s);color:var(--d)}header{background:var(--o);padding:12px 6%;color:white;display:flex;align-items:center;gap:14px}header img{width:42px;height:42px;object-fit:cover;border-radius:50%;background:white}header a{color:white;margin-right:18px}main{max-width:900px;margin:30px auto;padding:0 20px}.card{background:white;padding:24px;border-radius:16px;margin-bottom:20px;box-shadow:0 10px 30px #7a350015}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}.label{color:#78665a;font-size:13px}.value{font-weight:700}.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#fff0e5;color:#9a4300}.active{background:#eaf8ef;color:#176b3a}.actions{display:flex;gap:10px;flex-wrap:wrap}.button,button{background:var(--o);color:white;border:0;padding:11px 15px;border-radius:9px;font-weight:700;text-decoration:none}.danger{background:#b42318}.role-scope-card h2{margin:0 0 18px}.role-summary{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px;border:1px solid #f1e3d8;border-radius:13px;background:#fffbf7}.role-name{font-size:19px;font-weight:800}.role-status{padding:5px 10px;border-radius:999px;background:#eaf8ef;color:#176b3a;font-size:12px;font-weight:800}.scope-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px}.scope-item{padding:14px;border:1px solid #eee7e1;border-radius:12px;background:#fff}.scope-item .value{margin-top:5px}.permissions-heading{display:flex;align-items:end;justify-content:space-between;gap:12px;margin:24px 0 12px;padding-top:20px;border-top:1px solid #eee7e1}.permissions-heading h3{margin:0}.permission-total{color:#78665a;font-size:13px;font-weight:700}.permission-groups{display:grid;gap:9px}.permission-group{border:1px solid #eee7e1;border-radius:12px;background:#fff;overflow:hidden}.permission-group summary{display:flex;align-items:center;gap:12px;padding:14px 16px;cursor:pointer;list-style:none;font-weight:750}.permission-group summary::-webkit-details-marker{display:none}.permission-group summary::after{content:'›';margin-left:auto;color:#78665a;font-size:22px;line-height:1;transition:transform .18s ease}.permission-group[open] summary::after{transform:rotate(90deg)}.permission-count{display:inline-grid;place-items:center;min-width:28px;height:24px;padding:0 8px;border-radius:999px;background:#fff0e5;color:#9a4300;font-size:12px;font-weight:800}.permission-list{list-style:none;margin:0;padding:2px 16px 15px;border-top:1px solid #f2ece7}.permission-list li{display:flex;align-items:flex-start;gap:9px;padding:9px 0;color:#4f463f}.permission-check{color:#22a447;font-weight:900}.empty-role{margin:0;color:#78665a}@media(max-width:700px){.grid,.scope-grid{grid-template-columns:1fr}.role-summary,.permissions-heading{align-items:flex-start}.role-summary{flex-direction:column}.permissions-heading{flex-direction:column}.permission-group summary{padding:13px}.card{padding:18px}}</style></head>
<body class="pc-app ">@include('components.app-sidebar')<header><img src="{{ asset('images/pharmacare-logo.png') }}" alt="Logo PharmaCare"><a href="{{ route('users.index') }}">← Utilisateurs</a><strong>Fiche utilisateur</strong></header><main>
@if(session('success'))<p class="card">{{ session('success') }}</p>@endif
<section class="card"><h1>{{ $managedUser->name }}</h1><p><span class="badge {{ $managedUser->is_active ? 'active' : '' }}">{{ $managedUser->is_active ? 'Compte actif' : 'Compte désactivé' }}</span></p>
<div class="grid"><div><div class="label">Identifiant</div><div class="value">{{ $managedUser->username ? '@'.$managedUser->username : 'Non renseigné' }}</div></div><div><div class="label">Adresse e-mail</div><div class="value">{{ $managedUser->email }}</div></div><div><div class="label">Téléphone</div><div class="value">{{ $managedUser->phone ?: 'Non renseigné' }}</div></div><div><div class="label">Dernière connexion</div><div class="value">{{ $managedUser->last_login_at?->format('d/m/Y H:i') ?: 'Jamais' }}</div></div><div><div class="label">Mot de passe modifié</div><div class="value">{{ $managedUser->password_changed_at?->format('d/m/Y H:i') ?: 'Non renseigné' }}</div></div></div></section>
<section class="card role-scope-card">
<h2>Rôle et périmètre</h2>
@forelse($managedUser->roles as $role)
@php
    $scopeType = $role->pivot->scope_type ?: 'platform';
    $scopeId = $role->pivot->scope_id;
    $scopeEntity = match ($scopeType) {
        'organization' => $scopeId ? \App\Models\Organization::find($scopeId) : null,
        'mission' => $scopeId ? \App\Models\Mission::with(['organization:id,name','country:id,name'])->find($scopeId) : null,
        'project' => $scopeId ? \App\Models\Project::with('organization:id,name')->find($scopeId) : null,
        'facility' => $scopeId ? \App\Models\HealthFacility::with('organization:id,name')->find($scopeId) : null,
        'site' => $scopeId ? \App\Models\Site::with('organization:id,name')->find($scopeId) : null,
        default => null,
    };
    $scopeLabel = match ($scopeType) {
        'organization' => 'Organisation',
        'mission' => 'Coordination pays',
        'project' => 'Projet',
        'facility' => 'Formation sanitaire',
        'site' => 'Site de dispensation',
        default => 'Plateforme',
    };
    $scopeName = $scopeEntity?->name ?? ($scopeType === 'platform' ? 'Toute la plateforme' : 'Périmètre non disponible');
    $organizationName = $scopeType === 'organization'
        ? $scopeEntity?->name
        : ($scopeEntity?->organization?->name ?? $managedUser->organization?->name);
    $categoryDefinitions = [
        'Utilisateurs & accès' => ['users.', 'roles.', 'permissions.'],
        'Projets' => ['missions.', 'projects.', 'project.'],
        'Formations sanitaires & sites' => ['health_facilities.', 'dispensing_sites.', 'facilities.', 'sites.', 'structures.'],
        'Référentiels & produits' => ['standard_lists.', 'catalog.', 'products.', 'batches.', 'modules.'],
        'Stock & réceptions' => ['stocks.', 'receipts.', 'transfers.'],
        'Dispensation' => ['dispensing.', 'dispensations.'],
        'Patients & ordonnances' => ['patients.', 'prescriptions.'],
        'Inventaires & commandes' => ['inventories.', 'orders.'],
        'Rapports & suivi' => ['reports.', 'dashboard.', 'alerts.'],
        'Synchronisation' => ['synchronization.', 'sync.'],
        'Journal d’activité' => ['activity_logs.', 'audit.'],
    ];
    $groupedPermissions = collect($categoryDefinitions)->mapWithKeys(fn ($prefixes, $category) => [$category => collect()]);
    $otherPermissions = collect();
    foreach ($role->permissions->sortBy('name') as $permission) {
        $category = collect($categoryDefinitions)->search(fn ($prefixes) => collect($prefixes)->contains(fn ($prefix) => str_starts_with($permission->code, $prefix)));
        if ($category === false) {
            $otherPermissions->push($permission);
        } else {
            $groupedPermissions[$category]->push($permission);
        }
    }
    $groupedPermissions = $groupedPermissions->filter->isNotEmpty();
    if ($otherPermissions->isNotEmpty()) {
        $groupedPermissions->put('Autres permissions', $otherPermissions);
    }
    $permissionTotal = $groupedPermissions->sum(fn ($permissions) => $permissions->count());
@endphp
<div class="role-summary">
    <div><div class="label">Rôle</div><div class="role-name">{{ $role->name }}</div></div>
    @if($role->is_active !== null)<span class="role-status">{{ $role->is_active ? 'Actif' : 'Inactif' }}</span>@endif
</div>
<div class="scope-grid">
    <div class="scope-item"><div class="label">{{ $scopeLabel }}</div><div class="value">{{ $scopeName }}</div></div>
    @if($organizationName)<div class="scope-item"><div class="label">Organisation</div><div class="value">{{ $organizationName }}</div></div>@endif
    <div class="scope-item"><div class="label">Périmètre</div><div class="value">{{ $scopeLabel }} uniquement</div></div>
</div>
<div class="permissions-heading"><div><h3>Permissions accordées</h3><div class="permission-total">{{ $permissionTotal }} {{ $permissionTotal > 1 ? 'permissions' : 'permission' }}</div></div></div>
<div class="permission-groups">
@forelse($groupedPermissions as $category => $permissions)
<details class="permission-group" @if($loop->first) open @endif>
    <summary><span>{{ $category }}</span><span class="permission-count">{{ $permissions->count() }}</span></summary>
    <ul class="permission-list">@foreach($permissions as $permission)<li><span class="permission-check" aria-hidden="true">✓</span><span>{{ $permission->name }}</span></li>@endforeach</ul>
</details>
@empty
<p class="empty-role">Aucune permission accordée à ce rôle.</p>
@endforelse
</div>
@empty
<p class="empty-role">Aucun rôle attribué.</p>
@endforelse
</section>
<section class="card"><h2>Appareils</h2>@forelse($managedUser->devices as $device)<p><strong>{{ $device->name }}</strong> · {{ $device->platform }} · {{ $device->revoked_at ? 'Révoqué' : 'Autorisé' }}</p>@empty<p>Aucun appareil enregistré.</p>@endforelse</section>
<div class="actions"><a class="button" href="{{ route('users.edit',$managedUser) }}">Modifier</a><form method="post" action="{{ route('users.reset-password',$managedUser) }}">@csrf<button>Réinitialiser le mot de passe</button></form><form method="post" action="{{ route('users.destroy',$managedUser) }}" onsubmit="return confirm('Archiver définitivement ce compte ?')">@csrf @method('DELETE')<button class="danger">Archiver</button></form></div>
</main></body></html>

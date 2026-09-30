<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Rôles et permissions · PharmaCare</title>
    <style>
        :root{--orange:var(--pc-color-primary-strong);--orange-2:var(--pc-color-primary-strong);--ink:var(--pc-color-text);--muted:var(--pc-color-text-muted);--line:var(--pc-color-border);--soft:var(--pc-color-background);--green:var(--pc-status-success-text);--red:var(--pc-status-danger-text)}
        *{box-sizing:border-box}body{margin:0;background:var(--soft);color:var(--ink);font-family:Inter,system-ui,-apple-system,sans-serif}button,input,select,textarea{font:inherit}
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:15px 4%;background:#fff;border-bottom:1px solid var(--line)}
        .brand{display:flex;align-items:center;gap:11px;text-decoration:none;color:var(--ink);font-weight:850}.brand img{width:44px;height:44px;object-fit:contain}
        .topnav{display:flex;gap:20px}.topnav a{color:var(--pc-color-text-muted);text-decoration:none;font-weight:700}.topnav a:hover{color:var(--orange)}
        main{max-width:1420px;margin:0 auto;padding:34px 24px 60px}.page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:25px}.eyebrow{color:var(--orange);font-weight:800;font-size:.8rem;text-transform:uppercase;letter-spacing:.12em}.page-head h1{margin:7px 0;font-size:clamp(1.75rem,4vw,2.55rem);letter-spacing:-.035em}.page-head p{margin:0;color:var(--muted);max-width:720px;line-height:1.55}
        .btn{min-height:44px;border:1px solid transparent;border-radius:11px;padding:0 17px;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:800;text-decoration:none;cursor:pointer;transition:.18s ease}.btn-primary{color:#fff;background:linear-gradient(135deg,var(--orange),var(--orange-2));box-shadow:0 8px 20px #f5660a2b}.btn-primary:hover{transform:translateY(-1px);box-shadow:0 11px 24px #f5660a38}.btn-secondary{background:#fff;border-color:var(--line);color:var(--ink)}.btn-danger{color:var(--red);background:#fff;border-color:var(--pc-status-danger-bg)}.btn-sm{min-height:36px;padding:0 12px;font-size:.86rem}
        .notice{border-radius:12px;padding:13px 16px;margin-bottom:18px;background:var(--pc-status-success-bg);color:var(--pc-status-success-text);font-weight:700}.notice.error{background:var(--pc-status-danger-bg);color:var(--red)}
        .section-head{display:flex;justify-content:space-between;align-items:end;gap:15px;margin:30px 0 14px}.section-head h2{margin:0;font-size:1.18rem}.section-head p{margin:4px 0 0;color:var(--muted);font-size:.9rem}
        .roles-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.role-card{position:relative;background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;text-decoration:none;color:inherit;transition:.18s}.role-card:hover{border-color:var(--pc-color-border);transform:translateY(-2px);box-shadow:0 10px 28px #24324a0c}.role-card.selected{border:2px solid var(--orange);padding:17px;background:linear-gradient(145deg,#fff,var(--pc-color-background))}.role-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.role-icon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:var(--pc-color-primary-soft);color:var(--orange);font-size:1.2rem}.role-card h3{margin:12px 0 5px;font-size:1rem}.role-card p{min-height:38px;margin:0 0 14px;color:var(--muted);font-size:.84rem;line-height:1.45}.badge{border-radius:999px;padding:5px 9px;font-size:.7rem;font-weight:850}.badge.active{background:var(--pc-status-success-bg);color:var(--green)}.badge.inactive{background:var(--pc-color-background);color:var(--pc-color-text-muted)}.role-meta{display:flex;gap:16px;padding-top:13px;border-top:1px solid var(--line);color:var(--pc-color-text-muted);font-size:.78rem;font-weight:700}.role-actions{display:flex;gap:8px;margin-top:14px}
        .permissions-card,.audit-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:24px;box-shadow:0 10px 35px #26344c09}.permissions-title{display:flex;justify-content:space-between;align-items:flex-start;gap:18px}.title-icon{display:flex;align-items:center;gap:13px}.shield{width:48px;height:48px;border-radius:14px;background:var(--pc-color-primary-soft);color:var(--orange);display:grid;place-items:center;font-size:1.4rem}.permissions-title h2{margin:0}.permissions-title p{margin:5px 0 0;color:var(--muted)}.total-pill{white-space:nowrap;padding:10px 15px;border-radius:999px;background:var(--pc-status-success-bg);border:1px solid var(--pc-status-success-bg);color:var(--green);font-weight:850}
        .tools{display:grid;grid-template-columns:minmax(240px,1fr) 270px auto;gap:12px;margin:22px 0}.control{min-height:48px;border:1px solid var(--line);border-radius:11px;background:#fff;padding:0 14px;color:var(--ink);width:100%}.search-wrap{position:relative}.search-wrap span{position:absolute;left:15px;top:13px;color:var(--pc-color-text-muted)}.search-wrap input{padding-left:42px}
        .bulk{display:flex;gap:8px}.module{border:1px solid var(--line);border-radius:14px;margin-top:12px;overflow:hidden;background:#fff}.module[hidden]{display:none}.module summary{list-style:none;cursor:pointer;padding:17px;display:flex;align-items:center;justify-content:space-between;gap:15px;background:#fff}.module summary::-webkit-details-marker{display:none}.module-summary{display:flex;align-items:center;gap:13px}.module-icon{width:42px;height:42px;border-radius:11px;display:grid;place-items:center;background:var(--pc-color-background);color:var(--orange);font-weight:900}.module h3{margin:0;font-size:1rem}.module small{display:block;margin-top:3px;color:var(--muted)}.module-right{display:flex;align-items:center;gap:12px}.module-count{padding:6px 10px;background:var(--pc-status-success-bg);color:var(--green);border-radius:999px;font-size:.78rem;font-weight:850}.chevron{transition:.2s}.module[open] .chevron{transform:rotate(180deg)}.module-tools{display:flex;justify-content:flex-end;padding:10px 18px;border-top:1px solid var(--line);background:var(--pc-color-surface-subtle)}
        .permission-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:15px;padding:16px 20px;border-top:1px solid var(--line)}.permission-row[hidden]{display:none}.permission-name{font-weight:800}.code{display:inline-block;margin-left:8px;background:var(--pc-color-background);color:var(--pc-color-primary-strong);border-radius:999px;padding:4px 9px;font-size:.7rem;font-weight:750}.permission-desc{margin-top:6px;color:var(--muted);font-size:.83rem}.switch{position:relative;width:48px;height:27px;display:inline-block}.switch input{opacity:0;width:0;height:0}.slider{position:absolute;inset:0;background:var(--pc-color-text-muted);border-radius:999px;cursor:pointer;transition:.2s}.slider:before{content:"";position:absolute;width:21px;height:21px;left:3px;top:3px;background:#fff;border-radius:50%;box-shadow:0 2px 6px #0002;transition:.2s}.switch input:checked+.slider{background:var(--orange)}.switch input:checked+.slider:before{transform:translateX(21px)}.switch input:focus-visible+.slider{outline:3px solid var(--pc-color-border)}.switch input:disabled+.slider{cursor:not-allowed;opacity:.6}
        .savebar{position:sticky;bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:15px;margin-top:18px;padding:13px 15px;border:1px solid var(--pc-color-border);border-radius:13px;background:#fffaf6eF;backdrop-filter:blur(12px);box-shadow:0 10px 30px #27344a14}.savebar p{margin:0;color:var(--muted);font-size:.83rem}
        .advice{margin-top:18px;padding:16px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface-subtle);border-radius:13px;color:var(--pc-color-primary-strong)}.advice strong{color:var(--pc-color-primary-strong)}
        .audit-card{margin-top:28px}.audit-tools{display:flex;gap:10px;margin:15px 0}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;min-width:760px}th,td{text-align:left;padding:13px;border-bottom:1px solid var(--line);font-size:.82rem}th{color:var(--muted);text-transform:uppercase;font-size:.7rem;letter-spacing:.05em}
        .sheet-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.field.full{grid-column:1/-1}.field label{display:block;margin-bottom:6px;font-size:.82rem;font-weight:800}.field input,.field select,.field textarea{width:100%;border:1px solid var(--pc-color-border);border-radius:11px;padding:12px;background:#fff}.field textarea{min-height:100px;resize:vertical}.help{color:var(--muted);font-size:.75rem;margin-top:5px}.sheet-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid var(--line)}
        @media(max-width:1000px){.roles-grid{grid-template-columns:repeat(2,1fr)}.tools{grid-template-columns:1fr 1fr}.bulk{grid-column:1/-1}}@media(max-width:680px){main{padding:24px 14px 45px}.topnav{display:none}.page-head,.permissions-title{flex-direction:column}.page-head .btn{width:100%}.roles-grid{grid-template-columns:1fr}.tools{grid-template-columns:1fr}.bulk{grid-column:auto;display:grid;grid-template-columns:1fr 1fr}.permissions-card{padding:16px}.total-pill{align-self:flex-start}.permission-row{padding:15px}.permission-desc{display:none}.code{display:block;margin:5px 0 0;width:max-content}.savebar{align-items:stretch;flex-direction:column}.savebar .btn{width:100%}.sheet-grid{grid-template-columns:1fr}.field.full{grid-column:auto}}
    </style>
</head>
<body class="pc-app ">
@include('components.app-sidebar')
<header class="topbar">
    <a class="brand" href="{{ route('dashboard') }}"><img src="{{ asset('images/pharmacare-logo.png') }}" alt="Logo PharmaCare"><span>PharmaCare</span></a>
    <nav class="topnav"><a href="{{ route('dashboard') }}">Tableau de bord</a><a href="{{ route('users.index') }}">Utilisateurs</a><a href="{{ route('profile.show') }}">Mon profil</a></nav>
</header>
<main>
    <div class="page-head">
        <div><div class="eyebrow">Administration · Sécurité</div><h1>Rôles et permissions</h1><p>Définissez précisément les responsabilités de chaque profil selon son périmètre, en appliquant le principe du moindre privilège.</p></div>
        <button class="btn btn-primary" type="button" data-sheet-open="create-role-sheet"><span>＋</span> Nouveau rôle</button>
    </div>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif

    <div class="section-head"><div><h2>Profils d’accès</h2><p>Sélectionnez un rôle pour consulter et gérer ses droits.</p></div><span>{{ $roles->count() }} rôle(s)</span></div>
    <section class="roles-grid">
        @forelse($roles as $role)
            <a class="role-card {{ $selectedRole?->is($role) ? 'selected' : '' }}" href="{{ route('security.index', ['role' => $role->id]) }}">
                <div class="role-card-top"><span class="role-icon">{{ $role->is_system ? '◆' : '♙' }}</span><span class="badge {{ $role->is_active ? 'active' : 'inactive' }}">{{ $role->is_active ? 'Actif' : 'Inactif' }}</span></div>
                <h3>{{ $role->name }}</h3>
                <p>{{ $role->description ?: ($role->is_system ? 'Rôle système sécurisé de PharmaCare.' : 'Rôle personnalisé pour les besoins de votre organisation.') }}</p>
                <div class="role-meta"><span>👥 {{ $role->users_count }} utilisateur(s)</span><span>✓ {{ $role->permissions->count() }} droit(s)</span></div>
                @unless($role->is_system)<div class="role-actions"><button type="button" class="btn btn-secondary btn-sm" data-sheet-open="edit-role-{{ $role->id }}">Modifier</button></div>@endunless
            </a>
        @empty
            <p>Aucun rôle disponible dans votre périmètre.</p>
        @endforelse
    </section>

    @if($selectedRole)
    @php($selectedIds = $selectedRole->permissions->pluck('id'))
    <div class="section-head"><div><h2>Autorisations de « {{ $selectedRole->name }} »</h2><p>{{ $selectedRole->is_system ? 'Ce rôle système est protégé et consultable en lecture seule.' : 'Activez uniquement les droits nécessaires à ce profil.' }}</p></div></div>
    <section class="permissions-card" id="permission-manager">
        <div class="permissions-title">
            <div class="title-icon"><span class="shield">✓</span><div><h2>Permissions</h2><p>Attribuez les droits par module fonctionnel.</p></div></div>
            <span class="total-pill"><strong id="selected-total">{{ $selectedIds->count() }}</strong> sélectionnée(s) sur {{ $permissions->count() }}</span>
        </div>
        <div class="tools">
            <div class="search-wrap"><span>⌕</span><input id="permission-search" class="control" type="search" placeholder="Rechercher une permission…" autocomplete="off"></div>
            <select id="module-filter" class="control" aria-label="Filtrer par module"><option value="">Tous les modules</option>@foreach($permissionGroups as $group)<option value="{{ $group['key'] }}">{{ $group['name'] }}</option>@endforeach</select>
            @unless($selectedRole->is_system)<div class="bulk"><button class="btn btn-secondary btn-sm" id="select-all" type="button">Tout activer</button><button class="btn btn-secondary btn-sm" id="clear-all" type="button">Tout retirer</button></div>@endunless
        </div>
        <form method="post" action="{{ route('security.roles.update', $selectedRole) }}" id="permission-form">
            @csrf @method('PUT')
            <input type="hidden" name="code" value="{{ $selectedRole->code }}"><input type="hidden" name="name" value="{{ $selectedRole->name }}">
            <input type="hidden" name="description" value="{{ $selectedRole->description }}"><input type="hidden" name="is_active" value="{{ (int)$selectedRole->is_active }}">
            @foreach($permissionGroups as $group)
                @php($moduleSelected = $group['permissions']->whereIn('id', $selectedIds)->count())
                <details class="module" data-module="{{ $group['key'] }}" {{ $loop->first ? 'open' : '' }}>
                    <summary>
                        <div class="module-summary"><span class="module-icon">{{ strtoupper(substr($group['name'],0,1)) }}</span><div><h3>{{ $group['name'] }}</h3><small>Gestion des droits du module</small></div></div>
                        <div class="module-right"><span class="module-count"><b>{{ $moduleSelected }}</b> / {{ $group['permissions']->count() }}</span><span class="chevron">⌄</span></div>
                    </summary>
                    @unless($selectedRole->is_system)<div class="module-tools"><button class="btn btn-secondary btn-sm module-toggle" type="button">Tout activer</button></div>@endunless
                    <div class="permission-list">
                    @foreach($group['permissions'] as $permission)
                        <div class="permission-row" data-search="{{ Str::lower($permission->name.' '.$permission->code) }}">
                            <div><span class="permission-name">{{ $permission->name }}</span><span class="code">{{ $permission->code }}</span><div class="permission-desc">Autorise « {{ Str::lower($permission->name) }} » dans le périmètre attribué au rôle.</div></div>
                            <label class="switch"><input class="permission-switch" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($selectedIds->contains($permission->id)) @disabled($selectedRole->is_system)><span class="slider"></span></label>
                        </div>
                    @endforeach
                    </div>
                </details>
            @endforeach
            @unless($selectedRole->is_system)
                <div class="savebar"><p>Les changements ne prennent effet qu’après enregistrement.</p><button class="btn btn-primary" type="submit">✓ Enregistrer les permissions</button></div>
            @endunless
        </form>
        <div class="advice"><strong>Conseil de sécurité</strong><br>Attribuez uniquement les permissions indispensables aux missions de ce rôle.</div>
    </section>
    @endif

    <section class="audit-card">
        <div class="section-head" style="margin-top:0"><div><h2>Journal d’audit</h2><p>Historique des opérations sensibles effectuées dans l’application.</p></div></div>
        <form class="audit-tools" method="get"><input type="hidden" name="role" value="{{ $selectedRole?->id }}"><input class="control" name="event" value="{{ request('event') }}" placeholder="Ex. role.updated"><button class="btn btn-secondary">Filtrer</button></form>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Utilisateur</th><th>Événement</th><th>Objet</th><th>Adresse IP</th></tr></thead><tbody>
        @forelse($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->user?->email ?? 'Système' }}</td><td>{{ $log->event }}</td><td>{{ class_basename($log->auditable_type ?? '') }} #{{ $log->auditable_id }}</td><td>{{ $log->ip_address }}</td></tr>@empty<tr><td colspan="5">Aucun événement enregistré.</td></tr>@endforelse
        </tbody></table></div>{{ $logs->withQueryString()->links() }}
    </section>
</main>

<x-form-sheet id="create-role-sheet" title="Créer un nouveau rôle" description="Définissez l’identité et le périmètre du rôle. Les permissions pourront ensuite être configurées.">
    <form method="post" action="{{ route('security.roles.store') }}" data-sheet-form>@csrf
        <div class="sheet-grid">
            <div class="field"><label for="create-role-name">Nom du rôle</label><input id="create-role-name" name="name" value="{{ old('name') }}" placeholder="Responsable logistique" required maxlength="120"></div>
            <div class="field"><label for="create-role-code">Code technique</label><input id="create-role-code" name="code" value="{{ old('code') }}" placeholder="responsable_logistique" required maxlength="80"><div class="help">Lettres, chiffres, tirets et underscores.</div></div>
            <div class="field full"><label for="create-role-description">Description</label><textarea id="create-role-description" name="description" maxlength="500" placeholder="Décrivez les responsabilités principales de ce rôle.">{{ old('description') }}</textarea></div>
            <div class="field"><label for="create-role-status">Statut</label><select id="create-role-status" name="is_active"><option value="1" @selected(old('is_active','1')==='1')>Actif</option><option value="0" @selected(old('is_active')==='0')>Inactif</option></select></div>
            <div class="field"><label for="create-role-scope">Périmètre</label><select id="create-role-scope" name="scope" required>
                @if(auth()->user()->roles()->wherePivot('scope_type','platform')->exists())<option value="platform">Plateforme entière</option>@endif
                @foreach($organizations as $item)<option value="organization:{{ $item->id }}">Organisation · {{ $item->name }}</option>@endforeach
                @foreach($projects as $item)<option value="project:{{ $item->id }}">Projet · {{ $item->name }}</option>@endforeach
                @foreach($facilities as $item)<option value="facility:{{ $item->id }}">Formation sanitaire · {{ $item->name }}</option>@endforeach
                @foreach($sites as $item)<option value="site:{{ $item->id }}">Site · {{ $item->name }}</option>@endforeach
            </select></div>
        </div>
        <div class="sheet-actions"><button class="btn btn-secondary" type="button" data-sheet-close="create-role-sheet">Annuler</button><button class="btn btn-primary" type="submit">Créer le rôle</button></div>
    </form>
</x-form-sheet>

@foreach($roles->where('is_system', false) as $role)
<x-form-sheet id="edit-role-{{ $role->id }}" title="Modifier le rôle" description="Mettez à jour l’identité et l’état de « {{ $role->name }} ».">
    <form method="post" action="{{ route('security.roles.update', $role) }}" data-sheet-form>@csrf @method('PUT')
        @foreach($role->permissions as $permission)<input type="hidden" name="permission_ids[]" value="{{ $permission->id }}">@endforeach
        <div class="sheet-grid">
            <div class="field"><label>Nom du rôle</label><input name="name" value="{{ $role->name }}" required maxlength="120"></div>
            <div class="field"><label>Code technique</label><input name="code" value="{{ $role->code }}" required maxlength="80"></div>
            <div class="field full"><label>Description</label><textarea name="description" maxlength="500">{{ $role->description }}</textarea></div>
            <div class="field"><label>Statut</label><select name="is_active"><option value="1" @selected($role->is_active)>Actif</option><option value="0" @selected(!$role->is_active)>Inactif</option></select></div>
        </div>
        <div class="sheet-actions"><button class="btn btn-danger" type="submit" form="delete-role-{{ $role->id }}">Supprimer</button><button class="btn btn-secondary" type="button" data-sheet-close="edit-role-{{ $role->id }}">Annuler</button><button class="btn btn-primary" type="submit">Enregistrer</button></div>
    </form>
    <form id="delete-role-{{ $role->id }}" method="post" action="{{ route('security.roles.destroy', $role) }}" onsubmit="return confirm('Supprimer définitivement ce rôle ? Cette action est impossible s’il est attribué à un utilisateur.')">@csrf @method('DELETE')</form>
</x-form-sheet>
@endforeach

<script>
document.addEventListener('DOMContentLoaded',()=>{
    const manager=document.getElementById('permission-manager'); if(!manager)return;
    const boxes=[...manager.querySelectorAll('.permission-switch')], total=document.getElementById('selected-total');
    const update=()=>{total.textContent=boxes.filter(b=>b.checked).length;manager.querySelectorAll('.module').forEach(m=>{const own=[...m.querySelectorAll('.permission-switch')],n=own.filter(b=>b.checked).length;m.querySelector('.module-count b').textContent=n;const btn=m.querySelector('.module-toggle');if(btn){btn.textContent=n===own.length?'Tout retirer':(n?'Compléter la sélection':'Tout activer');btn.dataset.partial=n>0&&n<own.length?'1':'0'}})};
    const setBoxes=(items,value)=>{items.filter(b=>!b.disabled).forEach(b=>b.checked=value);update()};
    boxes.forEach(b=>b.addEventListener('change',update));
    document.getElementById('select-all')?.addEventListener('click',()=>setBoxes(boxes,true));
    document.getElementById('clear-all')?.addEventListener('click',()=>setBoxes(boxes,false));
    manager.querySelectorAll('.module-toggle').forEach(btn=>btn.addEventListener('click',()=>{const own=[...btn.closest('.module').querySelectorAll('.permission-switch')];setBoxes(own,!own.every(b=>b.checked))}));
    const applyFilters=()=>{const q=document.getElementById('permission-search').value.toLocaleLowerCase('fr').trim(),module=document.getElementById('module-filter').value;manager.querySelectorAll('.module').forEach(group=>{const moduleMatch=!module||group.dataset.module===module;let visible=0;group.querySelectorAll('.permission-row').forEach(row=>{const show=moduleMatch&&(!q||row.dataset.search.includes(q));row.hidden=!show;if(show)visible++});group.hidden=!moduleMatch||!visible;if(q&&visible)group.open=true})};
    document.getElementById('permission-search').addEventListener('input',applyFilters);document.getElementById('module-filter').addEventListener('change',applyFilters);update();
    document.querySelectorAll('.role-card button').forEach(button=>button.addEventListener('click',e=>{e.preventDefault();e.stopPropagation()}));
});
</script>
@if($errors->any())<script>document.addEventListener('DOMContentLoaded',()=>document.querySelector('[data-sheet-open="create-role-sheet"]')?.click())</script>@endif
</body>
</html>

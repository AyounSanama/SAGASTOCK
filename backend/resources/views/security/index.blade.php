<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sécurité · PharmaCare</title>
    <style>
        :root{--orange:#f47a20;--orange-dark:#c9570c;--ink:#34251d;--muted:#735f54;--line:#eadbd1;--soft:#fff7f0;--danger:#b42318}
        *{box-sizing:border-box}body{margin:0;background:var(--soft);color:var(--ink);font-family:Inter,system-ui,sans-serif}
        header{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:14px 5%;background:#fff;border-bottom:1px solid var(--line)}
        .brand{display:flex;align-items:center;gap:12px;color:var(--ink);text-decoration:none;font-weight:800}.brand img{width:48px;height:48px;object-fit:contain}
        nav{display:flex;gap:18px;flex-wrap:wrap}nav a{color:var(--orange-dark);font-weight:700;text-decoration:none}
        main{max-width:1280px;margin:30px auto;padding:0 20px}.title p{color:var(--muted)}
        .grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:22px}.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:24px;margin-bottom:22px;box-shadow:0 12px 34px #7a35000f}
        label{display:block;font-weight:700;margin:12px 0 6px}input,select{width:100%;padding:11px 12px;border:1px solid #d8c6bb;border-radius:10px;background:#fff}
        button,.button{display:inline-block;border:0;border-radius:10px;padding:11px 15px;background:var(--orange);color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.danger{background:var(--danger)}
        .permissions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:14px 0}.permissions label{margin:0;padding:9px;background:#fff5ed;border-radius:9px;font-weight:600}.permissions input{width:auto}
        .role{padding:20px 0;border-top:1px solid var(--line)}.role:first-of-type{border-top:0}.role-head{display:flex;justify-content:space-between;align-items:center;gap:15px}.badge{padding:5px 9px;border-radius:999px;background:#edf7f0;color:#176b3a;font-size:12px;font-weight:800}.badge.system{background:#eef4ff;color:#245ca6}
        .actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.message{padding:13px;border-radius:10px;background:#e9f8ee;color:#176b3a}.error{background:#fff0ee;color:var(--danger)}
        .table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:760px}th,td{text-align:left;padding:12px;border-bottom:1px solid #eee;font-size:14px}th{color:var(--muted)}
        @media(max-width:850px){header{align-items:flex-start;flex-direction:column}.grid{grid-template-columns:1fr}.permissions{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header>
    <a class="brand" href="{{ route('dashboard') }}"><img src="{{ asset('images/pharmacare-logo.png') }}" alt="Logo PharmaCare"><span>PharmaCare</span></a>
    <nav><a href="{{ route('dashboard') }}">Utilisateurs</a><a href="{{ route('organizations.index') }}">Organisation</a><a href="{{ route('profile.show') }}">Mon profil</a></nav>
</header>
<main>
    <div class="title"><h1>Sécurité, rôles et audit</h1><p>Gérez les autorisations dans le périmètre qui vous est confié et contrôlez les opérations sensibles.</p></div>
    @if(session('status'))<p class="message">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="message error">{{ $errors->first() }}</p>@endif
    <div class="grid">
        <section class="card">
            <h2>Créer un rôle personnalisé</h2>
            <form method="post" action="{{ route('security.roles.store') }}">@csrf
                <label for="code">Code du rôle</label><input id="code" name="code" value="{{ old('code') }}" placeholder="responsable_logistique" required>
                <label for="name">Nom du rôle</label><input id="name" name="name" value="{{ old('name') }}" placeholder="Responsable logistique" required>
                <label for="scope">Périmètre d’utilisation</label>
                <select id="scope" name="scope" required>
                    @if($organizations->count() || $projects->count() || auth()->user()->roles()->wherePivot('scope_type','platform')->exists())
                        @if(auth()->user()->roles()->wherePivot('scope_type','platform')->exists())<option value="platform">Plateforme entière</option>@endif
                        @foreach($organizations as $organization)<option value="organization:{{ $organization->id }}">Organisation · {{ $organization->name }}</option>@endforeach
                        @foreach($projects as $project)<option value="project:{{ $project->id }}">Projet · {{ $project->name }}</option>@endforeach
                        @foreach($facilities as $facility)<option value="facility:{{ $facility->id }}">Formation sanitaire · {{ $facility->name }}</option>@endforeach
                        @foreach($sites as $site)<option value="site:{{ $site->id }}">Site · {{ $site->name }}</option>@endforeach
                    @endif
                </select>
                <div class="permissions">@foreach($permissions as $permission)<label><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}"> {{ $permission->name }}</label>@endforeach</div>
                <button>Créer le rôle</button>
            </form>
        </section>
        <aside class="card"><h2>Règles de sécurité</h2><p>Les rôles système sont protégés. Les rôles personnalisés ne peuvent être modifiés que dans votre périmètre. Un rôle encore attribué ne peut pas être supprimé.</p><p>Les créations, modifications et suppressions sont inscrites dans le journal d’audit.</p></aside>
    </div>
    <section class="card">
        <h2>Rôles disponibles</h2>
        @foreach($roles as $role)
            <article class="role">
                <div class="role-head"><div><strong>{{ $role->name }}</strong><div>{{ $role->code }} · {{ $role->scope_type }}</div></div><span class="badge {{ $role->is_system ? 'system' : '' }}">{{ $role->is_system ? 'Système protégé' : 'Personnalisé' }}</span></div>
                @if($role->is_system)
                    <div class="permissions">@foreach($role->permissions as $permission)<label>{{ $permission->name }}</label>@endforeach</div>
                @else
                    <form method="post" action="{{ route('security.roles.update',$role) }}">@csrf @method('PUT')
                        <label>Code</label><input name="code" value="{{ $role->code }}" required>
                        <label>Nom</label><input name="name" value="{{ $role->name }}" required>
                        <div class="permissions">@foreach($permissions as $permission)<label><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($role->permissions->contains($permission))> {{ $permission->name }}</label>@endforeach</div>
                        <button>Enregistrer</button>
                    </form>
                    <form method="post" action="{{ route('security.roles.destroy',$role) }}" onsubmit="return confirm('Supprimer définitivement ce rôle personnalisé ?')">@csrf @method('DELETE')<button class="danger">Supprimer le rôle</button></form>
                @endif
            </article>
        @endforeach
    </section>
    <section class="card">
        <h2>Journal d’audit</h2>
        <form method="get"><label for="event">Événement</label><div class="actions"><input id="event" name="event" value="{{ request('event') }}" placeholder="Ex. user.archived"><button>Filtrer</button></div></form>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Utilisateur</th><th>Événement</th><th>Objet</th><th>Adresse IP</th></tr></thead><tbody>
        @forelse($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->user?->email ?? 'Système' }}</td><td>{{ $log->event }}</td><td>{{ class_basename($log->auditable_type ?? '') }} #{{ $log->auditable_id }}</td><td>{{ $log->ip_address }}</td></tr>@empty<tr><td colspan="5">Aucun événement enregistré.</td></tr>@endforelse
        </tbody></table></div>{{ $logs->withQueryString()->links() }}
    </section>
</main>
</body>
</html>

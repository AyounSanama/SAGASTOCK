<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Utilisateurs · PharmaCare</title>
    <style>
        :root {
            --o: #f47a20;
            --d: #382319;
            --s: #fff7f0
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: system-ui;
            background: var(--s);
            color: var(--d)
        }

        header {
            background: var(--o);
            color: white;
            padding: 13px 5%;
            display: flex;
            align-items: center;
            gap: 18px
        }

        header a {
            color: white
        }

        .brand-lockup {
            display: flex;
            align-items: center;
            gap: 10px
        }

        .brand-lockup img {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 50%;
            background: white
        }

        header form {
            margin-left: auto
        }

        main {
            max-width: 1300px;
            margin: auto;
            padding: 28px 5%
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 16px;
            box-shadow: 0 10px 30px #7a350015;
            margin-bottom: 22px
        }

        details summary {
            cursor: pointer;
            font-size: 20px;
            font-weight: 800;
            color: var(--o)
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #dbcabd;
            border-radius: 9px;
            font: inherit
        }

        label {
            font-weight: 650
        }

        button,
        .button {
            display: inline-block;
            background: var(--o);
            color: white;
            border: 0;
            padding: 11px 15px;
            border-radius: 9px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none
        }

        .secondary {
            background: #fff0e5;
            color: #9a4300
        }

        .success {
            background: #eaf8ef;
            color: #176b3a;
            padding: 13px;
            border-radius: 9px
        }

        .secret {
            background: #2f241e;
            color: white;
            padding: 14px;
            border-radius: 9px;
            font-family: monospace
        }

        .head {
            display: flex;
            justify-content: space-between;
            gap: 12px
        }

        .muted {
            color: #78665a;
            font-size: 14px
        }

        .password {
            position: relative
        }

        .password button {
            position: absolute;
            right: 4px;
            top: 4px;
            width: 42px;
            padding: 8px;
            background: transparent;
            color: #705b4c
        }

        .filters {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 18px
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            text-align: left;
            padding: 13px 10px;
            border-bottom: 1px solid #f1e1d5
        }

        th {
            font-size: 13px;
            color: #78665a
        }

        .badge {
            padding: 5px 9px;
            border-radius: 999px;
            background: #fff0e5;
            color: #9a4300;
            font-size: 13px
        }

        .badge.active {
            background: #eaf8ef;
            color: #176b3a
        }

        .actions {
            display: flex;
            gap: 7px
        }

        .actions .button {
            padding: 8px 10px
        }

        .danger {
            background: #b42318
        }

        @media(max-width:850px) {

            .grid,
            .filters {
                grid-template-columns: 1fr
            }

            .head,
            header {
                flex-wrap: wrap
            }

            header form {
                margin-left: 0
            }

            .table-wrap {
                overflow: auto
            }

            table {
                min-width: 760px
            }
        }
    </style>
</head>

<body>
    <header><strong class="brand-lockup"><img src="{{ asset('images/pharmacare-logo.png') }}"
                alt="">PharmaCare</strong><a href="{{ route('organizations.index') }}">Organisations</a><a
            href="{{ route('security.index') }}">Rôles et sécurité</a><a href="{{ route('profile.show') }}">Mon profil</a>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary">Déconnexion</button></form>
    </header>
    <main>
        <h1>Gestion des utilisateurs</h1>
        <h3 class="muted">Gérez les utilisateurs, leurs rôles et leurs niveaux d’accès.</h3>
        @if (session('success'))
            <p class="success">{{ session('success') }}</p>
        @endif
        @if (session('temporary_password'))
            <p class="secret">Mot de passe temporaire à transmettre une seule fois :
                <strong>{{ session('temporary_password') }}</strong>
            </p>
        @endif
        @if ($errors->any())
            <p class="success">{{ $errors->first() }}</p>
        @endif

        <div class="head" style="margin-bottom:18px">
            <div>

            </div>
            <div class="actions"><a class="button secondary" href="{{ route('users.archived') }}">Utilisateurs
                    archivés</a><a class="button" href="{{ route('users.create') }}">Créer un utilisateur</a></div>
        </div>

        <section class="card">
            <div class="head">
                <div>
                    <h2>Utilisateurs</h2>
                    <p class="muted">{{ $users->total() }} compte(s) correspondant aux critères.</p>
                </div>
            </div>
            <form class="filters" method="get"><input name="search" value="{{ request('search') }}"
                    placeholder="Rechercher par nom, e-mail ou téléphone"><select name="role_id">
                    <option value="">Tous les rôles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(request('role_id') == $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select><select name="status">
                    <option value="">Tous les statuts</option>
                    <option value="active" @selected(request('status') === 'active')>Actifs</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Désactivés</option>
                </select><button>Filtrer</button></form>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Coordonnées</th>
                            <th>Rôle</th>
                            <th>Périmètre</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            @php($currentRole = $user->roles->first())
                            <tr>
                                <td><strong>{{ $user->name }}</strong>
                                    <div class="muted">
                                        {{ $user->username ? '@' . $user->username : 'Sans identifiant' }} · Créé le
                                        {{ $user->created_at->format('d/m/Y') }}</div>
                                </td>
                                <td>{{ $user->email }}<div class="muted">{{ $user->phone ?: 'Sans téléphone' }}
                                    </div>
                                </td>
                                <td>{{ $currentRole?->name ?? 'Sans rôle' }}</td>
                                <td>{{ match ($currentRole?->pivot->scope_type) {'organization' => 'Organisation','project' => 'Projet',default => 'Plateforme'} }}
                                </td>
                                <td><span
                                        class="badge {{ $user->is_active ? 'active' : '' }}">{{ $user->is_active ? 'Actif' : 'Désactivé' }}</span>
                                </td>
                                <td>
                                    <div class="actions"><a class="button secondary"
                                            href="{{ route('users.show', $user) }}">Voir</a><a class="button"
                                            href="{{ route('users.edit', $user) }}">Modifier</a>
                                        <form method="post" action="{{ route('users.destroy', $user) }}"
                                            onsubmit="return confirm('Archiver ce compte utilisateur ?')">@csrf
                                            @method('DELETE')<button class="danger">Archiver</button></form>
                                    </div>
                                </td>
                            </tr>
                        @empty<tr>
                                <td colspan="6">Aucun utilisateur ne correspond aux critères.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>{{ $users->links() }}
        </section>
    </main>
</body>

</html>

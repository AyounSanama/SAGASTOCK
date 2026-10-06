<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Utilisateurs · PharmaCare</title>
    <style>
        :root {
            --o: var(--pc-color-primary-strong);
            --d: var(--pc-color-text);
            --s: var(--pc-color-primary-soft)
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
            border: 1px solid var(--pc-color-border);
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
            background: var(--pc-color-primary-soft);
            color:var(--pc-color-primary-soft-text)
        }

        .success {
            background: var(--pc-status-success-bg);
            color: var(--pc-status-success-text);
            padding: 13px;
            border-radius: 9px
        }

        .secret {
            background: var(--pc-color-text);
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
            color: var(--pc-color-text-muted);
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
            color: var(--pc-color-text-muted)
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
            border-bottom: 1px solid var(--pc-color-border)
        }

        th {
            font-size: 13px;
            color: var(--pc-color-text-muted)
        }

        .badge {
            padding: 5px 9px;
            border-radius: 999px;
            background: var(--pc-color-primary-soft);
            color:var(--pc-color-primary-soft-text);
            font-size: 13px
        }

        .badge.active {
            background: var(--pc-status-success-bg);
            color: var(--pc-status-success-text)
        }

        .actions {
            display: flex;
            gap: 7px
        }

        .actions .button {
            padding: 8px 10px
        }

        .danger {
            background: var(--pc-status-danger-text)
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

<body class="pc-app ">
    @include('components.app-sidebar')
    <header><strong class="brand-lockup"><img src="{{ asset('images/pharmacare-logo.png') }}"
                alt="">PharmaCare</strong><a href="{{ route('organizations.index') }}">Organisations</a><a
            href="{{ route('security.index') }}">Rôles et sécurité</a><a href="{{ route('profile.show') }}">Mon profil</a>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary">Déconnexion</button></form>
    </header>
    <main>@if(app(\App\Services\GovernanceService::class)->roleCode(auth()->user()) === \App\Services\GovernanceService::PROJECT_ADMIN)@include('projects.partials.project-admin-tabs', ['activeTab' => 'users'])@endif
        <h1>Gestion des utilisateurs</h1>
        <h3 class="muted">Gérez les utilisateurs, leurs rôles et leurs niveaux d’accès.</h3>
        @if (session('success'))
            <p class="success">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <p class="success">{{ $errors->first() }}</p>
        @endif

        <div class="head" style="margin-bottom:18px">
            <div>

            </div>
            <div class="actions"><a class="button secondary" href="{{ route('users.archived') }}">Utilisateurs
                    archivés</a>
                @if ($canCreateUsers)
                    <button type="button" data-sheet-open="user-create-sheet">＋ Créer un utilisateur</button>
                @endif
            </div>
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
        @if ($canCreateUsers)
            <style>
                .user-form-section {
                    padding: 4px 0 22px;
                    margin-bottom: 20px;
                    border-bottom: 1px solid var(--pc-color-border)
                }

                .user-form-section:last-of-type {
                    border: 0
                }

                .user-form-section h3 {
                    margin: 0 0 15px
                }

                .user-form-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 16px
                }

                .user-form-grid label {
                    display: block;
                    font-weight: 750;
                    font-size: 14px
                }

                .user-form-grid input,
                .user-form-grid select {
                    display: block;
                    width: 100%;
                    margin-top: 6px;
                    padding: 12px;
                    border: 1px solid var(--pc-color-border);
                    border-radius: 10px;
                    background: #fff;
                    font: inherit
                }

                .password-wrap {
                    position: relative
                }

                .password-wrap input {
                    padding-right: 48px
                }

                .password-toggle {
                    position: absolute;
                    right: 5px;
                    bottom: 5px;
                    width: 39px;
                    height: 39px;
                    padding: 0 !important;
                    background: transparent !important;
                    color: var(--pc-color-text) !important
                }

                .field-hint {
                    font-size: 12px;
                    color: var(--pc-color-text-muted);
                    margin-top: 5px
                }

                .full {
                    grid-column: 1/-1
                }

                @media(max-width:650px) {
                    .user-form-grid {
                        grid-template-columns: 1fr
                    }

                    .full {
                        grid-column: auto
                    }
                }
            </style>
            <x-form-sheet id="user-create-sheet" title="Créer un utilisateur"
                description="Créez un compte personnel et attribuez-lui un rôle et un périmètre d’accès adaptés."
                width="820px">
                @if ($errors->any())
                    <div class="form-sheet-error"><strong>La création n’a pas
                            abouti.</strong><br>{{ $errors->first() }}</div>
                @endif
                <form id="user-create-form" method="post" action="{{ route('users.store') }}" novalidate>@csrf
                    <section class="user-form-section">
                        <h3>Informations personnelles</h3>
                        <div class="user-form-grid">
                            <label>Prénom *<input name="first_name" value="{{ old('first_name') }}"
                                    autocomplete="given-name" required></label>
                            <label>Nom *<input name="last_name" value="{{ old('last_name') }}"
                                    autocomplete="family-name" required></label>
                            <label>Adresse e-mail *<input name="email" type="email" value="{{ old('email') }}"
                                    autocomplete="email" required></label>
                            <label>Téléphone<input name="phone" type="tel" value="{{ old('phone') }}"
                                    autocomplete="tel"></label>
                        </div>
                    </section>
                    <section class="user-form-section">
                        <h3>Identifiant et sécurité</h3>
                        <div class="user-form-grid">
                            <label>Identifiant *<input name="username" value="{{ old('username') }}"
                                    autocomplete="username" placeholder="ex. jdupont" required><small
                                    class="field-hint">Lettres, chiffres, tirets et traits de
                                    soulignement.</small></label>
                            <div></div>
                            <label>Mot de passe<div class="password-wrap"><input id="sheet-password" name="password"
                                        type="password" autocomplete="new-password"><button class="password-toggle"
                                        type="button" data-password-toggle="sheet-password"
                                        aria-label="Afficher le mot de passe">◉</button></div></label>
                            <label>Confirmer le mot de passe<div class="password-wrap"><input
                                        id="sheet-password-confirmation" name="password_confirmation" type="password"
                                        autocomplete="new-password"><button class="password-toggle" type="button"
                                        data-password-toggle="sheet-password-confirmation"
                                        aria-label="Afficher le mot de passe">◉</button></div></label>
                            <p class="field-hint full">Laissez les mots de passe vides pour générer automatiquement un
                                mot de passe temporaire sécurisé. Un mot de passe personnalisé doit avoir au moins 12
                                caractères avec majuscule, minuscule, chiffre et symbole.</p>
                            <label class="full" style="display:flex;align-items:center;gap:9px"><input
                                    style="width:auto;margin:0" type="checkbox" name="must_change_password"
                                    value="1" @checked(old('must_change_password'))> Obliger l’utilisateur à changer son
                                mot de passe à la première connexion</label>
                        </div>
                    </section>
                    <section class="user-form-section">
                        <h3>Rôle et niveau d’accès</h3>
                        <div class="user-form-grid">
                            <label>Rôle *<select id="create-role" name="role_id" required>
                                    <option value="">Sélectionner un rôle</option>
                                    @foreach ($assignableRoles as $role)
                                        <option value="{{ $role->id }}" data-code="{{ $role->code }}" @selected(old('role_id') == $role->id)>
                                            {{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @php($formLocked = (bool) ($userFormContext['locked'] ?? false))
                            @php($selectedOrganization = old('organization_id', $userFormContext['organization_id'] ?? null))
                            @php($selectedMission = old('mission_id', $userFormContext['mission_id'] ?? null))
                            @php($selectedProject = old('project_id', $userFormContext['project_id'] ?? null))
                            <label id="create-organization-field">Organisation *<select id="create-organization" name="organization_id" @disabled($formLocked)><option value="">Sélectionner</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected($selectedOrganization==$organization->id)>{{ $organization->name }}</option>@endforeach</select>@if($formLocked)<input type="hidden" name="organization_id" value="{{ $selectedOrganization }}"><small class="field-hint">Organisation définie par votre périmètre.</small>@endif</label>
                            <label id="create-mission-field" hidden>Mission *<select id="create-mission" name="mission_id" @disabled($formLocked)><option value="">Sélectionner</option>@foreach($missions as $mission)<option value="{{ $mission->id }}" data-organization="{{ $mission->organization_id }}" @selected($selectedMission==$mission->id)>{{ $mission->name }}</option>@endforeach</select>@if($formLocked)<input type="hidden" name="mission_id" value="{{ $selectedMission }}"><small class="field-hint">Mission définie par votre projet.</small>@endif</label>
                            <label id="create-project-field" hidden>Projet *<select id="create-project" name="project_id" @disabled($formLocked)><option value="">Sélectionner</option>@foreach($projects as $project)<option value="{{ $project->id }}" data-organization="{{ $project->organization_id }}" data-mission="{{ $project->mission_id }}" @selected($selectedProject==$project->id)>{{ $project->name }}</option>@endforeach</select>@if($formLocked)<input type="hidden" name="project_id" value="{{ $selectedProject }}"><small class="field-hint">Projet défini par votre périmètre.</small>@endif</label>
                            <label id="create-facility-field" hidden>Formation sanitaire *<select id="create-facility" name="health_facility_id"><option value="">Sélectionner</option>@foreach($facilities as $facility)<option value="{{ $facility->id }}" data-organization="{{ $facility->organization_id }}" data-projects="{{ $facility->projects->pluck('id')->implode(',') }}" @selected(old('health_facility_id')==$facility->id)>{{ $facility->name }}</option>@endforeach</select></label>
                            <label id="create-site-field" hidden>Site de dispensation *<select id="create-site" name="dispensing_site_id"><option value="">Sélectionner</option>@foreach($sites as $site)<option value="{{ $site->id }}" data-facility="{{ $site->health_facility_id }}" @selected(old('dispensing_site_id')==$site->id)>{{ $site->name }}</option>@endforeach</select></label>
                            <input id="create-scope" type="hidden" name="scope" value="{{ old('scope') }}">
                        </div>
                    </section>
                    @if ($delegablePermissions->isNotEmpty())
                        <section class="user-form-section">
                            <h3>Autorisations opérationnelles du site</h3>
                            <p class="field-hint full">Ces droits sont limités au site sélectionné. La gestion des
                                utilisateurs et des rôles ne peut pas être déléguée.</p>
                            <div class="user-form-grid">
                                @foreach ($delegablePermissions as $permission)
                                    <label class="full" style="display:flex;align-items:center;gap:9px"><input
                                            style="width:auto;margin:0" type="checkbox" name="permission_ids[]"
                                            value="{{ $permission->id }}" @checked(in_array($permission->id, old('permission_ids', [])))>
                                        {{ $permission->name }}</label>
                                @endforeach
                            </div>
                        </section>
                    @endif
                    <div class="form-sheet-actions"><button class="secondary" type="button"
                            data-sheet-close="user-create-sheet">Annuler</button><button type="submit">Créer
                            l’utilisateur</button></div>
                </form>
            </x-form-sheet>
            <script>
                document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
                    const field = document.getElementById(button.dataset.passwordToggle);
                    const visible = field.type === 'text';
                    field.type = visible ? 'password' : 'text';
                    button.textContent = visible ? '◉' : '●';
                    button.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
                    field.focus()
                }));
                const role = document.getElementById('create-role');
                const organization = document.getElementById('create-organization');
                const mission = document.getElementById('create-mission');
                const project = document.getElementById('create-project');
                const facility = document.getElementById('create-facility');
                const site = document.getElementById('create-site');
                const filterOptions = (select, predicate) => [...select.options].forEach((option, index) => {
                    if (index === 0) return;
                    option.hidden = !predicate(option);
                    option.disabled = option.hidden;
                    if (option.hidden && option.selected) select.value = '';
                });
                const refreshUserScope = () => {
                    filterOptions(mission, option => option.dataset.organization === organization.value);
                    filterOptions(project, option => option.dataset.organization === organization.value && option.dataset.mission === mission.value);
                    filterOptions(facility, option => option.dataset.organization === organization.value && (option.dataset.projects || '').split(',').includes(project.value));
                    filterOptions(site, option => option.dataset.facility === facility.value);
                    const roleCode = role.selectedOptions[0]?.dataset.code;
                    const officialRole = ['coordination_admin', 'project_admin', 'site_admin'].includes(roleCode);
                    const projectRole = roleCode === 'project_admin';
                    const siteRole = roleCode === 'site_admin';
                    const coordinationRole = roleCode === 'coordination_admin';
                    const needsProject = projectRole || siteRole;
                    document.getElementById('create-organization-field').hidden = !officialRole;
                    document.getElementById('create-mission-field').hidden = !(coordinationRole || needsProject);
                    document.getElementById('create-project-field').hidden = !needsProject;
                    document.getElementById('create-facility-field').hidden = !siteRole;
                    document.getElementById('create-site-field').hidden = !siteRole;
                    organization.required = officialRole;
                    mission.required = coordinationRole || needsProject;
                    project.required = needsProject;
                    facility.required = siteRole;
                    site.required = siteRole;
                    document.getElementById('create-scope').value = coordinationRole
                        ? `mission:${mission.value}`
                        : roleCode === 'project_admin'
                        ? `project:${project.value}`
                        : siteRole ? `site:${site.value}` : '';
                };
                [role, organization, mission, project, facility, site]
                    .forEach(field => field.addEventListener('change', refreshUserScope));
                refreshUserScope();
                @if ($errors->any() || request()->boolean('create'))
                    document.addEventListener('DOMContentLoaded', () => document.getElementById('user-create-sheet')
                ?.showModal());
                @endif
            </script>
        @endif
    </main>
</body>

</html>

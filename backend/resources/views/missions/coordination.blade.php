@extends('layouts.portal')
@section('title', 'Ma Coordination · PharmaCare')
@section('page-title', 'Ma Coordination')
@php
    $c = $coordination;
    $stats = $c['stats'];
    $tabUrl = fn (string $tab, array $extra = []) => route('organizations.missions.show', [$organization, $mission, 'tab' => $tab, ...$extra]);
    $statusTone = ['pending' => 'info', 'validated' => 'success', 'refused' => 'danger', 'suspended' => 'danger'];
    $statusShort = ['pending' => 'En attente', 'validated' => 'Validée', 'refused' => 'Refusée', 'suspended' => 'Suspendue'];
@endphp
@push('styles')<style>
.mc{width:100%;box-sizing:border-box;max-width:1440px;margin:auto;padding:28px 32px 60px;color:var(--pc-color-text)}
.mc-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}.mc-head h1{margin:0;font-size:24px}.mc-head p{margin:4px 0 0;color:var(--pc-color-text-muted)}
.mc-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;padding:8px 14px;border-radius:10px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);color:var(--pc-color-text);font:inherit;font-weight:700;text-decoration:none;cursor:pointer;white-space:nowrap}
.mc-btn.primary{background:var(--pc-color-primary-strong);border-color:var(--pc-color-primary-strong);color:#fff}.mc-btn.danger{border-color:var(--pc-status-danger-text);color:var(--pc-status-danger-text)}.mc-btn.sm{min-height:32px;padding:5px 11px;font-size:13px}
.mc-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:20px 0}.mc-kpi{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;padding:16px 18px}.mc-kpi small{color:var(--pc-color-text-muted);font-size:13px}.mc-kpi strong{display:block;font-size:28px;margin:6px 0 4px}.mc-kpi span{color:var(--pc-color-text-muted);font-size:13px}.mc-kpi strong.info{color:var(--pc-status-info-text)}
.mc-banner{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;border-radius:12px;background:var(--pc-status-info-bg);color:var(--pc-status-info-text);margin-bottom:18px}
.mc-tabs{display:flex;gap:26px;border-bottom:1px solid var(--pc-color-border);margin:18px 0;overflow-x:auto}.mc-tabs a{display:inline-flex;gap:8px;align-items:center;padding:10px 2px;color:var(--pc-color-text-muted);text-decoration:none;font-weight:600;white-space:nowrap;border-bottom:3px solid transparent}.mc-tabs a.on{color:var(--pc-color-primary-strong);border-color:var(--pc-color-primary-strong)}.mc-tabs em{font-style:normal;font-size:12px;padding:1px 7px;border-radius:999px;background:var(--pc-status-neutral-bg);color:var(--pc-status-neutral-text)}
.mc-card{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px}.mc-table{width:100%;border-collapse:collapse}.mc-table th{font-size:12px;text-align:left;color:var(--pc-color-text-muted);padding:12px 16px;border-bottom:1px solid var(--pc-color-border)}.mc-table td{padding:13px 16px;border-bottom:1px solid var(--pc-color-border);vertical-align:middle}.mc-table tr:last-child td{border-bottom:0}.mc-scroll{overflow-x:auto}
.mc-badge{display:inline-flex;white-space:nowrap;padding:3px 10px;border-radius:999px;font-size:13px;font-weight:600}.mc-badge.success{background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.mc-badge.info{background:var(--pc-status-info-bg);color:var(--pc-status-info-text)}.mc-badge.danger{background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.mc-badge.neutral{background:var(--pc-status-neutral-bg);color:var(--pc-status-neutral-text)}.mc-badge.brand{background:var(--pc-color-primary-soft);color:var(--pc-color-primary-strong)}
.mc-muted{color:var(--pc-color-text-muted)}.mc-right{text-align:right}.mc-actions{display:flex;gap:8px;justify-content:flex-end}.mc-actions form{margin:0}
.mc-split{display:grid;grid-template-columns:340px minmax(0,1fr);gap:18px;align-items:start}.mc-list{display:grid;gap:12px}.mc-item{display:block;padding:14px 16px;border:1px solid var(--pc-color-border);border-radius:12px;background:var(--pc-color-surface,#fff);color:inherit;text-decoration:none}.mc-item.on{border-color:var(--pc-color-primary);background:var(--pc-color-primary-soft)}.mc-item header{display:flex;justify-content:space-between;gap:8px}.mc-item p{margin:6px 0 0;color:var(--pc-color-text-muted);font-size:14px}
.mc-detail{padding:22px}.mc-detail h2{margin:0;font-size:20px}.mc-cols{display:grid;grid-template-columns:1fr 1fr;gap:26px;margin-top:18px}.mc-cols h3,.mc-detail h3{font-size:15px;margin:0 0 8px}.mc-dl{display:grid;grid-template-columns:auto 1fr;margin:0}.mc-dl dt,.mc-dl dd{padding:8px 0;border-top:1px solid var(--pc-color-border);margin:0}.mc-dl dt{color:var(--pc-color-text-muted)}.mc-dl dd{text-align:right}
.mc-chips{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}.mc-chip{padding:4px 11px;border-radius:999px;border:1px solid var(--pc-color-primary);background:var(--pc-color-primary-soft);color:var(--pc-color-primary-strong);font-size:13px}
.mc-std{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px;border-radius:10px;background:var(--pc-color-surface-subtle);margin:6px 0 18px}.mc-foot{display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--pc-color-border);padding-top:16px}.mc-note{text-align:right;color:var(--pc-color-text-muted);font-size:13px;margin-top:10px}
.mc-filters{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap}.mc-filters input,.mc-filters select{width:auto!important;flex:0 0 auto;min-height:40px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 12px;font:inherit;background:var(--pc-color-surface,#fff);color:inherit}.mc-filters input{flex:0 1 320px!important;min-width:240px}
details.mc-row>summary{list-style:none;cursor:pointer}details.mc-row>summary::-webkit-details-marker{display:none}.mc-sub{padding:4px 16px 16px 36px;background:var(--pc-color-primary-soft)}.mc-sub p{margin:8px 0;color:var(--pc-color-text-muted);font-size:13px}.mc-sub .mc-table{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:10px}
.mc-grid-acc{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:18px;align-items:start}.mc-journal{padding:18px}.mc-journal header{display:flex;justify-content:space-between}.mc-journal h2{font-size:16px;margin:0}.mc-journal ul{list-style:none;padding:0;margin:10px 0}.mc-journal li{display:grid;grid-template-columns:10px 1fr;gap:10px;padding:10px 0;border-bottom:1px solid var(--pc-color-border)}.mc-journal li i{width:8px;height:8px;border-radius:50%;background:var(--pc-color-primary);margin-top:7px}.mc-journal small{display:block;color:var(--pc-color-text-muted)}
.mc-notice{padding:12px 15px;border-radius:10px;margin:0 0 16px;background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.mc-notice.error{background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.mc-empty{padding:30px;text-align:center;color:var(--pc-color-text-muted)}
.mc-field{display:block;font-weight:600;font-size:14px}.mc-field :is(input:not([type=checkbox]),select){display:block;width:100%;margin-top:6px;min-height:40px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 10px;font:inherit}.mc-field textarea{display:block;width:100%;margin-top:6px;border:1px solid var(--pc-color-border);border-radius:10px;padding:10px;font:inherit}
@media(max-width:1100px){.mc-kpis{grid-template-columns:repeat(2,1fr)}.mc-split,.mc-grid-acc{grid-template-columns:1fr}}
@media(max-width:650px){.mc{padding:18px 16px 40px}.mc-head{flex-direction:column}.mc-head .mc-btn{width:100%}.mc-kpis{grid-template-columns:1fr}.mc-cols{grid-template-columns:1fr}.mc-filters input,.mc-filters select{width:100%!important;flex:1 1 100%!important;min-width:0}}
</style>@endpush
@section('content')
<div class="mc">
    <header class="mc-head">
        <div><h1>Ma Coordination</h1><p>{{ $organization->name }}, {{ $mission->name }}, {{ $mission->country->name }}</p></div>
        @if($c['can_act'] && $canManageProjects)<a class="mc-btn primary" href="{{ route('modules.projects', ['create' => 1]) }}"><span class="material-symbols-outlined">add</span> Créer un projet / programme</a>@endif
    </header>
    @if(session('status'))<div class="mc-notice" style="margin-top:16px">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mc-notice error" style="margin-top:16px">{{ $errors->first() }}</div>@endif

    @if($tab === 'projects')
        <section class="mc-kpis">
            <article class="mc-kpi"><small>Projets et programmes</small><strong>{{ $stats['projects'] }}</strong><span>{{ $stats['donor_projects'] }} {{ $stats['donor_projects'] > 1 ? 'projets bailleurs' : 'projet bailleur' }}, {{ $stats['national_programs'] }} {{ $stats['national_programs'] > 1 ? 'programmes nationaux' : 'programme national' }}</span></article>
            <article class="mc-kpi"><small>FOSA validées</small><strong>{{ $stats['validated'] }}</strong><span>dans {{ $stats['validated_projects'] }} {{ $stats['validated_projects'] > 1 ? 'projets' : 'projet' }}</span></article>
            <article class="mc-kpi"><small>FOSA en attente</small><strong class="info">{{ $stats['pending'] }}</strong><span>à valider par vous</span></article>
            <article class="mc-kpi"><small>Comptes actifs</small><strong>{{ $stats['active_accounts'] }}</strong><span>{{ $stats['active_coordination_accounts'] }} coordination, {{ $stats['active_facility_accounts'] }} FOSA</span></article>
        </section>
        @if($stats['pending'] > 0)
            <div class="mc-banner"><span><strong>{{ $stats['pending'] }} {{ $stats['pending'] > 1 ? 'FOSA attendent' : 'FOSA attend' }} votre validation.</strong> Leurs comptes ne pourront être créés qu’après validation.</span><a class="mc-btn sm" href="{{ $tabUrl('pending') }}">Voir les FOSA à valider</a></div>
        @endif
    @endif

    <nav class="mc-tabs" aria-label="Ma Coordination">
        <a href="{{ $tabUrl('projects') }}" @class(['on' => $tab === 'projects'])>Projets et programmes <em>{{ $stats['projects'] }}</em></a>
        <a href="{{ $tabUrl('pending') }}" @class(['on' => $tab === 'pending'])>FOSA à valider <em>{{ $stats['pending'] }}</em></a>
        <a href="{{ $tabUrl('facilities') }}" @class(['on' => $tab === 'facilities'])>FOSA et comptes <em>{{ $c['facilities']->count() }}</em></a>
        <a href="{{ $tabUrl('accounts') }}" @class(['on' => $tab === 'accounts'])>Comptes de la coordination <em>{{ $c['coordination_accounts']->count() }}</em></a>
        <a href="{{ $tabUrl('journal') }}" @class(['on' => $tab === 'journal'])>Journal des actions</a>
    </nav>

    @if($tab === 'projects')
        <section class="mc-card mc-scroll"><table class="mc-table" style="min-width:980px">
            <thead><tr><th>Code</th><th>Intitulé</th><th>Type</th><th>Bailleur</th><th>Admin Projet</th><th>FOSA validées / en attente</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            @forelse($c['projects'] as $project)
                <tr>
                    <td><strong>{{ $project->code }}</strong></td>
                    <td>{{ $project->name }}</td>
                    <td><span class="mc-badge {{ $project->type === 'national_program' ? 'brand' : 'neutral' }}">{{ $project->type_label }}</span></td>
                    <td class="mc-muted">{{ $project->donor_name ?? '—' }}</td>
                    <td>{{ $project->admin_name ?? 'Non attribué' }}</td>
                    <td>{{ $project->validated_facilities_count }} / {{ $project->pending_facilities_count }}</td>
                    <td><span class="mc-badge {{ ['active' => 'success', 'draft' => 'info', 'suspended' => 'danger'][$project->status] ?? 'neutral' }}">{{ $project->status_label }}</span></td>
                    <td class="mc-right"><a class="mc-btn sm" href="{{ route('projects.medical-configuration', $project) }}">Ouvrir</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="mc-empty">Aucun projet ni programme dans cette coordination.</td></tr>
            @endforelse
            </tbody>
        </table></section>
    @endif

    @if($tab === 'pending')
        @if($c['pending']->isEmpty())
            <section class="mc-card mc-empty">Aucune FOSA n’attend votre validation.</section>
        @else
            <div class="mc-split">
                <div class="mc-list">
                    @foreach($c['pending'] as $facility)
                        <a href="{{ $tabUrl('pending', ['facility' => $facility->id]) }}" @class(['mc-item', 'on' => $c['selected']?->id === $facility->id])>
                            <header><strong>{{ $facility->name }}</strong><span class="mc-badge info">En attente</span></header>
                            <p>Projet {{ $facility->projects->pluck('code')->join(', ') ?: '—' }}<br>déclarée le {{ $facility->created_at?->format('d/m/Y') }}</p>
                        </a>
                    @endforeach
                </div>
                @php($f = $c['selected'])
                <article class="mc-card mc-detail">
                    <div class="mc-head"><div><h2>{{ $f->name }}</h2><p>Projet {{ $f->projects->pluck('code')->join(', ') }}, déclarée @if($c['selected_details']['declared_by'])par {{ $c['selected_details']['declared_by'] }} (Admin Projet) @endif le {{ $f->created_at?->format('d/m/Y') }}</p></div><span class="mc-badge info">En attente de validation</span></div>
                    <div class="mc-cols">
                        <div><h3>Détails de la FOSA</h3><dl class="mc-dl"><dt>Code</dt><dd>{{ $f->code }}</dd><dt>Catégorie</dt><dd>{{ $f->facilityCategory?->name ?? '—' }}</dd><dt>Niveau de soins</dt><dd>{{ $f->careLevel?->name ?? '—' }}</dd><dt>Localisation</dt><dd>{{ collect([$f->locality, $f->district, $f->region])->filter()->join(', ') ?: '—' }}</dd></dl></div>
                        <div><h3>Paramètres d’approvisionnement</h3><dl class="mc-dl"><dt>Périodicité de commande</dt><dd>{{ $f->order_period_months ?? '—' }} mois</dd><dt>Délai de livraison</dt><dd>{{ $f->delivery_lead_time_months ?? '—' }} mois</dd><dt>Stock de sécurité</dt><dd>{{ $f->safety_stock_months !== null ? rtrim(rtrim(number_format($f->safety_stock_months, 2, ',', ''), '0'), ',') : '—' }} mois</dd></dl></div>
                    </div>
                    <h3 style="margin-top:20px">Population cible</h3>
                    <div class="mc-chips">@forelse($f->targetPopulations as $population)<span class="mc-chip">{{ $population->name }}</span>@empty<span class="mc-muted">—</span>@endforelse</div>
                    <h3>Pathologies et activités</h3>
                    <div class="mc-chips">@forelse($f->pathologies as $pathology)<span class="mc-chip">{{ $pathology->name }}</span>@empty<span class="mc-muted">—</span>@endforelse</div>
                    <div class="mc-std"><div><strong>Liste Standard générée : {{ $c['selected_details']['standard_list_count'] }} produits</strong><div class="mc-muted">À partir du niveau de soins, des populations et des pathologies déclarés.</div></div></div>
                    @if($c['can_act'])
                        <div class="mc-foot">
                            <button class="mc-btn danger" type="button" data-sheet-open="refuse-sheet">Refuser avec un motif</button>
                            <form method="post" action="{{ route('coordination.facilities.validate', $f->id) }}">@csrf<button class="mc-btn primary"><span class="material-symbols-outlined">check</span> Valider la FOSA</button></form>
                        </div>
                        <p class="mc-note">Après validation, l’Admin Projet pourra créer les comptes Admin Site et Utilisateur Site de cette FOSA.</p>
                    @endif
                </article>
            </div>
            @if($c['can_act'])
                <x-form-sheet id="refuse-sheet" title="Refuser la FOSA" description="Le motif est obligatoire et sera visible par l’Admin Projet. Après correction, la FOSA reviendra en attente.">
                    <form method="post" action="{{ route('coordination.facilities.refuse', $f->id) }}">@csrf
                        <label class="mc-field">Motif du refus *<textarea name="reason" rows="4" required minlength="5" maxlength="1000">{{ old('reason') }}</textarea></label>
                        <div class="mc-foot" style="margin-top:18px;border:0"><button class="mc-btn secondary" type="button" data-sheet-close="refuse-sheet">Annuler</button><button class="mc-btn danger">Refuser la FOSA</button></div>
                    </form>
                </x-form-sheet>
            @endif
        @endif
    @endif

    @if($tab === 'facilities')
        <form class="mc-filters" method="get" action="{{ route('organizations.missions.show', [$organization, $mission]) }}">
            <input type="hidden" name="tab" value="facilities">
            <input type="search" name="search" value="{{ $c['filters']['search'] }}" placeholder="Rechercher une FOSA ou un compte">
            <select name="project" onchange="this.form.submit()"><option value="">Tous les projets</option>@foreach($c['projects'] as $project)<option value="{{ $project->id }}" @selected($c['filters']['project'] === $project->id)>{{ $project->code }}</option>@endforeach</select>
            <select name="status" onchange="this.form.submit()"><option value="">Tous les statuts</option>@foreach($statusShort as $value => $label)<option value="{{ $value }}" @selected($c['filters']['status'] === $value)>{{ $label }}</option>@endforeach</select>
        </form>
        <section class="mc-card mc-scroll" style="min-width:0">
            <div style="min-width:900px">
            <div class="mc-table" style="display:grid;grid-template-columns:2fr 1fr 1.4fr 1fr 1fr 1.6fr;padding:0"><div style="display:contents">@foreach(['Formation sanitaire', 'Projet', 'Catégorie', 'Comptes', 'Statut', ''] as $heading)<div style="padding:12px 16px;font-size:12px;color:var(--pc-color-text-muted);border-bottom:1px solid var(--pc-color-border)">{{ $heading }}</div>@endforeach</div></div>
            @forelse($c['filtered_facilities'] as $facility)
                @php($accounts = $c['accounts_by_facility'][$facility->id] ?? collect())
                <details class="mc-row" style="border-bottom:1px solid var(--pc-color-border)" @if($loop->first && $accounts->isNotEmpty()) open @endif>
                    <summary style="display:grid;grid-template-columns:2fr 1fr 1.4fr 1fr 1fr 1.6fr;align-items:center">
                        <div style="padding:13px 16px"><strong>▸ {{ $facility->name }}</strong></div>
                        <div style="padding:13px 16px">{{ $facility->projects->pluck('code')->join(', ') ?: '—' }}</div>
                        <div style="padding:13px 16px" class="mc-muted">{{ $facility->facilityCategory?->name ?? '—' }}</div>
                        <div style="padding:13px 16px">{{ $accounts->isEmpty() ? 'Aucun compte' : $accounts->count().' '.($accounts->count() > 1 ? 'comptes' : 'compte') }}</div>
                        <div style="padding:13px 16px"><span class="mc-badge {{ $statusTone[$facility->validation_status] ?? 'neutral' }}">{{ $statusShort[$facility->validation_status] ?? $facility->validation_status_label }}</span></div>
                        <div style="padding:13px 16px" class="mc-actions">
                            @if($c['can_act'])
                                @if($facility->validation_status === 'validated')<button class="mc-btn sm danger" type="button" data-sheet-open="suspend-{{ $facility->id }}">Suspendre</button>
                                @elseif($facility->validation_status === 'suspended')<form method="post" action="{{ route('coordination.facilities.reactivate', $facility->id) }}" onsubmit="return confirm('Réactiver cette FOSA ?')">@csrf<button class="mc-btn sm secondary">Réactiver</button></form>
                                @elseif($facility->validation_status === 'pending')<a class="mc-btn sm primary" href="{{ $tabUrl('pending', ['facility' => $facility->id]) }}">Valider</a>@endif
                            @endif
                        </div>
                    </summary>
                    <div class="mc-sub">
                        @if($facility->validation_status === 'refused' && $facility->refusal_reason)<p>Motif du refus : {{ $facility->refusal_reason }}</p>@endif
                        @if($facility->validation_status === 'suspended' && $facility->suspension_reason)<p>Motif de la suspension : {{ $facility->suspension_reason }}</p>@endif
                        <p>Comptes créés par l’Admin Projet</p>
                        @if($accounts->isEmpty())<p>Aucun compte pour cette FOSA.</p>@else
                        <table class="mc-table"><tbody>
                        @foreach($accounts as $account)
                            @php($status = \App\Services\CoordinationService::accountStatus($account))
                            <tr>
                                <td>{{ $account->name }}</td>
                                <td class="mc-muted">{{ $account->roles->first()?->code === 'site_admin' ? 'Admin Site' : 'Utilisateur Site' }}</td>
                                <td class="mc-muted">{{ $account->username }}</td>
                                <td class="mc-muted">Dernière connexion : {{ $account->last_login_at ? $account->last_login_at->diffForHumans() : 'jamais (1re connexion à faire)' }}</td>
                                <td><span class="mc-badge {{ $status['tone'] }}">{{ $status['label'] }}</span></td>
                                <td class="mc-actions">@if($c['can_act'])
                                    <form method="post" action="{{ route($account->is_active ? 'coordination.accounts.suspend' : 'coordination.accounts.reactivate', $account) }}" onsubmit="return confirm('{{ $account->is_active ? 'Suspendre' : 'Réactiver' }} ce compte ?')">@csrf<button class="mc-btn sm {{ $account->is_active ? 'danger' : 'secondary' }}">{{ $account->is_active ? 'Suspendre' : 'Réactiver' }}</button></form>
                                @endif</td>
                            </tr>
                        @endforeach
                        </tbody></table>@endif
                    </div>
                </details>
                @if($c['can_act'] && $facility->validation_status === 'validated')
                    <x-form-sheet id="suspend-{{ $facility->id }}" title="Suspendre {{ $facility->name }}" description="Les comptes de la FOSA perdront l’accès à PharmaCare. Le motif est obligatoire.">
                        <form method="post" action="{{ route('coordination.facilities.suspend', $facility->id) }}">@csrf
                            <label class="mc-field">Motif de la suspension *<textarea name="reason" rows="4" required minlength="5" maxlength="1000"></textarea></label>
                            <div class="mc-foot" style="margin-top:18px;border:0"><button class="mc-btn secondary" type="button" data-sheet-close="suspend-{{ $facility->id }}">Annuler</button><button class="mc-btn danger">Suspendre la FOSA</button></div>
                        </form>
                    </x-form-sheet>
                @endif
            @empty
                <div class="mc-empty">Aucune FOSA ne correspond à ces critères.</div>
            @endforelse
            </div>
        </section>
    @endif

    @if($tab === 'accounts')
        <div class="mc-grid-acc">
            <div>
                <div class="mc-head" style="margin-bottom:14px;align-items:center"><p style="margin:0">Vous pouvez créer des comptes Admin Projet et Coordination (lecture seule). Les comptes FOSA sont créés par les Admin Projet.</p>
                    @if($c['can_act'] && $canManageProjectAdmins && $accountRoles->isNotEmpty())<button class="mc-btn primary" type="button" data-sheet-open="project-admin-create-sheet"><span class="material-symbols-outlined">add</span> Créer un compte</button>@endif</div>
                <section class="mc-card mc-scroll"><table class="mc-table" style="min-width:640px">
                    <thead><tr><th>Nom</th><th>Identifiant</th><th>Rôle et projet</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    @forelse($c['coordination_accounts'] as $account)
                        @php($status = \App\Services\CoordinationService::accountStatus($account))
                        @php($projectRole = $account->roles->firstWhere('code', 'project_admin'))
                        <tr>
                            <td><strong>{{ $account->name }}</strong></td>
                            <td class="mc-muted">{{ $account->email ?: $account->username }}</td>
                            <td>@if($projectRole)<span class="mc-badge brand">Admin Projet, {{ $c['projects']->firstWhere('id', $projectRole->pivot->scope_id)?->code ?? '—' }}</span>@else<span class="mc-badge neutral">Coordination (lecture seule)</span>@endif</td>
                            <td><span class="mc-badge {{ $status['tone'] }}">{{ $status['label'] }}</span></td>
                            <td class="mc-actions">@if($c['can_act'])
                                <form method="post" action="{{ route($account->is_active ? 'coordination.accounts.suspend' : 'coordination.accounts.reactivate', $account) }}" onsubmit="return confirm('{{ $account->is_active ? 'Suspendre' : 'Réactiver' }} ce compte ?')">@csrf<button class="mc-btn sm {{ $account->is_active ? 'danger' : 'secondary' }}">{{ $account->is_active ? 'Suspendre' : 'Réactiver' }}</button></form>
                            @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="mc-empty">Aucun compte de coordination.</td></tr>
                    @endforelse
                    </tbody>
                </table></section>
            </div>
            @include('missions.partials.journal', ['entries' => $c['journal'], 'compact' => true])
        </div>
    @endif

    @if($tab === 'journal')
        @include('missions.partials.journal', ['entries' => $c['journal'], 'compact' => false])
    @endif
</div>

@if($c['can_act'] && $canManageProjectAdmins && $accountRoles->isNotEmpty())
<x-form-sheet id="project-admin-create-sheet" title="Créer un compte" description="Compte rattaché à la coordination {{ $mission->name }}."><form method="post" action="{{ route('users.store') }}" data-account-form>@csrf<input type="hidden" name="form_context" value="mission-project-admin"><input type="hidden" name="organization_id" value="{{ $organization->id }}"><input type="hidden" name="mission_id" value="{{ $mission->id }}"><div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px"><label class="mc-field">Prénom *<input name="first_name" value="{{ old('first_name') }}" required maxlength="80"></label><label class="mc-field">Nom *<input name="last_name" value="{{ old('last_name') }}" required maxlength="80"></label><label class="mc-field">E-mail *<input type="email" name="email" value="{{ old('email') }}" required></label><label class="mc-field">Téléphone<input name="phone" value="{{ old('phone') }}"></label><label class="mc-field">Identifiant *<input name="username" value="{{ old('username') }}" required></label><label class="mc-field" style="grid-column:1/-1">Rôle et niveau d’accès *<select name="role_id" required data-account-role>@foreach($accountRoles as $accountRole)<option value="{{ $accountRole->id }}" data-role-code="{{ $accountRole->code }}" @selected((string) old('role_id', $projectAdminRole?->id) === (string) $accountRole->id)>{{ $accountRole->code === 'coordination_admin' ? 'Admin Coordination (lecture seule)' : 'Admin Projet' }}</option>@endforeach</select><small class="mc-muted" data-account-role-help hidden>Voit les mêmes informations que la coordination, sans pouvoir créer, modifier ni supprimer.</small></label><label class="mc-field" style="grid-column:1/-1" data-account-project>Projet *<select name="project_id" required><option value="">Sélectionner un projet</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id') === $project->id)>{{ $project->name }} ({{ $project->code }})</option>@endforeach</select></label><label class="mc-field">Mot de passe<input type="password" name="password" autocomplete="new-password"></label><label class="mc-field">Confirmation<input type="password" name="password_confirmation" autocomplete="new-password"></label><label class="mc-field"><input type="checkbox" name="is_active" value="1" checked> Compte actif</label><label class="mc-field"><input type="checkbox" name="must_change_password" value="1" checked> Changement du mot de passe à la première connexion</label></div><div class="mc-foot" style="margin-top:18px"><button class="mc-btn secondary" type="button" data-sheet-close="project-admin-create-sheet">Annuler</button><button class="mc-btn primary">Créer le compte</button></div></form></x-form-sheet>
<script>
document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-account-form]').forEach(form => {
    const role = form.querySelector('[data-account-role]'), project = form.querySelector('[data-account-project]'), help = form.querySelector('[data-account-role-help]');
    const sync = () => { const isProjectAdmin = role.selectedOptions[0]?.dataset.roleCode === 'project_admin'; project.hidden = !isProjectAdmin; project.querySelector('select').required = isProjectAdmin; project.querySelector('select').disabled = !isProjectAdmin; help.hidden = isProjectAdmin; };
    role.addEventListener('change', sync); sync();
}));
</script>
@if($errors->any() && old('form_context') === 'mission-project-admin')<script>document.addEventListener('DOMContentLoaded', () => document.getElementById('project-admin-create-sheet')?.showModal())</script>@endif
@endif
@if($errors->has('reason') && $tab === 'pending')<script>document.addEventListener('DOMContentLoaded', () => document.getElementById('refuse-sheet')?.showModal())</script>@endif
@endsection

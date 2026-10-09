@extends('layouts.portal')
@section('title', 'Synchronisation · PharmaCare')
@section('page-title', 'Synchronisation')
@php
    $stats = $board['stats'];
    $filters = $board['filters'];
    $generated = \Illuminate\Support\Carbon::parse($board['generated_at'])->setTimezone(config('app.timezone'));
    $projectName = collect($board['projects'])->firstWhere('id', $filters['project_id'])['name'] ?? 'Tous les projets';
    $link = fn (array $query) => route('modules.synchronization', array_filter([...$filters, ...$query], fn ($value) => $value !== null && $value !== '' && $value !== 'all'));
    $tone = ['ok' => 'success', 'check' => 'warning', 'late' => 'danger'];
    $selected = $board['selected'];
@endphp
@push('styles')<style>
.sy{width:100%;box-sizing:border-box;max-width:1440px;margin:auto;padding:28px 32px 60px;color:var(--pc-color-text)}
.sy-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap}.sy-head small{color:var(--pc-color-text-muted)}.sy-head h1{margin:2px 0 0;font-size:24px}
.sy-project{display:flex;align-items:center;gap:10px;color:var(--pc-color-text-muted);font-size:13px}.sy-project select{min-height:40px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 12px;font:inherit;background:var(--pc-color-surface,#fff);color:var(--pc-color-text)}
.sy-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:20px 0}.sy-kpi{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;padding:16px 18px}.sy-kpi small{color:var(--pc-color-text-muted);font-size:13px}.sy-kpi strong{font-size:28px;margin-right:6px}.sy-kpi span{color:var(--pc-color-text-muted)}.sy-kpi .warn{color:var(--pc-color-primary-strong)}.sy-kpi .bad{color:var(--pc-status-danger-text)}
.sy-card{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;overflow:hidden;margin-bottom:18px}
.sy-bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 16px}.sy-chips{display:flex;gap:8px;flex-wrap:wrap}
.sy-chip{display:inline-flex;align-items:center;min-height:34px;padding:5px 14px;border-radius:999px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);color:var(--pc-color-text);text-decoration:none;font-weight:700;font-size:13px}.sy-chip.on{background:var(--pc-color-primary-strong);border-color:var(--pc-color-primary-strong);color:#fff}
.sy-search{min-height:40px;min-width:260px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 12px;font:inherit;background:var(--pc-color-surface,#fff);color:inherit}
.sy-scroll{overflow-x:auto}.sy-table{width:100%;border-collapse:collapse;min-width:860px}.sy-table th{font-size:12px;text-align:left;color:var(--pc-color-text-muted);padding:10px 16px;background:var(--pc-color-surface-subtle);border-top:1px solid var(--pc-color-border);border-bottom:1px solid var(--pc-color-border)}.sy-table td{padding:13px 16px;border-bottom:1px solid var(--pc-color-border)}.sy-table .num{text-align:right}.sy-table tr.sel td{background:var(--pc-color-primary-soft)}.sy-table tbody tr{cursor:pointer}.sy-table tbody tr:hover td{background:var(--pc-color-surface-subtle)}.sy-table a{color:inherit;text-decoration:none;font-weight:700}
.sy-zero{color:var(--pc-color-text-muted)}.sy-red{color:var(--pc-status-danger-text);font-weight:700}.sy-orange{color:var(--pc-color-primary-strong);font-weight:700}
.sy-badge{display:inline-flex;white-space:nowrap;padding:3px 10px;border-radius:999px;font-size:13px;font-weight:700}.sy-badge.success{background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.sy-badge.warning{background:var(--pc-color-primary-soft);color:var(--pc-color-primary-strong)}.sy-badge.danger{background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}
.sy-note{padding:12px 16px;color:var(--pc-color-text-muted);font-size:13px;border-top:1px solid var(--pc-color-border)}.sy-empty{padding:22px 16px;color:var(--pc-color-text-muted)}
.sy-detail>header{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:16px 16px 8px}.sy-detail h2{margin:0;font-size:17px}.sy-detail header span{color:var(--pc-color-text-muted);font-size:13px}
.sy-issues{display:grid;gap:10px;padding:8px 16px}.sy-issue{display:grid;grid-template-columns:110px 1fr auto;gap:12px;align-items:center;border:1px solid var(--pc-color-border);border-radius:12px;padding:12px 14px}.sy-issue strong{display:block}.sy-issue p{margin:2px 0 0;color:var(--pc-color-text-muted);font-size:13px}.sy-issue time{color:var(--pc-color-text-muted);font-size:13px;white-space:nowrap}
.sy-pending{display:flex;gap:16px;flex-wrap:wrap;padding:4px 16px 0;color:var(--pc-color-text-muted);font-size:13px}
@media(max-width:1100px){.sy-kpis{grid-template-columns:repeat(2,1fr)}}
@media(max-width:650px){.sy{padding:18px 16px 40px}.sy-search{min-width:0;width:100%}.sy-issue{grid-template-columns:1fr}}
</style>@endpush
@section('content')
<div class="sy">
    <header class="sy-head">
        <div><small>{{ $board['context'] ? $board['context'].' · ' : '' }}{{ $projectName }}</small><h1>Supervision de la synchronisation</h1></div>
        <form class="sy-project" method="get" action="{{ route('modules.synchronization') }}">
            @if(count($board['projects']) > 1)
                <label for="sy-project">Projet</label>
                <select id="sy-project" name="project_id" onchange="this.form.submit()">
                    <option value="">Tous les projets</option>
                    @foreach($board['projects'] as $project)<option value="{{ $project['id'] }}" @selected($filters['project_id'] === $project['id'])>{{ $project['name'] }}</option>@endforeach
                </select>
                <input type="hidden" name="filter" value="{{ $filters['filter'] }}">
            @endif
            <span>Mis à jour à {{ $generated->format('H:i') }}</span>
        </form>
    </header>

    <section class="sy-kpis">
        <article class="sy-kpi"><small>Appareils à jour (moins de 24 h)</small><div><strong>{{ $stats['up_to_date'] }}</strong><span>sur {{ $stats['devices'] }}</span></div></article>
        <article class="sy-kpi"><small>En retard (plus de 24 h)</small><div><strong class="{{ $stats['late'] ? 'warn' : '' }}">{{ $stats['late'] }}</strong><span>appareil{{ $stats['late'] > 1 ? 's' : '' }}</span></div></article>
        <article class="sy-kpi"><small>Envois refusés</small><div><strong class="{{ $stats['refused'] ? 'bad' : '' }}">{{ $stats['refused'] }}</strong></div></article>
        <article class="sy-kpi"><small>Conflits à examiner</small><div><strong class="{{ $stats['conflicts'] ? 'warn' : '' }}">{{ $stats['conflicts'] }}</strong></div></article>
    </section>

    <section class="sy-card">
        <div class="sy-bar">
            <nav class="sy-chips" aria-label="Filtrer les appareils">
                @foreach(['all' => 'Tous', 'late' => 'En retard', 'refused' => 'Avec refus', 'conflicts' => 'Avec conflits'] as $key => $label)
                    <a class="sy-chip {{ $filters['filter'] === $key ? 'on' : '' }}" href="{{ $link(['filter' => $key, 'device' => null]) }}">{{ $label }}</a>
                @endforeach
            </nav>
            <form method="get" action="{{ route('modules.synchronization') }}">
                @if($filters['project_id'])<input type="hidden" name="project_id" value="{{ $filters['project_id'] }}">@endif
                @if($filters['filter'] !== 'all')<input type="hidden" name="filter" value="{{ $filters['filter'] }}">@endif
                <input class="sy-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Rechercher une FOSA ou un utilisateur" aria-label="Rechercher une FOSA ou un utilisateur">
            </form>
        </div>
        @if(empty($board['rows']))
            <p class="sy-empty">{{ $stats['devices'] === 0 ? 'Aucun téléphone de FOSA n’est encore connecté dans ce périmètre.' : 'Aucun appareil ne correspond à ce filtre.' }}</p>
        @else
            <div class="sy-scroll"><table class="sy-table">
                <thead><tr><th>FOSA</th><th>Projet</th><th>Appareil · utilisateur</th><th>Dernière synchro</th><th class="num">En attente</th><th class="num">Refusés</th><th class="num">Conflits</th><th>État</th></tr></thead>
                <tbody>
                @foreach($board['rows'] as $row)
                    @php($href = $link(['device' => $row['key']]).'#detail')
                    <tr class="{{ $selected && $selected['key'] === $row['key'] ? 'sel' : '' }}" onclick="location.href='{{ $href }}'">
                        <td><a href="{{ $href }}">{{ $row['facility'] }}</a></td>
                        <td>{{ implode(', ', $row['projects']) ?: '—' }}</td>
                        <td>{{ $row['device'] }} · {{ $row['user'] }}</td>
                        <td>{{ $row['last_success_label'] }}</td>
                        <td class="num">{{ $row['pending'] }}</td>
                        <td class="num {{ $row['refused'] ? 'sy-red' : 'sy-zero' }}">{{ $row['refused'] }}</td>
                        <td class="num {{ $row['conflicts'] ? 'sy-orange' : 'sy-zero' }}">{{ $row['conflicts'] }}</td>
                        <td><span class="sy-badge {{ $tone[$row['status']] }}">{{ $row['status_label'] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        <p class="sy-note">« En attente » = opérations signalées par l’appareil lors de sa dernière connexion. Cliquez sur une ligne pour voir le détail.</p>
    </section>

    @if($selected)
        <section class="sy-card sy-detail" id="detail">
            <header><h2>{{ $selected['facility'] }} · {{ $selected['device'] }} · {{ $selected['user'] }}</h2><span>Dernière synchronisation : {{ $selected['last_success_label'] }}</span></header>
            @if($selected['pending'] > 0)
                <div class="sy-pending">@foreach($selected['pending_by_module'] as $module => $count)@if($count > 0)<span>{{ $module }} en attente : <strong>{{ $count }}</strong></span>@endif @endforeach</div>
            @endif
            <div class="sy-issues">
                @forelse($selected['issues'] as $issue)
                    <article class="sy-issue">
                        <span class="sy-badge {{ $issue['kind'] === 'conflict' ? 'warning' : 'danger' }}" style="justify-content:center">{{ $issue['kind_label'] }}</span>
                        <div><strong>{{ $issue['module_label'] }} {{ $issue['reference'] }}</strong><p>{{ $issue['kind'] === 'conflict' ? '' : 'Refusé : ' }}{{ $issue['reason'] ?: 'Motif non transmis.' }}@if($issue['reported']) · <strong style="display:inline">Signalé par l’utilisateur</strong>@endif</p></div>
                        <time>{{ $issue['occurred_label'] }}</time>
                    </article>
                @empty
                    <p class="sy-empty" style="padding:6px 0">Aucun envoi refusé ni conflit sur cet appareil.</p>
                @endforelse
            </div>
            <p class="sy-note" style="border-top:0">Seul l’utilisateur du téléphone peut réessayer ou abandonner un envoi. La supervision est en lecture seule.</p>
        </section>
    @endif
</div>
@endsection

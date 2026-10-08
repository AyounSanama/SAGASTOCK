@extends('layouts.portal')
@section('title', 'Tableau de bord · PharmaCare')
@section('page-title', 'Tableau de bord')
@php
    $stats = $board['stats'];
    $myCoordination = fn (array $query = []) => route('organizations.missions.show', [$mission->organization_id, $mission, ...$query]);
    $tone = ['ok' => 'success', 'late' => 'info', 'never' => 'neutral', 'failed' => 'danger', 'suspended' => 'danger', 'none' => 'neutral'];
    $generated = \Illuminate\Support\Carbon::parse($board['generated_at'])->locale('fr');
@endphp
@push('styles')<style>
.cd{width:100%;box-sizing:border-box;max-width:1440px;margin:auto;padding:28px 32px 60px;color:var(--pc-color-text)}
.cd-head-filters{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}.cd-filters{display:flex;gap:10px;flex-wrap:wrap}
.cd-filters select{min-height:40px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 12px;font:inherit;background:var(--pc-color-surface,#fff);color:inherit}.cd-filters select:disabled{color:var(--pc-color-text-muted);background:var(--pc-color-surface-subtle,#f6f6f4);cursor:not-allowed}
@media(max-width:650px){.cd-filters,.cd-filters select{width:100%}}
.cd-head h1{margin:0;font-size:24px}.cd-head p{margin:4px 0 0;color:var(--pc-color-text-muted)}
.cd-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:20px 0}.cd-kpi{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;padding:16px 18px}.cd-kpi small{color:var(--pc-color-text-muted);font-size:13px}.cd-kpi strong{display:block;font-size:28px;margin:6px 0 4px}.cd-kpi span{color:var(--pc-color-text-muted);font-size:13px}.cd-kpi.na strong{color:var(--pc-color-text-muted)}
.cd-grid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(0,1fr);gap:18px;align-items:start}.cd-col{display:grid;gap:18px}
.cd-card{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;overflow:hidden}.cd-card>header{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:16px 18px}.cd-card h2{margin:0;font-size:16px}.cd-card header span{color:var(--pc-color-text-muted);font-size:13px}
.cd-table{width:100%;border-collapse:collapse}.cd-table th{font-size:12px;text-align:left;color:var(--pc-color-text-muted);padding:10px 16px;background:var(--pc-color-surface-subtle);border-top:1px solid var(--pc-color-border);border-bottom:1px solid var(--pc-color-border)}.cd-table td{padding:13px 16px;border-bottom:1px solid var(--pc-color-border)}.cd-table tr:last-child td{border-bottom:0}.cd-na{color:var(--pc-color-text-muted)}
.cd-badge{display:inline-flex;white-space:nowrap;padding:3px 10px;border-radius:999px;font-size:13px;font-weight:600}.cd-badge.success{background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.cd-badge.info{background:var(--pc-status-info-bg);color:var(--pc-status-info-text)}.cd-badge.danger{background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.cd-badge.neutral{background:var(--pc-status-neutral-bg);color:var(--pc-status-neutral-text)}
.cd-todo{list-style:none;margin:0;padding:0 18px 8px}.cd-todo li{display:grid;grid-template-columns:10px 1fr auto;gap:12px;align-items:center;padding:12px 0;border-top:1px solid var(--pc-color-border)}.cd-dot{width:9px;height:9px;border-radius:50%}.cd-dot.info{background:var(--pc-status-info-text)}.cd-dot.danger{background:var(--pc-status-danger-text)}.cd-todo strong{display:block}.cd-todo small{color:var(--pc-color-text-muted)}
.cd-btn{display:inline-flex;align-items:center;min-height:32px;padding:5px 12px;border-radius:9px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);color:var(--pc-color-text);text-decoration:none;font-weight:700;font-size:13px}
.cd-later{padding:4px 18px 22px;color:var(--pc-color-text-muted)}.cd-empty{padding:4px 18px 18px;color:var(--pc-color-text-muted)}.cd-scroll{overflow-x:auto}
@media(max-width:1100px){.cd-kpis{grid-template-columns:repeat(2,1fr)}.cd-grid{grid-template-columns:1fr}}
@media(max-width:650px){.cd{padding:18px 16px 40px}.cd-kpis{grid-template-columns:1fr 1fr}.cd-kpi strong{font-size:24px}}
</style>@endpush
@section('content')
<div class="cd">
    <header class="cd-head cd-head-filters">
        <div><h1>Tableau de bord</h1><p>{{ $mission->name }}, situation au {{ $generated->translatedFormat('j F Y') }} à {{ $generated->format('H:i') }}</p></div>
        {{-- Niveau 3 (maquette Coordination 07) : couple ONG/Bailleur et projet filtrent les blocs ci-dessous. --}}
        <form class="cd-filters" method="get" action="{{ route('dashboard') }}">
            <select name="donor_id" aria-label="Couple ONG / Bailleur" onchange="this.form.submit()">
                <option value="">Tous les couples ONG / Bailleur</option>
                @foreach($board['filters']['donors'] as $donor)<option value="{{ $donor['id'] }}" @selected($board['filters']['donor_id'] === $donor['id'])>{{ $donor['label'] }}</option>@endforeach
            </select>
            <select name="project_id" aria-label="Projet" onchange="this.form.submit()">
                <option value="">Tous les projets</option>
                @foreach($board['filters']['projects'] as $project)<option value="{{ $project['id'] }}" @selected($board['filters']['project_id'] === $project['id'])>{{ $project['code'] }}</option>@endforeach
            </select>
            <select aria-label="Période" disabled title="Disponible avec les analyses de base (ruptures, péremptions, consommations)">
                <option>Mois en cours ({{ now()->locale('fr')->translatedFormat('F Y') }})</option>
            </select>
            <noscript><button class="cd-btn" type="submit">Filtrer</button></noscript>
        </form>
    </header>

    <section class="cd-kpis">
        {{-- Ruptures : articles retenus sans lot utilisable (calcul serveur) ; pré-rupture et péremption : niveau 9. --}}
        @php($stockouts = $board['stockouts'] ?? ['products' => 0, 'facilities' => 0, 'facilities_with_stock' => 0])
        @if($stockouts['facilities_with_stock'] === 0)
            <article class="cd-kpi na"><small>Produits en rupture</small><strong>—</strong><span>Aucune FOSA n’a encore enregistré de stock</span></article>
        @else
            <article class="cd-kpi"><small>Produits en rupture</small><strong>{{ $stockouts['products'] }}</strong><span>{{ $stockouts['products'] === 0 ? 'Aucune rupture' : 'dans '.$stockouts['facilities'].' FOSA sur '.$stockouts['facilities_with_stock'] }}</span></article>
        @endif
        @foreach(['Produits en pré-rupture', 'Lots à risque de péremption'] as $label)
            <article class="cd-kpi na"><small>{{ $label }}</small><strong>—</strong><span>Disponible avec les analyses de base</span></article>
        @endforeach
        <article class="cd-kpi"><small>FOSA validées</small><strong>{{ $stats['validated'] }} / {{ $stats['total'] }}</strong><span>{{ $stats['pending'] }} en attente de validation</span></article>
    </section>

    <div class="cd-grid">
        <div class="cd-col">
            <section class="cd-card">
                <header><h2>Situation par projet</h2><span>FOSA validées / total</span></header>
                <div class="cd-scroll"><table class="cd-table" style="min-width:620px">
                    <thead><tr><th>Projet</th><th>FOSA</th><th>Ruptures</th><th>Pré-ruptures</th><th>Péremption &lt; 2 mois</th><th>Synchronisation</th></tr></thead>
                    <tbody>
                    @forelse($board['projects'] as $project)
                        <tr>
                            <td><strong>{{ $project['code'] }}</strong></td>
                            <td>{{ $project['validated'] }} / {{ $project['total'] }}</td>
                            <td class="cd-na">—</td><td class="cd-na">—</td><td class="cd-na">—</td>
                            <td><span class="cd-badge {{ $tone[$project['sync_status']] ?? 'neutral' }}">{{ $project['sync_label'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="cd-na">Aucun projet dans cette coordination.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <p class="cd-later" style="padding-top:12px;margin:0">Ruptures, pré-ruptures et péremption : disponibles avec les analyses de base.</p>
            </section>

            <section class="cd-card">
                <header><h2>Synchronisation des FOSA</h2><span>Dernier contact avec le serveur</span></header>
                <div class="cd-scroll"><table class="cd-table" style="min-width:560px">
                    <thead><tr><th>Formation sanitaire</th><th>Projet</th><th>Dernière synchro</th><th>Statut</th></tr></thead>
                    <tbody>
                    @forelse($board['facilities'] as $facility)
                        <tr>
                            <td><strong>{{ $facility['name'] }}</strong><div class="cd-na" style="font-size:13px">{{ $facility['code'] }}</div></td>
                            <td>{{ collect($facility['projects'])->join(', ') ?: '—' }}</td>
                            <td class="cd-na">{{ $facility['last_contact_at'] ? \Illuminate\Support\Carbon::parse($facility['last_contact_at'])->locale('fr')->diffForHumans() : 'jamais' }}</td>
                            <td><span class="cd-badge {{ $tone[$facility['sync_status']] ?? 'neutral' }}">{{ $facility['sync_label'] }}@if($facility['failed_operations']) · {{ $facility['failed_operations'] }} op.@endif</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="cd-na">Aucune FOSA validée.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>

            <section class="cd-card">
                <header><h2>Ruptures et pré-ruptures à traiter</h2><span>CMM sur les 3 derniers mois</span></header>
                <p class="cd-later">Disponible avec les analyses de base.</p>
            </section>
        </div>

        <div class="cd-col">
            <section class="cd-card">
                <header><h2>À traiter</h2></header>
                @if($board['todo']->isEmpty())
                    <p class="cd-empty">Rien à traiter pour le moment.</p>
                @else
                    <ul class="cd-todo">
                        @foreach($board['todo'] as $item)
                            <li><span class="cd-dot {{ $item['tone'] }}"></span><div><strong>{{ $item['title'] }}</strong><small>{{ $item['detail'] }}</small></div>
                                @if($item['action'] === 'validate')<a class="cd-btn" href="{{ $myCoordination(['tab' => 'pending']) }}">Valider</a>
                                @else<a class="cd-btn" href="{{ $myCoordination(['tab' => 'facilities', 'search' => collect($board['facilities'])->firstWhere('id', $item['facility_id'])['name'] ?? '']) }}">Voir</a>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
            <section class="cd-card">
                <header><h2>Ordonnances avec antipaludique</h2><span>5 dernières semaines</span></header>
                <p class="cd-later">Disponible avec les analyses de base.</p>
            </section>
        </div>
    </div>
</div>
@endsection

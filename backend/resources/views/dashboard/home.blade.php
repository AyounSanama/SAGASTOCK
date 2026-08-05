<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tableau de bord · PharmaCare</title>
    <style>
        :root{
            --dash-blue:#2563EB;
            --dash-cyan:#14B8A6;
            --dash-orange:#FF7A00;
            --dash-red:#EF4444;
            --dash-green:#16A34A;
            --dash-purple:#7C3AED;
            --dash-ink:#152033;
            --dash-muted:#667085;
            --dash-border:#E4E9F0;
        }
        .dashboard-main{display:grid;gap:24px}
        .dashboard-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:24px}
        .dashboard-hero h1{margin:0 0 7px!important;font-size:32px!important}
        .dashboard-hero p{margin:0;color:var(--dash-muted);font-size:14px}
        .dashboard-date{display:inline-flex;align-items:center;gap:9px;min-height:44px;padding:10px 14px;background:#fff;border:1px solid var(--dash-border);border-radius:12px;color:var(--dash-muted);font-size:13px;font-weight:700;white-space:nowrap}
        .dashboard-date svg{width:18px;height:18px;color:var(--dash-blue)}

        .dashboard-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
        .legacy-dashboard-metrics{display:none!important}
        .metric-icon.material-symbols-outlined{font-size:25px;color:inherit}
        .dashboard-metric{position:relative;min-height:172px;padding:21px;border-radius:18px;color:#fff;overflow:hidden;box-shadow:0 16px 28px rgba(21,32,51,.14)}
        .dashboard-metric::after{content:"";position:absolute;width:150px;height:150px;right:-52px;bottom:-72px;border:24px solid rgba(255,255,255,.10);border-radius:50%}
        .dashboard-metric.blue{background:linear-gradient(135deg,#43A5F7,var(--dash-blue))}
        .dashboard-metric.cyan{background:linear-gradient(135deg,#31C9C5,var(--dash-cyan))}
        .dashboard-metric.orange{background:linear-gradient(135deg,#FFA126,var(--dash-orange))}
        .dashboard-metric.red{background:linear-gradient(135deg,#F46D73,var(--dash-red))}
        .metric-head{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:12px}
        .metric-icon{width:48px;height:48px;display:grid;place-items:center;border-radius:50%;background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.28)}
        .metric-icon svg{width:24px;height:24px}
        .metric-label{font-size:13px;font-weight:700;color:rgba(255,255,255,.88)}
        .dashboard-metric strong{position:relative;z-index:1;display:block;margin-top:17px;font-size:31px;line-height:1;font-weight:900;letter-spacing:-.03em}
        .metric-link{position:relative;z-index:1;display:inline-flex;align-items:center;gap:6px;margin-top:14px;color:#fff;font-size:11px;font-weight:750;opacity:.88}

        .dashboard-workspace{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(310px,.75fr);gap:18px;align-items:start}
        .dashboard-column{display:grid;gap:18px;min-width:0}
        .dashboard-card{background:#fff;border:1px solid var(--dash-border);border-radius:18px;box-shadow:0 12px 30px rgba(20,39,74,.07);overflow:hidden}
        .dashboard-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 20px 15px}
        .dashboard-card-title{display:flex;align-items:center;gap:12px;min-width:0}
        .dashboard-card-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:rgba(37,99,235,.09);color:var(--dash-blue);flex:0 0 auto}
        .dashboard-card-icon.orange{background:rgba(255,122,0,.10);color:var(--dash-orange)}
        .dashboard-card-icon.green{background:rgba(22,163,74,.10);color:var(--dash-green)}
        .dashboard-card-icon svg{width:21px;height:21px}
        .dashboard-card h2{margin:0!important;font-size:17px!important}
        .dashboard-card-head p{margin:3px 0 0;color:var(--dash-muted);font-size:11px}
        .dashboard-card-action{color:var(--dash-blue);font-size:12px;font-weight:800;white-space:nowrap}
        .dashboard-card-body{padding:0 20px 20px}

        .dashboard-shortcuts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .dashboard-shortcut{display:flex;align-items:center;gap:13px;min-height:82px;padding:14px;border:1px solid var(--dash-border);border-radius:14px;color:var(--dash-ink);background:#fff;transition:transform .16s,border-color .16s,box-shadow .16s}
        .dashboard-shortcut:hover{transform:translateY(-2px);border-color:rgba(37,99,235,.42);box-shadow:0 9px 20px rgba(37,99,235,.08)}
        .dashboard-shortcut-icon{width:46px;height:46px;display:grid;place-items:center;border-radius:13px;flex:0 0 auto}
        .dashboard-shortcut-icon svg{width:22px;height:22px}
        .dashboard-shortcut-icon.orange{background:rgba(255,122,0,.10);color:var(--dash-orange)}
        .dashboard-shortcut-icon.blue{background:rgba(37,99,235,.09);color:var(--dash-blue)}
        .dashboard-shortcut-icon.green{background:rgba(22,163,74,.10);color:var(--dash-green)}
        .dashboard-shortcut-icon.purple{background:rgba(124,58,237,.09);color:var(--dash-purple)}
        .dashboard-shortcut b{display:block;font-size:13px}
        .dashboard-shortcut small{display:block;margin-top:4px;color:var(--dash-muted);font-size:10.5px}

        .dashboard-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
        .summary-item{padding:14px;border:1px solid var(--dash-border);border-radius:13px;background:#FAFBFD}
        .summary-item strong{display:block;color:var(--dash-ink);font-size:21px;font-weight:900}
        .summary-item span{display:block;margin-top:4px;color:var(--dash-muted);font-size:10px;line-height:1.35}

        .dashboard-table-wrap{overflow:auto;border:1px solid var(--dash-border);border-radius:13px}
        .dashboard-table{min-width:620px}
        .dashboard-table th{background:#F1F5FA!important;color:#536079!important}
        .dashboard-empty{display:flex;align-items:center;gap:12px;min-height:76px;padding:15px;border:1px dashed #CED6E2;border-radius:13px;color:var(--dash-muted);font-size:12px}
        .dashboard-empty-icon{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;background:rgba(22,163,74,.10);color:var(--dash-green);font-size:20px}
        .positive{color:var(--dash-green)!important;font-weight:800}
        .negative{color:var(--dash-red)!important;font-weight:800}

        .activity-list{max-height:410px;overflow:auto;padding-right:3px}
        .dashboard-activity{position:relative;display:grid;grid-template-columns:38px minmax(0,1fr);gap:11px;padding:12px 0}
        .dashboard-activity:not(:last-child){border-bottom:1px solid #EDF0F4}
        .activity-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:rgba(255,122,0,.10);color:var(--dash-orange)}
        .activity-icon svg{width:18px;height:18px}
        .dashboard-activity p{margin:0;color:var(--dash-ink);font-size:12px;line-height:1.45}
        .dashboard-activity strong{font-weight:800}
        .dashboard-activity small{display:block;margin-top:3px;color:var(--dash-muted);font-size:10px}
        .organization-list{display:grid;gap:9px}
        .organization-row{display:flex;align-items:center;gap:11px;padding:11px;border:1px solid var(--dash-border);border-radius:12px}
        .organization-avatar{width:36px;height:36px;display:grid;place-items:center;border-radius:10px;background:rgba(124,58,237,.09);color:var(--dash-purple);font-weight:900}
        .organization-row strong,.organization-row small{display:block}
        .organization-row strong{font-size:12px}.organization-row small{margin-top:2px;color:var(--dash-muted);font-size:10px}

        @media(max-width:1280px){
            .dashboard-workspace{grid-template-columns:1fr}
            .activity-list{max-height:330px}
        }
        @media(max-width:1050px){.dashboard-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:680px){
            .dashboard-hero{display:block}.dashboard-date{margin-top:15px}
            .dashboard-metrics{gap:10px}.dashboard-metric{min-height:150px;padding:16px}.dashboard-metric strong{font-size:25px}
            .dashboard-shortcuts{grid-template-columns:1fr}.dashboard-summary{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
@include('components.app-sidebar')
@php
    $dashboardLabel = 'Tableau de bord';
@endphp
<main class="dashboard-main">
    <section class="dashboard-hero">
        <div>
            <h1>{{ $dashboardLabel }}</h1>
            <p>Bonjour, {{ auth()->user()->first_name ?: auth()->user()->name }}. Voici la situation actuelle de votre espace PharmaCare.</p>
        </div>
        <div class="dashboard-date">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
            {{ now()->locale('fr')->translatedFormat('l d F Y') }}
        </div>
    </section>

    <section class="dashboard-metrics dashboard-metrics-dynamic" aria-label="Indicateurs adaptés à votre périmètre">
        @foreach($widgets as $widget)
            <article class="dashboard-metric {{ $widget['color'] }}">
                <div class="metric-head">
                    <span class="metric-icon material-symbols-outlined">{{ config('pharmacare_ui.module_icons.'.$widget['icon'], 'dashboard') }}</span>
                    <span class="metric-label">{{ $widget['label'] }}</span>
                </div>
                <strong>{{ number_format((float) $widget['value'], $widget['key']==='stock_quantity' ? 2 : 0, ',', ' ') }}</strong>
                <a class="metric-link" href="{{ $widget['route'] }}">{{ $widget['caption'] }} →</a>
            </article>
        @endforeach
    </section>

    <section class="dashboard-metrics legacy-dashboard-metrics" aria-hidden="true">
        @if(auth()->user()->hasPermission('stocks.view'))
        <article class="dashboard-metric blue">
            <div class="metric-head"><span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16v13H4zM8 7V4h8v3M8 12h8M12 9v6"/></svg></span><span class="metric-label">Stock disponible</span></div>
            <strong>{{ number_format((float)$stats['stock_quantity'],2,',',' ') }}</strong>
            <span class="metric-link">Unités de médicaments</span>
        </article>
        <article class="dashboard-metric cyan">
            <div class="metric-head"><span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg></span><span class="metric-label">Inventaire</span></div>
            <strong>{{ number_format($stats['stock_lines']) }}</strong>
            <span class="metric-link">Lignes de stock visibles</span>
        </article>
        @endif
        @if(auth()->user()->hasPermission('organizations.view'))
        <article class="dashboard-metric orange">
            <div class="metric-head"><span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h2M13 10h2M9 14h2M13 14h2"/></svg></span><span class="metric-label">Organisations</span></div>
            <strong>{{ number_format($stats['organizations']) }}</strong>
            <span class="metric-link">Structures accessibles</span>
        </article>
        @endif
        @if(auth()->user()->hasPermission('users.view'))
        <article class="dashboard-metric red">
            <div class="metric-head"><span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="metric-label">Utilisateurs actifs</span></div>
            <strong>{{ number_format($stats['users_active']) }}</strong>
            <span class="metric-link">Comptes actuellement actifs</span>
        </article>
        @endif
    </section>

    <section class="dashboard-workspace">
        <div class="dashboard-column">
            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></span>
                        <div><h2>Accès rapides</h2><p>Accédez à vos opérations les plus fréquentes</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body">
                    <div class="dashboard-shortcuts">
                        @if(auth()->user()->hasPermission('users.manage'))
                        <a class="dashboard-shortcut" href="{{ route('users.index',['create'=>1]) }}"><span class="dashboard-shortcut-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 19a6 6 0 0 0-12 0M9 11a4 4 0 1 0 0-8M19 8v6M16 11h6"/></svg></span><span><b>Créer un utilisateur</b><small>Nouveau compte, rôle et périmètre</small></span></a>
                        @endif
                        @if(auth()->user()->hasPermission('users.view'))
                        <a class="dashboard-shortcut" href="{{ route('users.index') }}"><span class="dashboard-shortcut-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8M20 8v6M17 11h6"/></svg></span><span><b>Utilisateurs et sécurité</b><small>Consulter, modifier et archiver</small></span></a>
                        @endif
                        @if(auth()->user()->hasPermission('organizations.view'))
                        <a class="dashboard-shortcut" href="{{ route('organizations.index') }}"><span class="dashboard-shortcut-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h2M13 10h2M9 14h2M13 14h2"/></svg></span><span><b>Organisations</b><small>ONG, missions et projets</small></span></a>
                        @endif
                        @if($organizations->first() && auth()->user()->hasPermission('stocks.view'))
                        <a class="dashboard-shortcut" href="{{ route('organizations.stocks.index',$organizations->first()) }}"><span class="dashboard-shortcut-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg></span><span><b>Gérer le stock de médicaments</b><small>Soldes par lot et mouvements</small></span></a>
                        @endif
                        <a class="dashboard-shortcut" href="{{ route('profile.show') }}"><span class="dashboard-shortcut-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span><b>Mon profil</b><small>Informations personnelles et mot de passe</small></span></a>
                    </div>
                </div>
            </article>

            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M12 3l4 4-4 4M12 21l-4-4 4-4"/></svg></span>
                        <div><h2>Mouvements de stock récents</h2><p>Dernières entrées, sorties et corrections validées</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body">
                    @if($recentMovements->isEmpty())
                        <div class="dashboard-empty"><span class="dashboard-empty-icon">✓</span><span>Aucun mouvement visible récemment.</span></div>
                    @else
                        <div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>Date</th><th>Produit</th><th>Site</th><th>Type</th><th>Quantité</th></tr></thead><tbody>
                        @foreach($recentMovements as $movement)
                            <tr><td>{{ $movement->validated_at?->format('d/m H:i') }}</td><td>{{ $movement->product?->name }}</td><td>{{ $movement->site?->name }}</td><td>{{ $movement->movement_type }}</td><td class="{{ (float)$movement->quantity>=0?'positive':'negative' }}">{{ (float)$movement->quantity>=0?'+':'' }}{{ $movement->quantity }}</td></tr>
                        @endforeach
                        </tbody></table></div>
                    @endif
                </div>
            </article>
        </div>

        <aside class="dashboard-column">
            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg></span>
                        <div><h2>Vue d’ensemble</h2><p>Comptes et accès</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body dashboard-summary">
                    <div class="summary-item"><strong>{{ number_format($stats['users_total']) }}</strong><span>Utilisateurs visibles</span></div>
                    <div class="summary-item"><strong>{{ number_format($stats['users_active']) }}</strong><span>Comptes actifs</span></div>
                    <div class="summary-item"><strong>{{ number_format($stats['users_archived']) }}</strong><span>Comptes archivés</span></div>
                </div>
            </article>

            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-9-9"/></svg></span>
                        <div><h2>Activités récentes</h2><p>Dernières actions enregistrées</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body activity-list">
                    @forelse($activities as $activity)
                        <div class="dashboard-activity"><span class="activity-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-9-9"/></svg></span><div><p><strong>{{ $activity->user?->name ?? 'Système' }}</strong><br>{{ str_replace(['.','_'],' ',ucfirst($activity->event)) }}</p><small>{{ $activity->created_at->locale('fr')->diffForHumans() }}</small></div></div>
                    @empty
                        <div class="dashboard-empty"><span class="dashboard-empty-icon">✓</span><span>Aucune activité récente.</span></div>
                    @endforelse
                </div>
            </article>

            <article class="dashboard-card">
                <header class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <span class="dashboard-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg></span>
                        <div><h2>Organisations accessibles</h2><p>Votre périmètre actuel</p></div>
                    </div>
                </header>
                <div class="dashboard-card-body organization-list">
                    @forelse($organizations as $organization)
                        <div class="organization-row"><span class="organization-avatar">{{ mb_strtoupper(mb_substr($organization->name,0,1)) }}</span><span><strong>{{ $organization->name }}</strong><small>{{ $organization->code }}</small></span></div>
                    @empty
                        <div class="dashboard-empty"><span>Aucune organisation accessible.</span></div>
                    @endforelse
                </div>
            </article>
        </aside>
    </section>
</main>
</body>
</html>

@extends('layouts.portal')
@section('title', 'Accueil Configuration · PharmaCare')
@section('page-title', 'Configuration')
@section('content')
    <section class="control-center">
        <header class="control-hero">
            <div class="control-hero-icon">✓</div>
            <div class="control-hero-copy">
                <span class="eyebrow">Configuration opérationnelle</span>
                <h2>Accueil Configuration</h2>
                <p>Gérez les organisations et les missions, ou accédez à l’application principale.</p>
            </div>
            <div class="control-hero-actions">
                @if ($canAddOrganization)
                    <a class="button configuration-add"
                        href="{{ route('configuration.workflow.start', 'new-organization') }}">＋ Ajouter une organisation</a>
                @endif
                @if ($organization)
                    <a class="button configuration-add"
                        href="{{ route('configuration.workflow.start', ['flowType' => 'new-mission', 'organization' => $organization->id]) }}">＋
                        Ajouter une mission</a>
                @endif
                <a class="button primary configuration-enter" href="{{ route('dashboard') }}">Entrer dans l’application
                    →</a>
            </div>
        </header>

        @if (!$organization)
            <div class="configuration-empty-state">
                <strong>Aucune organisation configurée</strong>
                <p>Commencez par ajouter une organisation afin d’activer les autres étapes.</p>
            </div>
        @else
            <div class="control-kpis">
                <article>
                    <span>Organisation</span><strong>{{ $organization->name }}</strong><small>{{ $organization->code }}</small>
                </article>
                <article>
                    <span>Mission</span><strong>{{ $mission?->name ?? 'Non disponible' }}</strong><small>{{ $mission?->country?->name ?? $organization->country_code }}</small>
                </article>
                <article><span>Projet
                        actif</span><strong>{{ $project?->name ?? 'Non disponible' }}</strong><small>{{ $project?->code }}</small>
                </article>
                <article>
                    <span>Configuration</span><strong>{{ $progress?->completed_at ? 'Validée' : 'En cours' }}</strong><small>{{ $progress?->completed_at?->format('d/m/Y à H:i') ?? 'Progression conservée' }}</small>
                </article>
            </div>

            <div class="control-grid">
                <article class="control-card">
                    <header>
                        <div class="control-card-icon orange">⌖</div>
                        <div>
                            <h3>Périmètre opérationnel</h3>
                            <p>Structure et site actifs</p>
                        </div>
                    </header>
                    <dl>
                        <div>
                            <dt>Formation sanitaire</dt>
                            <dd>{{ $facility?->name ?? 'Non configurée' }}</dd>
                        </div>
                        <div>
                            <dt>Type</dt>
                            <dd>{{ $facility?->facility_type ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Site</dt>
                            <dd>{{ $site?->name ?? 'Non configuré' }}</dd>
                        </div>
                        <div>
                            <dt>Type de site</dt>
                            <dd>{{ $site?->site_type ?? '—' }}</dd>
                        </div>
                    </dl>
                </article>

                <article class="control-card">
                    <header>
                        <div class="control-card-icon blue">◦</div>
                        <div>
                            <h3>Modules actifs</h3>
                            <p>{{ $enabledModules->count() }} module(s) disponible(s)</p>
                        </div>
                    </header>
                    <div class="control-tags">
                        @forelse($enabledModules as $label)
                        <span>{{ $label }}</span>@empty<em>Aucun module actif</em>
                        @endforelse
                    </div>
                </article>

                <article class="control-card">
                    <header>
                        <div class="control-card-icon green">✓</div>
                        <div>
                            <h3>Fonctionnalités</h3>
                            <p>Paramètres opérationnels</p>
                        </div>
                    </header>
                    <div class="control-tags green">
                        @forelse($enabledFeatures as $label)
                        <span>{{ $label }}</span>@empty<em>Aucune fonctionnalité active</em>
                        @endforelse
                    </div>
                </article>

                <article class="control-card">
                    <header>
                        <div class="control-card-icon purple">☷</div>
                        <div>
                            <h3>Liste standard</h3>
                            <p>Référentiel publié</p>
                        </div>
                    </header>
                    <dl>
                        <div>
                            <dt>Liste</dt>
                            <dd>{{ $standardList?->name ?? 'Non configurée' }}</dd>
                        </div>
                        <div>
                            <dt>Code</dt>
                            <dd>{{ $standardList?->code ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Version</dt>
                            <dd>{{ $standardList?->latestVersion?->version_number ? 'v' . $standardList->latestVersion->version_number : '—' }}
                            </dd>
                        </div>
                    </dl>
                </article>

                <article class="control-card sync-card">
                    <header>
                        <div class="control-card-icon cyan">↻</div>
                        <div>
                            <h3>Synchronisation</h3>
                            <p>Préparation Offline-First</p>
                        </div>
                    </header>
                    <div class="sync-state"><span></span>
                        <div><strong>Prête</strong><small>Aucune synchronisation exécutée pour le moment.</small></div>
                    </div>
                </article>

                <article class="control-card">
                    <header>
                        <div class="control-card-icon orange">♙</div>
                        <div>
                            <h3>Accès et sécurité</h3>
                            <p>Périmètre protégé</p>
                        </div>
                    </header>
                    <dl>
                        <div>
                            <dt>Utilisateur connecté</dt>
                            <dd>{{ auth()->user()->name }}</dd>
                        </div>
                        <div>
                            <dt>Rôle</dt>
                            <dd>{{ auth()->user()->roles()->first()?->name ?? 'Utilisateur' }}</dd>
                        </div>
                        <div>
                            <dt>État</dt>
                            <dd><span class="control-status success">Accès autorisé</span></dd>
                        </div>
                    </dl>
                </article>
            </div>
        @endif
    </section>
@endsection

@push('styles')
    <style>
        .control-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 20px 22px;
            border: 1px solid var(--pc-border);
            border-radius: 18px;
            background: #fff;
            box-shadow: var(--pc-shadow);
        }

        .control-hero-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: #FFF3E8;
            color: var(--pc-orange);
            font-size: 22px;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .control-hero-copy {
            min-width: 0;
            flex: 1;
        }

        .control-hero-copy h2 {
            margin: 4px 0 6px;
            font-size: 1.35rem;
        }

        .control-hero-copy p {
            margin: 0;
            color: var(--pc-muted);
        }

        .control-hero-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            margin-left: auto;
        }

        .configuration-add {
            background: #fff !important;
            color: #f47a20 !important;
            border: 2px solid #f47a20 !important;
            box-shadow: none !important;
        }

        .configuration-enter {
            margin-left: 4px;
        }

        .configuration-empty-state {
            padding: 30px;
            text-align: center;
            background: #fff;
            border: 1px dashed #f4b47d;
            border-radius: 16px;
        }

        .configuration-empty-state strong {
            font-size: 18px;
        }

        .configuration-empty-state p {
            margin: 8px 0 0;
            color: #667085;
        }

        @media(max-width:1200px) {
            .control-hero {
                flex-wrap: wrap;
                align-items: flex-start
            }

            .control-hero-actions {
                width: 100%;
                justify-content: flex-start;
                margin-left: 0
            }

            .control-hero-actions .button {
                text-align: center;
                justify-content: center
            }
        }

        @media(max-width:700px) {
            .control-hero {
                padding: 18px
            }

            .control-hero-actions {
                flex-direction: column;
                align-items: stretch
            }

            .control-hero-actions .button {
                width: 100%
            }

            .configuration-enter {
                margin-left: 0
            }
        }
    </style>
@endpush

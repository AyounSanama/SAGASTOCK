@extends('layouts.portal')

@section('title', 'Organisations · PharmaCare')
@section('page-title', 'Organisations')

@push('styles')
    <style>
        :root {
            --primary: #f5660a;
            --primary-dark: #c9570d;
            --ink: #172033;
            --muted: #667085;
            --line: #e3e7ee;
            --surface: #f7f9fc;
            --success: #168447;
            --danger: #c7352b;
        }

        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
        }

        .page-head .eyebrow {
            color: var(--primary);
            font-size: .78rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: .11em;
        }

        .page-head h1 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            letter-spacing: -.035em;
            margin: 7px 0;
        }

        .page-head p {
            max-width: 680px;
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }

        .btn {
            min-height: 44px;
            border: 1px solid transparent;
            border-radius: 11px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: .18s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #ff8a24);
            color: #fff;
            box-shadow: 0 8px 20px #f5660a2c;
        }

        .btn-secondary {
            background: #fff;
            border-color: var(--line);
            color: var(--ink);
        }

        .btn-danger {
            background: #fff0ef;
            border-color: #f5ceca;
            color: var(--danger);
        }

        .btn-sm {
            min-height: 36px;
            padding: 0 12px;
            font-size: .82rem;
        }

        .notice {
            padding: 13px 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            background: #edf9f1;
            color: #126d38;
            font-weight: 700;
        }

        .notice.error {
            background: #fff0ee;
            color: var(--danger);
        }

        .toolbar {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) auto;
            gap: 12px;
            margin-bottom: 18px;
        }

        .search {
            position: relative;
        }

        .search span {
            position: absolute;
            left: 15px;
            top: 13px;
            color: #8a94a7;
        }

        .control {
            width: 100%;
            min-height: 48px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
        }

        .search .control {
            padding-left: 42px;
        }

        .org-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .org-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 17px;
            padding: 20px;
            box-shadow: 0 8px 28px #26344a08;
        }

        .org-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .org-identity {
            display: flex;
            gap: 13px;
        }

        .org-icon {
            width: 48px;
            height: 48px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            background: #fff0e5;
            color: var(--primary);
            font-weight: 900;
        }

        .org-card h2 {
            font-size: 1.05rem;
            margin: 1px 0 5px;
        }

        .meta {
            color: var(--muted);
            font-size: .83rem;
            line-height: 1.5;
        }

        .badge {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 850;
            background: #edf9f1;
            color: var(--success);
        }

        .badge.inactive {
            background: #f0f2f5;
            color: #697386;
        }

        .facts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 17px 0;
        }

        .fact {
            padding: 10px;
            border-radius: 10px;
            background: #f8fafc;
        }

        .fact small {
            display: block;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .fact strong {
            font-size: .83rem;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 14px;
            border-top: 1px solid var(--line);
        }

        .module-links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-top: 14px;
        }

        .module-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 11px;
            color: var(--ink);
            text-decoration: none;
            background: #fff;
            transition: .17s;
        }

        .module-link:hover {
            border-color: #ffc398;
            background: #fff9f4;
        }

        .module-link .mi {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .module-link div {
            flex: 1;
        }

        .module-link strong {
            display: block;
            font-size: .82rem;
        }

        .module-link small {
            color: var(--muted);
        }

        .arrow {
            color: #98a2b3;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: end;
            margin: 30px 0 14px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 1.2rem;
        }

        .section-title p {
            margin: 4px 0 0;
            color: var(--muted);
        }

        .empty {
            background: #fff;
            border: 1px dashed #ced5df;
            border-radius: 16px;
            text-align: center;
            padding: 42px 20px;
        }

        .empty .icon {
            font-size: 2.4rem;
        }

        .empty h2 {
            margin: 12px 0 6px;
        }

        .empty p {
            color: var(--muted);
        }

        .archive-list {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
        }

        .archive-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 16px 18px;
            border-top: 1px solid var(--line);
        }

        .archive-row:first-child {
            border-top: 0;
        }

        .sheet-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .field.full {
            grid-column: 1/-1;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: .82rem;
            font-weight: 800;
        }

        .field input,
        .field textarea,
        .field select {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce1e9;
            border-radius: 11px;
            background: #fff;
        }

        .field textarea {
            min-height: 92px;
            resize: vertical;
        }

        .switch-field {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 11px;
        }

        .switch-field input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        .sheet-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }

        @media (max-width: 900px) {
            .org-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .page-head {
                flex-direction: column;
            }

            .page-head .btn {
                width: 100%;
            }

            .toolbar {
                grid-template-columns: 1fr;
            }

            .facts,
            .module-links {
                grid-template-columns: 1fr;
            }

            .sheet-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .sheet-actions {
                flex-direction: column-reverse;
            }

            .sheet-actions .btn {
                width: 100%;
            }

            .archive-row {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
@endpush

@section('content')
    <section class="page-head">
        <div>
            <div class="eyebrow">Gestion institutionnelle</div>
            <h1>Organisations</h1>
            <p>Gestion des organisations utilisant PharmaCare.</p>
        </div>
        <button class="btn btn-primary" type="button" data-sheet-open="create-organization-sheet">+ Ajouter une organisation</button>
    </section>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice error">{{ $errors->first() }}</div>
    @endif

    <form class="toolbar" method="get">
        <div class="search">
            <span>⌕</span>
            <input class="control" name="search" value="{{ request('search') }}"
                placeholder="Rechercher une organisation par nom ou code…">
        </div>
        <button class="btn btn-secondary">Rechercher</button>
    </form>

    <div class="section-title">
        <div>
            <h2>Organisations actives</h2>
            <p>{{ $organizations->total() }} organisation(s) dans votre périmètre.</p>
        </div>
    </div>

    @if ($organizations->isEmpty())
        <section class="empty">
            <div class="icon">🏢</div>
            <h2>Aucune organisation trouvée</h2>
            <p>Modifiez votre recherche ou créez une nouvelle organisation.</p>
            <button class="btn btn-primary" data-sheet-open="create-organization-sheet">＋ Ajouter une organisation</button>
        </section>
    @else
        <section class="org-grid">
            @foreach ($organizations as $organization)
                <article class="org-card">
                    <div class="org-top">
                        <div class="org-identity">
                            <span class="org-icon">{{ Str::upper(Str::substr($organization->name, 0, 1)) }}</span>
                            <div>
                                <h2>{{ $organization->name }}</h2>
                                <div class="meta">
                                    {{ $organization->code }}{{ $organization->legal_name ? ' · ' . $organization->legal_name : '' }}
                                </div>
                            </div>
                        </div>
                        <span
                            class="badge {{ $organization->is_active ? '' : 'inactive' }}">{{ $organization->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <div class="facts">
                        <div class="fact">
                            <small>Pays</small><strong>{{ $organization->country_code ?: 'Non renseigné' }}</strong></div>
                        <div class="fact">
                            <small>Téléphone</small><strong>{{ $organization->phone ?: 'Non renseigné' }}</strong></div>
                        <div class="fact">
                            <small>E-mail</small><strong>{{ $organization->email ?: 'Non renseigné' }}</strong></div>
                    </div>
                    <div class="module-links">
                        <a class="module-link" href="{{ route('organizations.missions.index', $organization) }}"><span
                                class="mi">◎</span>
                            <div><strong>Pays et missions</strong><small>Gérer les missions</small></div><span
                                class="arrow">›</span>
                        </a>
                        <a class="module-link" href="{{ route('organizations.projects.index', $organization) }}"><span
                                class="mi">▣</span>
                            <div><strong>Projets</strong><small>Programmes opérationnels</small></div><span
                                class="arrow">›</span>
                        </a>
                        <a class="module-link" href="{{ route('organizations.structures.index', $organization) }}"><span
                                class="mi">✚</span>
                            <div><strong>Formations sanitaires</strong><small>Établissements et sites</small></div><span
                                class="arrow">›</span>
                        </a>
                        <a class="module-link" href="{{ route('organizations.catalog.index', $organization) }}"><span
                                class="mi">◆</span>
                            <div><strong>Médicaments</strong><small>Référentiels et produits</small></div><span
                                class="arrow">›</span>
                        </a>
                        <a class="module-link" href="{{ route('organizations.stocks.index', $organization) }}"><span
                                class="mi">▤</span>
                            <div><strong>Stock</strong><small>Quantités et mouvements</small></div><span
                                class="arrow">›</span>
                        </a>
                    </div>
                    <div class="actions">
                        <button class="btn btn-secondary btn-sm" type="button"
                            data-sheet-open="edit-organization-{{ $organization->id }}">✎ Modifier</button>
                        <form method="post" action="{{ route('organizations.destroy', $organization) }}"
                            onsubmit="return confirm('Voulez-vous vraiment archiver cette organisation ? Ses données seront conservées.')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm">Archiver</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </section>

        <div style="margin-top:20px">{{ $organizations->withQueryString()->links() }}</div>
    @endif

    @if ($archivedOrganizations->isNotEmpty())
        <div class="section-title">
            <div>
                <h2>Organisations archivées</h2>
                <p>Ces organisations restent conservées et peuvent être restaurées.</p>
            </div>
        </div>
        <section class="archive-list">
            @foreach ($archivedOrganizations as $organization)
                <article class="archive-row">
                    <div>
                        <strong>{{ $organization->name }}</strong>
                        <div class="meta">{{ $organization->code }} · Archivée le
                            {{ $organization->deleted_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <form method="post" action="{{ route('organizations.restore', $organization->id) }}"
                        onsubmit="return confirm('Restaurer cette organisation dans la liste active ?')">
                        @csrf
                        <button class="btn btn-secondary btn-sm">↻ Restaurer</button>
                    </form>
                </article>
            @endforeach
        </section>
    @endif

    @php($formOrganization = null)
    <x-form-sheet id="create-organization-sheet" title="Nouvelle organisation"
        description="Renseignez les informations institutionnelles principales.">
        <form method="post" action="{{ route('organizations.store') }}" data-sheet-form>
            @csrf
            @include('organizations.partials.form-fields', ['organization' => $formOrganization, 'countries' => $countries, 'withAdmin' => true])
            <div class="sheet-actions">
                <button class="btn btn-secondary" type="button"
                    data-sheet-close="create-organization-sheet">Annuler</button>
                <button class="btn btn-primary">Créer l’organisation</button>
            </div>
        </form>
    </x-form-sheet>

    @foreach ($organizations as $organization)
        <x-form-sheet id="edit-organization-{{ $organization->id }}" title="Modifier l’organisation"
            description="Mettez à jour les informations de « {{ $organization->name }} ».">
            <form method="post" action="{{ route('organizations.update', $organization) }}" data-sheet-form>
                @csrf
                @method('PUT')
                @include('organizations.partials.form-fields', ['organization' => $organization, 'countries' => $countries, 'withAdmin' => false])
                <div class="sheet-actions">
                    <button class="btn btn-secondary" type="button"
                        data-sheet-close="edit-organization-{{ $organization->id }}">Annuler</button>
                    <button class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </x-form-sheet>
    @endforeach
@endsection

@push('scripts')
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', () => document.querySelector(
                '[data-sheet-open="create-organization-sheet"]')?.click());
        </script>
    @endif
@endpush

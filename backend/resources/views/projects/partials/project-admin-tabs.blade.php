{{-- Niveau 5 (maquette AdminProjet 02) — Onglets de « Projet & FOSA » : FOSA, Comptes utilisateurs,
     Paramètres d'approvisionnement. Les informations du projet sont sur le tableau de bord.
     Style en ligne : la page « Comptes utilisateurs » n'utilise pas le gabarit commun. --}}
@php
    $tab = $activeTab ?? 'facilities';
    $counts = $counts ?? null;
@endphp
@once<style>.pa-tabs{display:flex;gap:26px;border-bottom:1px solid var(--pc-color-border);margin:18px 0;overflow-x:auto}.pa-tabs a{display:inline-flex;gap:6px;align-items:center;padding:10px 2px;color:var(--pc-color-text-muted);text-decoration:none;font-weight:600;white-space:nowrap;border-bottom:3px solid transparent}.pa-tabs a.on{color:var(--pc-color-primary-strong);border-color:var(--pc-color-primary-strong)}</style>@endonce
<nav class="pa-tabs" aria-label="Projet et formations sanitaires">
    <a @class(['on' => $tab === 'facilities']) href="{{ route('modules.health-facilities') }}" @if($tab === 'facilities') aria-current="page" @endif>FOSA @if($counts)({{ $counts['facilities'] }})@endif</a>
    <a @class(['on' => $tab === 'users']) href="{{ route('users.index') }}" @if($tab === 'users') aria-current="page" @endif>Comptes utilisateurs @if($counts)({{ $counts['accounts'] }})@endif</a>
    <a @class(['on' => $tab === 'supply']) href="{{ route('project-admin.supply') }}" @if($tab === 'supply') aria-current="page" @endif>Paramètres d’approvisionnement</a>
</nav>

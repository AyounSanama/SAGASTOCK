@php
    $actor = auth()->user();
    $roleLabel = $actor?->roles()->first()?->name ?? 'Utilisateur';
@endphp
<header class="portal-topbar">
    <button class="mobile-sidebar-toggle material-symbols-outlined" type="button" data-sidebar-toggle aria-label="Ouvrir le menu">menu</button>
    <h1>@yield('page-title', 'PharmaCare')</h1>
    <div class="portal-topbar-actions">
        <label class="language-selector">
            <span class="material-symbols-outlined" aria-hidden="true">language</span>
            <select aria-label="Langue de l’interface">
                <option value="fr" selected>Français</option>
                <option value="en">English</option>
            </select>
        </label>
        <button class="topbar-icon-button material-symbols-outlined" type="button" aria-label="Notifications">notifications</button>
        <details class="user-menu">
            <summary>
                <span class="user-avatar">{{ mb_strtoupper(mb_substr($actor?->name ?? 'U',0,1)) }}</span>
                <span class="user-copy"><strong>{{ $actor?->name }}</strong><small>{{ $roleLabel }}</small></span>
                <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
            </summary>
            <div class="user-menu-panel">
                <a href="{{ route('profile.show') }}">Mon profil</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Déconnexion</button></form>
            </div>
        </details>
    </div>
</header>

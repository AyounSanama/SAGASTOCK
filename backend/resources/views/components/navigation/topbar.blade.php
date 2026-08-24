@php($actor = $actor ?? auth()->user())
<header class="app-shell-topbar">
    <button class="sidebar-toggle material-symbols-outlined" type="button"
        aria-label="Réduire ou agrandir le menu" aria-expanded="true"
        aria-controls="pharmacare-sidebar">menu</button>
    <strong class="topbar-context">@yield('page-title', 'PharmaCare')</strong>
    <label class="topbar-search"><span class="material-symbols-outlined">search</span><input type="search" placeholder="Rechercher…" aria-label="Rechercher dans PharmaCare"></label>
    <span class="topbar-spacer"></span>
    <button class="topbar-icon material-symbols-outlined" type="button" aria-label="Notifications">notifications_none @if(session('notification_count', 0))<b>{{ min(9, (int) session('notification_count')) }}</b>@endif</button>
    <a class="topbar-profile" href="{{ route('profile.show') }}">
        <span class="profile-avatar">{{ mb_strtoupper(mb_substr($actor?->name ?? 'U', 0, 1)) }}</span>
        <span class="topbar-profile-copy">
            <strong>{{ $actor?->first_name ?: $actor?->name }}</strong>
            <small>{{ $actor?->roles()->first()?->name ?? 'Utilisateur' }}</small>
        </span>
    </a>
</header>

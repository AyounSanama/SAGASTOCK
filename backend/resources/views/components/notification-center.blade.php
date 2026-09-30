<details class="notification-center" data-notifications data-url="{{ route('notifications.index') }}" data-token="{{ csrf_token() }}">
    <summary class="topbar-icon" aria-label="Notifications">
        <span class="material-symbols-outlined" aria-hidden="true">notifications</span>
        @php($unreadCount = auth()->user()->unreadNotifications()->count())
        <span class="notification-count" data-notification-count @if(!$unreadCount) hidden @endif>{{ $unreadCount }}</span>
    </summary>
    <section class="notification-panel" aria-label="Centre de notifications">
        <header><h2>Notifications</h2><button type="button" class="secondary" data-notification-refresh>Actualiser</button></header>
        <button type="button" class="secondary" data-notification-read-all>Tout marquer comme lu</button>
        <p role="status" aria-live="polite" data-notification-status></p>
        <div data-notification-list></div>
        <button type="button" class="secondary" data-notification-more hidden>Charger la suite</button>
    </section>
</details>

@props(['title', 'description' => null, 'icon' => 'analytics', 'tone' => 'orange'])
<section {{ $attributes->class('app-dashboard-panel') }}>
    <header class="app-dashboard-panel__header">
        <span class="app-dashboard-panel__icon app-dashboard-panel__icon--{{ $tone }} material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
        <div><h2>{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif</div>
        @isset($action)<div class="app-dashboard-panel__action">{{ $action }}</div>@endisset
    </header>
    <div class="app-dashboard-panel__body">{{ $slot }}</div>
</section>

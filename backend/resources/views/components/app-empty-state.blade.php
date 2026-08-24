@props(['title', 'description' => null, 'icon' => 'inbox'])
<div {{ $attributes->class('app-empty-state') }}>
    <span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
    <h3>{{ $title }}</h3>
    @if($description)<p>{{ $description }}</p>@endif
    @isset($action)<div class="app-empty-state__action">{{ $action }}</div>@endisset
</div>

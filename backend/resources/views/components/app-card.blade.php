@props(['title' => null, 'description' => null])
<section {{ $attributes->class('app-card') }}>
    @if($title || $description || isset($actions))
        <header class="app-card__header"><div>@if($title)<h2 class="app-card__title">{{ $title }}</h2>@endif @if($description)<p class="app-card__description">{{ $description }}</p>@endif</div>@isset($actions)<div>{{ $actions }}</div>@endisset</header>
    @endif
    {{ $slot }}
</section>

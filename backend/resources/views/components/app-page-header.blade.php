@props(['title', 'description' => null])
<header {{ $attributes->class('app-page-header') }}>
    <div class="app-page-header__copy">
        <h1 class="app-page-header__title">{{ $title }}</h1>
        @if($description)<p class="app-page-header__description">{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="app-page-header__actions">{{ $actions }}</div>@endisset
</header>

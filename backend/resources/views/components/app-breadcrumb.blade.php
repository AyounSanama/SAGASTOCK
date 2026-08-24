@props(['items' => []])
<nav {{ $attributes->class('app-breadcrumb') }} aria-label="Fil d’Ariane">
    @foreach($items as $item)
        @if(!$loop->first)<span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>@endif
        @if(!empty($item['url']) && !$loop->last)<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@else<span @if($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>@endif
    @endforeach
</nav>

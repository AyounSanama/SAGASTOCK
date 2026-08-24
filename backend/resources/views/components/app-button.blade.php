@props(['variant' => 'primary', 'type' => 'button', 'href' => null, 'icon' => null, 'loading' => false, 'disabled' => false, 'expanded' => false])
@php
    $classes = 'app-button app-button--'.$variant.($expanded ? ' app-button--expanded' : '').($loading ? ' is-loading' : '');
@endphp
@if($href)
    <a href="{{ $disabled || $loading ? '#' : $href }}" {{ $attributes->class($classes) }} @if($disabled || $loading) aria-disabled="true" @endif>
        @if($loading)<span class="app-button-spinner" aria-hidden="true"></span>@elseif($icon)<span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>@endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @disabled($disabled || $loading) @if($loading) aria-busy="true" @endif>
        @if($loading)<span class="app-button-spinner" aria-hidden="true"></span>@elseif($icon)<span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>@endif
        <span>{{ $slot }}</span>
    </button>
@endif

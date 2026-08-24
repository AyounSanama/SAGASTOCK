@props(['icon', 'label', 'type' => 'button', 'href' => null, 'disabled' => false])
@if($href)
    <a href="{{ $disabled ? '#' : $href }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->class('app-icon-button') }} @if($disabled) aria-disabled="true" @endif><span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span></a>
@else
    <button type="{{ $type }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->class('app-icon-button') }} @disabled($disabled)><span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span></button>
@endif

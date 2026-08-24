@props(['variant' => 'neutral', 'icon' => null])
<span {{ $attributes->class('app-badge app-badge--'.$variant) }}>@if($icon)<span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>@endif {{ $slot }}</span>

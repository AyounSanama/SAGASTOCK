@props(['label', 'value', 'icon' => 'analytics', 'caption' => null, 'href' => null, 'tone' => 'orange'])
@php($tag = $href ? 'a' : 'article')
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['app-kpi-card', 'app-kpi-card--'.$tone]) }} aria-label="{{ $label }} : {{ $value }}">
    <span class="app-kpi-card__icon material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
    <span class="app-kpi-card__content">
        <small>{{ $label }}</small>
        <strong>{{ $value }}</strong>
        @if($caption)<span>{{ $caption }}</span>@endif
    </span>
    @if($href)<span class="app-kpi-card__arrow material-symbols-outlined" aria-hidden="true">arrow_outward</span>@endif
</{{ $tag }}>
